<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_NOTICE);
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require __DIR__ . '/includes/auth.php';
auth_bootstrap();
auth_require_login();

// ---- View override + auto-detect mobile ----
$viewParam = $_GET['view'] ?? '';
if ($viewParam === 'mobile') {
    setcookie('view', 'mobile', time()+60*60*24*30, '/');
    header('Location: mobile.php');
    exit;
}
if ($viewParam === 'desktop') {
    setcookie('view', 'desktop', time()+60*60*24*30, '/');
}
$viewCookie = $_COOKIE['view'] ?? '';
if (is_mobile() && $viewCookie !== 'desktop') {
    header('Location: mobile.php');
    exit;
}

require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/repo.php';

$repo_url  = "https://github.com/dancorrigan1/iptvservice.git";
$clone_dir = __DIR__ . "/repo";
$yaml_path = "vars/credentials.yml";
$branch = $_GET['branch'] ?? '';
$action = $_GET['action'] ?? '';

$ip_user_map = [
    '136.244.52.57' => 'Dan',
    '192.168.1.254' => 'Dan Home',
];

if ($action === 'refresh') {
    if (is_dir($clone_dir)) deleteDir($clone_dir);
    repo_clone($repo_url, $clone_dir, $branch);
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if (!file_exists($clone_dir)) {
    repo_clone($repo_url, $clone_dir, $branch);
}

$yaml_file = $clone_dir . "/" . $yaml_path;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [];
    $data['role_iptvservice__credentials'] = $_POST['providers'] ?? [];

    $all_usernames = [];
    $duplicates = [];
    foreach ($data['role_iptvservice__credentials'] as &$provider) {
        if (isset($provider['proxy_users']) && is_array($provider['proxy_users'])) {
            foreach ($provider['proxy_users'] as &$user) {
                $u = $user['username'] ?? '';
                if ($u !== '') {
                    if (in_array($u, $all_usernames, true)) { $duplicates[] = $u; } else { $all_usernames[] = $u; }
                }
                // Defaults: live=true, vod=false
                $user['live'] = boolish($user['live'] ?? true, true);
                $user['vod']  = boolish($user['vod']  ?? false, false);
            }
            unset($user);
        }
    }
    unset($provider);

    if (!empty($duplicates)) {
        echo "The following usernames are duplicates: " . implode(", ", array_unique($duplicates));
        exit;
    }

    foreach ($data['role_iptvservice__credentials'] as &$provider) {
        if (isset($provider['proxy_users'])) $provider['proxy_users'] = array_values($provider['proxy_users']);
        if (isset($provider['provider_credentials'])) $provider['provider_credentials'] = array_values($provider['provider_credentials']);
    }
    unset($provider);

    $yaml_string = yaml_emit($data);
    if (file_put_contents($yaml_file, $yaml_string) === false) { die("Failed to write YAML file."); }

    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $commit_user = $ip_user_map[$user_ip] ?? 'Unknown User';
    $commit_message = "Updated via Web Form by $commit_user";

    chdir($clone_dir);
    exec("git add " . escapeshellarg($yaml_file));
    exec("git commit -m " . escapeshellarg($commit_message));
    exec("git push");
    deleteDir($clone_dir);
    header("Location: ?action=refresh&saved=1");
    exit;
}

if (!file_exists($yaml_file)) { die("YAML file not found."); }
$data = yaml_parse_file($yaml_file);
if ($data === false) { die("Failed to parse YAML file."); }
$providers = $data['role_iptvservice__credentials'] ?? [];

require __DIR__ . '/includes/layout/header.php';
?>
<div class="container">
  <div class="toolbar">
    <input id="filterInput" class="input" placeholder="Filter by provider name or URL">
    <div class="group">
      <span class="tag" id="provCount"></span>
      <span class="tag" id="userCount"></span>
      <span class="tag" id="credCount"></span>
      <span class="tag warn" id="dupWarn"></span>
    </div>
    <div class="group">
      <button type="button" data-live-filter="all" class="btn btn-ghost" id="liveAllBtn">All</button>
      <button type="button" data-live-filter="true" class="btn btn-ghost" id="liveOnBtn">Live On</button>
      <button type="button" data-live-filter="false" class="btn btn-ghost" id="liveOffBtn">Live Off</button>
    </div>
    <div class="group" style="margin-left:auto">
      <a class="btn" href="index.php?view=mobile" title="Switch to mobile view">Mobile View</a>
      <a class="btn" href="change_password.php">Change Password</a>
      <a class="btn btn-danger" href="logout.php">Logout (<?=htmlspecialchars(auth_username() ?? '')?>)</a>
      <button type="button" id="refreshRepoBtn" class="btn">Refresh Repo</button>
    </div>
  </div>

  <!-- Live Sessions (desktop table view) -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-header">
      <div class="card-title">Live Sessions</div>
      <div class="inline">
        <button id="sessionsShowAll" class="btn btn-ghost" type="button">All</button>
        <button id="sessionsShowWatching" class="btn btn-ghost" type="button">Watching</button>
        <button id="sessionsShowIdle" class="btn btn-ghost" type="button">Idle</button>
        <button id="sessionsRefresh" class="btn" type="button">Refresh</button>
        <span class="tag" id="liveSessionsCount">Loading…</span>
      </div>
    </div>
    <div class="card-body" style="overflow:auto">
      <table class="sessions-table">
        <thead>
          <tr>
            <th>Proxy Name</th>
            <th>Username</th>
            <th>IP</th>
            <th class="col-provider">Provider</th>
            <th>Channel</th>
            <th class="col-program">Program</th>
            <th>Since</th>
            <th class="col-ua">User Agent</th>
          </tr>
        </thead>
        <tbody id="liveSessionsBody">
          <tr><td colspan="8" class="muted">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Provider Editor (unchanged) -->
  <form method="post" id="providersForm">
    <div id="providersContainer" class="grid">
      <?php
        foreach ($providers as $pIndex => $provider) {
            $pu = (isset($provider['proxy_users']) && is_array($provider['proxy_users'])) ? $provider['proxy_users'] : [];
            usort($pu, function($a,$b){ return strcmp($a['username'] ?? '', $b['username'] ?? ''); });
            $pc = (isset($provider['provider_credentials']) && is_array($provider['provider_credentials'])) ? $provider['provider_credentials'] : [];
            $totalUsers = count($pu);
            $totalCreds = count($pc);
            include __DIR__ . '/includes/partials/provider_card.php';
        }
      ?>
    </div>
    <div class="footer-space"></div>
  </form>
</div>

<?php
include __DIR__ . '/includes/partials/templates.php';
require __DIR__ . '/includes/layout/footer.php';

// Status widget config + script (desktop)
echo '<link rel="stylesheet" href="public/responsive.css?v=2">';
echo '<script>window.STATUS_API="api/status.php?debug=1";window.STATUS_AUTOREFRESH_SECONDS=15;</script>';
echo '<script src="public/monitor.js?v=2025-08-16e" defer></script>';
