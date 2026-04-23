<?php
// public/seller/edit_property.php
declare(strict_types=1);

// --- session + db helpers ---
$session_file = __DIR__ . '/../../src/session.php';
if (!file_exists($session_file)) { http_response_code(500); echo "Missing session helper"; exit; }
require_once $session_file;

$db_file = __DIR__ . '/../../src/db.php';
if (!file_exists($db_file)) { http_response_code(500); echo "Missing DB helper"; exit; }
require_once $db_file; // provides $pdo

// --- seller guard ---
$seller = $_SESSION['seller'] ?? null;
if (!$seller) {
    header('Location: ../user/login.php');
    exit;
}
$seller_id = (int)$seller['id'];

// --- config (same as add_property) ---
$PUBLIC_IMG_PREFIX = 'assets/img';
$IMAGE_DIR = realpath(__DIR__ . '/../assets/img');
if ($IMAGE_DIR === false) {
    $attempt = __DIR__ . '/../assets/img';
    @mkdir($attempt, 0755, true);
    $IMAGE_DIR = realpath($attempt) ?: $attempt;
}
if (substr($IMAGE_DIR, -1) !== DIRECTORY_SEPARATOR) $IMAGE_DIR .= DIRECTORY_SEPARATOR;

$MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5 MB
$ALLOWED_CITIES = ['Mumbai','Navi-Mumbai','Thane'];
$ALLOWED_TYPES = ['upcoming','sale','rental'];
$CONFIG_KEYS = ['RK','1BHK','2BHK','3BHK','4BHK','5BHK'];
$ALLOWED_RENTAL_TYPES = ['rental','pg'];

// --- helpers (copied from add_property for parity) ---
function clean(string $v): string { return trim($v); }

function normalize_public_path(string $p) : string {
    $p = trim($p);
    if ($p === '') return '';
    if (preg_match('#^(https?:)?//#i', $p)) return $p;
    $p = str_replace('\\','/',$p);
    return ltrim($p, '/');
}

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

