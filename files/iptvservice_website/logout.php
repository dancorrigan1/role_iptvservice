<?php
require __DIR__ . '/includes/auth.php';
auth_bootstrap();
auth_logout();
header('Location: login.php');
