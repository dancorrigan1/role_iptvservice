<?php
// includes/epg_cache.php

/**
 * This module:
 *  - picks a username/password per provider (proxy user preferred)
 *  - caches M3U and XMLTV to /cache
 *  - parses M3U into stream_id -> { name, tvg_id, tvg_chno, ... }
 *  - loads XMLTV and answers "what's on now" by tvg_id
 *  - can fallback to short EPG (player_api.php?action=get_short_epg&stream_id=...)
 *
 * You may set EPG_BASE_URL (absolute, e.g. https://iptv.yiafe.com) before including this file.
 * If not set, we try to derive it from HTTP_HOST.
 */

if (!defined('EPG_BASE_URL')) {
    if (!empty($_SERVER['HTTP_HOST'])) {
        define('EPG_BASE_URL', 'https://' . $_SERVER['HTTP_HOST']);
    } else {
        // Fallback – edit me if you run from CLI/cron
        define('EPG_BASE_URL', 'https://iptv.yiafe.com');
    }
}

// NEW: tolerate many URL shapes and query strings
function extract_stream_id_from_url($url) {
    $url = trim($url);
    // most common: /live/user/pass/12345.ts  OR .../12345.m3u8 OR .../12345?token=...
    if (preg_match('#/(\d{1,9})(?=\.ts|\.m3u8|\?|/|$)#', $url, $m)) {
        return $m[1];
    }
    // fallback: last numeric path segment
    if (preg_match('#/(\d{1,9})(?:/)?$#', parse_url($url, PHP_URL_PATH), $m)) {
        return $m[1];
    }
    return null;
}


/** Pick credentials (proxy user first, then provider_credentials). */
function pick_userpass_for_provider($provider) {
    // Try proxy users
    if (!empty($provider['proxy_users']) && is_array($provider['proxy_users'])) {
        foreach ($provider['proxy_users'] as $u) {
            $un = isset($u['username']) ? trim($u['username']) : '';
            $pw = isset($u['password']) ? trim($u['password']) : '';
            if ($un !== '' && $pw !== '') return array($un, $pw);
        }
    }
    // Try provider_credentials (upstream)
    if (!empty($provider['provider_credentials']) && is_array($provider['provider_credentials'])) {
        foreach ($provider['provider_credentials'] as $c) {
            $un = isset($c['username']) ? trim($c['username']) : '';
            $pw = isset($c['password']) ? trim($c['password']) : '';
            if ($un !== '' && $pw !== '') return array($un, $pw);
        }
    }
    return array(null, null);
}

function provider_slug($name) {
    $key = $name ? $name : 'provider';
    return preg_replace('/[^a-z0-9]+/i', '_', $key);
}

function ensure_dir($dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return is_dir($dir);
}

/** Generic HTTP GET (cURL), returns body or null. */
function http_get($url, $timeout = 30) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'iptvservice/epg-cache'
    ));
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) return null;
    return $body;
}

/** Cache a URL to a file if missing/stale. */
function cache_fetch($url, $path, $ttl) {
    $need = true;
    if (is_file($path)) {
        $age = time() - filemtime($path);
        if ($age < $ttl) $need = false;
    }
    if ($need) {
        $body = http_get($url, 60);
        if ($body !== null) {
            @file_put_contents($path, $body);
        }
    }
    return is_file($path) ? $path : null;
}

/** Build URLs for xmltv/m3u/short_epg on THIS server using chosen credentials. */
function url_xmltv($user, $pass) {
    return EPG_BASE_URL . '/xmltv.php?username=' . rawurlencode($user) . '&password=' . rawurlencode($pass);
}
function url_m3u($user, $pass) {
    return EPG_BASE_URL . '/get.php?username=' . rawurlencode($user) . '&password=' . rawurlencode($pass) . '&type=m3u_plus&output=ts';
}
function url_short_epg($user, $pass, $stream_id) {
    return EPG_BASE_URL . '/player_api.php?username=' . rawurlencode($user) . '&password=' . rawurlencode($pass)
        . '&action=get_short_epg&stream_id=' . rawurlencode($stream_id);
}

