<?php
// public/admin/admin_property_details.php
declare(strict_types=1);

// Admin property details page (view + admin actions launcher)

// session + db
require_once __DIR__ . '/../src/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db_file = __DIR__ . '/../src/db.php';
if (!file_exists($db_file)) { http_response_code(500); echo "Missing DB helper"; exit; }
require_once $db_file; // provides $pdo

// --- admin guard (flexible) ---
$admin_ok = false;
if (!empty($_SESSION['admin'])) $admin_ok = true;
if (!empty($_SESSION['user']) && is_array($_SESSION['user']) && (isset($_SESSION['user']['is_admin']) && (int)$_SESSION['user']['is_admin'] === 1)) $admin_ok = true;
if (!$admin_ok) {
    // adjust login path if you have a different admin login URL
    header('Location: ../admin/admin_login.php');
    exit;
}

// ensure CSRF for admin forms
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['admin_csrf'];

// small helpers (reused pattern)
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function val(array|object|null $arr, string $key, $default = null) {
    if (is_array($arr)) return array_key_exists($key, $arr) ? $arr[$key] : $default;
    if (is_object($arr)) return property_exists($arr, $key) ? $arr->$key : $default;
    return $default;
}

/**
 * Normalize various stored image values into a usable web path from admin/.
 * - Accepts: absolute http(s) URLs, protocol-relative (//...), site-relative (/...), "assets/..." or "public/assets/..." or just filenames.
 * - For local asset filenames it checks common FS locations and returns a web path relative to admin/ so <div style="background-image:url(...)"> works.
 *
 * Returns string (web path) or null when input empty.
 */
function normalize_img(string $raw = null): ?string {
    $raw = trim((string)$raw);
    if ($raw === '') return null;

    // Absolute URLs or protocol-relative
    if (preg_match('#^https?://#i', $raw) || strpos($raw, '//') === 0) return $raw;

    // If user stored a site-root absolute path (starts with '/'), keep it as-is
    if (strpos($raw, '/') === 0) return $raw;

    // If already includes 'public/assets' path, make it relative to admin (../public/...)
    if (stripos($raw, 'public/assets/') === 0) {
        return '../' . ltrim($raw, '/');
    }

    // If already includes 'assets/' prefix (like 'assets/img/file.jpg'), point to ../public/assets/...
    if (stripos($raw, 'assets/') === 0) {
        return '../public/' . ltrim($raw, '/');
    }

    // Candidate filesystem checks (prefer public/assets/img)
    $candidates = [];

    // admin dir is __DIR__ ( .../HouseRader/admin )
    // project root:
    $projectRoot = dirname(__DIR__); // .../HouseRader

    // 1) public/assets/img/<raw>
    $candidates[] = $projectRoot . '/public/assets/img/' . $raw;

    // 2) public/assets/img/<basename(raw)> (in case DB stored just filename with path separators)
    $candidates[] = $projectRoot . '/public/assets/img/' . basename($raw);

    // 3) projectRoot/assets/img/<raw> (older conventions)
    $candidates[] = $projectRoot . '/assets/img/' . $raw;
    $candidates[] = $projectRoot . '/assets/img/' . basename($raw);

    // 4) uploads/attachments (in case attachments were used)
    $candidates[] = $projectRoot . '/public/uploads/attachments/' . $raw;
    $candidates[] = $projectRoot . '/public/uploads/attachments/' . basename($raw);

    foreach ($candidates as $path) {
        if ($path && file_exists($path)) {
            // convert filesystem path to web path relative to admin/
            // we want something like: ../public/assets/img/<filename>
            // find the portion after project root
            $rel = str_replace('\\', '/', substr($path, strlen($projectRoot) + 1)); // remove leading slash
            return '../' . ltrim($rel, '/');
        }
    }

    // As a last resort, if the raw looks like 'imgname.ext' treat it as public/assets/img/<raw>
    if (preg_match('/^[\w\-\.\@]+?\.(jpe?g|png|gif|webp|svg)$/i', $raw)) {
        return '../public/assets/img/' . $raw;
    }

    // otherwise return raw unchanged — caller may still try to use it (e.g. it's already a relative path)
    return $raw;
}

// get property id
$pid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($pid <= 0) {
    http_response_code(400);
    echo "Invalid property id.";
    exit;
}

// fetch property
try {
    $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $pid]);
    $prop = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Admin property fetch failed: ' . $e->getMessage());
    $prop = false;
}
if (!$prop) {
    http_response_code(404);
    echo "Property not found.";
    exit;
}

