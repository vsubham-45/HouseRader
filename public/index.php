<?php
// public/index.php
// HouseRader — Homepage (Featured section + Listings)
// Requires: src/session.php and src/db.php (PDO $pdo)

require_once __DIR__ . '/../src/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../src/db.php';

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

// Session identities
$user = $_SESSION['user'] ?? null;         // buyer / generic user session
$seller = $_SESSION['seller'] ?? null;     // seller session (Option A you chose)

// Avatar fallback
$DEFAULT_AVATAR = '👨🏻‍🦱';
$navAvatar = $DEFAULT_AVATAR;

$navAuthProvider = 'local';

// If a session exists, prefer the avatar stored in DB. Fall back to default only when DB value is empty/null.
try {
    if (!empty($seller) && isset($seller['id'])) {
        $stmt = $pdo->prepare("SELECT avatar, auth_provider FROM sellers WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $seller['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if (!empty($row['avatar'])) $navAvatar = $row['avatar'];
            $navAuthProvider = $row['auth_provider'] ?? 'local';
        }

    } elseif (!empty($user) && isset($user['id'])) {
        $stmt = $pdo->prepare("SELECT avatar, auth_provider FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if (!empty($row['avatar'])) $navAvatar = $row['avatar'];
            $navAuthProvider = $row['auth_provider'] ?? 'local';
        }
    }
} catch (Exception $e) {
    error_log("index.php: avatar lookup failed: " . $e->getMessage());
    $navAvatar = $seller['avatar'] ?? $user['avatar'] ?? $DEFAULT_AVATAR;
    $navAuthProvider = 'local';
}


// Helpers
function safe($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function format_price($p) {
    if ($p === null || $p === '') return '';
    return '₹' . number_format((float)$p);
}

/**
 * image_url() for pages inside public/
 * - If $path is a full http(s) URL => return as-is
 * - If $path contains 'assets/img' or 'public/assets/img' => normalize to 'assets/img/...' (relative to public/)
 * - If $path looks like a Windows or absolute filesystem path => return basename and map to 'assets/img/<basename>'
 * - Otherwise assume it's a filename and return 'assets/img/<filename>'
 *
 * Using relative 'assets/img/...' is correct here because this file lives in public/
 */

    $placeholder = 'assets/img/placeholder.png';
function image_url($path) {
    if (!$path) return $placeholder;
    $path = trim($path);

    // Full URL -> use as-is
    if (preg_match('#^https?://#i', $path)) return $path;

    // If stored path contains assets/img, extract that segment and use relative public path
    $pos = strpos($path, 'assets/img');
    if ($pos !== false) {
        return ltrim(substr($path, $pos), '/'); // e.g. assets/img/foo.jpg
    }

    // If stored path contains public/assets/img, map to assets/img/...
    $pos2 = strpos($path, 'public/assets/img');
    if ($pos2 !== false) {
        return ltrim(substr($path, $pos2 + strlen('public/')), '/'); // assets/img/...
    }

    // Windows path or absolute filesystem path fallback: take basename
    // e.g. C:\xampp\htdocs\HouseRader\public\assets\img\property_123.jpg
    if (preg_match('#[A-Za-z]:\\\\|\\\\#', $path) || strpos($path, DIRECTORY_SEPARATOR) !== false) {
        $base = basename(str_replace('\\', '/', $path));
        if ($base) return 'assets/img/' . ltrim($base, '/');
    }

    // If it's root-relative and includes assets segment
    if (strpos($path, '/') === 0) {
        $pos3 = strpos($path, 'assets/img');
        if ($pos3 !== false) {
            return ltrim(substr($path, $pos3), '/');
        }
    }

    // Otherwise assume bare filename
    return 'assets/img/' . ltrim($path, '/');
}

$search = trim($_GET['q'] ?? '');
$type   = $_GET['type'] ?? 'all';
$sort   = $_GET['sort'] ?? 'new';
$isSearch = ($search !== '' || $type !== 'all');

$where = " WHERE status = 'live' ";
$searchParams = [];

/* ======================
   SEARCH FILTER
====================== */

if ($search !== '') {

    $words = preg_split('/\s+/', $search);

    $andConditions = [];
$orConditions = [];

foreach ($words as $i => $word) {

    // STRICT MATCH (AND)
    $andConditions[] = "(title LIKE :at$i OR city LIKE :ac$i OR locality LIKE :al$i)";

    // LOOSE MATCH (OR)
    $orConditions[] = "(title LIKE :ot$i OR city LIKE :oc$i OR locality LIKE :ol$i)";

    // Bind params
    $searchParams[":at$i"] = "%$word%";
    $searchParams[":ac$i"] = "%$word%";
    $searchParams[":al$i"] = "%$word%";

    $searchParams[":ot$i"] = "%$word%";
    $searchParams[":oc$i"] = "%$word%";
    $searchParams[":ol$i"] = "%$word%";
}

    $where .= " AND (
    (" . implode(" AND ", $andConditions) . ")
    OR
    (" . implode(" OR ", $orConditions) . ")
)";
}
/* ======================
   TYPE FILTER
====================== */
if ($type === 'buy') {
    $where .= " AND min_price IS NOT NULL";
}
elseif ($type === 'rent') {
    $where .= " AND min_rent IS NOT NULL";
}
elseif ($type === 'pg') {
    $where .= " AND rental_type = 'pg'";
}


