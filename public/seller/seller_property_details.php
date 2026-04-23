<?php
// public/seller/seller_property_details.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db_file = __DIR__ . '/../../src/db.php';
if (!file_exists($db_file)) { http_response_code(500); echo "Missing DB helper"; exit; }
require_once $db_file; // provides $pdo

// --- Seller guard: only logged-in sellers can view this page ---
$seller = $_SESSION['seller'] ?? null;
if (!$seller || empty($seller['id'])) {
    header('Location: ../user/login.php');
    exit;
}
$seller_id_session = (int)$seller['id'];

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
     * Normalize various stored image values into a usable filesystem/web path.
     * Returns a path as stored (may be relative like 'assets/img/..' or absolute URL).
     */
    function normalize_img(string $raw = null): ?string {
        $raw = trim((string)$raw);
        if ($raw === '') return null;
        if (preg_match('#^https?://#i', $raw) || strpos($raw, '//') === 0) return $raw;
        if (strpos($raw, '/') === 0 || str_starts_with($raw, './') || str_starts_with($raw, '../')) return $raw;
        // check public/assets/img/
        $candidate = __DIR__ . '/../assets/img/' . $raw;
        if (file_exists($candidate)) return 'assets/img/' . $raw;
        return $raw;
    }
}

/**
 * Convert a normalized src to a web URL appropriate for this seller page.
 * - If src is an absolute URL (http/https or protocol-relative) return as-is.
 * - If src starts with '/' return as-is.
 * - If src starts with 'assets/' return '../' + src because current file lives in public/seller/.
 * - If src starts with './' or '../' or other relative paths, return them unchanged.
 */
function web_src(?string $src): string {
    $src = (string)$src;
    if ($src === '') return '../assets/img/placeholder.png';
    if (preg_match('#^https?://#i', $src)) return $src;
    if (strpos($src, '//') === 0) return $src;
    if (strpos($src, '/') === 0) return $src;
    if (str_starts_with($src, 'assets/')) return '../' . $src; // path from seller/ -> public/
    if (str_starts_with($src, './') || str_starts_with($src, '../')) return $src;
    // fallback: treat as asset inside public/assets/img
    return '../assets/img/' . ltrim($src, "./");
}

// get property id from query
$pid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($pid <= 0) {
    http_response_code(400);
    echo "Invalid property id.";
    exit;
}

// fetch property (one row)
try {
    $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $pid]);
    $prop = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Seller property fetch failed: ' . $e->getMessage());
    $prop = false;
}
if (!$prop) {
    http_response_code(404);
    echo "Property not found.";
    exit;
}

// ownership check: only allow the seller who owns the property to view this page
$owner_seller_id = (int)val($prop, 'seller_id', 0);
if ($owner_seller_id !== $seller_id_session) {
    http_response_code(403);
    echo "Access denied: you do not own this property.";
    exit;
}

// map main fields defensively
$title = val($prop, 'title', 'Untitled Property');
$property_type = val($prop, 'property_type', 'Unknown');
$city = val($prop, 'city', '');
$locality = val($prop, 'locality', '');
$address = val($prop, 'address', ''); // NEW: address passthrough
$priceLabel = 'Contact for price';
$min_price = val($prop, 'min_price', null);
$rent_field = val($prop, 'rent', null);
$min_rent = val($prop, 'min_rent', null);
if ($min_price !== null && $min_price !== '') {
    $priceLabel = '₹' . number_format((float)$min_price, ((float)$min_price != floor((float)$min_price)) ? 2 : 0);
} elseif ($rent_field !== null && $rent_field !== '') {
    $priceLabel = '₹' . number_format((float)$rent_field, ((float)$rent_field != floor((float)$rent_field)) ? 2 : 0) . ' /mo';
} elseif ($min_rent !== null && $min_rent !== '') {
    $priceLabel = '₹' . number_format((float)$min_rent, ((float)$min_rent != floor((float)$min_rent)) ? 2 : 0) . ' /mo';
}

$createdLabel = val($prop, 'created_at') ? date('M j, Y', strtotime($prop['created_at'])) : '';
$is_featured = (int)val($prop, 'is_featured', 0) === 1;
$featured_until = val($prop, 'featured_until', null);

