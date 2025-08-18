<?php
require __DIR__ . '/includes/auth.php';
auth_bootstrap();
auth_require_login();

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$ok, $text] = auth_set_password($_POST['current'] ?? '', $_POST['new'] ?? '', $_POST['confirm'] ?? '');
    if ($ok) { $msg = $text; } else { $err = $text; }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <title>Change Password — IPTV Service</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="stylesheet" href="public/responsive.css?v=1"/>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Inter,Roboto,Arial;background:#0b0f14;color:#e7edf5;margin:0;display:grid;place-items:center;min-height:100vh}
    .card{width:100%;max-width:420px;background:#121821;border:1px solid #1f2a38;border-radius:16px;padding:20px}
    .title{font-weight:700;margin:0 0 12px}
    .muted{color:#7a8aa0}
    .input{width:100%;padding:10px 12px;border:1px solid #1f2a38;border-radius:10px;background:#0f141b;color:#e7edf5;margin-bottom:10px}
    .btn{width:100%;padding:10px 12px;border-radius:10px;border:1px solid #1f2a38;background:#4da3ff;color:#fff;cursor:pointer}
    .ok{color:#34d399;margin-bottom:10px}
    .err{color:#ff5d5d;margin-bottom:10px}
    .row{display:flex;gap:8px;justify-content:space-between}
    .row a{color:#e7edf5;text-decoration:none}
  </style>
</head>
<body>
  <form class="card" method="post" autocomplete="off">
    <h1 class="title">Change password</h1>
    <p class="muted" style="margin-top:-6px">Please set a strong password.</p>
    <?php if ($msg): ?><div class="ok"><?=$msg?></div><?php endif; ?>
    <?php if ($err): ?><div class="err"><?=$err?></div><?php endif; ?>
    <input class="input" name="current" type="password" placeholder="Current password" required>
    <input class="input" name="new" type="password" placeholder="New password (min 6 chars)" required>
    <input class="input" name="confirm" type="password" placeholder="Confirm new password" required>
    <button class="btn" type="submit">Save</button>
    <div class="row" style="margin-top:10px">
      <span></span>
      <a href="index.php">Back to app</a>
    </div>
  </form>
</body>
</html>
