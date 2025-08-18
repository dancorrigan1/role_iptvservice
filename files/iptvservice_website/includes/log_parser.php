<?php
// includes/log_parser.php
// Parser for your nginx `log_format main_ext`

$GLOBALS['LOG_STRIP_PREFIXES'] = [];

function set_log_strip_prefixes(array $prefixes) {
    // longest first, so /iptvservice/dev matches before /iptvservice
    usort($prefixes, function($a,$b){ return strlen($b) - strlen($a); });
    $GLOBALS['LOG_STRIP_PREFIXES'] = $prefixes;
}

function parse_time_local($s) {
    // [16/Aug/2025:13:45:12 -0500]
    if (preg_match('/\[(\d{2})\/([A-Za-z]{3})\/(\d{4}):(\d{2}):(\d{2}):(\d{2}) ([+\-]\d{4})\]/', $s, $m)) {
        $map = ['Jan'=>1,'Feb'=>2,'Mar'=>3,'Apr'=>4,'May'=>5,'Jun'=>6,'Jul'=>7,'Aug'=>8,'Sep'=>9,'Oct'=>10,'Nov'=>11,'Dec'=>12];
        $mon = $map[$m[2]] ?? 1;
        $dt  = sprintf('%04d-%02d-%02d %02d:%02d:%02d %s', $m[3], $mon, $m[1], $m[4], $m[5], $m[6], $m[7]);
        $ts  = strtotime($dt);
        return $ts ?: null;
    }
    return null;
}

/*
 main_ext:
 $remote_addr - $remote_user [$time_local] "$request" $status $body_bytes_sent "$http_referer" "$http_user_agent" $request_time $upstream_response_time $iptv_agents $upstream_addr $upstream_status
*/
function parse_main_ext_line($line) {
    $re = '/^(\S+)\s+-\s+(\S+)\s+(\[[^\]]+\])\s+"([^"]*)"\s+(\d{3})\s+(\d+)\s+"([^"]*)"\s+"([^"]*)"\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)/';
    if (!preg_match($re, $line, $m)) return null;

    $ts = parse_time_local($m[3]);
    return [
        'time'            => $ts,
        'ip'              => $m[1],
        'user'            => $m[2] !== '-' ? $m[2] : null,
        'request'         => $m[4],               // METHOD path?query HTTP/x.y
        'status'          => (int)$m[5],
        'bytes'           => (int)$m[6],
        'referer'         => $m[7] !== '-' ? $m[7] : null,
        'ua'              => $m[8],
        'request_time'    => is_numeric($m[9]) ? (float)$m[9] : null,
        'upstream_time'   => $m[10],
        'iptv_agents'     => $m[11] !== '-' ? $m[11] : null,
        'upstream_addr'   => $m[12] !== '-' ? $m[12] : null,
        'upstream_status' => $m[13] !== '-' ? (int)$m[13] : null,
    ];
}

function read_recent_log_lines($path, $lookbackSeconds = 600, $maxLines = 50000) {
    if (!is_file($path)) return [];
    $fh = fopen($path, 'r');
    if (!$fh) return [];
    $lines = [];
    fseek($fh, 0, SEEK_END);
    $pos = ftell($fh);
    $chunk = 16384;
    $buf = '';
    while ($pos > 0 && count($lines) < $maxLines) {
        $read = ($pos >= $chunk) ? $chunk : $pos;
        $pos -= $read;
        fseek($fh, $pos);
        $buf = fread($fh, $read) . $buf;
        while (($nl = strrpos($buf, "\n")) !== false) {
            $line = substr($buf, $nl+1);
            $buf  = substr($buf, 0, $nl);
            if ($line !== '') $lines[] = $line;
        }
    }
    if ($buf !== '') $lines[] = $buf;
    fclose($fh);

    $cutoff = time() - $lookbackSeconds;
    $out = [];
    foreach ($lines as $line) {
        $row = parse_main_ext_line($line);
        if (!$row) continue;
        if ($row['time'] !== null && $row['time'] >= $cutoff) $out[] = $row;
    }
    return $out;
}