$furnishing = val($prop, 'furnishing', '');
$amenities = val($prop, 'amenities', '');
$description = val($prop, 'description', '');
$lat = val($prop, 'latitude', null);
$lng = val($prop, 'longitude', null);

// images
$images = [];
for ($i = 1; $i <= 4; $i++) {
    $k = 'img' . $i;
    $raw = val($prop, $k, null);
    if ($raw && trim((string)$raw) !== '') {
        $src = normalize_img($raw);
        if ($src) $images[] = $src;
    }
}
if (empty($images)) {
    $images[] = 'assets/img/placeholder.png';
}

// parse flattened configs
$configs = [];
for ($i = 1; $i <= 6; $i++) {
    $ck = 'config' . $i;
    $pk = 'price' . $i;
    $bk = 'builtup_area' . $i;
    $car = 'carpet_area' . $i;
    $cfg = val($prop, $ck, null);
    if ($cfg === null || trim((string)$cfg) === '') continue;
    $configs[] = [
        'label' => $cfg,
        'price' => (val($prop, $pk, null) !== null && val($prop, $pk, null) !== '') ? (float)val($prop, $pk, null) : null,
        'built' => (val($prop, $bk, null) !== null && val($prop, $bk, null) !== '') ? (string)val($prop, $bk, null) : null,
        'carpet' => (val($prop, $car, null) !== null && val($prop, $car, null) !== '') ? (string)val($prop, $car, null) : null,
    ];
}

// LIVE metrics: views from property_views table (aggregated)
$views_count_live = 0;
try {
    $vstmt = $pdo->prepare('SELECT COUNT(*) FROM property_views WHERE property_id = :pid');
    $vstmt->execute([':pid' => $pid]);
    $views_count_live = (int)$vstmt->fetchColumn();
} catch (Throwable $e) {
    error_log('Failed to fetch property_views count: ' . $e->getMessage());
    $views_count_live = (int)val($prop, 'views_count', 0);
}

// messages_count (fall back to properties.messages_count if present)
$messages_count_live = (int)val($prop, 'messages_count', 0);
try {
    $cstmt = $pdo->prepare('SELECT SUM(messages_count) FROM conversations WHERE property_id = :pid');
    $cstmt->execute([':pid' => $pid]);
    $sum = $cstmt->fetchColumn();
    if ($sum !== false && $sum !== null) $messages_count_live = max($messages_count_live, (int)$sum);
} catch (Throwable $e) {
    // ignore
}

// NEW: unread inquiries for seller for this property
$unread_inquiries_for_seller = 0;
try {
    $u = $pdo->prepare('SELECT COUNT(*) FROM conversations WHERE property_id = :pid AND unread_for_seller > 0');
    $u->execute([':pid' => $pid]);
    $unread_inquiries_for_seller = (int)$u->fetchColumn();
} catch (Throwable $e) {
    // ignore silently
}

// seller URLs
$edit_url = 'edit_property.php?id=' . urlencode((string)$pid);
$seller_index = 'seller_index.php';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Seller — <?= h($title) ?> — HouseRadar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script>(function(){try{ if(localStorage.getItem('hr_theme')==='dark') document.documentElement.classList.add('dark'); }catch(e){} })();</script>

  <link rel="stylesheet" href="../assets/css/index.css" />
  <link rel="stylesheet" href="../assets/css/seller_index.css" />
  <link rel="stylesheet" href="../assets/css/seller_property_details.css" />
