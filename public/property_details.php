<?php
// public/property_details.php
declare(strict_types=1);

require_once __DIR__ . '/../src/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../src/db.php';

// small helpers
if (!function_exists('val')) {
    function val(array|object|null $arr, string $key, $default = null) {
        if (is_array($arr)) return array_key_exists($key, $arr) ? $arr[$key] : $default;
        if (is_object($arr)) return property_exists($arr, $key) ? $arr->$key : $default;
        return $default;
    }
}
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('normalize_img')) {
    /**
     * Normalize various stored image values into a usable URL/path for browser.
     */
    function normalize_img(string $raw = null): ?string {
        $raw = trim((string)$raw);
        if ($raw === '') return null;
        if (preg_match('#^https?://#i', $raw) || strpos($raw, '//') === 0) return $raw;
        if (strpos($raw, '/') === 0 || str_starts_with($raw, './') || str_starts_with($raw, '../')) return $raw;
        // check public/assets/img/
        $candidate = __DIR__ . '/assets/img/' . $raw;
        if (file_exists($candidate)) return 'assets/img/' . $raw;
        return $raw;
    }
}

// get id
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo "Invalid property id.";
    exit;
}

// fetch property
try {
    $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Property fetch failed: ' . $e->getMessage());
    $row = false;
}
if (!$row) {
    http_response_code(404);
    echo "Property not found.";
    exit;
}

// map important fields defensively
$title = val($row, 'title', 'Untitled Property');
$property_type = val($row, 'property_type', 'Unknown');
$city = val($row, 'city', '');
$locality = val($row, 'locality', '');
$owner_name = val($row, 'owner_name', 'Owner');

// NEW: address column (optional, may be empty)
$address = val($row, 'address', '');

// retrieve canonical seller id from real column (use seller_id)
$seller_id = (int)val($row, 'seller_id', 0);

// pricing logic
$min_price = val($row, 'min_price', null);
$rent_field = val($row, 'rent', null);
$min_rent = val($row, 'min_rent', null);

$priceLabel = 'Contact for price';
if ($min_price !== null && $min_price !== '') {
    $priceLabel = '₹' . number_format((float)$min_price, ((float)$min_price != floor((float)$min_price)) ? 2 : 0);
} elseif ($rent_field !== null && $rent_field !== '') {
    $priceLabel = '₹' . number_format((float)$rent_field, ((float)$rent_field != floor((float)$rent_field)) ? 2 : 0) . ' /mo';
} elseif ($min_rent !== null && $min_rent !== '') {
    $priceLabel = '₹' . number_format((float)$min_rent, ((float)$min_rent != floor((float)$min_rent)) ? 2 : 0) . ' /mo';
}

// areas
$built = val($row, 'builtup_area1', null);
$carpet = val($row, 'carpet_area1', null);
$builtLabel = ($built !== null && $built !== '') ? rtrim(rtrim((string)$built, '0'), '.') . ' sqft' : '';
$carpetLabel = ($carpet !== null && $carpet !== '') ? rtrim(rtrim((string)$carpet, '0'), '.') . ' sqft' : '';

$furnishing = val($row, 'furnishing', '');
$amenities = val($row, 'amenities', '');
$description = val($row, 'description', '');
$created = val($row, 'created_at', null);
$createdLabel = $created ? date('M j, Y', strtotime($created)) : '';
$is_featured = !empty(val($row, 'is_featured', 0));
$lat = val($row, 'latitude', null);
$lng = val($row, 'longitude', null);

// rental-specific fields
$rental_config = val($row, 'rental_config', null);
$rental_carpet_area = val($row, 'rental_carpet_area', null);

// collect images
$images = [];
for ($i=1;$i<=4;$i++) {
    $k = 'img'.$i;
    $raw = val($row, $k, null);
    if ($raw && trim((string)$raw) !== '') {
        $src = normalize_img($raw);
        if ($src) $images[] = $src;
    }
}
if (empty($images)) {
    $fallbackKeys = ['image','image1','photo','thumb','img','image_url'];
    foreach ($fallbackKeys as $fk) {
        $raw = val($row, $fk, null);
        if ($raw && trim((string)$raw) !== '') {
            $src = normalize_img($raw);
            if ($src) { $images[] = $src; break; }
        }
    }
}
if (empty($images)) {
    $images[] = 'assets/img/placeholder.png';
}

// collect flattened configurations config1..config6
$configs = [];
for ($i=1;$i<=6;$i++) {
    $ck = 'config'.$i;
    $pk = 'price'.$i;
    $bk = 'builtup_area'.$i;
    $car = 'carpet_area'.$i;
    $cfg = val($row, $ck, null);
    if ($cfg === null || trim((string)$cfg) === '') continue;
    $configs[] = [
        'label' => $cfg,
        'price' => (val($row, $pk, null) !== null && val($row, $pk, null) !== '') ? (float)val($row, $pk, null) : null,
        'built' => (val($row, $bk, null) !== null && val($row, $bk, null) !== '') ? (string)val($row,$bk,null) : null,
        'carpet' => (val($row, $car, null) !== null && val($row,$car,null) !== '') ? (string)val($row,$car,null) : null,
    ];
}

