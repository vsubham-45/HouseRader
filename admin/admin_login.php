<?php
// /admin/admin_login.php
declare(strict_types=1);

// --- session helper (correct path) ---
$session_file = __DIR__ . '/../src/session.php';
if (!file_exists($session_file)) {
    http_response_code(500);
    echo "Missing session helper";
    exit;
}
require_once $session_file;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Already logged in? ----
if (!empty($_SESSION['admin']['id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

// ---- Permanent admin credentials ----
const HR_ADMIN_EMAIL = 'admin@power.com';
const HR_ADMIN_PASSWORD = 'admin';

// ---- CSRF ----
if (!isset($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['admin_csrf'];

$errors = [];
$old = ['email' => ''];

// ---- POST handler ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf, $token)) {
        $errors[] = "Invalid CSRF token.";
    } else {

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $old['email'] = $email;

        if ($email === '' || $password === '') {
            $errors[] = "Email and password are required.";
        } else {

            if (strcasecmp($email, HR_ADMIN_EMAIL) === 0 && $password === HR_ADMIN_PASSWORD) {

                // ✅ SINGLE, CANONICAL ADMIN SESSION
                $_SESSION['admin'] = [
                    'id'    => 1,
                    'email' => HR_ADMIN_EMAIL,
                    'name'  => 'Admin'
                ];

                if (function_exists('session_regenerate_id')) {
                    session_regenerate_id(true);
                }

                header("Location: admin_dashboard.php");
                exit;

            } else {
                $errors[] = "Invalid email or password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Login — HouseRader</title>

    <!-- Apply saved dark theme before render -->
    <script>
        (function () {
            try {
                if (localStorage.getItem('hr_theme') === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>

    <!-- Load admin styles -->
    <link rel="stylesheet" href="../admin/css/admin_index.css">
    <link rel="stylesheet" href="../admin/css/admin_login.css">
</head>
<body>

<main class="admin-login-root">

<div class="login-card">

    <a href="../public/index.php" class="brand">
        🏘️ <span class="brand-text">HouseRader</span>
    </a>

    <h1>Admin Login</h1>
    <p class="muted">Enter admin credentials to continue.</p>

    <?php if (!empty($errors)): ?>
        <div class="msg msg-error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="login-form">

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <div class="form-row">
            <button class="btn-primary" type="submit">Login</button>
            <a class="btn-muted" href="../public/index.php">Back</a>
        </div>

    </form>

    <p class="hint muted">
        Demo login → <code>admin@power.com</code> / <code>admin</code>
    </p>

</div>

<footer class="login-footer">
    © <?= date("Y") ?> HouseRader
</footer>

</main>

</body>
</html>