/** Ensure XMLTV cached (default TTL 6h). Returns path or null. */
function ensure_xmltv_cached($provider, $cacheDir, $ttlSeconds = 21600) {
    $slug = provider_slug(isset($provider['name']) ? $provider['name'] : '');
    $path = rtrim($cacheDir, '/')."/{$slug}.xml";
    if (!ensure_dir($cacheDir)) return null;

    list($u,$p) = pick_userpass_for_provider($provider);
    if (!$u || !$p) return is_file($path) ? $path : null;

    $url = url_xmltv($u,$p);
    return cache_fetch($url, $path, $ttlSeconds);
}

/** Ensure M3U cached (default TTL 6h). Returns path or null. */
function ensure_m3u_cached($provider, $cacheDir, $ttlSeconds = 21600) {
    $slug = provider_slug(isset($provider['name']) ? $provider['name'] : '');
    $path = rtrim($cacheDir, '/')."/{$slug}.m3u";
    if (!ensure_dir($cacheDir)) return null;

    list($u,$p) = pick_userpass_for_provider($provider);
    if (!$u || !$p) return is_file($path) ? $path : null;

    $url = url_m3u($u,$p);
    return cache_fetch($url, $path, $ttlSeconds);
}

/** Cache short EPG per stream_id (TTL default 120s). Returns associative array or null. */
function short_epg_now_for_streamid($provider, $stream_id, $cacheDir, $tz='UTC', $ttlSeconds = 120) {
    $slug = provider_slug(isset($provider['name']) ? $provider['name'] : '');
    $dir  = rtrim($cacheDir, '/')."/short_epg";
    if (!ensure_dir($dir)) return null;
    $path = "{$dir}/{$slug}_{$stream_id}.json";

    list($u,$p) = pick_userpass_for_provider($provider);
    if (!$u || !$p) return null;

    $url  = url_short_epg($u,$p,$stream_id);
    $file = cache_fetch($url, $path, $ttlSeconds);
    if (!$file) return null;

    $json = json_decode(@file_get_contents($file), true);
    if (!is_array($json)) return null;

    // Xtream "epg_listings": [{title, start, end, ...}, ...]
    if (empty($json['epg_listings']) || !is_array($json['epg_listings'])) return null;

    $now = new DateTime('now', new DateTimeZone($tz));
    $now_ts = $now->getTimestamp();

    foreach ($json['epg_listings'] as $item) {
        // start/end can be UNIX or string; normalize to epoch
        $st = isset($item['start_timestamp']) ? (int)$item['start_timestamp'] : null;
        $et = isset($item['stop_timestamp'])  ? (int)$item['stop_timestamp']  : null;

        // Fallback: some APIs use "start" / "end" as strings
        if (!$st && !empty($item['start'])) $st = strtotime($item['start']);
        if (!$et && !empty($item['end']))   $et = strtotime($item['end']);

        if ($st && $et && $now_ts >= $st && $now_ts < $et) {
            return array(
                'title' => isset($item['title']) ? $item['title'] : null,
                'start' => $st,
                'end'   => $et
            );
        }
    }
    return null;
}

/** Parse M3U into stream_id -> meta (tvg_id, name, chno, logo, group, etc.) */
function parse_m3u_channels($m3u_path) {
    $map = array();
    if (!is_file($m3u_path)) return $map;

    $fh = fopen($m3u_path, 'r');
    if (!$fh) return $map;

    $lastMeta = null;
    while (($line = fgets($fh)) !== false) {
        $line = rtrim($line, "\r\n");

        if (strpos($line, '#EXTINF:') === 0) {
            // Example: #EXTINF:-1 tvg-id="ABC" tvg-name="ABC" tvg-logo="..." group-title="News" tvg-chno="123", ABC Channel
            $meta = array(
                'tvg_id'    => null,
                'tvg_name'  => null,
                'tvg_logo'  => null,
                'group'     => null,
                'tvg_chno'  => null,
                'name'      => null,
                'stream_id' => null,
            );
            if (preg_match('/tvg-id="([^"]*)"/i', $line, $m))     $meta['tvg_id']   = $m[1];
            if (preg_match('/tvg-name="([^"]*)"/i', $line, $m))   $meta['tvg_name'] = $m[1];
            if (preg_match('/tvg-logo="([^"]*)"/i', $line, $m))   $meta['tvg_logo'] = $m[1];
            if (preg_match('/group-title="([^"]*)"/i', $line, $m))$meta['group']    = $m[1];
            if (preg_match('/tvg-chno="([^"]*)"/i', $line, $m))   $meta['tvg_chno'] = $m[1];
            if (preg_match('/,(.*)$/', $line, $m))                $meta['name']     = trim($m[1]);

            // If no display name in the comma tail, fall back to tvg-name
            if (!$meta['name'] && $meta['tvg_name']) $meta['name'] = $meta['tvg_name'];

            $lastMeta = $meta;
            continue;
        }

        // URL line after EXTINF:
        if ($lastMeta && $line !== '' && $line[0] !== '#') {
            $sid = extract_stream_id_from_url($line);
            if ($sid) {
                $lastMeta['stream_id'] = $sid;
                $map[$sid] = $lastMeta;
            }
            $lastMeta = null;
        }
    }
    fclose($fh);
    return $map;
}


