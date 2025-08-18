<?php
// includes/status_helpers.php

// Ensure log parser helpers (parse_main_ext_line, read_recent_log_lines, read_last_events_for_ips, extract_username_channel_from_request)
if (!function_exists('read_recent_log_lines')) {
    $lp = __DIR__ . '/log_parser.php';
    if (file_exists($lp)) require_once $lp;
}

// EPG/M3U cache & helpers
require_once __DIR__ . '/epg_cache.php';

/* ==========================
   Request Classification
   ========================== */

/**
 * Keep only IPTV streaming requests.
 * EXCLUDE guide/browse endpoints (xmltv/player_api) so we show "watching", not "browsing".
 */
function is_streaming_request($path) {
    if (!$path) return false;
    $p = strtolower($path);

    // exclude guide/api endpoints
    if (strpos($p, 'xmltv.php') !== false) return false;
    if (strpos($p, 'player_api.php') !== false) return false;

    // include streaming pulls
    if (substr($p, -3) === '.ts') return true;
    if (substr($p, -5) === '.m3u8') return true;
    if (strpos($p, '/live/') !== false) return true;

    return false;
}

/* ==========================
   Socket Sampler (:443)
   ========================== */

function ss_active_443() {
    $out = array();
    $lines = array(); $rc = 0;

    @exec('ss -tnp sport = :443 2>/dev/null', $lines, $rc);
    if ($rc === 0 && !empty($lines)) {
        foreach ($lines as $i => $ln) {
            if ($i === 0 && strpos($ln, 'State') === 0) continue;
            if (preg_match('/\s+.*?:443\s+(\d{1,3}(?:\.\d{1,3}){3}):(\d+)/', $ln, $m)) {
                $out[] = array('remote_ip' => $m[1], 'remote_port' => $m[2]);
            }
        }
        return $out;
    }

    // Fallback: lsof
    $lines = array(); $rc = 0;
    @exec('lsof -nP -iTCP:443 -sTCP:ESTABLISHED 2>/dev/null', $lines, $rc);
    if ($rc === 0 && !empty($lines)) {
        foreach ($lines as $i => $ln) {
            if ($i === 0 && stripos($ln, 'COMMAND') !== false) continue;
            if (preg_match('/TCP\s+[\d\.]+:443->(\d{1,3}(?:\.\d{1,3}){3}):(\d+)/', $ln, $m)) {
                $out[] = array('remote_ip' => $m[1], 'remote_port' => $m[2]);
            }
        }
    }
    return $out;
}

function unique_ips_from_sockets($sockets) {
    $seen = array(); $ips = array();
    foreach ($sockets as $s) {
        $ip = isset($s['remote_ip']) ? $s['remote_ip'] : null;
        if ($ip && !isset($seen[$ip])) { $seen[$ip] = true; $ips[] = $ip; }
    }
    return $ips;
}

function socket_counts_by_ip($sockets) {
    $counts = array();
    foreach ($sockets as $s) {
        $ip = isset($s['remote_ip']) ? $s['remote_ip'] : null;
        if ($ip) { if (!isset($counts[$ip])) $counts[$ip] = 0; $counts[$ip]++; }
    }
    return $counts;
}

/* ==========================
   Provider / EPG helpers
   ========================== */

function slugify($s) { return preg_replace('/[^a-z0-9]+/i', '_', strtolower($s)); }

/** Build username → {provider, display} maps from YAML providers structure. */
function build_username_maps($providers) {
    $u2p = array(); $u2d = array();
    foreach ($providers as $p) {
        $pname = isset($p['name']) ? $p['name'] : '';
        if (!empty($p['proxy_users']) && is_array($p['proxy_users'])) {
            foreach ($p['proxy_users'] as $u) {
                $uname = isset($u['username']) ? $u['username'] : null;
                if ($uname) {
                    $u2p[$uname] = $pname;
                    if (!empty($u['name'])) $u2d[$uname] = $u['name'];
                }
            }
        }
    }
    return array($u2p, $u2d);
}

/** Try to turn provider['url'] into a base (strip known endpoints). */
function provider_base_url($url) {
    $u = $url;
    // Strip query and trailing endpoint (xmltv.php/player_api.php/get.php)
    $u = preg_replace('#\?(.*)$#', '', $u);
    $u = preg_replace('#/(xmltv\.php|player_api\.php|get\.php)$#i', '', rtrim($u, '/'));
    return rtrim($u, '/');
}

/** Minimal HTTP GET (no external libs). */
function http_get_simple($url, $timeout = 15) {
    $ctx = stream_context_create(array(
        'http' => array(
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => "User-Agent: iptvservice-monitor/1.0\r\n"
        ),
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false
        )
    ));
    return @file_get_contents($url, false, $ctx);
}