// Fetch featured (premium) properties first — only those currently live and featured (and not expired)
$featured = [];

if (!$isSearch) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, title, city, locality, property_type,
                   min_price, min_rent, img1, rental_type,
                   builtup_area1
            FROM properties
            $where
            AND is_featured = 1
            AND (featured_until IS NULL OR featured_until >= NOW())
            ORDER BY featured_priority DESC, featured_order ASC, created_at DESC
            LIMIT 12
        ");
        $stmt->execute($searchParams);
        $featured = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fetch featured error: " . $e->getMessage());
    }
}


$allowedSorts = [
    'new' => 'created_at DESC',
    'price_low' => 'COALESCE(min_price, min_rent) ASC',
    'price_high' => 'COALESCE(min_price, min_rent) DESC'
];

$orderSql = $allowedSorts[$sort] ?? $allowedSorts['new'];

/* ======================
   PAGINATION SETUP
====================== */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

/* ======================
   TOTAL COUNT FOR PAGINATION
====================== */
try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM properties
        $where
        " . (!$isSearch ? "
AND NOT (
    is_featured = 1
    AND (featured_until IS NULL OR featured_until >= NOW())
)
" : "") . "

    ");
    $countStmt->execute($searchParams);
    $totalListings = (int)$countStmt->fetchColumn();
} catch (PDOException $e) {
    $totalListings = 0;
}

$totalPages = max(1, ceil($totalListings / $perPage));

