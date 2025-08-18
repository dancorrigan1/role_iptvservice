<?php
// includes/auth.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const AUTH_FILE = __DIR__ . '/../data/auth.json';

function auth_bootstrap(): void {
    $dir = dirname(AUTH_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!is_file(AUTH_FILE)) {
        $default = [
            'username'      => 'admin',
            'password_hash' => password_hash('admin', PASSWORD_DEFAULT),
            'force_change'  => true,
            'updated_at'    => time(),
        ];
        @file_put_contents(AUTH_FILE, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

function auth_load(): array {
    $raw = @file_get_contents(AUTH_FILE);
    if ($raw === false) return [];
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

function auth_save(array $data): bool {
    $data['updated_at'] = time();
    return (bool) @file_put_contents(AUTH_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function auth_is_logged_in(): bool {
    return !empty($_SESSION['auth_logged_in']) && !empty($_SESSION['auth_user']);
}

function auth_username(): ?string {
    return $_SESSION['auth_user'] ?? null;
}

function auth_login(string $username, string $password): bool {
    $cfg = auth_load();
    if (empty($cfg['username']) || empty($cfg['password_hash'])) return false;
    if ($username !== $cfg['username']) return false;
    if (!password_verify($password, $cfg['password_hash'])) return false;
    session_regenerate_id(true);
    $_SESSION['auth_logged_in'] = true;
    $_SESSION['auth_user'] = $cfg['username'];
    $_SESSION['force_change'] = !empty($cfg['force_change']);
    return true;
}

function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function auth_force_change(): bool {
    return !empty($_SESSION['force_change']);
}

function auth_set_password(string $old, string $new, string $confirm): array {
    $cfg = auth_load();
    if (!password_verify($old, $cfg['password_hash'])) {
        return [false, 'Current password is incorrect'];
    }
    if ($new === '' || $confirm === '' || $new !== $confirm) {
        return [false, 'New passwords do not match'];
    }
    if (strlen($new) < 6) {
        return [false, 'New password must be at least 6 characters'];
    }
    $cfg['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
    $cfg['force_change'] = false;
    if (!auth_save($cfg)) {
        return [false, 'Failed to save new password'];
    }
    $_SESSION['force_change'] = false;
    return [true, 'Password changed'];
}

function auth_require_login(): void {
    if (!auth_is_logged_in()) {
        header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
        exit;
    }
    if (auth_force_change() && !preg_match('~change_password\.php$~', $_SERVER['REQUEST_URI'] ?? '')) {
        header('Location: change_password.php');
        exit;
    }
}

function auth_require_login_api(): void {
    if (!auth_is_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

function is_mobile(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return (bool) preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $ua);
}
