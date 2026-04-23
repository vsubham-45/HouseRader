<?php
// public/seller/seller_index.php
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

// Require seller login
$seller = $_SESSION['seller'] ?? null;
if (!$seller) {
    header("Location: ../user/login.php");
    exit;
}

// Helpers
function safe($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function format_price($p) {
    if ($p === null || $p === "") return "—";
    return "₹" . number_format((float)$p);
}

// UPDATED HELPER: return paths relative to public/seller page (../assets/...)
function image_url($path) {
    // default placeholder relative to this page
    $placeholder = '../assets/img/placeholder.png';

    if (!$path) return $placeholder;
    $path = trim($path);

    // absolute URL -> use as-is
    if (preg_match('#^https?://#i', $path)) return $path;

    // If path contains 'assets/img' (maybe 'public/assets/img' or 'assets/img/...')
    $pos = strpos($path, 'assets/img');
    if ($pos !== false) {
        // return relative-to-seller page
        return '../' . substr($path, $pos);
    }

    // If path begins with a slash (root relative), try to convert to relative by searching assets/img
    if (strpos($path, '/') === 0) {
        $pos2 = strpos($path, 'assets/img');
        if ($pos2 !== false) {
            return '../' . substr($path, $pos2);
        }
        // otherwise keep as-is (rare)
        return $path;
    }

    // If it contains 'public/assets/img'
    if (strpos($path, 'public/assets/img') !== false) {
        $pos3 = strpos($path, 'assets/img');
        return '../' . substr($path, $pos3);
    }

    // Otherwise assume a filename stored like "img.jpg"
    return '../assets/img/' . ltrim($path, '/');
}

// Load fresh seller details (avatar, name, etc.)
$DEFAULT_AVATAR = "👨🏻‍🦱";
try {
    $stmt = $pdo->prepare(
        "SELECT id, name, email, phone, avatar, auth_provider
         FROM sellers
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $seller['id']]);
    $sellerRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: $seller;
} catch (Exception $e) {
    error_log("seller_index: seller lookup failed: " . $e->getMessage());
    $sellerRow = $seller;
}

$sellerAvatar = !empty($sellerRow['avatar'])
    ? $sellerRow['avatar']
    : $DEFAULT_AVATAR;


// Check whether a user account exists for this email (so we can show the switch)
$has_user_account = false;
try {
    $uStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $uStmt->execute([':email' => $sellerRow['email']]);
    $has_user_account = (bool) $uStmt->fetchColumn();
} catch (Exception $e) {
    // ignore
}

// Fetch seller properties
try {
    $stmt = $pdo->prepare("SELECT id, title, img1, city, locality, min_price, min_rent, is_featured, status FROM properties WHERE seller_id = :sid ORDER BY created_at DESC");
    $stmt->execute([':sid' => $sellerRow['id']]);
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("seller_index: property fetch failed: " . $e->getMessage());
    $properties = [];
}

$totalProps = count($properties);
$featuredCount = 0;
$liveCount = 0;

