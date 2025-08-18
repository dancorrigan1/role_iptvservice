<?php
require __DIR__ . '/includes/auth.php';
auth_bootstrap();
auth_require_login();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>IPTV — Mobile</title>
<link rel="stylesheet" href="public/mobile.css?v=2"/>
</head>
<body>
  <header class="m-header">
    <div class="m-title">IPTV</div>
    <div class="m-actions">
      <a class="m-btn" href="index.php?view=desktop" title="Switch to desktop view">Desktop</a>
      <a class="m-btn" href="logout.php">Logout</a>
    </div>
  </header>

  <main class="m-main">
    <section class="m-controls">
      <input id="mSearch" class="m-input" placeholder="Search by name, username, IP, channel">
      <div class="m-btnbar">
        <button id="mAll" class="m-btn m-ghost">All</button>
        <button id="mWatching" class="m-btn m-ghost">Watching</button>
        <button id="mIdle" class="m-btn m-ghost">Idle</button>
        <button id="mRefresh" class="m-btn">Refresh</button>
      </div>
      <div class="m-meta" id="mCount">Loading…</div>
    </section>

    <section id="mCards" class="m-cards">
      <!-- Cards injected here -->
      <div class="m-muted">Loading…</div>
    </section>
  </main>

  <script>
    window.STATUS_API='api/status.php?debug=1';
    window.STATUS_AUTOREFRESH_SECONDS=15;
  </script>
  <script src="public/monitor.mobile.js?v=2025-08-16b" defer></script>
</body>
</html>
