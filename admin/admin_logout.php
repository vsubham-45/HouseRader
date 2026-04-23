<?php
// public/admin_logout.php
declare(strict_types=1);

// load session helper (same pattern used across the project)
$session_file = __DIR__ . '/../src/session.php';
if (file_exists($session_file)) {
    require_once $session_file;
} else {
    // Fallback: start session if helper missing
    if (session_status() === PHP_SESSION_NONE) session_start();
}

// Ensure session started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear application-specific session keys (covers admin/seller/user variants)
$keysToClear = [
    'admin', 'seller', 'user', 'user_id', 'userId', 'viewer_id',
    'csrf_token', 'property_csrf', 'flash_success', 'flash_error'
];

foreach ($keysToClear as $k) {
    if (isset($_SESSION[$k])) unset($_SESSION[$k]);
}

// Optionally, clear entire session array
$_SESSION = [];

// Destroy session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"] ?? '/',
        $params["domain"] ?? '',
        $params["secure"] ?? false,
        $params["httponly"] ?? true
    );
}

// Destroy PHP session
@session_destroy();

// Regenerate a fresh session id (defensive)
if (session_status() === PHP_SESSION_NONE) session_start();
session_regenerate_id(true);

// Redirect user to admin login if available, else to homepage
$adminLogin = 'admin_login.php';
$index = '../public/index.php';
$target = file_exists(__DIR__ . '/' . $adminLogin) ? $adminLogin : $index;

// small header-safe redirect
header('Location: ' . $target);
exit;