// safe header include (if you have a partial)
$header_candidates = [__DIR__ . '/partials/header.php', __DIR__ . '/header.php'];
$header_included = false;
foreach ($header_candidates as $hf) {
    if (file_exists($hf)) { include $hf; $header_included = true; break; }
}
if (!$header_included) {
    echo '<header class="hr-navbar"><div class="container"><a class="brand" href="index.php">🏠 HouseRadar</a></div></header>';
}

// small helper to mark the page as rental for CSS (.for-rent)
$is_rental = (strtolower((string)$property_type) === 'rental');

// ---------------------------
// Chat / inbox logic (server-side)
// ---------------------------

// determine viewer id
$viewer_id = 0;
if (function_exists('hr_get_viewer_id')) {
    $viewer_id = (int)hr_get_viewer_id();
} elseif (!empty($_SESSION['user_id'])) {
    $viewer_id = (int)$_SESSION['user_id'];
} elseif (!empty($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['id'])) {
    $viewer_id = (int)$_SESSION['user']['id'];
} elseif (!empty($_SESSION['seller']) && is_array($_SESSION['seller']) && !empty($_SESSION['seller']['id'])) {
    $viewer_id = (int)$_SESSION['seller']['id'];
}

// Default policy: show chat to everyone EXCEPT the property owner (seller).
$show_chat = ($seller_id > 0 ? $viewer_id !== $seller_id : true);

// mark guest
$is_guest = ($viewer_id === 0);

// ---------------------------
// Record property view (one per account / one per session)
// ---------------------------
// Do not record if the viewer is the property owner
try {
    if (!($seller_id > 0 && $viewer_id === $seller_id)) {
        // prepare visitor identifiers
        $visitor_session = session_id() ?: null;
        if (!$visitor_session) {
            // ensure a session id available — create a fallback session id (non-persistent)
            try { $visitor_session = bin2hex(random_bytes(12)); } catch (Throwable $e) { $visitor_session = uniqid('sess_', true); }
        }

        // Determine IP (respect X-Forwarded-For if present)
        $visitor_ip = null;
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $visitor_ip = trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $visitor_ip = trim($_SERVER['REMOTE_ADDR']);
        }
        $visitor_ip = $visitor_ip !== null ? substr($visitor_ip, 0, 45) : null;

        // User agent (trim to 255)
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

        if ($viewer_id > 0) {
            // logged-in viewer: ensure exactly one row per (property_id, visitor_user_id)
            $checkStmt = $pdo->prepare('SELECT id FROM property_views WHERE property_id = :pid AND visitor_user_id = :uid LIMIT 1');
            $checkStmt->execute([':pid' => $id, ':uid' => $viewer_id]);
            $already = $checkStmt->fetchColumn();
            if (!$already) {
                $ins = $pdo->prepare('INSERT INTO property_views (property_id, visitor_user_id, visitor_session, visitor_ip, user_agent) VALUES (:pid, :uid, :sess, :ip, :ua)');
                $ins->execute([
                    ':pid' => $id,
                    ':uid' => $viewer_id,
                    ':sess' => $visitor_session,
                    ':ip' => $visitor_ip,
                    ':ua' => $user_agent
                ]);
            }
        } else {
            // guest viewer: ensure exactly one row per (property_id, visitor_session)
            $checkStmt = $pdo->prepare('SELECT id FROM property_views WHERE property_id = :pid AND visitor_session = :sess LIMIT 1');
            $checkStmt->execute([':pid' => $id, ':sess' => $visitor_session]);
            $already = $checkStmt->fetchColumn();
            if (!$already) {
                $ins = $pdo->prepare('INSERT INTO property_views (property_id, visitor_user_id, visitor_session, visitor_ip, user_agent) VALUES (:pid, NULL, :sess, :ip, :ua)');
                $ins->execute([
                    ':pid' => $id,
                    ':sess' => $visitor_session,
                    ':ip' => $visitor_ip,
                    ':ua' => $user_agent
                ]);
            }
        }
    }
} catch (Throwable $e) {
    // Log but do not break page rendering
    error_log('Property view logging failed: ' . $e->getMessage());
}

