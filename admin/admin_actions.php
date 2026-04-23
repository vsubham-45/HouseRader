<?php
// admin/admin_actions.php
declare(strict_types=1);

// Centralized admin actions handler (status toggle, feature toggle, delete property)

require_once __DIR__ . '/../src/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db_file = __DIR__ . '/../src/db.php';
if (!file_exists($db_file)) { http_response_code(500); echo "Missing DB helper"; exit; }
require_once $db_file; // provides $pdo

// ---------- admin guard ----------
$admin_ok = false;
if (!empty($_SESSION['admin'])) $admin_ok = true;
if (!empty($_SESSION['user']) && is_array($_SESSION['user']) && (isset($_SESSION['user']['is_admin']) && (int)$_SESSION['user']['is_admin'] === 1)) $admin_ok = true;
if (!$admin_ok) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

// ---------- CSRF ----------
if (!isset($_SESSION['admin_csrf']) || empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}
$token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (!hash_equals((string)($_SESSION['admin_csrf'] ?? ''), (string)$token)) {
    // prefer redirect back with message if referer exists
    $_SESSION['admin_flash_error'] = 'Invalid CSRF token.';
    $back = $_SERVER['HTTP_REFERER'] ?? 'properties_list.php';
    header('Location: ' . $back);
    exit;
}

// ---------- helpers ----------
function flash_success(string $msg) { $_SESSION['admin_flash_success'] = $msg; }
function flash_error(string $msg) { $_SESSION['admin_flash_error'] = $msg; }

/**
 * Resolve safe filesystem path for an image path stored in DB.
 * Only returns a path under public/assets/img or public/uploads/attachments.
 * Returns null for external URLs or unsafe paths.
 */
function resolve_local_image_path(string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') return null;
    // External URLs -> ignore
    if (preg_match('#^https?://#i', $raw) || strpos($raw, '//') === 0) return null;
    // Site-root absolute ("/public/assets/img/...") or relative forms
    $projectRoot = dirname(__DIR__); // admin/.. => project root
    $candidates = [];

    // Normalize common variants found in DB
    $rawNormalized = ltrim(str_replace('\\', '/', $raw), '/');

    // common public assets path
    $candidates[] = $projectRoot . '/public/' . $rawNormalized;
    $candidates[] = $projectRoot . '/public/assets/img/' . basename($rawNormalized);
    $candidates[] = $projectRoot . '/public/uploads/attachments/' . basename($rawNormalized);
    $candidates[] = $projectRoot . '/public/assets/img/' . $rawNormalized;
    $candidates[] = $projectRoot . '/assets/img/' . basename($rawNormalized);

    foreach ($candidates as $path) {
        $path = str_replace(['//','\\'], ['/','/'], $path);
        if (file_exists($path) && is_file($path)) {
            // final safety check: ensure path is inside public/assets/img or public/uploads/attachments
            $allowed1 = realpath($projectRoot . '/public/assets/img');
            $allowed2 = realpath($projectRoot . '/public/uploads/attachments');
            $real = realpath($path);
            if ($real !== false && $allowed1 !== false && str_starts_with($real, $allowed1)) return $real;
            if ($real !== false && $allowed2 !== false && str_starts_with($real, $allowed2)) return $real;
        }
    }
    return null;
}

/**
 * Safely remove a local image file if inside allowed dir.
 */
function safe_unlink_image(string $raw): bool {
    $p = resolve_local_image_path($raw);
    if ($p === null) return false;
    // attempt unlink
    try {
        return @unlink($p);
    } catch (Throwable $e) {
        return false;
    }
}

// ---------- input ----------
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$property_id = isset($_POST['property_id']) ? (int)$_POST['property_id'] : (isset($_GET['property_id']) ? (int)$_GET['property_id'] : 0);
if ($property_id <= 0) {
    flash_error('Invalid property id.');
    $back = $_SERVER['HTTP_REFERER'] ?? 'properties_list.php';
    header('Location: ' . $back);
    exit;
}

