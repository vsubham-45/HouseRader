<?php
// public/user/edit_profile.php
require_once __DIR__ . '/../../src/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../src/db.php';

// helper
function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// require login
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
    // Basic validation
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $avatar = trim($_POST['avatar'] ?? '');

// Google users must NOT update avatar manually
if ($isGoogleUser) {
    $avatar = null; // ignore submitted avatar
}
 // emoji or small text

    // validation
    if ($name === '') $errors[] = 'Name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';

    // Avatar: limit length so DB doesn't get giant strings; emojis can be multi-byte,
    // use mb_strlen and limit to, say, 8 characters (enough for an emoji or short emoji sequence).
    if ($avatar !== '' && mb_strlen($avatar) > 8) {
        $errors[] = 'Avatar must be a short emoji or a few characters (max 8).';
    }

    if (empty($errors)) {
        try {
            // include avatar column in update
            if ($activeRole === 'seller') {
                $stmt = $pdo->prepare('UPDATE sellers SET name = :name, email = :email, phone = :phone, avatar = :avatar, updated_at = NOW() WHERE id = :id');
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name = :name, email = :email, phone = :phone, avatar = :avatar, updated_at = NOW() WHERE id = :id');
            }
            $stmt->execute([
                ':name'   => $name,
                ':email'  => $email,
                ':phone'  => $phone ?: null,
                ':avatar' => $isGoogleUser ? null : ($avatar ?: null),


                ':id'     => $me['id']
            ]);

            // update session copy (explicit keys to avoid surprises)
            if ($activeRole === 'seller') {
                $_SESSION['seller'] = array_merge($_SESSION['seller'] ?? [], [
                    'name'  => $name,
                    'email' => $email,
                    'phone' => $phone ?: null,
                    'avatar' => $isGoogleUser
    ? ($_SESSION['seller']['avatar'] ?? null)
    : ($avatar ?: null)

                ]);
            } else {
                $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], [
                    'name'  => $name,
                    'email' => $email,
                    'phone' => $phone ?: null,
                    'avatar' => $isGoogleUser
    ? ($_SESSION['user']['avatar'] ?? null)
    : ($avatar ?: null)

                ]);
            }

            $_SESSION['flash'] = 'Profile updated successfully.';
            $_SESSION['flash_type'] = 'success';
            header('Location: profile.php');
            exit;
        } catch (Exception $e) {
            error_log('edit_profile: update failed: ' . $e->getMessage());
            $errors[] = 'Update failed. Try again later.';
        }
    }
}

// load current values (fresh from DB to ensure accuracy)
try {
    $tbl = $activeRole === 'seller' ? 'sellers' : 'users';
    // include avatar in SELECT
    $stmt = $pdo->prepare("SELECT id, name, email, phone, avatar, auth_provider FROM {$tbl}
 WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $me['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $row = ['name' => $me['name'] ?? '', 'email' => $me['email'] ?? '', 'phone' => $me['phone'] ?? '', 'avatar' => $me['avatar'] ?? ''];
}

// 🔁 Re-evaluate auth provider from DB (authoritative source)
$authProvider = $row['auth_provider'] ?? $authProvider ?? 'local';
$isGoogleUser = ($authProvider === 'google');


?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Edit profile — HouseRader</title>

  <!-- apply saved theme early to avoid flash -->
  <script>
    (function(){
      try {
        if (localStorage.getItem('hr_theme') === 'dark') document.documentElement.classList.add('dark');
      } catch(e){}
    })();
  </script>

  <link rel="stylesheet" href="../assets/css/index.css" />
  <link rel="stylesheet" href="../assets/css/edit_profile.css" />
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
      <h1>Edit profile</h1>

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

  <!-- ✅ AVATAR BLOCK (ONLY THIS ONE) -->
  <label class="field">
    <span class="field-label">Avatar</span>

    <?php if ($isGoogleUser): ?>
  <div class="avatar-row">
    <img
      src="<?php echo safe($row['avatar']); ?>"
      alt="Google profile photo"
      class="avatar-preview"
      referrerpolicy="no-referrer"
    >
  </div>
  <small class="hint">
    Your profile photo is managed by Google and cannot be changed here.
  </small>
<?php else: ?>

      <div class="avatar-row">
        <input
          name="avatar"
          id="avatarInput"
          value="<?php echo safe($row['avatar'] ?? ''); ?>"
          placeholder="e.g. 👨🏻‍🦱 or 🧑‍💻"
        >
        <div id="avatarPreview" class="avatar-preview">
          <?php echo safe($row['avatar'] ?? '👨🏻‍🦱'); ?>
        </div>
      </div>
      <small class="hint">
        Enter a single emoji or short emoji sequence (max 8 chars).
      </small>
    <?php endif; ?>
  </label>

  <!-- NAME -->
  <label class="field">
    <span class="field-label">Name</span>
    <input name="name" value="<?php echo safe($row['name'] ?? ''); ?>" required>
  </label>

  <!-- EMAIL -->
  <label class="field">
    <span class="field-label">Email</span>
    <input name="email" type="email" value="<?php echo safe($row['email'] ?? ''); ?>" required>
  </label>

  <!-- PHONE -->
  <label class="field">
    <span class="field-label">Phone</span>
    <input name="phone" value="<?php echo safe($row['phone'] ?? ''); ?>">
  </label>

  <div class="form-actions">
  <a class="btn-muted" href="profile.php">Cancel</a>
  <button class="btn-primary" type="submit">Save changes</button>
</div>

</form>

    </div>
  </main>

  <footer class="hr-footer">
    <div class="container"><small>© <?php echo date('Y'); ?> HouseRader</small></div>
  </footer>

  <!-- update avatar preview live + theme sync -->
  <script>
    try {
      var avatarInput = document.getElementById('avatarInput');
      var avatarPreview = document.getElementById('avatarPreview');
      if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('input', function(){
          avatarPreview.textContent = avatarInput.value || '👨🏻‍🦱';
        });
      }

      window.addEventListener('storage', function(e){
        if (e.key === 'hr_theme') {
          if (e.newValue === 'dark') document.documentElement.classList.add('dark');
          else document.documentElement.classList.remove('dark');
        }
      });
    } catch(e){}
  </script>
</body>
</html>