// optional: compute unread count if conversation exists and DB tables are present
$unread_count = 0;
if ($show_chat && !$is_guest) {
    try {
        $cstmt = $pdo->prepare('SELECT * FROM conversations WHERE property_id = :pid AND (buyer_id = :uid OR seller_id = :uid) LIMIT 1');
        $cstmt->execute([':pid' => $id, ':uid' => $viewer_id]);
        $conv = $cstmt->fetch(PDO::FETCH_ASSOC);
        if ($conv) {
            if ((int)$conv['buyer_id'] === $viewer_id) {
                $unread_count = (int)val($conv, 'unread_for_buyer', 0);
            } else {
                $unread_count = (int)val($conv, 'unread_for_seller', 0);
            }
            $conversation_id = (int)$conv['id'];
        } else {
            $conversation_id = 0;
        }
    } catch (Throwable $e) {
        error_log('Conversation lookup failed: ' . $e->getMessage());
        $conversation_id = 0;
    }
} else {
    $conversation_id = 0;
}

// Determine whether the quick "I'm interested" button should be shown/enabled.
// Conditions:
//  - user must be logged in (no guests allowed to quick-send)
//  - viewer must not be the seller (owner)
//  - and viewer must not already have a conversation for this property (we treat any existing conversation as "already initiated")
$can_send_quick = (!$is_guest && $viewer_id !== $seller_id && (int)$conversation_id === 0);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title><?= h($title) ?> — HouseRadar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />

  <!-- Debug meta (helps front-end console checks) -->
  <meta name="hr-debug" content="<?= ($show_chat ? 'show' : 'hide') ?>;viewer:<?= (int)$viewer_id ?>;seller:<?= (int)$seller_id ?>" />

  <script>(function(){try{ if(localStorage.getItem('hr_theme')==='dark')document.documentElement.classList.add('dark'); }catch(e){} })();</script>

  <link rel="stylesheet" href="assets/css/index.css" />
  <link rel="stylesheet" href="assets/css/property_details.css" />
  <link rel="stylesheet" href="assets/css/inbox.css" />
  <link id="leaflet-css" rel="stylesheet" href="" />

  <?php
  // Fallback CSS if inbox.css missing
  $inbox_css_path = __DIR__ . '/assets/css/inbox.css';
  if (!file_exists($inbox_css_path)) {
      echo '<style>
      .hr-chat-fab{position:fixed;right:22px;bottom:22px;width:56px;height:56px;border-radius:999px;display:inline-grid;place-items:center;background:#1ea1ff;color:#fff;font-size:22px;border:0;cursor:pointer;box-shadow:0 10px 30px rgba(2,8,14,0.08);z-index:1110}
      .hr-chat-fab .dot{position:absolute;right:8px;top:8px;width:10px;height:10px;border-radius:999px;background:#ff4d4f;display:none}
      .hr-chat-fab.has-unread .dot{display:block}
      .hr-chat-panel{position:fixed;right:22px;bottom:calc(22px + 56px + 12px);width:360px;height:420px;background:#fff;color:#06202b;border-radius:12px;box-shadow:0 10px 30px rgba(2,8,14,0.08);z-index:1120;display:flex;flex-direction:column;transform:translateY(12px) scale(.98);opacity:0;pointer-events:none;transition:transform .16s,opacity .12s;overflow:hidden;border:1px solid rgba(0,0,0,0.04)}
      .hr-chat-panel.open{transform:none;opacity:1;pointer-events:auto}
      .hr-chat-header{display:flex;align-items:center;gap:12px;padding:12px;border-bottom:1px solid rgba(0,0,0,0.04)}
      .hr-chat-messages{flex:1 1 auto;padding:10px;overflow-y:auto;display:flex;flex-direction:column;gap:10px}
      .hr-chat-messages .msg{max-width:86%;padding:10px 12px;border-radius:12px;background:#f7fbff;border:1px solid rgba(0,0,0,0.03)}
      .hr-chat-messages .msg.me{align-self:flex-end;background:linear-gradient(90deg,rgba(30,161,255,0.12),rgba(30,161,255,0.04));border-color:rgba(30,161,255,0.08);font-weight:700}
      .hr-chat-compose{display:flex;gap:8px;padding:10px;border-top:1px solid rgba(0,0,0,0.04);align-items:flex-end}
      .hr-chat-input{flex:1;border-radius:10px;padding:8px 10px;min-height:44px;border:1px solid rgba(0,0,0,0.06)}
      .hr-chat-send{background:#1ea1ff;color:#fff;border:0;padding:10px 12px;border-radius:8px;cursor:pointer}
      .hr-guest-note { padding:8px 12px; font-size:13px; background: linear-gradient(90deg, rgba(30,161,255,0.04), transparent); color: #084; }
      @media (max-width:520px){.hr-chat-panel{right:12px;left:12px;bottom:80px;width:auto;height:60vh;max-height:calc(100vh - 120px)}}
      </style>';
  }
  ?>

  <style>
    .main-wrap { max-width:1200px; margin:22px auto; padding:0 24px; display:grid; grid-template-columns:1fr 340px; gap:28px; box-sizing:border-box; }
    @media (max-width:980px) { .main-wrap{ grid-template-columns:1fr; } .property-sidebar{ width:100%; } }
    .quick-interest { display:inline-flex; align-items:center; gap:8px; background: linear-gradient(180deg,#1ea1ff,#0b7ed8); color:#fff; padding:10px 14px; border-radius:8px; border:0; cursor:pointer; font-weight:800; }
    .quick-interest.disabled, .quick-interest[disabled] { opacity:0.55; cursor:not-allowed; }
  </style>

  <!-- Expose small runtime object: csrf token & viewer id for JS (safe) -->
  <script>
    window.__HR = {
      csrf: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>,
      viewer_id: <?= json_encode($viewer_id ?? 0) ?>
    };
  </script>
</head>
<body class="<?= $is_rental ? 'for-rent' : '' ?>">
  <main class="main-wrap" role="main">
    <div>
      <header class="prop-header" style="margin-bottom:12px;">
        <h1 style="margin:0; font-size:22px;"><?= h($title) ?></h1>
        <div style="margin-top:8px; display:flex; gap:12px; align-items:center; flex-wrap:wrap; color:var(--muted);">
          <div style="font-weight:800; color:var(--primary);"><?= h($priceLabel) ?></div>
          <div><?= h($property_type) ?></div>
          <?php if ($locality || $city): ?><div><?= h(trim($locality . ($locality && $city ? ' • ' : '') . $city)) ?></div><?php endif; ?>
          <!-- NEW: optional address (muted, unobtrusive) -->
          <?php if (!empty($address)): ?><div class="prop-address"><?= h($address) ?></div><?php endif; ?>
          <div>Posted: <?= h($createdLabel) ?></div>
          <?php if ($is_featured): ?><div class="featured-badge" style="background:linear-gradient(90deg,var(--primary),var(--primary-variant)); color:#fff; padding:6px 10px; border-radius:8px; font-weight:800;">Featured</div><?php endif; ?>
          <?php if ($is_rental): ?><div class="rental-badge">For Rent</div><?php endif; ?>
        </div>
      </header>

      <!-- Gallery -->
      <section class="gallery" aria-label="Property gallery">
        <div id="galleryMain" class="gallery-main" style="background-image:url('<?= h($images[0]) ?>');" tabindex="0" role="region" aria-label="Main image">
          <button id="prevArrow" class="gallery-arrow left" aria-label="Previous image">◀</button>
          <button id="nextArrow" class="gallery-arrow right" aria-label="Next image">▶</button>
          <div class="gallery-caption" id="galleryCounter"><?= (count($images) > 1) ? '1 / ' . count($images) : '1 / 1' ?></div>
        </div>

        <div id="thumbRow" class="gallery-thumbs" role="list" aria-label="Thumbnails">
          <?php foreach ($images as $ix => $src): $i = (int)$ix; ?>
            <div class="thumb <?= $i === 0 ? 'active' : '' ?>" role="listitem"
                 data-index="<?= $i ?>" data-src="<?= h($src) ?>"
                 style="background-image:url('<?= h($src) ?>')" tabindex="0" aria-label="Image <?= $i+1 ?>"></div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Specs -->
      <section style="margin-top:16px;">
        <div class="specs-grid">
          <?php if ($builtLabel): ?><div class="spec"><div class="label">Built-up</div><div class="value-muted"><?= h($builtLabel) ?></div></div><?php endif; ?>
          <?php if ($carpetLabel): ?><div class="spec"><div class="label">Carpet</div><div class="value-muted"><?= h($carpetLabel) ?></div></div><?php endif; ?>
        </div>
      </section>

      <!-- Config pills -->
      <?php if (!empty($configs)): ?>
      <section style="margin-top:12px;">
        <h3 style="margin:0 0 8px 0;">Configurations</h3>
        <div id="configPills" class="config-pills" role="toolbar" aria-label="Configuration options">
          <?php foreach ($configs as $idx => $c): ?>
            <button class="config-pill" data-idx="<?= $idx ?>" type="button"><?= h($c['label']) ?></button>
          <?php endforeach; ?>
        </div>

        <div id="configInfo" class="config-info" style="display:none; margin-top:8px;">
          <div class="ci-item">
            <span class="ci-label">Price</span><span id="ciPrice" class="ci-val">-</span>
          </div>
          <div class="ci-item">
            <span class="ci-label">Built-up</span><span id="ciBuilt" class="ci-val">-</span>
          </div>
          <div class="ci-item">
            <span class="ci-label">Carpet</span><span id="ciCarpet" class="ci-val">-</span>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <!-- Furnishing -->
      <?php if ($furnishing): ?>
        <div class="furnishing">Furnishing: <?= h($furnishing) ?></div>
      <?php endif; ?>

      <!-- Rental brief -->
      <?php
        $has_rental = ($rent_field !== null && $rent_field !== '') || ($min_rent !== null && $min_rent !== '') || ($rental_config !== null && $rental_config !== '') || ($rental_carpet_area !== null && $rental_carpet_area !== '');
      ?>
      <?php if ($has_rental): ?>
      <section class="rental-section <?= $has_rental ? 'rental-panel' : '' ?>" <?php if (!$has_rental) echo 'data-empty="1"'; ?> style="margin-top:12px;">
        <h3 style="margin:0 0 8px 0;">Rental Info</h3>
        <div style="color:var(--muted);">
          <div class="rental-row"><strong>Configuration:</strong>
            <?php if (!empty($rental_config)): ?>
              <span><?= h($rental_config) ?></span>
            <?php else: ?>
              <span class="no-value">-</span>
            <?php endif; ?>
          </div>

          <div class="rental-row"><strong>Rent:</strong>
            <?php
              if (!empty($rent_field)) {
                echo '<span class="rent-value">₹' . number_format((float)$rent_field, ((float)$rent_field != floor((float)$rent_field)) ? 2 : 0) . '</span>';
              } elseif (!empty($min_rent)) {
                echo '<span class="rent-value">₹' . number_format((float)$min_rent, ((float)$min_rent != floor((float)$min_rent)) ? 2 : 0) . '</span>';
              } else {
                echo '<span class="no-value">-</span>';
              }
            ?>
          </div>

          <div class="rental-row"><strong>Rental carpet area:</strong>
            <?php if (!empty($rental_carpet_area)): ?>
              <span class="rental-area"><?= h($rental_carpet_area) ?> sqft</span>
            <?php else: ?>
              <span class="no-value">-</span>
            <?php endif; ?>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <!-- Amenities -->
      <?php if ($amenities): ?>
      <section style="margin-top:12px;">
        <h3 style="margin:0 0 8px 0;">Amenities</h3>
        <div class="amenities-list">
          <?php
            $parts = preg_split('/[\r\n,]+/', (string)$amenities);
            $uniq = [];
            foreach ($parts as $p) {
              $p = trim($p);
              if ($p === '' || in_array($p, $uniq, true)) continue;
              $uniq[] = $p;
              echo '<span class="amenity">' . h($p) . '</span>';
            }
          ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- Description -->
      <?php if ($description): ?>
      <section style="margin-top:12px;">
        <h3 style="margin:0 0 8px 0;">Description</h3>
        <div class="description"><?= nl2br(h($description)) ?></div>
      </section>
      <?php endif; ?>

      <!-- Map -->
      <?php if (!empty($lat) && !empty($lng)): ?>
      <section style="margin-top:12px;">
        <h3 style="margin:0 0 8px 0;">Location</h3>
        <div style="margin-top:10px;">
          <button id="showMap"
  class="cta-btn show-map-btn"
  style="display:inline-block; background:#fff; color:var(--text); border:1px solid rgba(0,0,0,0.06);">
  Show map
</button>

          <a target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($lat . ',' . $lng) ?>" class="cta-btn" style="display:inline-block; background:#28a06b; margin-left:10px; width:auto;">Open in Google Maps</a>
        </div>

        <div id="mapWrapper" data-lat="<?= h($lat) ?>" data-lng="<?= h($lng) ?>" style="margin-top:12px; display:none; position:relative;">
          <button id="closeMap" class="map-close-btn" style="display:none;">✕</button>
          <div id="map" style="width:100%; height:360px; border-radius:10px; overflow:hidden;"></div>
        </div>
      </section>
      <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <aside class="property-sidebar" aria-label="Actions">
      <div class="contact-card" style="background:var(--card); color:var(--text);">
        <h3 style="margin:0 0 6px 0;"><?= h($owner_name) ?></h3>
        <div class="muted" style="color:var(--muted); margin-bottom:12px;">Listed by owner / agency</div>

        <div style="font-weight:800; font-size:18px; color:var(--text);"><?= h($priceLabel) ?></div>

        <div style="margin-top:12px;">
          <a href="tel:<?= h(val($row,'owner_phone','')) ?>" class="cta-btn cta-call" style="background:var(--primary);">Call Owner</a>
          <a href="mailto:<?= h(val($row,'owner_email','')) ?>?subject=<?= rawurlencode('Enquiry: ' . $title) ?>" class="cta-btn cta-mail" style="background:#28a06b; margin-top:8px;">Email Owner</a>
        </div>

        <div style="margin-top:12px; color:var(--muted); font-size:13px;">
          <div>Posted: <?= h($createdLabel) ?></div>
          <div>Status: available</div>
          <!-- NEW: subtly show address in sidebar (muted, not emphasized) -->
          <?php if (!empty($address)): ?>
            <div style="margin-top:8px;"><span class="muted" style="font-weight:600; font-size:13px;">Address:</span> <div class="prop-address" style="margin-top:4px;"><?= h($address) ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </aside>
  </main>

  <footer style="text-align:center; padding:20px 10px; color:var(--muted); font-size:13px;">
    © <?= date('Y') ?> HouseRadar
  </footer>

  <!-- ---------- CHAT: only rendered when $show_chat is true ---------- -->
  <?php if ($show_chat): ?>
    <!-- Chat FAB + Panel (rendered for guests and logged-in viewers, except owner) -->
    <button id="hrChatFab" class="hr-chat-fab <?= $unread_count > 0 ? 'has-unread' : '' ?>" aria-expanded="false" aria-controls="hrChatPanel" title="<?= $is_guest ? 'Log in to chat' : 'Chat with seller' ?>">
      💬
      <span class="dot" aria-hidden="true"></span>
    </button>

    <div id="hrChatPanel" class="hr-chat-panel" role="dialog" aria-label="Chat with seller" aria-hidden="true" data-guest="<?= $is_guest ? '1' : '0' ?>">
      <div class="hr-chat-header">
        <div style="flex:1 1 auto;">
          <div class="title">Chat with <?= h($owner_name) ?></div>
          <div class="sub">Property: <?= h($title) ?></div>
        </div>
        <div>
          <button id="hrChatClose" aria-label="Close chat">✕</button>
        </div>
      </div>

      <?php if ($is_guest): ?>
        <div class="hr-guest-note" role="status" aria-live="polite" style="display:flex;gap:8px;align-items:center;">
          You are viewing as a guest — <a href="user/login.php">Log in</a> or <a href="user/signup.php">Sign up</a> to send messages.
        </div>
      <?php endif; ?>

      <div id="hrChatMessages" class="hr-chat-messages" aria-live="polite" aria-atomic="false">
        <div class="msg them">Start a conversation with the seller. Messages are private.</div>
      </div>

      <!-- REPLACED compose area with Quick Message UI -->
      <div class="hr-chat-compose">
        <?php if ($is_guest): ?>
          <a class="quick-interest" href="user/login.php">Log in to Express Interest</a>

        <?php else: ?>
          <?php if ($can_send_quick): ?>
            <button id="quickInterestBtn" class="quick-interest" type="button">I'm interested</button>
          <?php else: ?>
            <!-- Conversation already exists: show link to open chat -->
            <button id="openConversationBtn" class="quick-interest" type="button" title="Open conversation">Open Conversation</button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <script>
    (function(){
      // Minimal, defensive chat toggle + local behaviour only.
      const fab = document.getElementById('hrChatFab');
      const panel = document.getElementById('hrChatPanel');
      const closeBtn = document.getElementById('hrChatClose');
      const messagesEl = document.getElementById('hrChatMessages');

      // Quick UI controls
      const quickBtn = document.getElementById('quickInterestBtn');
      const openConvBtn = document.getElementById('openConversationBtn');

      // Emitted server-side values for JS usage (safe integers)
      const PROPERTY_ID = <?= json_encode($id, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
      // Note: CONVERSATION_ID is const from server; we'll keep an updatable var for client-side
      let CONVERSATION_ID = <?= json_encode($conversation_id ?? 0, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
      const VIEWER_ID = <?= json_encode($viewer_id ?? 0, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
      const SELLER_ID = <?= json_encode($seller_id ?? 0, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
      const IS_GUEST = (VIEWER_ID === 0);

      function openPanel() {
        if (!panel) return;
        panel.classList.add('open');
        panel.setAttribute('aria-hidden','false');
        fab.setAttribute('aria-expanded','true');

        // load messages lazily if a conversation id exists (use proper endpoint & include cookies)
        if (!IS_GUEST && CONVERSATION_ID && messagesEl.dataset.loaded !== '1') {
          fetch('../api/conversations.php?action=get&conversation_id=' + encodeURIComponent(CONVERSATION_ID), {
            method: 'GET',
            credentials: 'include' // ensures PHP session cookie is sent
          })
            .then(r => {
              if (!r.ok) return Promise.reject('fetch-failed');
              return r.json();
            })
            .then(data => {
              messagesEl.innerHTML = '';
              if (Array.isArray(data.messages) && data.messages.length) {
                data.messages.forEach(m => {
                  const div = document.createElement('div');
                  const me = (m.sender_id === VIEWER_ID);
                  div.className = 'msg ' + (me ? 'me' : 'them');
                  div.innerHTML = '<div>' + (m.body ? m.body.replace(/\n/g,'<br>') : '') + '</div><time>' + (m.created_at||'') + '</time>';
                  messagesEl.appendChild(div);
                });
              } else {
                messagesEl.innerHTML = '<div class="msg them">No messages yet. Say hi 👋</div>';
              }
              messagesEl.scrollTop = messagesEl.scrollHeight;
              messagesEl.dataset.loaded = '1';
            })
            .catch(() => {
              messagesEl.dataset.loaded = '1';
            });
        }
      }

      function closePanel() {
        if (!panel) return;
        panel.classList.remove('open');
        panel.setAttribute('aria-hidden','true');
        fab.setAttribute('aria-expanded','false');
      }

      if (fab) {
        fab.addEventListener('click', e => {
          const isOpen = panel.classList.contains('open');
          if (isOpen) closePanel();
          else openPanel();
        });
      }
      if (closeBtn) closeBtn.addEventListener('click', closePanel);

      // Open conversation button just opens the panel and refreshes messages if needed
      if (openConvBtn) {
        openConvBtn.addEventListener('click', () => {
          openPanel();
          // if conversation id wasn't set but server believed a conversation existed, reload the page or attempt to fetch again
          if (!CONVERSATION_ID) {
            // Try to fetch conversation via property (best-effort)
            fetch('../api/conversations.php?action=find_by_property&property_id=' + encodeURIComponent(PROPERTY_ID), { credentials:'include' })
              .then(r=>r.ok? r.json(): Promise.reject('fail'))
              .then(data => {
                if (data && (data.conversation_id || data.id)) {
                  CONVERSATION_ID = data.conversation_id || data.id;
                }
              }).catch(()=>{});
          }
        });
      }

      // Quick interest button handling
      if (quickBtn) {
        quickBtn.addEventListener('click', function(){
          if (IS_GUEST) { window.location = 'user/login.php'; return; }
          if (!confirm('Send a quick message to the seller saying you are interested in this property?')) return;

          // disable while sending
          quickBtn.disabled = true;
          quickBtn.classList.add('disabled');
          quickBtn.textContent = 'Sending…';

          const fd = new FormData();
          // If conversation exists we pass its id; otherwise pass property + seller and let backend create conversation
          if (CONVERSATION_ID && CONVERSATION_ID > 0) {
            fd.append('conversation_id', String(CONVERSATION_ID));
          } else {
            fd.append('property_id', String(PROPERTY_ID));
            fd.append('seller_id', String(SELLER_ID || ''));
          }

          // canned message
          const canned = "I'm interested in this property.";
          fd.append('body', canned);

          // include csrf token if available
          if (window.__HR && window.__HR.csrf) {
            fd.append('csrf_token', window.__HR.csrf);
          }

          fetch('/HouseRader/api/messages_send.php', {
            method: 'POST',
            credentials: 'include',
            body: fd
          })
          .then(resp => {
            if (!resp.ok) return Promise.reject('send-failed');
            return resp.json();
          })
          .then(data => {
            const ok = !!(data && (data.success === true || data.ok === true));
            if (ok) {
              // set conversation id if returned
              if (!CONVERSATION_ID && (data.conversation_id || data.conversationId)) {
                CONVERSATION_ID = data.conversation_id || data.conversationId;
              } else if (!CONVERSATION_ID && (data.id || data.conversation)) {
                CONVERSATION_ID = data.id || (data.conversation && data.conversation.id) || CONVERSATION_ID;
              }

              // Replace quick button with disabled state / label and open chat panel
              quickBtn.textContent = 'Interest sent';
              quickBtn.classList.add('disabled');
              quickBtn.disabled = true;

              // show a short success note in messages panel (optimistic)
              if (messagesEl) {
                const meDiv = document.createElement('div');
                meDiv.className = 'msg me';
                meDiv.innerHTML = '<div>Interested — seller notified.</div><time>' + new Date().toLocaleString() + '</time>';
                messagesEl.appendChild(meDiv);
                messagesEl.scrollTop = messagesEl.scrollHeight;
              }

              // open panel so user can see conversation
              openPanel();
            } else {
              throw new Error((data && (data.error || data.message)) ? (data.error || data.message) : 'Failed to send');
            }
          })
          .catch(err => {
            console.error('Quick send failed', err);
            quickBtn.disabled = false;
            quickBtn.classList.remove('disabled');
            quickBtn.textContent = "I'm interested — notify seller";
            alert('Failed to send quick message. Please try again or open the chat to send a full message.');
          });
        });
      }

      // escape close
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && panel.classList.contains('open')) closePanel();
      });

    })();
    </script>

  <?php endif; ?>

<script>
(function(){
  // GALLERY logic...
  const main = document.getElementById('galleryMain');
  const thumbRow = document.getElementById('thumbRow');
  const thumbs = Array.from(document.querySelectorAll('.thumb'));
  const prev = document.getElementById('prevArrow');
  const next = document.getElementById('nextArrow');
  const counter = document.getElementById('galleryCounter');
  let idx = 0;
  const imgs = thumbs.map(t => t.dataset.src);

  function show(i, opts={}) {
    if (i < 0) i = imgs.length - 1;
    if (i >= imgs.length) i = 0;
    idx = i;
    if (main) main.style.backgroundImage = `url("${imgs[idx]}")`;
    if (counter) counter.textContent = `${idx+1} / ${imgs.length}`;
    thumbs.forEach(t => t.classList.toggle('active', parseInt(t.dataset.index,10) === idx));
    const active = thumbs[idx];
    if (active && active.scrollIntoView) {
      try {
        active.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'});
      } catch(e) {
        active.scrollIntoView(false);
      }
    }
    if (opts.focus) main.focus();
  }

  if (thumbs.length) show(0, {focus:true});

  thumbs.forEach(t => {
    t.addEventListener('click', e => {
      e.preventDefault();
      const i = parseInt(t.dataset.index, 10);
      if (!Number.isNaN(i)) show(i);
    });
    t.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); t.click(); }
    });
  });

  if (prev) prev.addEventListener('click', () => show(idx-1));
  if (next) next.addEventListener('click', () => show(idx+1));

  if (main) {
    main.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') { show(idx-1); e.preventDefault(); }
      if (e.key === 'ArrowRight') { show(idx+1); e.preventDefault(); }
    });
    main.setAttribute('tabindex','0');
  }

  // CONFIG pills and MAP logic left unchanged from original — omitted here for brevity but preserved in the file above.

  // CONFIG pills: toggle details
  const configRows = <?= json_encode($configs, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
  const pills = Array.from(document.querySelectorAll('.config-pill'));
  const info = document.getElementById('configInfo');
  const ciPrice = document.getElementById('ciPrice');
  const ciBuilt = document.getElementById('ciBuilt');
  const ciCarpet = document.getElementById('ciCarpet');

  function openConfig(i){
    const row = configRows[i];
    if (!row) return;
    ciPrice.textContent = row.price ? ('₹' + Number(row.price).toLocaleString()) : '-';
    ciBuilt.textContent = row.built ? (row.built + ' sqft') : '-';
    ciCarpet.textContent = row.carpet ? (row.carpet + ' sqft') : '-';
    info.style.display = 'flex';
    pills.forEach((p,j)=> p.classList.toggle('active', j===i));
  }
  function closeConfig(){
    if (info) info.style.display = 'none';
    pills.forEach(p => p.classList.remove('active'));
  }

  const isRentalPage = document.body.classList.contains('for-rent');

pills.forEach((p, i) => {
  if (isRentalPage) {
    // Rental properties: pills are display-only
    p.setAttribute('aria-disabled', 'true');
    p.style.cursor = 'default';
    return;
  }

  // Sale / non-rental behavior (unchanged)
  p.addEventListener('click', () => {
    if (p.classList.contains('active')) closeConfig();
    else openConfig(i);
  });

  p.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      p.click();
    }
  });
});


  // MAP lazy load + close
  const showMapBtn = document.getElementById('showMap');
  const mapWrapper = document.getElementById('mapWrapper');
  const closeMapBtn = document.getElementById('closeMap');
  let mapInitiated = false;

  function loadScript(src){ return new Promise((res,rej)=>{ if (document.querySelector('script[src="'+src+'"]')) { res(); return; } const s=document.createElement('script'); s.src=src; s.onload=res; s.onerror=rej; document.head.appendChild(s); }); }
  function loadCss(href){ return new Promise((res,rej)=>{ const existing=document.getElementById('leaflet-css'); if (existing) { existing.href = href; res(); return; } const l=document.createElement('link'); l.rel='stylesheet'; l.href=href; l.id='leaflet-css'; l.onload=res; l.onerror=rej; document.head.appendChild(l); }); }

  async function initMapIfNeeded(lat, lng){
    if (mapInitiated) {
      try { mapWrapper._map.setView([lat,lng],15); } catch(e){}
      return;
    }
    try {
      await loadCss('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
      await loadScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js');
      const mapEl = document.getElementById('map');
      if (!mapEl) return;
      const Lmap = L.map(mapEl).setView([lat,lng],15);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ maxZoom:19, attribution:'© OpenStreetMap' }).addTo(Lmap);
      L.marker([lat,lng]).addTo(Lmap);
      mapWrapper._map = Lmap;
      mapInitiated = true;
    } catch(err) {
      console.error('Leaflet load failed', err);
      window.open('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(lat+','+lng), '_blank');
    }
  }

  if (showMapBtn && mapWrapper) {
    showMapBtn.addEventListener('click', async () => {
      const lat = parseFloat(mapWrapper.dataset.lat);
      const lng = parseFloat(mapWrapper.dataset.lng);
      if (Number.isNaN(lat) || Number.isNaN(lng)) { alert('Coordinates not available'); return; }
      mapWrapper.style.display = 'block';
      if (closeMapBtn) closeMapBtn.style.display = 'inline-block';
      mapWrapper.scrollIntoView({behavior:'smooth'});
      await initMapIfNeeded(lat, lng);
    });
    if (closeMapBtn) {
      closeMapBtn.addEventListener('click', () => {
        mapWrapper.style.display = 'none';
        closeMapBtn.style.display = 'none';
      });
    }
  }
})();
</script>
</body>
</html>
