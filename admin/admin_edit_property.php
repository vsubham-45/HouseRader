<?php
// admin/admin_edit_property.php
declare(strict_types=1);

// Admin edit property page — shows full editable form and saves updates (including featured controls)

// session + db
require_once __DIR__ . '/../src/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db_file = __DIR__ . '/../src/db.php';
if (!file_exists($db_file)) { http_response_code(500); echo "Missing DB helper"; exit; }
require_once $db_file; // provides $pdo

// admin guard
$admin_ok = false;
if (!empty($_SESSION['admin'])) $admin_ok = true;
if (!empty($_SESSION['user']) && is_array($_SESSION['user']) && (isset($_SESSION['user']['is_admin']) && (int)$_SESSION['user']['is_admin'] === 1)) $admin_ok = true;
if (!$admin_ok) {
    header('Location: login.php');
    exit;
}

// CSRF
if (!isset($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['admin_csrf'];

// helpers
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function val(array|object|null $arr, string $key, $default = null) {
    if (is_array($arr)) return array_key_exists($key,$arr) ? $arr[$key] : $default;
    if (is_object($arr)) return property_exists($arr,$key) ? $arr->$key : $default;
    return $default;
}

/* Image helpers — similar to seller utilities */
$PUBLIC_IMG_PREFIX = 'assets/img';
$IMAGE_DIR = realpath(__DIR__ . '/../public/assets/img');
if ($IMAGE_DIR === false) {
    $attempt = __DIR__ . '/../public/assets/img';
    @mkdir($attempt, 0755, true);
    $IMAGE_DIR = realpath($attempt) ?: $attempt;
}
if (substr($IMAGE_DIR, -1) !== DIRECTORY_SEPARATOR) $IMAGE_DIR .= DIRECTORY_SEPARATOR;

$MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB
function is_valid_image_file(array $f, int $maxSize): bool {
    if (!isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK) return false;
    if (!isset($f['size']) || $f['size'] > $maxSize) return false;
    $info = @getimagesize($f['tmp_name']);
    return ($info !== false && isset($info['mime']) && strpos($info['mime'], 'image/') === 0);
}
function make_unique_filename(string $ext): string {
    $ext = preg_replace('/[^a-z0-9]+/i','', $ext) ?: 'jpg';
    try { $rnd = bin2hex(random_bytes(6)); } catch (Throwable $e) { $rnd = bin2hex(openssl_random_pseudo_bytes(6)); }
    return 'property_' . time() . '_' . $rnd . '.' . $ext;
}
function save_uploaded_image(array $f, string $imageDir, string $publicPrefix, int $maxSize): string {
    if (!is_valid_image_file($f, $maxSize)) throw new RuntimeException('Invalid image or exceeds size limit.');
    $info = @getimagesize($f['tmp_name']);
    $ext = image_type_to_extension($info[2], false) ?: pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $ext = preg_replace('/[^a-z0-9]+/i','', $ext) ?: 'jpg';
    $filename = make_unique_filename($ext);
    if (!is_dir($imageDir)) {
        if (!@mkdir($imageDir, 0755, true) && !is_dir($imageDir)) {
            throw new RuntimeException('Upload folder not available.');
        }
    }
    if (!is_writable($imageDir)) throw new RuntimeException('Upload folder not writable.');
    $target = $imageDir . $filename;
    if (!@move_uploaded_file($f['tmp_name'], $target)) throw new RuntimeException('Failed to move uploaded file.');
    return rtrim($publicPrefix, '/') . '/' . $filename;
}
function normalize_public_path(string $p) : string {
    $p = trim($p);
    if ($p === '') return '';
    if (preg_match('#^(https?:)?//#i', $p)) return $p;
    $p = str_replace('\\','/',$p);
    return ltrim($p, '/');
}
function handle_image_input_admin(int $i, string $imageDir, string $publicPrefix, int $maxSize) {
    $urlField = "img_url_{$i}";
    $fileField = "img_file_{$i}";

    if (!empty($_POST[$urlField])) {
        $val = trim((string)$_POST[$urlField]);
        if (filter_var($val, FILTER_VALIDATE_URL) === false && !preg_match('#^[\w\-\./]+$#', $val)) {
            throw new RuntimeException("Image {$i}: invalid URL/path.");
        }
        return normalize_public_path($val);
    }

    if (!empty($_FILES[$fileField]) && ($_FILES[$fileField]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES[$fileField];
        return save_uploaded_image($f, $imageDir, $publicPrefix, $maxSize);
    }

    return null;
}

/* fetch property id */
$pid = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($pid <= 0) {
    http_response_code(400);
    echo "Invalid property id.";
    exit;
}

/* load existing row */
try {
    $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $pid]);
    $prop = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Admin edit fetch failed: ' . $e->getMessage());
    $prop = false;
}
if (!$prop) {
    http_response_code(404);
    echo "Property not found.";
    exit;
}

/* defaults for form */
$errors = [];
$success = null;

/* on POST: validate & update */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals((string)($_SESSION['admin_csrf'] ?? ''), (string)$token)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        // collect & validate fields (lenient)
        $seller_id = (int)($_POST['seller_id'] ?? $prop['seller_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? $prop['title'] ?? ''));
        $owner_name = trim((string)($_POST['owner_name'] ?? $prop['owner_name'] ?? ''));
        $owner_phone = trim((string)($_POST['owner_phone'] ?? $prop['owner_phone'] ?? ''));
        $owner_email = trim((string)($_POST['owner_email'] ?? $prop['owner_email'] ?? ''));
        $city = trim((string)($_POST['city'] ?? $prop['city'] ?? ''));
        $locality = trim((string)($_POST['locality'] ?? $prop['locality'] ?? ''));
        $address = trim((string)($_POST['address'] ?? $prop['address'] ?? ''));
        $property_type = trim((string)($_POST['property_type'] ?? $prop['property_type'] ?? 'sale'));
        $description = trim((string)($_POST['description'] ?? $prop['description'] ?? ''));
        $latitude = trim((string)($_POST['latitude'] ?? $prop['latitude'] ?? ''));
        $longitude = trim((string)($_POST['longitude'] ?? $prop['longitude'] ?? ''));
        $furnishing = trim((string)($_POST['furnishing'] ?? $prop['furnishing'] ?? ''));
        $amenities = trim((string)($_POST['amenities'] ?? $prop['amenities'] ?? ''));

        // featured controls
        $is_featured = isset($_POST['is_featured']) ? 1 : (int)($prop['is_featured'] ?? 0);
        $featured_until = trim((string)($_POST['featured_until'] ?? $prop['featured_until'] ?? ''));
        $featured_priority = (int)($_POST['featured_priority'] ?? $prop['featured_priority'] ?? 0);
        $featured_order = (int)($_POST['featured_order'] ?? $prop['featured_order'] ?? 0);

        // configs: accept raw slot values (config1..config6 and priceX,builtup_areaX,carpet_areaX)
        $slotConfig = [];
        $slotPrice = [];
        $slotBuilt = [];
        $slotCarpet = [];
        for ($s = 1; $s <= 6; $s++) {
            $ck = trim((string)($_POST['config' . $s] ?? $prop['config' . $s] ?? ''));
            $pp = $_POST['price' . $s] ?? $prop['price' . $s] ?? null;
            $bb = $_POST['builtup_area' . $s] ?? $prop['builtup_area' . $s] ?? null;
            $cc = $_POST['carpet_area' . $s] ?? $prop['carpet_area' . $s] ?? null;
            $slotConfig[$s] = ($ck !== '') ? $ck : null;
            $slotPrice[$s]  = ($pp === '' || $pp === null) ? null : (is_numeric($pp) ? (float)$pp : null);
            $slotBuilt[$s]  = ($bb === '' || $bb === null) ? null : (string)$bb;
            $slotCarpet[$s] = ($cc === '' || $cc === null) ? null : (string)$cc;
        }

        // rental fields (optional)
        $rental_type = trim((string)($_POST['rental_type'] ?? $prop['rental_type'] ?? 'rental'));
        $rental_config = trim((string)($_POST['rental_config'] ?? $prop['rental_config'] ?? ''));
        $rent = $_POST['rent'] ?? $prop['rent'] ?? null;
        $rental_carpet_area = $_POST['rental_carpet_area'] ?? $prop['rental_carpet_area'] ?? null;
        $rent = ($rent === '' || $rent === null) ? null : (is_numeric($rent) ? (float)$rent : null);
        $rental_carpet_area = ($rental_carpet_area === '' || $rental_carpet_area === null) ? null : (string)$rental_carpet_area;

        // images processing: for each slot, prefer URL input then file upload, otherwise keep existing
        $savedImages = [];
        for ($i = 1; $i <= 4; $i++) {
            try {
                $img = handle_image_input_admin($i, $IMAGE_DIR, $PUBLIC_IMG_PREFIX, $MAX_IMAGE_SIZE);
                if ($img !== null) {
                    $savedImages['img' . $i] = normalize_public_path($img);
                } else {
                    // keep existing if any
                    if (!empty($prop['img' . $i])) $savedImages['img' . $i] = $prop['img' . $i];
                    else $savedImages['img' . $i] = null;
                }
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
        }

        // basic validation
        if ($title === '') $errors[] = 'Title is required.';
        if ($owner_name === '') $errors[] = 'Owner name is required.';
        if ($seller_id <= 0) $errors[] = 'Seller ID required.';

        // if no errors proceed to update
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // compute min_price/min_rent
                $min_price = null; $min_rent = null;
                foreach ($slotPrice as $p) { if ($p === null) continue; if ($min_price === null || $p < $min_price) $min_price = $p; }
                if ($rent !== null) $min_rent = $rent;
                // also consider any price-like rents in rental slots (none here)

                $sql = "UPDATE properties SET
                    seller_id = :seller_id,
                    owner_name = :owner_name, owner_phone = :owner_phone, owner_email = :owner_email,
                    title = :title, property_type = :property_type, city = :city, locality = :locality, address = :address,
                    description = :description, furnishing = :furnishing, amenities = :amenities,
                    latitude = :latitude, longitude = :longitude,
                    config1 = :config1, config2 = :config2, config3 = :config3, config4 = :config4, config5 = :config5, config6 = :config6,
                    price1 = :price1, price2 = :price2, price3 = :price3, price4 = :price4, price5 = :price5, price6 = :price6,
                    builtup_area1 = :builtup_area1, builtup_area2 = :builtup_area2, builtup_area3 = :builtup_area3, builtup_area4 = :builtup_area4, builtup_area5 = :builtup_area5, builtup_area6 = :builtup_area6,
                    carpet_area1 = :carpet_area1, carpet_area2 = :carpet_area2, carpet_area3 = :carpet_area3, carpet_area4 = :carpet_area4, carpet_area5 = :carpet_area5, carpet_area6 = :carpet_area6,
                    rental_type = :rental_type, rental_config = :rental_config, rent = :rent, rental_carpet_area = :rental_carpet_area,
                    img1 = :img1, img2 = :img2, img3 = :img3, img4 = :img4,
                    min_price = :min_price, min_rent = :min_rent,
                    is_featured = :is_featured, featured_until = :featured_until, featured_priority = :featured_priority, featured_order = :featured_order,
                    updated_at = NOW()
                    WHERE id = :id
                ";

                $stmt = $pdo->prepare($sql);
                $bind = [
                    ':seller_id' => $seller_id,
                    ':owner_name' => $owner_name, ':owner_phone' => $owner_phone, ':owner_email' => $owner_email,
                    ':title' => $title, ':property_type' => $property_type, ':city' => $city, ':locality' => $locality, ':address' => $address,
                    ':description' => $description, ':furnishing' => $furnishing, ':amenities' => $amenities,
                    ':latitude' => $latitude !== '' ? (float)$latitude : null, ':longitude' => $longitude !== '' ? (float)$longitude : null,
                    ':config1' => $slotConfig[1], ':config2' => $slotConfig[2], ':config3' => $slotConfig[3],
                    ':config4' => $slotConfig[4], ':config5' => $slotConfig[5], ':config6' => $slotConfig[6],
                    ':price1' => $slotPrice[1], ':price2' => $slotPrice[2], ':price3' => $slotPrice[3],
                    ':price4' => $slotPrice[4], ':price5' => $slotPrice[5], ':price6' => $slotPrice[6],
                    ':builtup_area1' => $slotBuilt[1], ':builtup_area2' => $slotBuilt[2], ':builtup_area3' => $slotBuilt[3],
                    ':builtup_area4' => $slotBuilt[4], ':builtup_area5' => $slotBuilt[5], ':builtup_area6' => $slotBuilt[6],
                    ':carpet_area1' => $slotCarpet[1], ':carpet_area2' => $slotCarpet[2], ':carpet_area3' => $slotCarpet[3],
                    ':carpet_area4' => $slotCarpet[4], ':carpet_area5' => $slotCarpet[5], ':carpet_area6' => $slotCarpet[6],
                    ':rental_type' => $rental_type, ':rental_config' => $rental_config, ':rent' => $rent, ':rental_carpet_area' => $rental_carpet_area,
                    ':img1' => $savedImages['img1'] ?? null, ':img2' => $savedImages['img2'] ?? null, ':img3' => $savedImages['img3'] ?? null, ':img4' => $savedImages['img4'] ?? null,
                    ':min_price' => $min_price, ':min_rent' => $min_rent,
                    ':is_featured' => $is_featured, ':featured_until' => ($featured_until === '' ? null : $featured_until),
                    ':featured_priority' => $featured_priority, ':featured_order' => $featured_order,
                    ':id' => $pid
                ];

                $stmt->execute($bind);
                $pdo->commit();

                // reload property from DB for display
                $stmt2 = $pdo->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
                $stmt2->execute([':id' => $pid]);
                $prop = $stmt2->fetch(PDO::FETCH_ASSOC);

                $success = 'Property updated successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

/* helper to produce preview src for admin UI */
function web_preview_src(?string $raw): string {
    if (empty($raw)) return '../public/assets/img/placeholder.png';
    $r = trim((string)$raw);
    if (preg_match('#^https?://#i', $r) || strpos($r, '//') === 0) return $r;
    // if it already starts with 'assets/' or 'public/assets' adjust path relative to admin/
    if (str_starts_with($r, 'assets/')) return '../public/' . ltrim($r, '/');
    if (str_starts_with($r, 'public/')) return '..' . '/' . ltrim($r, '/');
    // assume stored basename inside public/assets/img
    return '../public/assets/img/' . ltrim($r, '/');
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin Edit Property — <?= h(val($prop,'title','')) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="css/admin_index.css" />
  <link rel="stylesheet" href="css/admin_edit_property.css" />
</head>
<body>
  <main class="admin-container">
    <header class="admin-header">
      <div>
        <h1>Edit Property: <?= h(val($prop,'title','')) ?></h1>
        <?php if ($success): ?><div class="flash success"><?= h($success) ?></div><?php endif; ?>
        <?php if (!empty($errors)): ?><div class="flash error"><ul><?php foreach($errors as $e) echo '<li>'.h($e).'</li>'; ?></ul></div><?php endif; ?>
      </div>
      <div class="admin-actions">
        <a class="btn" href="admin_property_details.php?id=<?= h((string)$pid) ?>">← Back</a>
      </div>
    </header>

    <form method="post" enctype="multipart/form-data" class="edit-form" action="<?= h($_SERVER['PHP_SELF'] . '?id=' . $pid) ?>">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>" />
      <input type="hidden" name="id" value="<?= h((string)$pid) ?>" />

      <div class="grid">
        <section class="left-col card">
          <h3>Basic</h3>
          <div class="row">
            <label>Seller ID</label>
            <input name="seller_id" type="number" value="<?= h(val($prop,'seller_id','')) ?>" required />
          </div>

          <div class="row">
            <label>Title</label>
            <input name="title" type="text" value="<?= h(val($prop,'title','')) ?>" required />
          </div>

          <div class="row two">
            <div>
              <label>City</label>
              <input name="city" type="text" value="<?= h(val($prop,'city','')) ?>" />
            </div>
            <div>
              <label>Locality</label>
              <input name="locality" type="text" value="<?= h(val($prop,'locality','')) ?>" />
            </div>
          </div>

          <div class="row">
            <label>Address</label>
            <input name="address" type="text" value="<?= h(val($prop,'address','')) ?>" />
          </div>

          <div class="row two">
            <div>
              <label>Latitude</label>
              <input name="latitude" type="text" value="<?= h(val($prop,'latitude','')) ?>" />
            </div>
            <div>
              <label>Longitude</label>
              <input name="longitude" type="text" value="<?= h(val($prop,'longitude','')) ?>" />
            </div>
          </div>

          <div class="row">
            <label>Property Type</label>
            <select name="property_type">
              <?php $pt = val($prop,'property_type','sale'); ?>
              <option value="sale" <?= $pt==='sale' ? 'selected' : '' ?>>For Sale</option>
              <option value="rental" <?= $pt==='rental' ? 'selected' : '' ?>>Rental</option>
              <option value="upcoming" <?= $pt==='upcoming' ? 'selected' : '' ?>>Upcoming</option>
            </select>
          </div>

          <div class="row">
            <label>Description</label>
            <textarea name="description" rows="6"><?= h(val($prop,'description','')) ?></textarea>
          </div>

          <h3>Owner / Contact</h3>
          <div class="row">
            <label>Owner Name</label>
            <input name="owner_name" type="text" value="<?= h(val($prop,'owner_name','')) ?>" required />
          </div>
          <div class="row two">
            <div>
              <label>Owner Phone</label>
              <input name="owner_phone" type="text" value="<?= h(val($prop,'owner_phone','')) ?>" />
            </div>
            <div>
              <label>Owner Email</label>
              <input name="owner_email" type="email" value="<?= h(val($prop,'owner_email','')) ?>" />
            </div>
          </div>

          <h3>Images</h3>
          <div class="images-grid">
            <?php for ($i=1;$i<=4;$i++): $k='img'.$i; $src = val($prop,$k,''); ?>
            <div class="img-col">
              <label>Image <?= $i ?></label>
              <input type="text" name="img_url_<?= $i ?>" placeholder="Image URL (optional)" value="<?= h($src) ?>" />
              <div class="muted center">or</div>
              <input type="file" name="img_file_<?= $i ?>" accept="image/*" />
              <div class="img-preview"><img src="<?= h(web_preview_src($src)) ?>" alt="preview <?= $i ?>" onerror="this.src='../public/assets/img/placeholder.png'"/></div>
            </div>
            <?php endfor; ?>
          </div>

          <h3>Configurations (slots)</h3>
          <p class="muted">Edit up to six config slots — empty slots are ignored.</p>
          <table class="configs-table admin-small">
            <thead><tr><th>Slot</th><th>Config</th><th>Price</th><th>Built-up</th><th>Carpet</th></tr></thead>
            <tbody>
              <?php for ($s=1;$s<=6;$s++): ?>
                <tr>
                  <td><?= $s ?></td>
                  <td><input name="config<?= $s ?>" type="text" value="<?= h(val($prop,'config'.$s,'')) ?>" /></td>
                  <td><input name="price<?= $s ?>" type="number" step="0.01" value="<?= h(val($prop,'price'.$s,'')) ?>" /></td>
                  <td><input name="builtup_area<?= $s ?>" type="text" value="<?= h(val($prop,'builtup_area'.$s,'')) ?>" /></td>
                  <td><input name="carpet_area<?= $s ?>" type="text" value="<?= h(val($prop,'carpet_area'.$s,'')) ?>" /></td>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>

        </section>

        <aside class="right-col card">
          <h3>Rental / Pricing</h3>
          <div class="row">
            <label>Rental Type</label>
            <select name="rental_type">
              <?php $rt = val($prop,'rental_type','rental'); ?>
              <option value="rental" <?= $rt==='rental' ? 'selected' : '' ?>>Normal</option>
              <option value="pg" <?= $rt==='pg' ? 'selected' : '' ?>>PG</option>
            </select>
          </div>
          <div class="row">
            <label>Rental Config (one)</label>
            <input name="rental_config" type="text" value="<?= h(val($prop,'rental_config','')) ?>" />
          </div>
          <div class="row">
            <label>Rent</label>
            <input name="rent" type="number" step="0.01" value="<?= h(val($prop,'rent','')) ?>" />
          </div>
          <div class="row">
            <label>Rental Carpet Area</label>
            <input name="rental_carpet_area" type="text" value="<?= h(val($prop,'rental_carpet_area','')) ?>" />
          </div>

          <h3>Furnishing & Amenities</h3>
          <div class="row">
            <label>Furnishing</label>
            <input name="furnishing" type="text" value="<?= h(val($prop,'furnishing','')) ?>" />
          </div>
          <div class="row">
            <label>Amenities (comma separated)</label>
            <input name="amenities" type="text" value="<?= h(val($prop,'amenities','')) ?>" />
          </div>

          <h3>Featured / Listing</h3>
          <div class="row">
            <label><input type="checkbox" name="is_featured" value="1" <?= (int)val($prop,'is_featured',0) === 1 ? 'checked' : '' ?> /> Mark featured</label>
          </div>
          <div class="row">
            <label>Featured until (YYYY-MM-DD HH:MM:SS)</label>
            <input name="featured_until" type="text" value="<?= h(val($prop,'featured_until','')) ?>" placeholder="2025-12-31 23:59:59" />
          </div>
          <div class="row two">
            <div><label>Featured priority</label><input name="featured_priority" type="number" min="0" value="<?= h(val($prop,'featured_priority',0)) ?>" /></div>
            <div><label>Featured order</label><input name="featured_order" type="number" min="0" value="<?= h(val($prop,'featured_order',0)) ?>" /></div>
          </div>

          <h3>Admin Controls</h3>
          <div class="row">
            <label>Status</label>
            <select name="status" disabled>
              <!-- status is updated through admin_actions.php; disable here to avoid accidental change -->
              <option><?= h(val($prop,'status','pending')) ?></option>
            </select>
          </div>

          <div style="margin-top:16px;">
            <button class="btn primary" type="submit">Save changes</button>
            <a class="btn ghost" href="admin_property_details.php?id=<?= h((string)$pid) ?>">Cancel</a>
          </div>
        </aside>
      </div>
    </form>
  </main>
</body>
</html>