// derived values
$title = val($prop, 'title', 'Untitled Property');
$status = val($prop, 'status', 'pending');
$is_featured = (int)val($prop, 'is_featured', 0) === 1;
$featured_until = val($prop, 'featured_until', null);
$seller_id = (int)val($prop, 'seller_id', 0);
$created_at = val($prop, 'created_at', null);
$createdLabel = $created_at ? date('M j, Y H:i', strtotime($created_at)) : '';
$min_price = val($prop, 'min_price', null);
$min_rent = val($prop, 'min_rent', null);
$priceLabel = 'Contact for price';
if ($min_price !== null && $min_price !== '') $priceLabel = '₹' . number_format((float)$min_price);
elseif ($min_rent !== null && $min_rent !== '') $priceLabel = '₹' . number_format((float)$min_rent) . ' /mo';

$city = val($prop, 'city', '');
$locality = val($prop, 'locality', '');
$address = val($prop, 'address', '');
$owner_name = val($prop, 'owner_name', '');
$owner_phone = val($prop, 'owner_phone', '');
$owner_email = val($prop, 'owner_email', '');

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
// final fallback: site placeholder (relative to admin/)
if (empty($images)) {
    // prefer existing public placeholder if present
    $projectRoot = dirname(__DIR__);
    $placeholderFs = $projectRoot . '/public/assets/img/placeholder.png';
    if (file_exists($placeholderFs)) {
        $images[] = '../public/assets/img/placeholder.png';
    } else {
        // generic safe relative path (admin -> public)
        $images[] = '../public/assets/img/placeholder.png';
    }
}

// configs aggregated
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

// live metrics (best-effort)
$views = 0;
$messages = (int)val($prop, 'messages_count', 0);
try {
    $vstmt = $pdo->prepare('SELECT COUNT(*) FROM property_views WHERE property_id = :pid');
    $vstmt->execute([':pid' => $pid]);
    $views = (int)$vstmt->fetchColumn();
} catch (Throwable $e) { /* ignore */ }
try {
    $cstmt = $pdo->prepare('SELECT COUNT(*) FROM conversations WHERE property_id = :pid');
    $cstmt->execute([':pid' => $pid]);
    $conv_count = (int)$cstmt->fetchColumn();
    if ($conv_count > 0) {
        $mstmt = $pdo->prepare('SELECT SUM(messages_count) FROM conversations WHERE property_id = :pid');
        $mstmt->execute([':pid' => $pid]);
        $sum = $mstmt->fetchColumn();
        if ($sum !== false && $sum !== null) $messages = max($messages, (int)$sum);
    }
} catch (Throwable $e) { /* ignore */ }

// admin action endpoints we will POST to (conservative: admin_actions.php)
$action_endpoint = 'admin_actions.php';