// fetch property row (to read current state and image paths)
try {
    $stmt = $pdo->prepare('SELECT * FROM properties WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $property_id]);
    $prop = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    flash_error('Database error: ' . $e->getMessage());
    $back = $_SERVER['HTTP_REFERER'] ?? 'properties_list.php';
    header('Location: ' . $back);
    exit;
}
if (!$prop) {
    flash_error('Property not found.');
    $back = $_SERVER['HTTP_REFERER'] ?? 'properties_list.php';
    header('Location: ' . $back);
    exit;
}

// ---------- perform actions ----------
try {
    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        $allowed = ['pending','live','rejected'];
        if (!in_array($newStatus, $allowed, true)) {
            throw new RuntimeException('Invalid status value.');
        }
        $pdo->beginTransaction();
        $up = $pdo->prepare("UPDATE properties SET status = :status, updated_at = NOW() WHERE id = :id");
        $up->execute([':status' => $newStatus, ':id' => $property_id]);
        $pdo->commit();
        flash_success("Status updated to '{$newStatus}'.");
        header('Location: admin_property_details.php?id=' . urlencode((string)$property_id));
        exit;
    }

    if ($action === 'toggle_feature') {
    $current = (int)($prop['is_featured'] ?? 0);

    $pdo->beginTransaction();

    if ($current === 1) {
        // ONLY disable featured flag
        $q = $pdo->prepare("
            UPDATE properties
            SET is_featured = 0,
                updated_at = NOW()
            WHERE id = :id
        ");
        $q->execute([':id' => $property_id]);
        flash_success('Featured flag removed.');
    } else {
        // ONLY enable featured flag (do not touch priority or expiry)
        $q = $pdo->prepare("
            UPDATE properties
            SET is_featured = 1,
                updated_at = NOW()
            WHERE id = :id
        ");
        $q->execute([':id' => $property_id]);
        flash_success('Featured flag enabled.');
    }

    $pdo->commit();
    header('Location: admin_property_details.php?id=' . urlencode((string)$property_id));
    exit;
}


    if ($action === 'delete_property') {
        // deletion is destructive: delete messages, conversations, views, payments (best-effort), and property row.
        // We also attempt to remove local image files stored under public/assets/img or uploads/attachments.
        $pdo->beginTransaction();

        // 1) gather conversations for this property (to delete messages)
        $cstmt = $pdo->prepare("SELECT id FROM conversations WHERE property_id = :pid");
        $cstmt->execute([':pid' => $property_id]);
        $convIds = $cstmt->fetchAll(PDO::FETCH_COLUMN, 0);

        if (!empty($convIds)) {
            // delete messages
            $in = implode(',', array_fill(0, count($convIds), '?'));
            $mstmt = $pdo->prepare("DELETE FROM messages WHERE conversation_id IN ($in)");
            $mstmt->execute($convIds);
            // delete conversations
            $dconv = $pdo->prepare("DELETE FROM conversations WHERE id IN ($in)");
            $dconv->execute($convIds);
        }

        // 2) delete property_views entries
        $v = $pdo->prepare("DELETE FROM property_views WHERE property_id = :pid");
        $v->execute([':pid' => $property_id]);

        // 3) delete payments / features / related tables (best-effort if such tables exist)
        try {
            $pay = $pdo->prepare("DELETE FROM payments WHERE property_id = :pid");
            $pay->execute([':pid' => $property_id]);
        } catch (Throwable $ignore) {
            // table might not exist - ignore
        }

        // 4) attempt to delete images from filesystem (img1..img4)
        for ($i = 1; $i <= 4; $i++) {
            $k = 'img' . $i;
            if (!empty($prop[$k])) {
                safe_unlink_image($prop[$k]);
            }
        }

        // 5) finally delete property row
        $del = $pdo->prepare("DELETE FROM properties WHERE id = :id LIMIT 1");
        $del->execute([':id' => $property_id]);

        $pdo->commit();

        flash_success('Property and related data deleted successfully.');
        header('Location: properties_list.php');
        exit;
    }

    // Unknown action
    flash_error('Unknown action: ' . htmlspecialchars((string)$action, ENT_QUOTES));
    $back = $_SERVER['HTTP_REFERER'] ?? 'properties_list.php';
    header('Location: ' . $back);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_error('Action failed: ' . $e->getMessage());
    $back = $_SERVER['HTTP_REFERER'] ?? 'properties_list.php';
    header('Location: ' . $back);
    exit;
}