</head>
<body>
  <?php
    $header_candidates = [__DIR__ . '/../partials/header.php', __DIR__ . '/../header.php'];
    $header_included = false;
    foreach ($header_candidates as $hf) {
      if (file_exists($hf)) { include $hf; $header_included = true; break; }
    }
    if (!$header_included) {
      echo '<header class="hr-navbar"><div class="container"><a class="brand" href="seller_index.php">🏠 HouseRadar</a></div></header>';
    }
  ?>

  <div class="seller-page container">
    <div class="seller-top">
      <div class="seller-info-left">
        <div class="seller-title"><?= h($title) ?></div>
        <?php if ($is_featured): ?>
          <div class="featured-badge">Featured • <?= $featured_until ? h(date('M j, Y', strtotime($featured_until))) : 'Active' ?></div>
        <?php endif; ?>
      </div>
      <div class="seller-info-right">
        <div class="stat-small"><span class="num"><?= number_format($views_count_live) ?></span><span class="small-muted">views</span></div>
        <div class="stat-small"><span class="num"><?= number_format($messages_count_live) ?></span><span class="small-muted">messages</span></div>
        <a class="btn-edit" href="<?= h($edit_url) ?>">Edit</a>
        <?php if (!$is_featured): ?>
          <button id="featureBtn" class="feature-cta" type="button">Feature this property</button>
        <?php else: ?>
          <button id="featureBtn" class="feature-cta" type="button" disabled>Already Featured</button>
        <?php endif; ?>
        <a class="btn-edit" href="<?= h($seller_index) ?>">Back to Dashboard</a>
      </div>
    </div>

    <div class="seller-main">
      <div class="seller-left">
        <!-- Gallery -->
        <div id="galleryMain" class="gallery-main" style="background-image:url('<?= h(web_src($images[0])) ?>')"></div>
        <div id="galleryThumbs" class="gallery-thumbs" role="list" aria-label="Thumbnails">
          <?php foreach ($images as $i => $img): $ws = web_src($img); ?>
            <div class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>"
                 data-src="<?= h($ws) ?>"
                 role="listitem" tabindex="0"
                 style="background-image:url('<?= h($ws) ?>')"></div>
          <?php endforeach; ?>
        </div>

        <!-- Price + meta -->
        <div class="price-meta">
          <div class="price-left">
            <div class="price-label"><?= h($priceLabel) ?></div>
            <div class="meta-row">
              <div class="small-muted"><?= h($property_type) ?></div>
              <?php if ($locality || $city): ?><div class="small-muted"><?= h(trim($locality . ($locality && $city ? ' • ' : '') . $city)) ?></div><?php endif; ?>
              <?php if ($address): ?><div class="small-muted">Address: <?= h($address) ?></div><?php endif; ?>
              <div class="small-muted">Posted: <?= h($createdLabel) ?></div>
            </div>
          </div>
          <div class="price-right small-muted">Status: <strong><?= h(val($prop,'status','-')) ?></strong></div>
        </div>

        <!-- Configurations -->
        <?php if (!empty($configs)): ?>
          <section class="section-configs">
            <h3>Configurations</h3>
            <div class="configs-wrap">
              <?php foreach ($configs as $c): ?>
                <div class="cfg-pill">
                  <?= h($c['label']) ?> <span class="cfg-price"><?= $c['price'] ? '₹'.number_format($c['price']) : '' ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- Description -->
        <?php if ($description): ?>
          <section class="section-desc">
            <h3>Description</h3>
            <div class="desc-text"><?= nl2br(h($description)) ?></div>
          </section>
        <?php endif; ?>

        <!-- Amenities -->
        <?php if ($amenities): ?>
          <section class="section-amen">
            <h3>Amenities</h3>
            <div class="amen-wrap">
              <?php
                $parts = preg_split('/[\r\n,]+/', (string)$amenities);
                $uniq = [];
                foreach ($parts as $p) {
                  $p = trim($p);
                  if ($p === '' || in_array($p, $uniq, true)) continue;
                  $uniq[] = $p;
                  echo '<span class="amen-pill">' . h($p) . '</span>';
                }
              ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- Map (lazy) -->
        <?php if (!empty($lat) && !empty($lng)): ?>
        <section class="section-map">
          <h3>Location</h3>
          <div class="map-actions">
            <button id="showMap" class="btn-outline">Show map</button>
            <a target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($lat . ',' . $lng) ?>" class="btn-outline">Open Google Maps</a>
          </div>
          <div id="mapWrapper" data-lat="<?= h($lat) ?>" data-lng="<?= h($lng) ?>" style="display:none;margin-top:12px;">
            <div id="map" style="width:100%; height:360px; border-radius:10px; overflow:hidden;"></div>
          </div>
        </section>
        <?php endif; ?>
      </div>

      <!-- Sidebar: stats + actions -->
      <aside class="card-side">
        <h3>Performance</h3>
        <div class="perf-grid">
          <div class="perf-row"><div class="small-muted">Views (unique)</div><div class="perf-val"><?= number_format($views_count_live) ?></div></div>
          <div class="perf-row"><div class="small-muted">Messages</div><div class="perf-val"><?= number_format($messages_count_live) ?></div></div>
          <div class="perf-row"><div class="small-muted">Featured</div><div class="perf-val"><?= $is_featured ? 'Yes' : 'No' ?></div></div>
          <?php if ($is_featured && $featured_until): ?>
            <div class="perf-row"><div class="small-muted">Featured Until</div><div class="perf-val"><?= h(date('M j, Y', strtotime($featured_until))) ?></div></div>
          <?php endif; ?>
        </div>

        <hr />

        <div class="sidebar-actions">
          <a class="overlay-btn" href="<?= h($edit_url) ?>">Edit listing</a>

          <?php
            // Build inquiries link with unread badge if any
            $inquiryLink = 'seller_inbox.php?property_id=' . h((string)$pid);
          ?>
          <a class="overlay-btn outline" href="<?= $inquiryLink ?>">
            View inquiries
            <?php if ($unread_inquiries_for_seller > 0): ?>
              <span style="display:inline-block;margin-left:8px;background:#e53e3e;color:#fff;padding:4px 8px;border-radius:999px;font-weight:700;font-size:12px;"><?= ($unread_inquiries_for_seller > 99) ? '99+' : (int)$unread_inquiries_for_seller ?></span>
            <?php endif; ?>
          </a>

          <?php $isLive = val($prop, 'status') === 'live'; ?>