/** Load XMLTV into a lightweight index by tvg_id -> programs[] (epoch times). */
function load_xmltv($xml_path) {
    if (!is_file($xml_path)) return null;
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_file($xml_path);
    if ($xml === false) return null;

    $index = array(
        'by_id' => array(), // tvg_id => [ programs... ]
    );

    // Build channel id map (channel@id => display-name)
    $channelNames = array();
    foreach ($xml->channel as $ch) {
        $id = (string)$ch['id'];
        if ($id === '') continue;
        $name = null;
        if (isset($ch->{'display-name'})) {
            foreach ($ch->{'display-name'} as $dn) {
                $name = (string)$dn;
                if ($name) break;
            }
        }
        $channelNames[$id] = $name ?: $id;
    }

    foreach ($xml->programme as $p) {
        $cid = (string)$p['channel'];
        if ($cid === '') continue;

        $start = xmltv_time_to_epoch((string)$p['start']);
        $stop  = xmltv_time_to_epoch((string)$p['stop']);

        $title = null;
        if (isset($p->title)) {
            $title = (string)$p->title;
        }

        if (!isset($index['by_id'][$cid])) $index['by_id'][$cid] = array();
        $index['by_id'][$cid][] = array(
            'start' => $start,
            'end'   => $stop,
            'title' => $title
        );
    }

    // Sort each channel’s programs by start time
    foreach ($index['by_id'] as $cid => &$list) {
        usort($list, function($a,$b){ return $a['start'] - $b['start']; });
    }
    unset($list);

    $index['channel_names'] = $channelNames;
    return $index;
}

/** XMLTV time like "20250816 140000 -0500" or "20250816140000 -0500" -> epoch. */
function xmltv_time_to_epoch($s) {
    // strip non-digits and timezone sign; XMLTV commonly yyyymmddHHMMSS +/-ZZZZ
    if (preg_match('/^(\d{8})(\d{6})\s*([+\-]\d{4})?$/', trim(str_replace(array('T','Z'), array(' ',' '), $s)), $m)) {
        $dt = $m[1] . ' ' . $m[2] . (isset($m[3]) ? ' ' . $m[3] : ' +0000');
        $ts = strtotime($dt);
        return $ts ?: null;
    }
    // last resort
    $ts = strtotime($s);
    return $ts ?: null;
}

/** Current program for tvg_id. */
function program_now_for_tvgid($xmlIndex, $tvg_id, $tz='UTC') {
    if (!$xmlIndex || !$tvg_id) return null;
    if (empty($xmlIndex['by_id'][$tvg_id])) return null;

    $now = new DateTime('now', new DateTimeZone($tz));
    $now_ts = $now->getTimestamp();

    foreach ($xmlIndex['by_id'][$tvg_id] as $p) {
        $st = isset($p['start']) ? $p['start'] : null;
        $et = isset($p['end'])   ? $p['end']   : null;
        if ($st && $et && $now_ts >= $st && $now_ts < $et) {
            return array(
                'title' => isset($p['title']) ? $p['title'] : null,
                'start' => $st,
                'end'   => $et
            );
        }
    }
    return null;
}