/*
 Supported path shapes you showed:
  - /{user}/live/{user}/{user}/{channel}.ts
  - /live/{user}/{user}/{channel}.ts
  - //{user}/...  (double slash)
  - /{user}/player_api.php?username=...&...
  - /{user}/xmltv.php?username=...&...
*/
function extract_username_channel_from_request($request) {
    $username = null;
    $channel  = null;
    $path     = null;
    $qs       = '';

    // "GET /path?query HTTP/1.1"
    if (preg_match('#^\S+\s+(\S+)\s+HTTP#', $request, $m)) {
        $full = $m[1];
        // collapse multiple slashes
        $full = preg_replace('#/{2,}#', '/', $full);
        $parts = explode('?', $full, 2);
        $path = $parts[0];
        if (isset($parts[1])) $qs = $parts[1];
    }

    // strip deployment prefixes (e.g. /iptvservice/dev, /iptvservice)
    if ($path) {
        $prefixes = isset($GLOBALS['LOG_STRIP_PREFIXES']) ? $GLOBALS['LOG_STRIP_PREFIXES'] : [];
        foreach ($prefixes as $pre) {
            if ($pre !== '' && strpos($path, $pre) === 0) {
                $path = substr($path, strlen($pre));
                if ($path === '' || $path[0] !== '/') $path = '/' . $path;
                break;
            }
        }
    }

    if ($path) {
        $segs = array_values(array_filter(explode('/', $path), 'strlen'));

        if (!empty($segs)) {
            $first  = strtolower($segs[0]);
            $second = isset($segs[1]) ? strtolower($segs[1]) : '';
            $firstIsPhp  = (substr($first,  -4) === '.php');
            $secondIsPhp = (substr($second, -4) === '.php');

            if ($first === 'live') {
                // /live/{user}/{user}/{channel}.ts
                $username = isset($segs[1]) ? $segs[1] : null;

            } elseif ($firstIsPhp) {
                // /xmltv.php?... or /player_api.php?...  (no user segment in path)
                // -> leave $username null; we’ll read it from the query string below.

            } elseif ($second === 'live' || $secondIsPhp) {
                // /{user}/live/...  OR /{user}/xmltv.php  OR /{user}/player_api.php
                $username = $segs[0];

            } else {
                // generic /{user}/...
                $username = $segs[0];
            }
        }

        // Extract channel from .../{digits}.ts
        if (preg_match('#/(\d{1,7})\.ts$#', $path, $mm)) {
            $channel = $mm[1];
        }
    }

    if ($qs) {
        parse_str($qs, $q);
        if (empty($username) && !empty($q['username'])) $username = $q['username'];
        if (empty($username) && !empty($q['user']))     $username = $q['user'];
        if (empty($channel)  && !empty($q['channel']) && preg_match('/^\d{1,7}$/', $q['channel'])) {
            $channel = $q['channel'];
        }
    }

    return array($username, $channel, $path);
}



function read_last_events_for_ips($path, array $ips, $maxLines = 200000) {
    $result = [];
    if (!is_file($path) || empty($ips)) return $result;

    // Normalize lookups
    $target = [];
    foreach ($ips as $ip) { if ($ip) $target[$ip] = true; }

    $fh = fopen($path, 'r');
    if (!$fh) return $result;

    fseek($fh, 0, SEEK_END);
    $pos = ftell($fh);
    $chunk = 16384;
    $buf = '';
    $linesSeen = 0;

    while ($pos > 0 && $linesSeen < $maxLines && count($result) < count($target)) {
        $read = ($pos >= $chunk) ? $chunk : $pos;
        $pos -= $read;
        fseek($fh, $pos);
        $buf = fread($fh, $read) . $buf;

        // Consume complete lines from the end
        while (($nl = strrpos($buf, "\n")) !== false) {
            $line = substr($buf, $nl + 1);
            $buf  = substr($buf, 0, $nl);
            if ($line === '') continue;
            $linesSeen++;

            $row = parse_main_ext_line($line);
            if (!$row) continue;

            $ip = $row['ip'] ?? null;
            if ($ip && isset($target[$ip]) && !isset($result[$ip])) {
                // First time we see this IP from the end = its latest event
                $result[$ip] = $row;
                if (count($result) >= count($target)) break 2; // all found
            }

            if ($linesSeen >= $maxLines) break 2;
        }
    }

    fclose($fh);
    return $result;
}