try {
    $stmt = $pdo->prepare("
        SELECT id, title, city, locality, property_type,
               min_price, min_rent, img1, rental_type,
               builtup_area1
        FROM properties
        $where
        " . (!$isSearch ? "
        AND NOT (
            is_featured = 1
            AND (featured_until IS NULL OR featured_until >= NOW())
        )
        " : "") . "
        ORDER BY $orderSql
        LIMIT $perPage OFFSET $offset
    ");

    $stmt->execute($searchParams);
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Fetch listings error: " . $e->getMessage());
    $listings = [];
}



?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>HouseRadar — Pinpoint your Perfect House!</title>
  <link rel="stylesheet" href="assets/css/index.css" />
  <link rel="stylesheet" href="assets/css/inbox_fab.css" />

  <meta name="description" content="HouseRadar — Search apartments, lands, and rentals with featured listings and verified locations." />
</head>
<body>
  <header class="hr-navbar" role="banner">
    <div class="nav-left">
      <a class="brand" href="index.php" aria-label="HouseRadar home">🏠<span class="brand-text">HouseRadar</span></a>
    </div>

    <div class="nav-center">
      <form id="topSearch" action="index.php" method="get">
        <input id="globalSearch"
       name="q"
       class="search-input"
       type="search"
       value="<?= safe($search) ?>"
 placeholder="Search by locality, city or property title (e.g. Goregaon)" aria-label="Search properties" />
        <button type="submit" class="search-btn">🔍</button>
      </form>
    </div>

    <div class="nav-right">
      <button id="themeBtn" class="theme-btn" title="Toggle theme" aria-pressed="false">🌤️</button>

      <?php if (!empty($user) || !empty($seller)): ?>
        <!-- Header inbox button with small red dot (no stylesheet edits required) -->
        <a id="navInboxBtn" href="inbox.php" title="Inbox" style="display:inline-flex;align-items:center;justify-content:center;margin-right:10px;position:relative;text-decoration:none;">
          <span aria-hidden="true" style="font-size:18px;line-height:1">💬</span>
          <span id="navInboxDot" aria-hidden="true"
                style="position:absolute;top:2px;right:-2px;width:10px;height:10px;background:#ff3b30;border-radius:50%;display:none;box-shadow:0 0 0 2px rgba(255,255,255,0.03)"></span>
        </a>

        <button id="profileBtn" class="icon-btn" aria-haspopup="true" aria-expanded="false" title="Open dashboard">
  <?php
   render_avatar($navAvatar, $navAuthProvider, 34);

  ?>
</button>

      <?php else: ?>
        <div class="nav-actions">
          <a href="user/login.php" class="btn-login">Login</a>
          <a href="user/signup.php" class="btn-signup">Sign up</a>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <main class="page-main" role="main">
    <!-- HERO / SEARCH -->
    <section class="hero">
      <div class="hero-inner">
        <h1 class="hero-title">Pinpoint your Perfect House in Mumbai, Thane & Navi Mumbai</h1>
        <p class="hero-sub">Properties from trusted sellers — Featured listings shown at the top.</p>

        <form method="get" class="hero-search">
  <input name="q"
         class="hero-search-input"
         type="search"
         value="<?= safe($search) ?>"
         placeholder="Search by locality, city or project name..." />

  <button type="submit" class="hero-search-btn">Search</button>

  <?php if (!empty($sort)): ?>
    <input type="hidden" name="sort" value="<?= safe($sort) ?>">
  <?php endif; ?>
</form>


        <div class="hero-filters">

  <a href="?type=all&q=<?= safe($search) ?>&sort=<?= safe($sort) ?>"
     class="pill <?= $type==='all'?'active':'' ?>">All</a>

  <a href="index.php?type=buy&q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"
     class="pill <?= $type==='buy'?'active':'' ?>">Buy</a>

  <a href="index.php?type=rent&q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"
     class="pill <?= $type==='rent'?'active':'' ?>">Rent</a>

  <a href="index.php?type=pg&q=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"
     class="pill <?= $type==='pg'?'active':'' ?>">PG</a>

</div>

      </div>
    </section>

    <!-- Premium / Featured Section (horizontal scroll with arrows) -->
    <?php if (!empty($featured)): ?>
    <section class="featured-section" aria-label="Premium properties">
      <div class="container">
        <div class="section-header">
          <h2>Featured Properties</h2>
          <p class="muted">Sponsored listings — shown first</p>
        </div>

        <div class="carousel-wrap">
          <button class="carousel-arrow left" data-target="#featuredGrid" aria-label="Scroll left">‹</button>
          <div class="featured-grid" id="featuredGrid" tabindex="0">
            <?php foreach ($featured as $p):
              $imgRaw = !empty($p['img1']) ? $p['img1'] : '';
              $img = image_url($imgRaw);
              $priceLabel = !empty($p['min_price']) ? format_price($p['min_price']) : (!empty($p['min_rent']) ? 'Rent: '.format_price($p['min_rent']) : 'Contact for price');
              $location = trim(($p['locality'] ? $p['locality'] . ', ' : '') . ($p['city'] ?: ''));
              $rentalType = $p['rental_type'] ?? 'rental';
              $minPriceAttr = $p['min_price'] ?? '';
              $minRentAttr = $p['min_rent'] ?? '';
            ?>
            <article class="card card-featured"
                     data-title="<?php echo safe(mb_strtolower($p['title'])); ?>"
                     data-city="<?php echo safe(mb_strtolower($p['city'])); ?>"
                     data-rental="<?php echo safe($rentalType); ?>"
                     data-min-price="<?php echo safe($minPriceAttr); ?>"
                     data-min-rent="<?php echo safe($minRentAttr); ?>"
                     data-img="<?php echo safe($img); ?>">
              <a class="card-link" href="property_details.php?id=<?php echo (int)$p['id']; ?>">
                <div class="card-media" style="background-image:url('<?php echo safe($img); ?>');" role="img" aria-label="<?php echo safe($p['title']); ?>"></div>

                <div class="card-body">
                  <h3 class="card-title"><?php echo safe($p['title']); ?></h3>
                  <div class="card-meta"><?php echo safe($p['property_type'] . ' • ' . $location); ?></div>
                  <div class="card-price"><?php echo safe($priceLabel); ?></div>
                  <div class="card-details"><?php echo safe((!empty($p['builtup_area1']) ? $p['builtup_area1'].' sqft' : '')); ?></div>
                </div>
              </a>
            </article>
            <?php endforeach; ?>
          </div>
          <button class="carousel-arrow right" data-target="#featuredGrid" aria-label="Scroll right">›</button>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Main listings (horizontal scroll row with arrows) -->
    <section class="listings" aria-label="Property listings">
      <div class="container">
        <div class="listings-header">
          <h2>Latest Listings</h2>
          <form method="get" class="listings-actions">
            <label class="sort-label">Sort:</label>
            <select name="sort" class="sort-select" onchange="this.form.submit()">
  <option value="new" <?= ($sort==='new')?'selected':'' ?>>Newest</option>
  <option value="price_low" <?= ($sort==='price_low')?'selected':'' ?>>Price: Low to High</option>
  <option value="price_high" <?= ($sort==='price_high')?'selected':'' ?>>Price: High to Low</option>
</select>
<?php if (!empty($search)): ?>
<input type="hidden" name="q" value="<?= safe($search) ?>">
<?php endif; ?>
<?php if (!empty($type)): ?>
<input type="hidden" name="type" value="<?= safe($type) ?>">
<?php endif; ?>


            </form>
          </div>
        </div>

        <div class="carousel-wrap">
          <button class="carousel-arrow left" data-target="#cardsRow" aria-label="Scroll left">‹</button>
          <div id="cardsRow" class="cards-row" tabindex="0">
            <?php if (empty($listings)): ?>
              <div class="empty-msg">
    No properties found matching your search.<br>
    Try adjusting filters or clearing search.
</div>
            <?php else: ?>
              <?php foreach ($listings as $p):
                $imgRaw = !empty($p['img1']) ? $p['img1'] : '';
                $img = image_url($imgRaw);
                $priceLabel = !empty($p['min_price']) ? format_price($p['min_price']) : (!empty($p['min_rent']) ? 'Rent: '.format_price($p['min_rent']) : 'Contact for price');
                $location = trim(($p['locality'] ? $p['locality'] . ', ' : '') . ($p['city'] ?: ''));
                $rentalType = $p['rental_type'] ?? 'rental';
                $minPriceAttr = $p['min_price'] ?? '';
                $minRentAttr = $p['min_rent'] ?? '';
              ?>
              <article class="card"
                       data-title="<?php echo safe(mb_strtolower($p['title'])); ?>"
                       data-city="<?php echo safe(mb_strtolower($p['city'])); ?>"
                       data-rental="<?php echo safe($rentalType); ?>"
                       data-min-price="<?php echo safe($minPriceAttr); ?>"
                       data-min-rent="<?php echo safe($minRentAttr); ?>"
                       data-img="<?php echo safe($img); ?>">
                <a class="card-link" href="property_details.php?id=<?php echo (int)$p['id']; ?>">
                  <div class="card-media" style="background-image:url('<?php echo safe($img); ?>');" role="img" aria-label="<?php echo safe($p['title']); ?>"></div>

                  <div class="card-body">
                    <h3 class="card-title"><?php echo safe($p['title']); ?></h3>
                    <div class="card-meta"><?php echo safe($p['property_type'] . ' • ' . $location); ?></div>
                    <div class="card-price"><?php echo safe($priceLabel); ?></div>
                    <div class="card-details"><?php echo safe((!empty($p['builtup_area1']) ? $p['builtup_area1'].' sqft' : '')); ?></div>
                  </div>
                </a>
              </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <button class="carousel-arrow right" data-target="#cardsRow" aria-label="Scroll right">›</button>
        </div>

      </div>
    </section>

    <?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="index.php?page=<?= $i ?>&q=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>&sort=<?= urlencode($sort) ?>"
           class="page-link <?= $i === $page ? 'active' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

    <!-- About -->
    <section id="AboutUs" class="about" aria-label="About us">
      <div class="container about-inner">
        <div>
          <h2>About HouseRadar</h2>
          <p>HouseRadar helps you explore properties across Mumbai, Thane and Navi Mumbai. Premium listings show first — sellers can request approval and subscribe for featured placement.</p>
        </div>

        <div>
          <h3>Contact</h3>
          <p>📞 123456789</p>
          <p>📧 vssubham4545@gmail.com</p>
        </div>
      </div>
    </section>
  </main>

  <footer class="hr-footer">
    <div class="container">
      <small>© <?php echo date('Y'); ?> HouseRadar — Mumbai & surrounding</small>
    </div>
  </footer>


<!-- ===========================
      PROFILE DROPDOWN DASHBOARD
     =========================== -->
<?php if (!empty($user) || !empty($seller)): ?>
<div id="profileMenu" class="profile-menu" hidden>
  <div class="pm-header">
    <div class="pm-avatar">
  <?php
    render_avatar($navAvatar, $navAuthProvider, 42);

  ?>
</div>

    <div class="pm-name"><?php echo safe($user['name'] ?? $seller['name'] ?? 'User'); ?></div>
  </div>

  <button class="pm-btn" onclick="location.href='user/profile.php'">Profile</button>

  <?php
  // Show switch button only if:
  // 1) Logged in as USER
  // 2) A SELLER ACCOUNT exists with same email
  if (!empty($user)) {

      $email = $user['email'];
      $stmt = $pdo->prepare("SELECT id FROM sellers WHERE email = :email LIMIT 1");
      $stmt->execute([':email'=>$email]);
      $sellerExists = $stmt->fetch();

      if ($sellerExists):
  ?>
        <form method="post" action="user/switch_role.php" style="margin:0">
            <input type="hidden" name="mode" value="seller">
            <button class="pm-btn" type="submit">Switch to Seller Mode</button>
        </form>
  <?php
      endif;
  }
  ?>

  <form method="post" action="user/logout.php" style="margin:0;margin-top:6px;">
      <button class="pm-btn logout" type="submit">Logout</button>
  </form>
</div>

<?php endif; ?>

<!-- ===========================
      PROFILE MENU JS
     =========================== -->
<script>
(function(){
  var btn = document.getElementById('profileBtn');
  var menu = document.getElementById('profileMenu');

  if (!btn || !menu) return;

  btn.addEventListener('click', function(e){
    e.stopPropagation();
    var isHidden = menu.hasAttribute('hidden');
    if (isHidden) {
      menu.removeAttribute('hidden');
      btn.setAttribute('aria-expanded','true');
    } else {
      menu.setAttribute('hidden','');
      btn.setAttribute('aria-expanded','false');
    }
  });

  document.addEventListener('click', function(e){
    if (!menu.contains(e.target) && e.target !== btn) {
      menu.setAttribute('hidden','');
      btn.setAttribute('aria-expanded','false');
    }
  });

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      menu.setAttribute('hidden','');
      btn.setAttribute('aria-expanded','false');
    }
  });
})();
</script>