// include theme header if present
$header_candidates = [__DIR__ . '/../partials/header.php', __DIR__ . '/../header.php'];
$header_included = false;
foreach ($header_candidates as $hf) {
    if (file_exists($hf)) { include $hf; $header_included = true; break; }
}
if (!$header_included) {
    echo '<header class="hr-navbar"><div class="container"><a class="brand" href="admin_dashboard.php">🏠 HouseRader Admin</a></div></header>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin — <?= h($title) ?> — HouseRader</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="../admin/css/admin_index.css" />
  <link rel="stylesheet" href="../admin/css/admin_property_details.css" />
</head>
<body>
  <main class="admin-container">
    <div class="admin-header">
      <div>
        <h1><?= h($title) ?></h1>
        <div class="meta-row">
          <div class="meta-item"><strong>Price</strong> <?= h($priceLabel) ?></div>
          <div class="meta-item"><strong>City</strong> <?= h($city ?: '-') ?></div>
          <div class="meta-item"><strong>Posted</strong> <?= h($createdLabel) ?></div>
        </div>
      </div>
      <div class="admin-actions">
        <a class="btn btn-ghost" href="properties.php?status=live">← Back to list</a>
        <a class="btn" href="admin_edit_property.php?id=<?= h((string)$pid) ?>">Edit (seller)</a>
      </div>
    </div>

    <div class="admin-main">
      <section class="left-col">
        <div class="card gallery-card">
          <div class="gallery-grid">
            <div class="gallery-main" style="background-image:url('<?= h($images[0]) ?>')"></div>
            <div class="gallery-thumbs">
              <?php foreach ($images as $i => $img): ?>
                <div class="thumb" style="background-image:url('<?= h($img) ?>')" title="Image <?= $i+1 ?>"></div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="card-section">
            <h3>Configurations</h3>
            <?php if (!empty($configs)): ?>
              <table class="configs-table">
                <thead><tr><th>Config</th><th>Price</th><th>Built-up</th><th>Carpet</th></tr></thead>
                <tbody>
                  <?php foreach ($configs as $c): ?>
                    <tr>
                      <td><?= h($c['label']) ?></td>
                      <td><?= $c['price'] ? '₹' . number_format($c['price']) : '-' ?></td>
                      <td><?= $c['built'] ? h($c['built']) . ' sqft' : '-' ?></td>
                      <td><?= $c['carpet'] ? h($c['carpet']) . ' sqft' : '-' ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div class="muted">No configurations provided.</div>
            <?php endif; ?>
          </div>

          <div class="card-section">
            <h3>Description</h3>
            <div class="muted"><?= nl2br(h(val($prop,'description',''))) ?></div>
          </div>
        </div>
      </section>

      <aside class="right-col">
        <div class="card info-card">
          <h3>Property Info</h3>
          <dl class="info-list">
            <dt>ID</dt><dd><?= h((string)$pid) ?></dd>
            <dt>Status</dt><dd><strong><?= h($status) ?></strong></dd>
            <dt>Featured</dt><dd><?= $is_featured ? 'Yes' : 'No' ?> <?= $featured_until ? ' (until ' . h(date('M j, Y', strtotime($featured_until))) . ')' : '' ?></dd>
            <dt>Seller ID</dt><dd><?= h((string)$seller_id) ?></dd>
            <dt>Owner</dt><dd><?= h($owner_name) ?></dd>
            <dt>Phone</dt><dd><?= h($owner_phone ?: '-') ?></dd>
            <dt>Email</dt><dd><?= h($owner_email ?: '-') ?></dd>
            <?php if (!empty($address)): ?>
              <dt>Address</dt><dd class="admin-address"><?= h($address) ?></dd>
            <?php endif; ?>
            <dt>City / Locality</dt><dd><?= h(trim($locality . ($locality && $city ? ' • ' : '') . $city)) ?></dd>
            <dt>Views</dt><dd><?= number_format($views) ?></dd>
            <dt>Messages</dt><dd><?= number_format($messages) ?></dd>
          </dl>

          <hr/>

          <div class="admin-form-row">
            <!-- update status -->
            <form method="post" action="<?= h($action_endpoint) ?>" onsubmit="return confirm('Change status?');">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>" />
              <input type="hidden" name="property_id" value="<?= h((string)$pid) ?>" />
              <input type="hidden" name="action" value="update_status" />
              <label for="status_select">Status</label>
              <select id="status_select" name="status" required>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>pending</option>
                <option value="live" <?= $status === 'live' ? 'selected' : '' ?>>live</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>rejected</option>
              </select>
              <div style="margin-top:10px;">
                <button class="btn primary" type="submit">Update status</button>
              </div>
            </form>
          </div>

          <div class="admin-form-row" style="margin-top:12px;">
            <!-- toggle feature -->
            <form method="post" action="<?= h($action_endpoint) ?>" onsubmit="return confirm('Toggle featured flag for this property?');">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>" />
              <input type="hidden" name="property_id" value="<?= h((string)$pid) ?>" />
              <input type="hidden" name="action" value="toggle_feature" />
              <button class="btn <?= $is_featured ? 'danger' : 'primary' ?>" type="submit"><?= $is_featured ? 'Remove Featured' : 'Feature property' ?></button>
            </form>
          </div>

          <div class="admin-form-row" style="margin-top:12px;">
            <!-- delete property -->
            <form method="post" action="<?= h($action_endpoint) ?>" onsubmit="return confirm('Permanently delete this property and related data? This cannot be undone.');">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>" />
              <input type="hidden" name="property_id" value="<?= h((string)$pid) ?>" />
              <input type="hidden" name="action" value="delete_property" />
              <button class="btn danger-outline" type="submit">Delete property</button>
            </form>
          </div>

          <hr/>

          <div class="admin-links">
            <a class="link" href="conversations_list.php?property_id=<?= h((string)$pid) ?>">View conversations (inbox)</a><br/>
            <a class="link" href="audit_logs.php?property_id=<?= h((string)$pid) ?>">Audit logs</a>
          </div>
        </div>
      </aside>
    </div>
  </main>

  <script>
    // small nicety: show preview on thumb click (no external dependencies)
    (function(){
      var main = document.querySelector('.gallery-main');
      var thumbs = document.querySelectorAll('.gallery-thumbs .thumb');
      thumbs.forEach(function(t){
        t.addEventListener('click', function(){
          var bg = window.getComputedStyle(t).backgroundImage;
          if (bg && main) main.style.backgroundImage = bg;
        });
      });
    })();
  </script>
</body>
</html>