foreach ($properties as $p) {
    if (!empty($p['is_featured'])) $featuredCount++;
    if (!empty($p['status']) && $p['status'] === 'live') $liveCount++;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Seller Dashboard — HouseRader</title>

<!-- apply theme early -->
<script>
  (function(){
    try { if (localStorage.getItem('hr_theme') === 'dark') document.documentElement.classList.add('dark'); } catch(e){}
  })();
</script>

<link rel="stylesheet" href="../assets/css/index.css">
<link rel="stylesheet" href="../assets/css/seller_index.css">
<link rel="stylesheet" href="../assets/css/inbox_fab.css">

</head>

<body>
<header class="hr-navbar">
  <div class="nav-left">
    <a class="brand" href="seller_index.php">🏠<span class="brand-text">HouseRadar</span></a>
  </div>

  <div class="nav-center"></div>

  <div class="nav-right">
    <button id="themeBtn" class="theme-btn" aria-pressed="false">🌤️</button>

    <!-- Header inbox button with small red dot (added to mirror public/index.php) -->
    <a id="navInboxBtn" href="../inbox.php" title="Inbox" style="display:inline-flex;align-items:center;justify-content:center;margin-right:10px;position:relative;text-decoration:none;">
      <span aria-hidden="true" style="font-size:18px;line-height:1">💬</span>
      <span id="navInboxDot" aria-hidden="true"
            style="position:absolute;top:2px;right:-2px;width:10px;height:10px;background:#ff3b30;border-radius:50%;display:none;box-shadow:0 0 0 2px rgba(255,255,255,0.03)"></span>
    </a>

    <a class="icon-btn" href="../user/profile.php" title="Profile">
  <?php render_avatar($sellerAvatar, $sellerRow['auth_provider'] ?? 'local', 34); ?>
</a>

  </div>
</header>

<main class="page-main">
  <section class="container dashboard-top">
    <div class="dashboard-head">
      <div>
        <h1>Seller Dashboard</h1>
        <p class="muted">Welcome back, <?php echo safe($sellerRow['name']); ?> — manage your listings and performance here.</p>
      </div>

      <div class="dashboard-actions">
        <a class="btn-primary" href="add_property.php">+ Add New Property</a>

        <!-- Switch to buyer mode: styled button (uses POST to switch_to_user.php) -->
        <form method="post" action="switch_to_user.php" style="display:inline;margin-left:8px;">
          <button class="btn-outline switch-btn" type="submit" title="Switch to buyer mode">🔁 Switch to buyer mode</button>
        </form>
      </div>
    </div>

    <div class="dashboard-stats" role="region" aria-label="Quick stats">
      <div class="stat-box" title="Total properties">
        <div class="stat-left"><div class="stat-icon">🏷️</div></div>
        <div class="stat-right">
          <div class="stat-num"><?php echo $totalProps; ?></div>
          <div class="stat-label">Total properties</div>
        </div>
      </div>

      <div class="stat-box" title="Live listings">
        <div class="stat-left"><div class="stat-icon">✅</div></div>
        <div class="stat-right">
          <div class="stat-num"><?php echo $liveCount; ?></div>
          <div class="stat-label">Live listings</div>
        </div>
      </div>

      <div class="stat-box" title="Featured listings">
        <div class="stat-left"><div class="stat-icon">★</div></div>
        <div class="stat-right">
          <div class="stat-num"><?php echo $featuredCount; ?></div>
          <div class="stat-label">Featured</div>
        </div>
      </div>
    </div>
  </section>

  <section class="container listings-grid" aria-labelledby="yourListings">
    <h2 id="yourListings">Your listings</h2>

    <?php if (empty($properties)): ?>
      <div class="empty-state">
        <div class="empty-emoji">🏠</div>
        <div>
          <h3>No properties yet</h3>
          <p class="muted">You don't have any listings. Add your first property to reach buyers.</p>
          <a class="btn-primary" href="add_property.php">Add first property</a>
        </div>
      </div>
    <?php else: ?>
      <div class="grid" role="list">
        <?php foreach ($properties as $p):
          // FIXED IMAGE HANDLING (relative paths for local server)
          $img = image_url($p['img1'] ?? null);

          $loc = trim(($p['locality'] ? $p['locality'] . ", " : "") . ($p['city'] ?: ""));
          $price = $p['min_price'] ? format_price($p['min_price']) : ($p['min_rent'] ? "Rent: ".format_price($p['min_rent']) : "Contact for price");
          $status = ucfirst($p['status'] ?? '—');
        ?>
        <article class="card card-grid" role="listitem">
          <div class="card-media-wrap">
            <a class="card-media-link" href="seller_property_details.php?id=<?php echo (int)$p['id']; ?>">
              <div class="card-media" style="background-image:url('<?php echo safe($img); ?>');"></div>
            </a>

            <?php if (!empty($p['is_featured'])): ?>
              <div class="featured-pill">★ Featured</div>
            <?php endif; ?>

            <div class="card-action-overlay" aria-hidden="true">
              <a href="edit_property.php?id=<?php echo (int)$p['id']; ?>" class="overlay-btn">Edit</a>
              <a href="../property_details.php?id=<?php echo (int)$p['id']; ?>" class="overlay-btn outline">View</a>
            </div>
          </div>

          <div class="card-body">
            <h3 class="card-title"><?php echo safe($p['title']); ?></h3>
            <div class="card-meta"><?php echo safe($loc); ?></div>

            <div class="card-bottom">
              <div class="card-price"><?php echo safe($price); ?></div>
              <div class="card-status <?php echo strtolower($status); ?>"><?php echo safe($status); ?></div>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<footer class="hr-footer">
  <div class="container">
    <small>© <?php echo date("Y"); ?> HouseRadar</small>
  </div>
</footer>

<script>
(function(){
  const btn = document.getElementById("themeBtn");
  if (!btn) return;

  function applyTheme(){
    const isDark = document.documentElement.classList.contains("dark");
    btn.textContent = isDark ? "🌙" : "🌤️";
    btn.setAttribute("aria-pressed", isDark ? "true" : "false");
  }

  btn.addEventListener("click", () => {
    document.documentElement.classList.toggle('dark');
    const isDark = document.documentElement.classList.contains('dark');
    try { localStorage.setItem("hr_theme", isDark ? "dark" : "light"); } catch(e){}
    applyTheme();
  });

  applyTheme();
})();
</script>

<!-- Floating Inbox Button -->
<?php if (!empty($seller)): ?>


  <script src="../assets/js/inbox_fab.js" defer></script>
<?php endif; ?>
</body>
</html>
