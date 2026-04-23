<?php
// public/user/profile.php
require_once __DIR__ . '/../../src/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../src/db.php';

/* ===========================
   AVATAR RENDER HELPER
   =========================== */
function render_avatar($avatar, $authProvider, $size = 34, $alt = 'Avatar') {
    $DEFAULT_AVATAR = '👨🏻‍🦱';

    $authProvider = $authProvider ?: 'local';
    $avatar = trim((string)$avatar);

    if ($authProvider === 'google' && preg_match('#^https?://#i', $avatar)) {
        $sizePx = (int)$size;
        echo '<img src="' . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '"
                   alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"
                   style="
                     width:' . $sizePx . 'px;
                     height:' . $sizePx . 'px;
                     border-radius:50%;
                     object-fit:cover;
                     display:block;
                   " />';
        return;
    }

    echo htmlspecialchars($avatar ?: $DEFAULT_AVATAR, ENT_QUOTES, 'UTF-8');
}

// Helper
function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Determine current identity (prefer seller if present)
$user   = $_SESSION['user'] ?? null;
$seller = $_SESSION['seller'] ?? null;
$me = $seller ?? $user;

// protect page
if (empty($me)) {
    // not logged in — redirect to login under user folder
    header('Location: login.php');
    exit;
}

// flash
$flash = $_SESSION['flash'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'info';

// Defensive: if the flash is a logout message but user is logged-in, ignore it.
if (($flash === 'You have been logged out.' || ($flash && strpos(strtolower($flash), 'logged out') !== false))
    && (isset($_SESSION['user']) || isset($_SESSION['seller']))
) {
    $flash = null;
}

// clear flash from session (if it exists)
if (isset($_SESSION['flash'])) {
    unset($_SESSION['flash'], $_SESSION['flash_type']);
}


// check if user has a seller account (only relevant when logged in as user)
$sellerExists = false;
if (!empty($user)) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM sellers WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $user['email']]);
        $sellerExists = (bool)$stmt->fetch();
    } catch (Exception $e) {
        // swallow — not critical
        error_log("profile: seller check failed: " . $e->getMessage());
    }
}

// show which role is active
$activeRole = $seller ? 'seller' : 'user';

/*
 * Avatar handling:
 * - Prefer the avatar stored in the DB (fresh lookup by id)
 * - If DB avatar is empty/null, fall back to session avatar if set
 * - Finally fall back to the default emoji
 */