/**
 * Local cached fetch of live streams list via player_api (stream_id → name + epg_channel_id).
 * If epg_cache.php provides ensure_streams_index_cached(), we delegate to it.
 */
function ensure_streams_index_cached_local($provider, $cacheDir, $ttl = 1800) {
    if (function_exists('ensure_streams_index_cached')) {
        return ensure_streams_index_cached($provider, $cacheDir, $ttl);
    }

    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    $slug = slugify(isset($provider['name']) ? $provider['name'] : 'provider');
    $cacheFile = rtrim($cacheDir, '/')."/{$slug}.streams.json";

    if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        $json = @file_get_contents($cacheFile);
        $map  = @json_decode($json, true);
        if (is_array($map)) return $map;
    }

    $base = provider_base_url(isset($provider['url']) ? $provider['url'] : '');
    $creds = isset($provider['provider_credentials']) && is_array($provider['provider_credentials'])
           ? $provider['provider_credentials'] : array();

    foreach ($creds as $c) {
        $u = isset($c['username']) ? $c['username'] : null;
        $p = isset($c['password']) ? $c['password'] : null;
        if (!$u || !$p) continue;

        $api = $base . '/player_api.php?username=' . urlencode($u)
             . '&password=' . urlencode($p) . '&action=get_live_streams';

        $raw = http_get_simple($api, 20);
        if ($raw === false || $raw === null) continue;

        $arr = @json_decode($raw, true);
        if (!is_array($arr)) continue;

        $map = array();
        foreach ($arr as $row) {
            if (!isset($row['stream_id'])) continue;
            $sid = (string)$row['stream_id'];
            $map[$sid] = array(
                'name'            => isset($row['name']) ? $row['name'] : null,
                'tvg_id'          => isset($row['epg_channel_id']) ? $row['epg_channel_id'] : (isset($row['tvg_id']) ? $row['tvg_id'] : null),
                'tvg_chno'        => isset($row['tvg_chno']) ? $row['tvg_chno'] : null,
                'group'           => isset($row['category_id']) ? $row['category_id'] : null,
                'stream_icon'     => isset($row['stream_icon']) ? $row['stream_icon'] : null,
            );
        }

        @file_put_contents($cacheFile, json_encode($map, JSON_UNESCAPED_SLASHES));
        return $map;
    }

    return array();
}

/** Fallback: find a channel by tvg_chno if stream_id didn’t match. */
function find_channel_by_chno($chanIdxForProvider, $chno) {
    if (!$chanIdxForProvider || $chno === null) return null;
    foreach ($chanIdxForProvider as $meta) {
        if (isset($meta['tvg_chno']) && (string)$meta['tvg_chno'] === (string)$chno) {
            return $meta;
        }
    }
    return null;
}

/**
 * Build provider maps:
 *  - $streamsIndex[provider][stream_id] = { name, tvg_id, tvg_chno, ... }  (from player_api)
 *  - $chanIndex[provider][stream_id]    = { name, tvg_id, tvg_name, tvg_chno, ... } (from M3U; fallback)
 *  - $xmlIndex[provider]                = XMLTV index (by tvg_id)
 *  - $providersByName[provider]         = raw provider array (for short EPG fallback)
 */
function build_provider_maps($providers, $cacheDir) {
    $streamsIndex = array();
    $chanIndex    = array();
    $xmlIndex     = array();
    $providersByName = array();

    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

    foreach ($providers as $p) {
        $name = isset($p['name']) ? $p['name'] : 'provider';
        $providersByName[$name] = $p;

        $streamsIndex[$name] = ensure_streams_index_cached_local($p, $cacheDir, 1800);

        $m3u = ensure_m3u_cached($p, $cacheDir, 21600);  // 6h TTL
        $chanIndex[$name] = $m3u ? parse_m3u_channels($m3u) : array();

        $xml = ensure_xmltv_cached($p, $cacheDir, 21600);
        $xmlIndex[$name] = $xml ? load_xmltv($xml) : null;
    }

    return array($streamsIndex, $chanIndex, $xmlIndex, $providersByName);
}

/* ==========================
   Correlators
   ========================== */

/**
 * Main correlator: unique sockets + recent logs + tail-scan fallback (+ EPG resolution)
 * @param array $ipLabels optional IP→['name'=>...] map to annotate sessions.
 */
