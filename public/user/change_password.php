<?php
// public/user/change_password.php
require_once __DIR__ . '/../../src/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../src/db.php';

function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$user = $_SESSION['user'] ?? null;
$seller = $_SESSION['seller'] ?? null;
$me = $seller ?? $user;
if (empty($me)) {
    header('Location: login.php');
    exit;
}

$activeRole = $seller ? 'seller' : 'user';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new === '' || strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
    if ($new !== $confirm) $errors[] = 'New password and confirmation do not match.';

    if (empty($errors)) {
        // fetch current hash
        $tbl = $activeRole === 'seller' ? 'sellers' : 'users';
        $stmt = $pdo->prepare("SELECT password_hash FROM {$tbl} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $me['id']]);
        $hash = $stmt->fetchColumn();

        if ($hash && password_verify($current, $hash)) {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE {$tbl} SET password_hash = :ph, updated_at = NOW() WHERE id = :id");
            $upd->execute([':ph' => $newHash, ':id' => $me['id']]);

            $_SESSION['flash'] = 'Password changed successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: profile.php');
            exit;
        } else {
            $errors[] = 'Current password is incorrect.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Change password — HouseRader</title>

  <!-- apply saved theme early to avoid flash (same approach used on index/profile) -->
  <script>
    (function(){
      try {
        if (localStorage.getItem('hr_theme') === 'dark') document.documentElement.classList.add('dark');
      } catch(e){}
    })();
  </script>

  <link rel="stylesheet" href="../assets/css/index.css" />
  <link rel="stylesheet" href="../assets/css/change_password.css" />
</head>
<body>
  <header class="hr-navbar" role="banner">
    <div class="nav-left">
      <a class="brand" href="../index.php" aria-label="HouseRader home">🏘️ <span class="brand-text">HouseRader</span></a>
    </div>
    <div class="nav-right">
      <a class="btn-login" href="../index.php">Home</a>
    </div>
  </header>

  <main class="page-main">
    <div class="container">
      <h1>Change password</h1>

      <?php if (!empty($errors)): ?>
        <div class="card card-error" role="alert">
          <ul>
            <?php foreach ($errors as $e): ?>
              <li><?php echo safe($e); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="" class="card form-card" novalidate>
        <label class="field">
          <span class="field-label">Current password</span>
          <input name="current_password" type="password" required>
        </label>

        <label class="field">
          <span class="field-label">New password</span>
          <input name="new_password" type="password" required>
        </label>

        <label class="field">
          <span class="field-label">Confirm new password</span>
          <input name="confirm_password" type="password" required>
        </label>

        <div class="form-actions">
          <a class="btn-muted" href="profile.php">Cancel</a>
          <button class="btn-primary" type="submit">Change password</button>
        </div>
      </form>
    </div>
  </main>

  <footer class="hr-footer">
    <div class="container"><small>© <?php echo date('Y'); ?> HouseRader</small></div>
  </footer>

  <!-- Keep theme sync so changes on homepage reflect here live -->
  <script>
    try {
      window.addEventListener('storage', function(e){
        if (e.key === 'hr_theme') {
          if (e.newValue === 'dark') document.documentElement.classList.add('dark');
          else document.documentElement.classList.remove('dark');
        }
      });
    } catch (e){}
  </script>
</body>
</html>