<!-- Floating Inbox Button -->
<?php if (!empty($user) || !empty($seller)): ?>

  <!-- relative asset path (no leading slash) so it resolves to public/assets/js/inbox_fab.js -->
  <script src="assets/js/inbox_fab.js" defer></script>
<?php endif; ?>


<!-- =========================
     HELP & SUPPORT CHATBOT
     INDEX PAGE ONLY
========================= -->

<link rel="stylesheet" href="assets/css/chatbot.css">

<div id="hr-chatbot">
  <button id="hr-chat-toggle">💬 Help & Support</button>

  <div id="hr-chat-box" class="hidden">
    <div class="hr-chat-header">
      <span>🏠 HouseRader Support</span>
      <button id="hr-chat-close">✕</button>
    </div>

    <div id="hr-chat-messages">
      <div class="bot-msg">
        Hi 👋 I’m the HouseRader assistant.<br>
        How can I help you today?
      </div>
    </div>
    <div class="quick-actions">
        <button data-msg="buy">Buy property</button>
        <button data-msg="sell">Sell property</button>
        <button data-msg="featured">Featured listing</button>
        <button data-msg="contact">Contact support</button>
      </div>
  </div>
</div>

<script src="assets/js/chatbot.js"></script>

<script>
(function() {
  const btn = document.getElementById('themeBtn');
  const root = document.documentElement;

  if (!btn) return;

  // Load saved theme
  const savedTheme = localStorage.getItem('hr-theme');
  if (savedTheme === 'dark') {
    root.classList.add('dark');
    btn.textContent = '🌙';
  }

  btn.addEventListener('click', function() {
    root.classList.toggle('dark');

    const isDark = root.classList.contains('dark');

    // Save preference
    localStorage.setItem('hr-theme', isDark ? 'dark' : 'light');

    // Change icon
    btn.textContent = isDark ? '🌙' : '🌤️';
  });
})();
</script>
<script>
document.querySelectorAll('.carousel-arrow').forEach(btn => {
  btn.addEventListener('click', () => {
    const target = document.querySelector(btn.dataset.target);
    if (!target) return;

    const scrollAmount = 400;

    if (btn.classList.contains('left')) {
      target.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
      target.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
  });
});
</script>
<script>
let customSearchActive = false;

document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {

        if (!customSearchActive) {
            e.preventDefault();

            const searchInput = document.querySelector('input[name="q"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }

            customSearchActive = true;

            setTimeout(() => {
                customSearchActive = false;
            }, 2000);
        }
    }
});
</script>
</body>
</html>
