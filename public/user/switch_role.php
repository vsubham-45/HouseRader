<?php
// public/user/switch_role.php
// Switch a logged-in user account into seller mode (create seller if needed)

require_once __DIR__ . '/../../src/session.php';
require_once __DIR__ . '/../../src/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Must be logged in as USER
if (empty($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$email = $user['email'] ?? null;

if (!$email) {
    $_SESSION['flash'] = 'Unable to switch role: invalid user session.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ../user/profile.php');
    exit;
}

try {
    // STEP 1 — does a seller account already exist under this email?
    $stmt = $pdo->prepare("
        SELECT id, name, avatar
        FROM sellers
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $sellerRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sellerRow) {
        // Seller account already exists
        $sellerId = (int)$sellerRow['id'];
        $sellerName = $sellerRow['name'];
        $sellerAvatar = $sellerRow['avatar'] ?? null;

    } else {
        // STEP 2 — create a seller account (reuse user's password_hash and avatar if available)
        // Fetch user's password_hash and avatar (to reuse)
        $pwdStmt = $pdo->prepare("SELECT password_hash, avatar FROM users WHERE id = :id LIMIT 1");
        $pwdStmt->execute([':id' => $user['id']]);
        $pwdRow = $pwdStmt->fetch(PDO::FETCH_ASSOC);

        $passwordHash = $pwdRow['password_hash'] ?? null;
        $avatar = $pwdRow['avatar'] ?? ($user['avatar'] ?? null);

        $create = $pdo->prepare("
            INSERT INTO sellers (name, email, phone, password_hash, avatar, created_at)
            VALUES (:name, :email, :phone, :ph, :avatar, NOW())
        ");

        $create->execute([
            ':name'   => $user['name'] ?? ($pwdRow['name'] ?? 'Seller'),
            ':email'  => $email,
            ':phone'  => $user['phone'] ?? null,
            ':ph'     => $passwordHash,
            ':avatar' => $avatar
        ]);

        $sellerId = (int)$pdo->lastInsertId();
        $sellerName = $user['name'] ?? 'Seller';
        $sellerAvatar = $avatar ?? null;
    }

    // STEP 3 — switch session from user → seller
    // Remove any existing user session
    unset($_SESSION['user']);

    // Set seller session with relevant fields (include avatar)
    $_SESSION['seller'] = [
        'id'    => $sellerId,
        'name'  => $sellerName,
        'email' => $email,
        'avatar'=> $sellerAvatar
    ];

    // Regenerate session ID if helper exists (optional but recommended)
    if (function_exists('hr_regenerate_session')) {
        hr_regenerate_session();
    } else {
        // fallback: regenerate session id for extra safety
        session_regenerate_id(true);
    }

    // STEP 4 — success flash and redirect to seller dashboard
    $_SESSION['flash'] = 'Switched to seller mode.';
    $_SESSION['flash_type'] = 'success';
    header("Location: ../seller/seller_index.php");
    exit;

} catch (Exception $e) {
    error_log('switch_role.php error: ' . $e->getMessage());

    // leave user session intact if something failed
    $_SESSION['flash'] = 'Failed to switch to seller mode. Please try again later.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ../user/profile.php');
    exit;
}
