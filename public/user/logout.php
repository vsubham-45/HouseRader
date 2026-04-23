<?php
// public/user/logout.php
// Safely log the user out, destroy session, and redirect to homepage (non-signed-in state).

require_once __DIR__ . '/../../src/session.php'; // safe session start

if (session_status() === PHP_SESSION_NONE) session_start();

// Clear user & seller session info
if (isset($_SESSION['user'])) {
    unset($_SESSION['user']);
}
if (isset($_SESSION['seller'])) {
    unset($_SESSION['seller']);
}

// Optionally clear a remember-me cookie if you use one (uncomment / replace name if needed)
// if (isset($_COOKIE['remember_me'])) {
//     setcookie('remember_me', '', time() - 3600, '/');
//     // also delete any server-side token if stored
// }

// Destroy entire session safely
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Start a clean session to set the flash message (so it persists for the next request)
session_start();
$_SESSION['flash'] = 'You have been logged out.';
$_SESSION['flash_type'] = 'info';

// Redirect to public homepage (non-signed-in view)
header('Location: ../index.php');
exit;
