<?php
require __DIR__ . '/includes/auth.php';
auth_bootstrap();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if (auth_login($u, $p)) {
        $next = $_GET['next'] ?? 'index.php';
        if (auth_force_change()) $next = 'change_password.php';
        header("Location: " . $next);
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <title>Login — IPTV Service</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="stylesheet" href="public/responsive.css?v=1"/>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Inter,Roboto,Arial;background:#0b0f14;color:#e7edf5;margin:0;display:grid;place-items:center;min-height:100vh}
    .card{width:100%;max-width:380px;background:#121821;border:1px solid #1f2a38;border-radius:16px;padding:20px}
    .title{font-weight:700;margin:0 0 12px}
    .muted{color:#7a8aa0}
    .input{width:100%;padding:10px 12px;border:1px solid #1f2a38;border-radius:10px;background:#0f141b;color:#e7edf5;margin-bottom:10px}
    .btn{width:100%;padding:10px 12px;border-radius:10px;border:1px solid #1f2a38;background:#4da3ff;color:#fff;cursor:pointer}
    .error{color:#ff5d5d;margin-bottom:10px}
  </style>
</head>
<body>
  <form class="card" method="post" autocomplete="off">
    <h1 class="title">Sign in</h1>
    <p class="muted" style="margin-top:-6px"></p>
    <?php if ($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <input class="input" name="username" placeholder="Username" autofocus>
    <input class="input" name="password" type="password" placeholder="Password">
    <button class="btn" type="submit">Login</button>
  </form>
</body>
</html>
