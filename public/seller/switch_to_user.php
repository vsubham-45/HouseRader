<?php
// public/seller/switch_to_user.php
// Switch seller session to user session (create user account if none exists)

require_once __DIR__ . '/../../src/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../src/db.php';

// Must be logged in as seller
if (empty($_SESSION['seller'])) {
    header("Location: ../user/login.php");
    exit;
}

$seller = $_SESSION['seller'];
$email = $seller['email'] ?? null;

if (!$email) {
    $_SESSION['flash'] = 'Cannot switch: invalid seller account.';
    $_SESSION['flash_type'] = 'error';
    header('Location: seller_index.php');
    exit;
}

try {
    // Does a user account already exist?
    $stmt = $pdo->prepare("SELECT id, name, avatar FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userRow) {
        // just switch to that user session
        $_SESSION['user'] = [
            'id' => $userRow['id'],
            'name' => $userRow['name'],
            'email' => $email,
            'avatar' => $userRow['avatar'] ?? null
        ];
    } else {
        // Create a user record reusing seller's name/phone/password_hash
        // Fetch seller password_hash and avatar
        $s = $pdo->prepare("SELECT password_hash, name, phone, avatar FROM sellers WHERE email = :email LIMIT 1");
        $s->execute([':email' => $email]);
        $sellerData = $s->fetch(PDO::FETCH_ASSOC);

        // Insert into users (reuse password_hash)
        $ins = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, avatar, created_at) VALUES (:name, :email, :phone, :ph, :avatar, NOW())");
        $ins->execute([
            ':name' => $sellerData['name'] ?? ($seller['name'] ?? 'Seller'),
            ':email' => $email,
            ':phone' => $sellerData['phone'] ?? null,
            ':ph' => $sellerData['password_hash'] ?? null,
            ':avatar' => $sellerData['avatar'] ?? null
        ]);

        $newId = (int)$pdo->lastInsertId();
        $_SESSION['user'] = [
            'id' => $newId,
            'name' => $sellerData['name'] ?? ($seller['name'] ?? 'Seller'),
            'email' => $email,
            'avatar' => $sellerData['avatar'] ?? null
        ];
    }

    // Remove seller session (switch)
    unset($_SESSION['seller']);
    if (function_exists('hr_regenerate_session')) hr_regenerate_session();
    else session_regenerate_id(true);

    $_SESSION['flash'] = 'Switched to buyer mode.';
    $_SESSION['flash_type'] = 'success';
    header('Location: ../index.php');
    exit;

} catch (Exception $e) {
    error_log("switch_to_user failed: " . $e->getMessage());
    $_SESSION['flash'] = 'Switch failed. Try again later.';
    $_SESSION['flash_type'] = 'error';
    header('Location: seller_index.php');
    exit;
}