<button 
  id="toggleListingBtn"
  class="overlay-btn outline"
  style="width:100%;"
  data-action="<?= $isLive ? 'unlist' : 'relist' ?>"
>
  <?= $isLive ? 'Unlist property' : 'Relist property' ?>
</button>
        </div>
        <form id="bulkActionForm" method="POST" action="bulk_actions.php" style="display:none;">
  <input type="hidden" name="property_id" value="<?= h((string)$pid) ?>">
  <input type="hidden" name="action" id="bulkActionInput">
</form>
      </aside>
    </div>
  </div>

  <!-- Feature modal (kept unchanged) -->
  <div id="featureModal" class="modal-backdrop" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal" role="document">
      <button class="close" id="featClose">✕</button>
      <h2>Feature this listing</h2>
      <p class="small-muted">Choose a premium plan to boost visibility on the homepage and search results.</p>
      <div id="plans">
        <div class="plan">
          <div><div class="plan-title">Boost</div><div class="meta">7 days • higher placement</div></div>
          <div class="plan-right"><div class="price">₹1,499</div><a href="../feature_checkout.php?property_id=<?= h((string)$pid) ?>&tier=1" class="feature-cta">Pick</a></div>
        </div>
        <div class="plan">
          <div><div class="plan-title">Premium Spotlight</div><div class="meta">30 days • top carousel placement</div></div>
          <div class="plan-right"><div class="price">₹3,999</div><a href="../feature_checkout.php?property_id=<?= h((string)$pid) ?>&tier=2" class="feature-cta">Pick</a></div>
        </div>
        <div class="plan">
          <div><div class="plan-title">Elite Advantage</div><div class="meta">60 days • top priority & recurring push</div></div>
          <div class="plan-right"><div class="price">₹7,999</div><a href="../feature_checkout.php?property_id=<?= h((string)$pid) ?>&tier=3" class="feature-cta">Pick</a></div>
        </div>
      </div>
      <p class="small-muted">After choosing a plan you'll be taken to checkout. Payments will create a record in the <code>payments</code> table and the admin will apply the featured flags on success.</p>
    </div>
  </div>

  <div id="confirmModal" class="modal-backdrop" style="display:none;">
  <div class="modal">
    <h3 id="confirmText">Are you sure?</h3>

    <div style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;">
      <button id="confirmCancel" class="overlay-btn outline">Cancel</button>
      <button id="confirmOk" class="overlay-btn">Yes, continue</button>
    </div>
  </div>