$DEFAULT_AVATAR = '👨🏻‍🦱';
$navAvatar = $DEFAULT_AVATAR;
$navAuthProvider = 'local';
try {
    if (!empty($seller) && isset($seller['id'])) {
        $stmt = $pdo->prepare(
            "SELECT avatar, auth_provider FROM sellers WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $seller['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if (!empty($row['avatar'])) $navAvatar = $row['avatar'];
            $navAuthProvider = $row['auth_provider'] ?? 'local';
        }

    } elseif (!empty($user) && isset($user['id'])) {
        $stmt = $pdo->prepare(
            "SELECT avatar, auth_provider FROM users WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if (!empty($row['avatar'])) $navAvatar = $row['avatar'];
            $navAuthProvider = $row['auth_provider'] ?? 'local';
        }
    }
} catch (Exception $e) {
    error_log("profile: avatar lookup failed: " . $e->getMessage());
    $navAvatar = $me['avatar'] ?? $DEFAULT_AVATAR;
    $navAuthProvider = 'local';
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Your profile — HouseRader</title>

  <!-- apply saved theme early to avoid flash -->
  <script>
    (function(){
      try {
        if (localStorage.getItem('hr_theme') === 'dark') document.documentElement.classList.add('dark');
      } catch(e){}
    })();
  </script>

  <!-- Keep index look + a small profile stylesheet -->
  <link rel="stylesheet" href="../assets/css/index.css" />
  <link rel="stylesheet" href="../assets/css/profile.css" />
</head>
<body>
  <header class="hr-navbar" role="banner">
    <div class="nav-left">
      <a class="brand" href="../index.php" aria-label="HouseRader home">🏘️ <span class="brand-text">HouseRader</span></a>
    </div>
    <div class="nav-center"></div>
    <div class="nav-right">
      <a class="btn-login" href="../index.php">Home</a>
      <a class="btn-signup" href="../seller/seller_index.php">Browse</a>
    </div>
  </header>

  <main class="page-main">
    <section class="profile-hero">
      <div class="container">
        <h1 class="hero-title">Your profile</h1>
        <p class="hero-sub">Manage your account details and role.</p>
      </div>
    </section>

    <div class="container">
      <?php if ($flash): ?>
        <div class="flash flash-<?php echo safe($flash_type); ?>" role="status">
          <?php echo safe($flash); ?>
        </div>
      <?php endif; ?>

      <div class="profile-wrapper">
        <aside class="profile-card">
          <div class="profile-avatar">
  <?php
    render_avatar(
  $navAvatar,
  $navAuthProvider,
  72,
  'Profile avatar'
);

  ?>
</div>

          <div class="profile-name"><?php echo safe($me['name'] ?? 'User'); ?></div>
          <div class="profile-email"><?php echo safe($me['email'] ?? ''); ?></div>
          <?php if (!empty($me['phone'])): ?>
            <div class="profile-phone"><?php echo safe($me['phone']); ?></div>
          <?php endif; ?>

          <div class="profile-actions">
            <a class="btn-primary btn-block" href="edit_profile.php">Edit profile</a>

            <?php if ($activeRole === 'user' && $sellerExists): ?>
              <form method="post" action="switch_role.php" style="margin:0;">
                <input type="hidden" name="mode" value="seller" />
                <button type="submit" class="btn-primary btn-block inverted">Switch to seller mode</button>
              </form>
            <?php elseif ($activeRole === 'seller'): ?>
              <a class="btn-primary btn-block inverted" href="../seller/seller_index.php">Open seller dashboard</a>
            <?php endif; ?>

            <form method="post" action="logout.php" style="margin:0;">
              <button type="submit" class="btn-primary btn-block logout">Logout</button>
            </form>
          </div>
        </aside>

        <section class="profile-main">
          <div class="card">
            <h2>Account details</h2>
            <dl class="account-list">
              <div class="row">
                <dt>Name</dt>
                <dd><?php echo safe($me['name'] ?? ''); ?></dd>
              </div>
              <div class="row">
                <dt>Email</dt>
                <dd><?php echo safe($me['email'] ?? ''); ?></dd>
              </div>
              <div class="row">
                <dt>Role</dt>
                <dd style="text-transform:capitalize;"><?php echo safe($activeRole); ?></dd>
              </div>
              <?php if (!empty($me['phone'])): ?>
              <div class="row">
                <dt>Phone</dt>
                <dd><?php echo safe($me['phone']); ?></dd>
              </div>
              <?php endif; ?>
              <div class="row">
                <dt>Member since</dt>
                <dd>
                  <?php
                    // show created_at if available (try both users and sellers tables)
                    $created = null;
                    try {
                      $tbl = $seller ? 'sellers' : 'users';
                      $stmt = $pdo->prepare("SELECT created_at FROM {$tbl} WHERE email = :email LIMIT 1");
                      $stmt->execute([':email' => $me['email']]);
                      $created = $stmt->fetchColumn();
                    } catch (Exception $e) { /* ignore */ }
                    echo $created ? date('M j, Y', strtotime($created)) : '—';
                  ?>
                </dd>
              </div>
            </dl>
          </div>

          <div class="card" style="margin-top:12px;">
            <h2>Security</h2>
            <p>If you want to change your password, click below.</p>
            <a class="btn-primary" href="change_password.php">Change password</a>
          </div>
        </section>
      </div>
    </div>
  </main>

  <footer class="hr-footer">
    <div class="container">
      <small>© <?php echo date('Y'); ?> HouseRader</small>
    </div>
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
  } catch(e){}
</script>
</body>
</html>