function correlate_sessions($providers, $accessLogPath, $tz = 'UTC', $lookback = 600, $ipLabels = []) {
    list($streamsIndex, $chanIndex, $xmlIndex, $providersByName) = build_provider_maps($providers, __DIR__ . '/../cache');
    list($u2p, $u2d) = build_username_maps($providers);

    $sockets   = ss_active_443();
    $socketIps = unique_ips_from_sockets($sockets);
    $counts    = socket_counts_by_ip($sockets);

    $logs = read_recent_log_lines($accessLogPath, $lookback);

    $byIp = array();
    foreach ($logs as $row) {
        $req = isset($row['request']) ? $row['request'] : '';
        list($u, $ch, $path) = extract_username_channel_from_request($req);
        if (!is_streaming_request($path)) continue;
        if (!$u && !$ch) continue;

        $ip = isset($row['ip']) ? $row['ip'] : null;
        if (!$ip) continue;

        $byIp[$ip] = array(
            'time'        => isset($row['time']) ? $row['time'] : null,
            'username'    => $u,
            'channel'     => $ch,
            'request'     => $req,
            'ua'          => isset($row['ua']) ? $row['ua'] : null,
            'iptv_agents' => isset($row['iptv_agents']) ? $row['iptv_agents'] : null
        );
    }

    $missing = array();
    foreach ($socketIps as $ip) { if ($ip && !isset($byIp[$ip])) $missing[] = $ip; }
    if (!empty($missing) && function_exists('read_last_events_for_ips')) {
        $lastEvents = read_last_events_for_ips($accessLogPath, $missing, 250000);
        foreach ($lastEvents as $ip => $row) {
            $req = isset($row['request']) ? $row['request'] : '';
            list($u, $ch, $path) = extract_username_channel_from_request($req);
            if (!is_streaming_request($path)) continue;
            if (!$u && !$ch) continue;

            if (!isset($byIp[$ip])) {
                $byIp[$ip] = array(
                    'time'        => isset($row['time']) ? $row['time'] : null,
                    'username'    => $u,
                    'channel'     => $ch,
                    'request'     => $req,
                    'ua'          => isset($row['ua']) ? $row['ua'] : null,
                    'iptv_agents' => isset($row['iptv_agents']) ? $row['iptv_agents'] : null
                );
            }
        }
    }

    $sessions = array();
    foreach ($socketIps as $ip) {
        if (!$ip) continue;

        $meta = isset($byIp[$ip]) ? $byIp[$ip] : null;
        if (!$meta) continue;

        $username     = isset($meta['username']) ? $meta['username'] : null;
        $providerName = ($username && isset($u2p[$username])) ? $u2p[$username] : null;
        $proxyDisplay = ($username && isset($u2d[$username])) ? $u2d[$username] : null;

        $resolvedName = null;
        $resolvedTvg  = null;
        $prog         = null;

        if ($providerName && !empty($meta['channel'])) {
            $sid = (string)$meta['channel'];

            if (isset($streamsIndex[$providerName][$sid])) {
                $m = $streamsIndex[$providerName][$sid];
                $resolvedName = isset($m['name']) ? $m['name'] : null;
                $resolvedTvg  = isset($m['tvg_id']) ? $m['tvg_id'] : null;
            }
            if (!$resolvedName && isset($chanIndex[$providerName][$sid])) {
                $m = $chanIndex[$providerName][$sid];
                $resolvedName = isset($m['name']) && $m['name'] !== '' ? $m['name']
                              : (isset($m['tvg_name']) ? $m['tvg_name'] : null);
                if (!$resolvedTvg && isset($m['tvg_id'])) $resolvedTvg = $m['tvg_id'];
            }
            if (!$resolvedName) {
                $m = find_channel_by_chno($chanIndex[$providerName], $sid);
                if ($m) {
                    $resolvedName = isset($m['name']) && $m['name'] !== '' ? $m['name']
                                  : (isset($m['tvg_name']) ? $m['tvg_name'] : null);
                    if (!$resolvedTvg && isset($m['tvg_id'])) $resolvedTvg = $m['tvg_id'];
                }
            }

            if ($resolvedTvg && isset($xmlIndex[$providerName])) {
                $prog = program_now_for_tvgid($xmlIndex[$providerName], $resolvedTvg, $tz);
            }
            if (!$prog && isset($providersByName[$providerName])) {
                $provObj = $providersByName[$providerName];
                $short = short_epg_now_for_streamid($provObj, $sid, __DIR__ . '/../cache', $tz, 120);
                if ($short) $prog = $short;
            }
        }

        // IP label integration
        $ipLabel = null;
        if (!empty($ipLabels) && isset($ipLabels[$ip])) {
            $v = $ipLabels[$ip];
            $ipLabel = is_array($v) ? ($v['name'] ?? null) : (string)$v;
        }

        $sessions[] = array(
            'ip'             => $ip,
            'ip_label'       => $ipLabel,
            'username'       => $username,
            'proxy_name'     => $proxyDisplay,
            'provider'       => $providerName,
            'channel_number' => isset($meta['channel']) ? $meta['channel'] : null,
            'channel_name'   => $resolvedName,
            'tvg_id'         => $resolvedTvg,
            'program_title'  => ($prog && isset($prog['title'])) ? $prog['title'] : null,
            'since'          => isset($meta['time']) ? $meta['time'] : null,
            'user_agent'     => isset($meta['ua']) ? $meta['ua'] : null,
            'iptv_agents'    => isset($meta['iptv_agents']) ? $meta['iptv_agents'] : null,
            'connections'    => isset($counts[$ip]) ? $counts[$ip] : 1
        );
    }

    return $sessions;
}

