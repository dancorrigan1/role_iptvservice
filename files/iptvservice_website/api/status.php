<?php
// api/status.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
auth_bootstrap();
auth_require_login_api();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/status_helpers.php';

// --- Config (override by env or ?query params) ---
$ACCESS_LOG = getenv('IPTV_ACCESS_LOG') ?: '/var/log/nginx/iptv.yiafe.com_access.log';
if (isset($_GET['log']) && $_GET['log'] !== '') $ACCESS_LOG = $_GET['log'];

$TZ       = isset($_GET['tz']) ? $_GET['tz'] : (getenv('IPTV_TZ') ?: 'UTC');
$LOOKBACK = isset($_GET['lookback']) ? max(60, intval($_GET['lookback'])) : 600;
$LOGONLY  = isset($_GET['logonly']) && $_GET['logonly'] === '1';
$DEBUG    = isset($_GET['debug']) && $_GET['debug'] === '1';

$DEFAULT_CREDS = realpath(__DIR__ . '/../repo/vars/credentials.yml') ?: '/etc/iptvservice/credentials.yml';
$CREDS_FILE    = isset($_GET['creds']) && $_GET['creds'] !== '' ? $_GET['creds'] : $DEFAULT_CREDS;

if (!is_file($CREDS_FILE)) { echo json_encode(['ok'=>false,'error'=>"Credentials file not found: $CREDS_FILE"]); exit; }
if (!function_exists('yaml_parse_file')) { echo json_encode(['ok'=>false,'error'=>'PHP yaml extension is required.']); exit; }
$yaml = @yaml_parse_file($CREDS_FILE);
if ($yaml === false) { echo json_encode(['ok'=>false,'error'=>'Failed to parse credentials YAML.']); exit; }
$providers = $yaml['role_iptvservice__credentials'] ?? [];

// IP labels
$ipLabelsPath = __DIR__ . '/../includes/ip_labels.php';
$ipLabels = is_file($ipLabelsPath) ? (require $ipLabelsPath) : [];

try {
    if ($LOGONLY) {
        $sessions = correlate_sessions_logonly($providers, $ACCESS_LOG, $TZ, $LOOKBACK, $ipLabels);
    } else {
        $sessions = correlate_sessions($providers, $ACCESS_LOG, $TZ, $LOOKBACK, $ipLabels);
    }

    $resp = [
        'ok'       => true,
        'sessions' => array_values($sessions),
        'meta'     => ['tz'=>$TZ,'lookback'=>$LOOKBACK,'logonly'=>$LOGONLY,'accessLog'=>$ACCESS_LOG,'credsFile'=>$CREDS_FILE],
    ];
    if ($DEBUG) $resp['ip_labels_loaded'] = $ipLabels;

    echo json_encode($resp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
