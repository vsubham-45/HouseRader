<?php
// public/user/login.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../src/db.php';

// Helper
function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// CSRF token for login form
if (empty($_SESSION['csrf_login'])) $_SESSION['csrf_login'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_login'];

$errors = [];
$success = null;
$role_choice = false;

// DEFAULT redirect = homepage
$redirect_to = "../index.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* -----------------------------------------------------------
       ROLE SELECTION (when both buyer & seller exist)
    ------------------------------------------------------------- */
    if (!empty($_POST['choose_role']) && !empty($_SESSION['pending_login'])) {

        $chosen = $_POST['choose_role'] === 'seller' ? 'seller' : 'user';
        $email  = $_SESSION['pending_login']['email'];

        if ($chosen === 'seller') {

            $sstmt = $pdo->prepare("
                SELECT id, name, avatar 
                FROM sellers 
                WHERE email = :email 
                LIMIT 1
            ");
            $sstmt->execute([':email'=>$email]);
            $r = $sstmt->fetch(PDO::FETCH_ASSOC);

            if ($r) {
                if (function_exists('hr_set_seller_session')) {
                    hr_set_seller_session([
                        'id'    => (int)$r['id'],
                        'name'  => $r['name'],
                        'email' => $email,
                        'avatar'=> $r['avatar'] ?? null
                    ]);
                } else {
                    $_SESSION['seller'] = [
                        'id' => (int)$r['id'],
                        'name' => $r['name'],
                        'email' => $email,
                        'avatar' => $r['avatar'] ?? null
                    ];
                    $_SESSION['seller_id'] = (int)$r['id'];
                }

                unset($_SESSION['pending_login']);
                if (function_exists('hr_regenerate_session')) hr_regenerate_session();

                $success = "Logged in as seller. Redirecting…";
                $redirect_to = "../seller/seller_index.php";
            } else {
                $errors[] = "Seller account not found.";
            }

        } else {

            $ustmt = $pdo->prepare("
                SELECT id, name, avatar 
                FROM users 
                WHERE email = :email 
                LIMIT 1
            ");
            $ustmt->execute([':email'=>$email]);
            $r = $ustmt->fetch(PDO::FETCH_ASSOC);

            if ($r) {
                if (function_exists('hr_set_user_session')) {
                    hr_set_user_session([
                        'id'    => (int)$r['id'],
                        'name'  => $r['name'],
                        'email' => $email,
                        'avatar'=> $r['avatar'] ?? null
                    ]);
                } else {
                    $_SESSION['user'] = [
                        'id' => (int)$r['id'],
                        'name' => $r['name'],
                        'email' => $email,
                        'avatar' => $r['avatar'] ?? null
                    ];
                    $_SESSION['user_id'] = (int)$r['id'];
                }

                unset($_SESSION['pending_login']);
                if (function_exists('hr_regenerate_session')) hr_regenerate_session();

                $success = "Logged in. Redirecting…";
                $redirect_to = "../index.php";
            } else {
                $errors[] = "User account not found.";
            }
        }

        $_SESSION['csrf_login'] = bin2hex(random_bytes(16));
        $csrf = $_SESSION['csrf_login'];

    } else {

        /* -----------------------------------------------------------
           NORMAL LOGIN FLOW
        ------------------------------------------------------------- */
        if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_login'], (string)($_POST['csrf'] ?? ''))) {
            $errors[] = "Invalid request.";
        } else {

            $email    = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';

            if ($email === '' || $password === '') {
                $errors[] = "Please fill both email and password.";
            } else {
                try {

                    // USER lookup
                    $ustmt = $pdo->prepare("
                        SELECT id, name, password_hash, avatar, auth_provider
                        FROM users 
                        WHERE email = :email 
                        LIMIT 1
                    ");
                    $ustmt->execute([':email'=>$email]);
                    $userRow = $ustmt->fetch(PDO::FETCH_ASSOC);

                    // SELLER lookup
                    $sstmt = $pdo->prepare("
                        SELECT id AS seller_id, name, password_hash, avatar, auth_provider
                        FROM sellers 
                        WHERE email = :email 
                        LIMIT 1
                    ");
                    $sstmt->execute([':email'=>$email]);
                    $sellerRow = $sstmt->fetch(PDO::FETCH_ASSOC);

                    /* ---- GOOGLE-ONLY GUARD (OPTION B) ---- */
                    if (
                        ($userRow   && ($userRow['auth_provider']   ?? 'local') === 'google') ||
                        ($sellerRow && ($sellerRow['auth_provider'] ?? 'local') === 'google')
                    ) {
                        $errors[] = "This account uses Google Sign-In. Please continue with Google.";
                    } else {

                        $userOk   = $userRow   && password_verify($password, (string)$userRow['password_hash']);
                        $sellerOk = $sellerRow && password_verify($password, (string)$sellerRow['password_hash']);

                        if ($userOk && $sellerOk) {
                            $_SESSION['pending_login'] = ['email' => $email];
                            $role_choice = true;

                        } elseif ($sellerOk) {

                            if (function_exists('hr_set_seller_session')) {
                                hr_set_seller_session([
                                    'id'    => (int)$sellerRow['seller_id'],
                                    'name'  => $sellerRow['name'],
                                    'email' => $email,
                                    'avatar'=> $sellerRow['avatar'] ?? null
                                ]);
                            }

                            if (function_exists('hr_regenerate_session')) hr_regenerate_session();
                            $success = "Logged in as seller. Redirecting…";
                            $redirect_to = "../seller/seller_index.php";

                        } elseif ($userOk) {

                            if (function_exists('hr_set_user_session')) {
                                hr_set_user_session([
                                    'id'    => (int)$userRow['id'],
                                    'name'  => $userRow['name'],
                                    'email' => $email,
                                    'avatar'=> $userRow['avatar'] ?? null
                                ]);
                            }

                            if (function_exists('hr_regenerate_session')) hr_regenerate_session();
                            $success = "Logged in. Redirecting…";
                            $redirect_to = "../index.php";

                        } else {
                            $errors[] = "Invalid email or password.";
                        }
                    }

                } catch (Exception $e) {
                    error_log("Login error: ".$e->getMessage());
                    $errors[] = "Login failed. Try again later.";
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — HouseRadar</title>

<script>
(function(){
  try {
    if (localStorage.getItem('hr_theme') === 'dark')
      document.documentElement.classList.add('dark');
  } catch(e){}
})();
</script>

<link rel="stylesheet" href="../assets/css/common.css" />
<style>
.messages{margin-bottom:12px}
.btn-google{
  display:flex;align-items:center;justify-content:center;
  gap:10px;border:1px solid #ddd;padding:10px;
  border-radius:8px;font-weight:600;
  background:#fff;text-decoration:none;color:#111;
  margin-bottom:14px;
}
.btn-google:hover{background:#f7f7f7}
</style>
</head>

<body>
<header class="hr-navbar">
  <div class="brand">
    <a href="../index.php" style="text-decoration:none;color:inherit;">
      🏘️ <span style="color:var(--primary-variant)">HouseRadar</span>
    </a>
  </div>
</header>

<main>
<section class="page-hero container">
  <h1 class="hero-title">Welcome back</h1>
  <p class="hero-sub">Login to manage your listings, save favourites and contact sellers.</p>
</section>

<div class="auth-wrapper">
<div class="auth-card narrow">

<?php if (!$role_choice && !$success): ?>
<a class="btn-google" href="../../api/google_login.php">
  <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" width="18">
  Continue with Google
</a>
<?php endif; ?>

<div class="messages">
<?php foreach ($errors as $err): ?>
  <div class="msg-error"><?= safe($err) ?></div>
<?php endforeach; ?>
<?php if ($success): ?>
  <div class="msg-success"><?= safe($success) ?></div>
<?php endif; ?>
</div>

<?php if ($role_choice && !$success): ?>

<form method="post" class="role-choices">
  <input type="hidden" name="csrf" value="<?= safe($csrf) ?>">
  <button class="btn-primary" name="choose_role" value="user">Continue as Buyer</button>
  <button class="btn-primary" name="choose_role" value="seller">Continue as Seller</button>
</form>

<?php elseif (!$success): ?>

<form method="post" id="loginForm">
  <input type="hidden" name="csrf" value="<?= safe($csrf) ?>">
  <div class="form-row">
    <label>Email</label>
    <input type="email" name="email" required>
  </div>
 <div class="form-row input-with-toggle">
  <label for="password">Password</label>

  <input
    id="password"
    type="password"
    name="password"
    placeholder="Password"
    required
  >

  <button
    type="button"
    class="toggle-visibility"
    aria-pressed="false"
    data-target="password"
    tabindex="-1"
  >👁</button>
</div>

  <button class="btn-primary">Sign in</button>
  <div class="muted-small">No account? <a href="signup.php">Create one</a></div>
</form>

<?php else: ?>

<script>
setTimeout(()=>location.href="<?= safe($redirect_to) ?>",900);
</script>

<?php endif; ?>

</div>
</div>
</main>
<script>
/* Enter -> next input */
(function(){
  var form = document.getElementById('loginForm');
  if (!form) return;
  var inputs = Array.from(
    form.querySelectorAll('input[type=email],input[type=password]')
  );
  inputs.forEach(function(el, idx){
    el.addEventListener('keydown', function(e){
      if (e.key === 'Enter') {
        if (idx === inputs.length - 1) return;
        e.preventDefault();
        inputs[idx+1].focus();
      }
    });
  });
})();

/* Password visibility */
(function(){
  document.querySelectorAll('.toggle-visibility').forEach(btn => {
    var id = btn.dataset.target;
    var input = document.getElementById(id);
    if (!input) return;

    btn.onclick = function(e){
      e.preventDefault();
      var visible = btn.getAttribute('aria-pressed') === 'true';
      input.type = visible ? 'password' : 'text';
      btn.setAttribute('aria-pressed', visible ? 'false' : 'true');
      btn.textContent = visible ? '👁' : '🙈';
      input.focus();
    };
  });
})();

/* Theme sync across tabs */
window.addEventListener('storage', e => {
  if (e.key === 'hr_theme') {
    document.documentElement.classList.toggle(
      'dark',
      e.newValue === 'dark'
    );
  }
});
</script>

</body>
</html>