/**
 * Log-only fallback (no sockets).
 */
function correlate_sessions_logonly($providers, $accessLogPath, $tz = 'UTC', $lookback = 600, $ipLabels = []) {
    list($streamsIndex, $chanIndex, $xmlIndex, $providersByName) = build_provider_maps($providers, __DIR__ . '/../cache');
    list($u2p, $u2d) = build_username_maps($providers);

    $logs = read_recent_log_lines($accessLogPath, $lookback);

    $byIp = array();
    foreach ($logs as $row) {
        $req = isset($row['request']) ? $row['request'] : '';
        list($u, $ch, $path) = extract_username_channel_from_request($req);
        if (!is_streaming_request($path)) continue;
        if (!$u && !$ch) continue;

        $ip = isset($row['ip']) ? $row['ip'] : null;
        if (!$ip) continue;

        $byIp[$ip] = array(
            'time'        => isset($row['time']) ? $row['time'] : null,
            'username'    => $u,
            'channel'     => $ch,
            'request'     => $req,
            'ua'          => isset($row['ua']) ? $row['ua'] : null,
            'iptv_agents' => isset($row['iptv_agents']) ? $row['iptv_agents'] : null
        );
    }

    $sessions = array();
    foreach ($byIp as $ip => $meta) {
        $username     = isset($meta['username']) ? $meta['username'] : null;
        $providerName = ($username && isset($u2p[$username])) ? $u2p[$username] : null;
        $proxyDisplay = ($username && isset($u2d[$username])) ? $u2d[$username] : null;

        $resolvedName = null;
        $resolvedTvg  = null;
        $prog         = null;

        if ($providerName && !empty($meta['channel'])) {
            $sid = (string)$meta['channel'];

            if (isset($streamsIndex[$providerName][$sid])) {
                $m = $streamsIndex[$providerName][$sid];
                $resolvedName = isset($m['name']) ? $m['name'] : null;
                $resolvedTvg  = isset($m['tvg_id']) ? $m['tvg_id'] : null;
            }
            if (!$resolvedName && isset($chanIndex[$providerName][$sid])) {
                $m = $chanIndex[$providerName][$sid];
                $resolvedName = isset($m['name']) && $m['name'] !== '' ? $m['name']
                              : (isset($m['tvg_name']) ? $m['tvg_name'] : null);
                if (!$resolvedTvg && isset($m['tvg_id'])) $resolvedTvg = $m['tvg_id'];
            }
            if (!$resolvedName) {
                $m = find_channel_by_chno($chanIndex[$providerName], $sid);
                if ($m) {
                    $resolvedName = isset($m['name']) && $m['name'] !== '' ? $m['name']
                                  : (isset($m['tvg_name']) ? $m['tvg_name'] : null);
                    if (!$resolvedTvg && isset($m['tvg_id'])) $resolvedTvg = $m['tvg_id'];
                }
            }

            if ($resolvedTvg && isset($xmlIndex[$providerName])) {
                $prog = program_now_for_tvgid($xmlIndex[$providerName], $resolvedTvg, $tz);
            }
            if (!$prog && isset($providersByName[$providerName])) {
                $provObj = $providersByName[$providerName];
                $short = short_epg_now_for_streamid($provObj, $sid, __DIR__ . '/../cache', $tz, 120);
                if ($short) $prog = $short;
            }
        }

        $ipLabel = null;
        if (!empty($ipLabels) && isset($ipLabels[$ip])) {
            $v = $ipLabels[$ip];
            $ipLabel = is_array($v) ? ($v['name'] ?? null) : (string)$v;
        }

        $sessions[] = array(
            'ip'             => $ip,
            'ip_label'       => $ipLabel,
            'username'       => $username,
            'proxy_name'     => $proxyDisplay,
            'provider'       => $providerName,
            'channel_number' => isset($meta['channel']) ? $meta['channel'] : null,
            'channel_name'   => $resolvedName,
            'tvg_id'         => $resolvedTvg,
            'program_title'  => ($prog && isset($prog['title'])) ? $prog['title'] : null,
            'since'          => isset($meta['time']) ? $meta['time'] : null,
            'user_agent'     => isset($meta['ua']) ? $meta['ua'] : null,
            'iptv_agents'    => isset($meta['iptv_agents']) ? $meta['iptv_agents'] : null,
            'connections'    => 1
        );
    }
    return $sessions;
}
