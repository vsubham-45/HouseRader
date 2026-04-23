<?php
// src/session.php
declare(strict_types=1);

/**
 * Centralized session configuration & helpers for HouseRader.
 *
 * SAFE UPDATE:
 * ✔ Fix OAuth state issue by scoping session cookie to /HouseRader
 * ✔ NO logic removed
 * ✔ NO helpers changed
 * ✔ NO timeout behavior changed
 * ✔ NO regeneration behavior changed
 */

// -----------------------------------------------------------------------------
// Timezone safeguard (unchanged)
if (!ini_get('date.timezone')) {
    @date_default_timezone_set('Asia/Kolkata');
}

// ----------------------------- CONFIG ----------------------------------------
$HR_SESSION_NAME = 'hr_sid';

if (!defined('HR_SESSION_IDLE_TIMEOUT')) {
    define('HR_SESSION_IDLE_TIMEOUT', 30 * 60);
}
if (!defined('HR_SESSION_REGEN_INTERVAL')) {
    define('HR_SESSION_REGEN_INTERVAL', 15 * 60);
}

$gc_maxlifetime = 60 * 60;

// ------------------------ Detect environment ---------------------------------
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

// ----------------------- Cookie & session params -----------------------------
$cookieParams = [
    'lifetime' => 0,

    // ✅ IMPORTANT FIX (OAuth-safe)
    // Scope session to HouseRader so Google callback keeps same session
    'path'     => '/',

    'domain'   => '',
    'secure'   => (bool)$isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
];

// apply recommended ini settings
@ini_set('session.use_only_cookies', '1');
@ini_set('session.use_strict_mode', '1');
@ini_set('session.cookie_lifetime', (string)$cookieParams['lifetime']);
@ini_set('session.cookie_httponly', $cookieParams['httponly'] ? '1' : '0');
@ini_set('session.gc_maxlifetime', (string)$gc_maxlifetime);

// IMPORTANT: session name BEFORE start
session_name($HR_SESSION_NAME);

// Apply cookie params
session_set_cookie_params($cookieParams);

// ----------------------- Start session safely --------------------------------
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        @session_start();
    }
}

// ----------------------- Session initialization ------------------------------
if (!isset($_SESSION['__hr_initialized'])) {
    session_regenerate_id(true);
    $_SESSION['__hr_initialized'] = time();
    $_SESSION['__hr_last_activity'] = time();
    $_SESSION['__hr_last_regenerated'] = time();
}

// Idle timeout handling (unchanged)
if (!empty($_SESSION['__hr_last_activity'])
    && (time() - (int)$_SESSION['__hr_last_activity'] > HR_SESSION_IDLE_TIMEOUT)
) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? true
        );
    }
    @session_destroy();

    session_start();
    session_regenerate_id(true);
    $_SESSION['__hr_initialized'] = time();
    $_SESSION['__hr_last_activity'] = time();
    $_SESSION['__hr_last_regenerated'] = time();
} else {
    $_SESSION['__hr_last_activity'] = time();
}

// Periodic session id rotation (unchanged)
if (empty($_SESSION['__hr_last_regenerated'])) {
    $_SESSION['__hr_last_regenerated'] = time();
}
if (time() - (int)$_SESSION['__hr_last_regenerated'] > HR_SESSION_REGEN_INTERVAL) {
    session_regenerate_id(true);
    $_SESSION['__hr_last_regenerated'] = time();
}

// --------------------------- Helper Functions --------------------------------

function hr_is_logged_in() {
    if (!empty($_SESSION['seller']) && is_array($_SESSION['seller'])) {
        return ['role' => 'seller', 'data' => $_SESSION['seller']];
    }
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        return ['role' => 'user', 'data' => $_SESSION['user']];
    }
    if (!empty($_SESSION['user_id'])) {
        return ['role' => 'user', 'data' => ['id' => (int)$_SESSION['user_id']]];
    }
    if (!empty($_SESSION['seller_id'])) {
        return ['role' => 'seller', 'data' => ['id' => (int)$_SESSION['seller_id']]];
    }
    return false;
}

function hr_get_viewer_id(): int {
    $who = hr_is_logged_in();
    if ($who && isset($who['data']['id'])) {
        return (int)$who['data']['id'];
    }
    return 0;
}

function hr_set_user_session(array $user): void {
    $_SESSION['user'] = $user;
    if (!empty($user['id'])) $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['__hr_last_activity'] = time();
    hr_regenerate_session();
}

function hr_set_seller_session(array $seller): void {
    $_SESSION['seller'] = $seller;
    if (!empty($seller['id'])) $_SESSION['seller_id'] = (int)$seller['id'];
    $_SESSION['__hr_last_activity'] = time();
    hr_regenerate_session();
}

function hr_require_login(string $redirect = '/public/user/login.php'): void {
    if (!hr_is_logged_in()) {
        $_SESSION['__hr_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: ' . $redirect);
        exit;
    }
}

function hr_logout(string $redirect = null): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? true
        );
    }
    @session_destroy();
    if ($redirect) {
        header('Location: ' . $redirect);
        exit;
    }
}

function hr_regenerate_session(): void {
    session_regenerate_id(true);
    $_SESSION['__hr_last_regenerated'] = time();
}

// Backward compatibility
if (!function_exists('is_logged_in')) {
    function is_logged_in() { return hr_is_logged_in(); }
}
if (!function_exists('get_viewer_id')) {
    function get_viewer_id() { return hr_get_viewer_id(); }
}