function handle_image_input(int $i, string $imageDir, string $publicPrefix, int $maxSize) {
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

function parse_and_validate_configs(array $configKeys, string $ptype): array {
    $res = [];
    if ($ptype === 'rental') {
        $key = isset($_POST['rental_config']) ? trim((string)$_POST['rental_config']) : '';
        if ($key === '' || !in_array($key, $configKeys, true)) throw new RuntimeException('Rental: select exactly one configuration.');
        $rent = $_POST['configs'][$key]['rent'] ?? null;
        $carpet = $_POST['configs'][$key]['carpet'] ?? null;
        if ($rent === null || trim((string)$rent) === '') throw new RuntimeException('Enter rent for the selected configuration.');
        if (!is_numeric($rent) || (float)$rent < 0) throw new RuntimeException('Invalid rent value.');
        if ($carpet === null || trim((string)$carpet) === '') throw new RuntimeException('Enter carpet area for the selected configuration.');
        $res[$key] = ['rent' => (float)$rent, 'carpet' => trim((string)$carpet)];
    } else {
        $chosen = $_POST['configurations'] ?? [];
        if (!is_array($chosen)) $chosen = [$chosen];
        $chosen = array_values(array_unique(array_filter(array_map('trim', $chosen))));
        if (count($chosen) < 1) throw new RuntimeException('Select at least one configuration (1-6).');
        if (count($chosen) > count($configKeys)) throw new RuntimeException('Too many configurations selected.');
        foreach ($chosen as $k) {
            if (!in_array($k, $configKeys, true)) throw new RuntimeException("Invalid configuration selected: {$k}");
            $price = $_POST['configs'][$k]['price'] ?? null;
            $built = $_POST['configs'][$k]['built_up'] ?? null;
            $carpet = $_POST['configs'][$k]['carpet'] ?? null;
            if ($price === null || trim((string)$price) === '') throw new RuntimeException("Enter price for {$k}.");
            if (!is_numeric($price) || (float)$price < 0) throw new RuntimeException("Invalid price for {$k}.");
            if ($built === null || trim((string)$built) === '') throw new RuntimeException("Enter built-up area for {$k}.");
            if ($carpet === null || trim((string)$carpet) === '') throw new RuntimeException("Enter carpet area for {$k}.");
            $res[$k] = ['price' => (float)$price, 'built_up' => trim((string)$built), 'carpet' => trim((string)$carpet)];
        }
    }
    return $res;
}

// --- identify property id (from GET or POST) ---
$errors = [];
$prop = [];

/** get id */
$id = 0;
if (!empty($_GET['id'])) $id = (int)$_GET['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) $id = (int)$_POST['id'];
if ($id <= 0) {
    $errors[] = 'Invalid property id.';
} else {
    // fetch existing property to ensure ownership and populate $prop
    try {
        $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            $errors[] = 'Property not found.';
        } else {
            // ownership check: only seller who owns the property can edit
            if ((int)$existing['seller_id'] !== $seller_id) {
                $errors[] = 'You do not have permission to edit this property.';
            } else {
                // convert DB slots (config1..config6, price1..price6, builtup_area1..6, carpet_area1..6)
                // into $prop['configs'] (JSON-ish) and $prop['configurations'] for template compatibility.
                $configs_out = [];
                $configs_list = [];
                for ($i = 1; $i <= 6; $i++) {
                    $cfg = $existing['config' . $i] ?? null;
                    if ($cfg !== null && $cfg !== '') {
                        $meta = [];
                        $pkey = 'price' . $i;
                        $bkey = 'builtup_area' . $i;
                        $ckey = 'carpet_area' . $i;
                        if (isset($existing[$pkey]) && $existing[$pkey] !== null) $meta['price'] = (float)$existing[$pkey];
                        if (isset($existing[$bkey]) && $existing[$bkey] !== null) $meta['built_up'] = $existing[$bkey];
                        if (isset($existing[$ckey]) && $existing[$ckey] !== null) $meta['carpet'] = $existing[$ckey];
                        $configs_out[$cfg] = $meta;
                        $configs_list[] = $cfg;
                    }
                }
                // attach to $existing so template picks them up
                if (!empty($configs_out)) {
                    // store as JSON string (template accepts strings and arrays)
                    $existing['configs'] = json_encode($configs_out);
                    $existing['configurations'] = json_encode(array_values($configs_list));
                } else {
                    // ensure keys exist but empty
                    $existing['configs'] = null;
                    $existing['configurations'] = null;
                }

                // ensure address passes through if present (new column)
                if (!isset($existing['address'])) $existing['address'] = null;

                $prop = $existing;
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'Failed to load property: ' . $e->getMessage();
    }
}

// set up CSRF (matching template usage)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['property_csrf'])) $_SESSION['property_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['property_csrf'];

// If POST, validate and update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // Merge posted values into $prop for form re-rendering if needed
    $prop = array_merge($prop, $_POST);
    // CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals((string)($_SESSION['property_csrf'] ?? ''), (string)$token)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        // basic required fields
        $title = clean($_POST['title'] ?? '');
        $owner_name = clean($_POST['owner_name'] ?? '');
        $owner_phone = clean($_POST['owner_phone'] ?? '');
        $owner_email = clean($_POST['owner_email'] ?? '');
        $city = clean($_POST['city'] ?? '');
        $property_type = clean($_POST['property_type'] ?? '');

        if ($title === '') $errors[] = 'Title is required.';
        if ($owner_name === '') $errors[] = 'Owner name is required.';
        if ($owner_phone === '') $errors[] = 'Owner phone is required.';
        if ($city === '' || !in_array($city, $ALLOWED_CITIES, true)) $errors[] = 'Please select a valid city.';
        if ($property_type === '' || !in_array($property_type, $ALLOWED_TYPES, true)) $errors[] = 'Please select a valid property type.';

        // rental_type handling
        $rental_type = null;
        if ($property_type === 'rental') {
            $rental_type = isset($_POST['rental_type']) ? trim((string)$_POST['rental_type']) : '';
            if ($rental_type === '' || !in_array($rental_type, $ALLOWED_RENTAL_TYPES, true)) {
                $errors[] = 'Please select a rental type (normal or pg).';
            }
        }

        $configs = [];
        if (empty($errors)) {
            try { $configs = parse_and_validate_configs($CONFIG_KEYS, $property_type); }
            catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
        }

        // optional fields
        $locality = clean($_POST['locality'] ?? '');
        $address = clean($_POST['address'] ?? ''); // NEW: address field
        $furnishing = clean($_POST['furnishing'] ?? '');
        $amenities = clean($_POST['amenities'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $latitude = clean($_POST['latitude'] ?? '');
        $longitude = clean($_POST['longitude'] ?? '');

        // process images: prefer text field, then upload. If neither provided, keep existing
        $savedImages = [];
        if (empty($errors)) {
            for ($i = 1; $i <= 4; $i++) {
                try {
                    $img = handle_image_input($i, $IMAGE_DIR, $PUBLIC_IMG_PREFIX, $MAX_IMAGE_SIZE);
                    if ($img !== null) {
                        $savedImages['img' . $i] = normalize_public_path($img);
                    } else {
                        // keep existing image if present
                        $existingKey = 'img' . $i;
                        if (!empty($prop[$existingKey])) $savedImages[$existingKey] = $prop[$existingKey];
                    }
                } catch (RuntimeException $ex) {
                    $errors[] = $ex->getMessage();
                    break;
                }
            }
        }

        // if no errors, update properties table
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Prepare config slots 1..6
                $slotConfig = array_fill(1, 6, null);
                $slotPrice  = array_fill(1, 6, null);
                $slotBuilt  = array_fill(1, 6, null);
                $slotCarpet = array_fill(1, 6, null);

                foreach ($configs as $k => $meta) {
                    $idx = array_search($k, $CONFIG_KEYS, true);
                    if ($idx === false) continue;
                    $slot = $idx + 1;
                    $slotConfig[$slot]  = $k;
                    if (isset($meta['price'])) $slotPrice[$slot] = (float)$meta['price'];
                    if (isset($meta['built_up'])) $slotBuilt[$slot] = (float)$meta['built_up'];
                    if (isset($meta['carpet'])) $slotCarpet[$slot] = (float)$meta['carpet'];
                }

                // Rental-specific fields
                $rental_config = null; $rent = null; $rental_carpet_area = null;
                if ($property_type === 'rental') {
                    $rk = array_keys($configs)[0] ?? null;
                    if ($rk !== null) {
                        $rental_config = $rk;
                        $rent = isset($configs[$rk]['rent']) ? (float)$configs[$rk]['rent'] : null;
                        $rental_carpet_area = isset($configs[$rk]['carpet']) ? (float)$configs[$rk]['carpet'] : null;
                    }
                }

                // Compute min_price and min_rent
                $min_price = null; $min_rent = null;
                foreach ($slotPrice as $p) { if ($p === null) continue; if ($min_price === null || $p < $min_price) $min_price = $p; }
                if ($rent !== null) $min_rent = $rent;
                foreach ($configs as $meta) { if (isset($meta['rent'])) { $r = (float)$meta['rent']; if ($min_rent === null || $r < $min_rent) $min_rent = $r; } }

                // Defensive rental_type default
                if ($rental_type === null) $rental_type = 'rental';

                // Build UPDATE statement (update all the relevant columns)
                $sql = "UPDATE properties SET
                    owner_name = :owner_name,
                    owner_phone = :owner_phone,
                    owner_email = :owner_email,
                    title = :title,
                    property_type = :property_type,
                    city = :city,
                    locality = :locality,
                    address = :address,
                    description = :description,
                    furnishing = :furnishing,
                    amenities = :amenities,
                    latitude = :latitude,
                    longitude = :longitude,
                    config1 = :config1, config2 = :config2, config3 = :config3,
                    config4 = :config4, config5 = :config5, config6 = :config6,
                    price1 = :price1, price2 = :price2, price3 = :price3,
                    price4 = :price4, price5 = :price5, price6 = :price6,
                    builtup_area1 = :builtup_area1, builtup_area2 = :builtup_area2, builtup_area3 = :builtup_area3,
                    builtup_area4 = :builtup_area4, builtup_area5 = :builtup_area5, builtup_area6 = :builtup_area6,
                    carpet_area1 = :carpet_area1, carpet_area2 = :carpet_area2, carpet_area3 = :carpet_area3,
                    carpet_area4 = :carpet_area4, carpet_area5 = :carpet_area5, carpet_area6 = :carpet_area6,
                    rental_config = :rental_config,
                    rent = :rent,
                    rental_carpet_area = :rental_carpet_area,
                    rental_type = :rental_type,
                    img1 = :img1, img2 = :img2, img3 = :img3, img4 = :img4,
                    min_price = :min_price, min_rent = :min_rent,
                    updated_at = NOW()
                    WHERE id = :id AND seller_id = :seller_id
                ";

                $stmt = $pdo->prepare($sql);

                $bind = [
                    ':owner_name' => $owner_name, ':owner_phone' => $owner_phone, ':owner_email' => $owner_email,
                    ':title' => $title, ':property_type' => $property_type, ':city' => $city, ':locality' => $locality,
                    ':address' => $address, ':description' => $description, ':furnishing' => $furnishing, ':amenities' => $amenities,
                    ':latitude' => $latitude !== '' ? (float)$latitude : null, ':longitude' => $longitude !== '' ? (float)$longitude : null,
                    ':config1' => $slotConfig[1], ':config2' => $slotConfig[2], ':config3' => $slotConfig[3],
                    ':config4' => $slotConfig[4], ':config5' => $slotConfig[5], ':config6' => $slotConfig[6],
                    ':price1' => $slotPrice[1], ':price2' => $slotPrice[2], ':price3' => $slotPrice[3],
                    ':price4' => $slotPrice[4], ':price5' => $slotPrice[5], ':price6' => $slotPrice[6],
                    ':builtup_area1' => $slotBuilt[1], ':builtup_area2' => $slotBuilt[2], ':builtup_area3' => $slotBuilt[3],
                    ':builtup_area4' => $slotBuilt[4], ':builtup_area5' => $slotBuilt[5], ':builtup_area6' => $slotBuilt[6],
                    ':carpet_area1' => $slotCarpet[1], ':carpet_area2' => $slotCarpet[2], ':carpet_area3' => $slotCarpet[3],
                    ':carpet_area4' => $slotCarpet[4], ':carpet_area5' => $slotCarpet[5], ':carpet_area6' => $slotCarpet[6],
                    ':rental_config' => $rental_config, ':rent' => $rent, ':rental_carpet_area' => $rental_carpet_area,
                    ':rental_type' => $rental_type,
                    ':img1' => $savedImages['img1'] ?? null, ':img2' => $savedImages['img2'] ?? null,
                    ':img3' => $savedImages['img3'] ?? null, ':img4' => $savedImages['img4'] ?? null,
                    ':min_price' => $min_price, ':min_rent' => $min_rent,
                    ':id' => $id, ':seller_id' => $seller_id
                ];

                $stmt->execute($bind);
                $pdo->commit();

                $_SESSION['flash_success'] = 'Property updated successfully.';
                header('Location: seller_index.php');
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// --- Render template (same template path as add_property) ---
?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8"/>
  <title>Edit Property</title>

  <!-- apply saved theme early to avoid flash -->
  <script>
    (function(){
      try {
        if (localStorage.getItem('hr_theme') === 'dark') document.documentElement.classList.add('dark');
      } catch(e){}
    })();
  </script>

  <link rel="stylesheet" href="../assets/css/index.css" />
  <link rel="stylesheet" href="../assets/css/seller_property_form_template.css" />
</head>
<body>

<div class="top-bar" style="display:flex;justify-content:flex-end;margin-bottom:10px;gap:12px;">
  <a href="seller_index.php" class="btn add-btn" style="padding:8px 16px;border-radius:6px;color:white;text-decoration:none;display:inline-block;background:#007BFF;">← Back to Dashboard</a>
</div>

<h2 style="text-align:center;color:var(--text);margin-bottom:20px;">Edit Property</h2>

<?php
// NOTE: your template file path — keep this unchanged as requested.
$template = __DIR__ . '/seller_property_form_template.php';
if (!file_exists($template)) {
    echo "<div style='color:#b00020;'>Missing template: " . htmlspecialchars($template, ENT_QUOTES) . "</div>";
} else {
    // the template expects $prop, $errors, $success, $action, $submitLabel
    $action = $_SERVER['PHP_SELF'] . '?id=' . (int)$id;
    $submitLabel = 'Update Property';
    $success = $_SESSION['flash_success'] ?? null;
    if ($success) unset($_SESSION['flash_success']);
    include $template;
}
?>

</body>
</html>