</div>

  <script>
  (function(){
    // thumbs & main gallery behaviour with safe fallback
    const main = document.getElementById('galleryMain');
    const thumbs = Array.from(document.querySelectorAll('.gallery-thumb'));
    const placeholder = '../assets/img/placeholder.png';

    function setMainBackground(url){
      if (!main) return;
      // simple preloader to avoid showing broken image
      const img = new Image();
      img.onload = function(){ main.style.backgroundImage = 'url("' + url + '")'; }
      img.onerror = function(){ main.style.backgroundImage = 'url("' + placeholder + '")'; }
      img.src = url;
    }

    thumbs.forEach((t, i) => {
      // ensure displayed background-image exists (attempt to preload)
      const src = t.dataset.src || t.getAttribute('data-src') || '';
      const img = new Image();
      img.onload = function(){ /* ok */ };
      img.onerror = function(){ t.style.backgroundImage = 'url("' + placeholder + '")'; };
      img.src = src;

      t.addEventListener('click', () => {
        thumbs.forEach(x => x.classList.remove('active'));
        t.classList.add('active');
        setMainBackground(src);
      });
      t.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); t.click(); } });
    });

    // open main with first image (preload)
    if (thumbs.length) {
      const first = thumbs[0].dataset.src;
      setMainBackground(first);
    }

    // modal
    const featureBtn = document.getElementById('featureBtn');
    const modal = document.getElementById('featureModal');
    const closeBtn = document.getElementById('featClose');
    function openModal(){
  if(!modal) return;
  modal.style.display = 'flex';
  modal.setAttribute('aria-hidden','false');
}

function closeModal(){
  if(!modal) return;
  modal.style.display = 'none';
  modal.setAttribute('aria-hidden','true');
}
    if (featureBtn) featureBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });

    // map lazy load
    const showMapBtn = document.getElementById('showMap');
    const mapWrapper = document.getElementById('mapWrapper');
    let mapInitiated = false;
    function loadScript(src){ return new Promise((res, rej)=>{ if (document.querySelector('script[src="'+src+'"]')) { res(); return; } const s=document.createElement('script'); s.src = src; s.onload = res; s.onerror = rej; document.head.appendChild(s); }); }
    function loadCss(href){ return new Promise((res, rej)=>{ const existing=document.getElementById('leaflet-css'); if (existing) { existing.href = href; res(); return; } const l=document.createElement('link'); l.rel='stylesheet'; l.href=href; l.id='leaflet-css'; l.onload=res; l.onerror=rej; document.head.appendChild(l); }); }
    async function initMap(lat, lng) {
      if (mapInitiated) { try{ mapWrapper._map.setView([lat,lng],15);}catch(e){} return; }
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
      } catch (err) {
        window.open('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(lat+','+lng), '_blank');
      }
    }
    if (showMapBtn && mapWrapper) {
      showMapBtn.addEventListener('click', async () => {
        const lat = parseFloat(mapWrapper.dataset.lat);
        const lng = parseFloat(mapWrapper.dataset.lng);
        if (Number.isNaN(lat) || Number.isNaN(lng)) { alert('Coordinates not available'); return; }
        mapWrapper.style.display = 'block';
        await initMap(lat,lng);
        mapWrapper.scrollIntoView({behavior:'smooth'});
      });
    }
    // ==========================
// TOGGLE UNLIST / RELIST
// ==========================
const toggleBtn = document.getElementById('toggleListingBtn');
const confirmModal = document.getElementById('confirmModal');
const confirmText = document.getElementById('confirmText');
const confirmOk = document.getElementById('confirmOk');
const confirmCancel = document.getElementById('confirmCancel');
const form = document.getElementById('bulkActionForm');
const actionInput = document.getElementById('bulkActionInput');

let selectedAction = null;

if (toggleBtn) {
  toggleBtn.addEventListener('click', () => {
    selectedAction = toggleBtn.dataset.action;

    confirmText.textContent =
      selectedAction === 'unlist'
        ? 'Are you sure you want to UNLIST this property?'
        : 'Are you sure you want to RELIST this property?';

    confirmModal.style.display = 'flex';
  });
}

if (confirmCancel) {
  confirmCancel.onclick = () => {
    confirmModal.style.display = 'none';
  };
}

if (confirmOk) {
  confirmOk.onclick = () => {
    if (!selectedAction) return;

    actionInput.value = selectedAction;
    form.submit();
  };
}
  })();
  </script>
</body>
</html>
