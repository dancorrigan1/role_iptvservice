<?php
require __DIR__ . '/includes/auth.php';
auth_bootstrap();
auth_require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>IPTV Live Sessions</title>
<link rel="stylesheet" href="public/responsive.css?v=1" />
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Inter,Roboto,Arial;margin:0;background:#0b0f14;color:#e7edf5}
.wrap{max-width:1200px;margin:0 auto;padding:16px}
.controls{display:flex;gap:8px;align-items:center;margin-bottom:12px}
button{background:#121821;color:#e7edf5;border:1px solid #1f2a38;border-radius:8px;padding:8px 12px;cursor:pointer}
button:hover{background:#182232}
.count{margin-left:auto;color:#7a8aa0}
table{width:100%;border-collapse:collapse}
thead th{position:sticky;top:0;background:#0f141b;border-bottom:1px solid #1f2a38;padding:10px;text-align:left}
tbody td{border-bottom:1px solid #1f2a38;padding:8px;vertical-align:top}
.muted{color:#7a8aa0}
.sessions-table .col-provider, .sessions-table .col-program, .sessions-table .col-ua { /* labels for responsive rules */ }
</style>
<script>
window.STATUS_API = 'api/status.php?debug=1';
window.STATUS_AUTOREFRESH_SECONDS = 15;
</script>
</head>
<body>
  <div class="wrap">
    <h1>Live Sessions</h1>
    <div class="controls">
      <button id="sessionsShowAll">All</button>
      <button id="sessionsShowWatching">Watching</button>
      <button id="sessionsShowIdle">Idle</button>
      <button id="sessionsRefresh">Refresh</button>
      <a href="index.php" style="margin-left:8px;text-decoration:none;color:#e7edf5">Back</a>
      <span class="count" id="liveSessionsCount">Loading…</span>
    </div>
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
  <script src="public/monitor.js?v=2025-08-16d" defer></script>
</body>
</html>
