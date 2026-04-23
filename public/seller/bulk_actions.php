<?php
require_once __DIR__ . '/../../src/session.php';
require_once __DIR__ . '/../../src/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// =========================
// 🔒 AUTH CHECK
// =========================
if (empty($_SESSION['seller'])) {
    header("Location: ../user/login.php");
    exit;
}

$sellerId = (int)$_SESSION['seller']['id'];

// =========================
// 🧾 INPUT VALIDATION
// =========================
$action = $_POST['action'] ?? '';
$propertyId = (int)($_POST['property_id'] ?? 0);

$allowedActions = ['unlist', 'relist'];

if (!$propertyId || !in_array($action, $allowedActions, true)) {
    die("Invalid request");
}

try {

    // =========================
    // 🔐 OWNERSHIP CHECK
    // =========================
    $stmt = $pdo->prepare("
        SELECT status 
        FROM properties 
        WHERE id = :id AND seller_id = :seller_id 
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $propertyId,
        ':seller_id' => $sellerId
    ]);

    $property = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$property) {
        die("Unauthorized");
    }

    $currentStatus = $property['status'];

    // =========================
    // 🔴 UNLIST
    // =========================
    if ($action === 'unlist') {

        // prevent useless query
        if ($currentStatus !== 'inactive') {
            $stmt = $pdo->prepare("
                UPDATE properties 
                SET status = 'inactive'
                WHERE id = :id
            ");
            $stmt->execute([':id' => $propertyId]);
        }
    }

    // =========================
    // 🟢 RELIST
    // =========================
    if ($action === 'relist') {

        // only allow relist if not rejected
        if ($currentStatus === 'inactive' || $currentStatus === 'live') {
            $stmt = $pdo->prepare("
                UPDATE properties 
                SET status = 'live'
                WHERE id = :id
            ");
            $stmt->execute([':id' => $propertyId]);
        } else {
            // pending/rejected cannot be relisted
            die("This property cannot be relisted");
        }
    }

} catch (Exception $e) {
    error_log("Bulk action error: " . $e->getMessage());
    die("Something went wrong");
}

// =========================
// 🔁 REDIRECT BACK
// =========================
header("Location: seller_property_details.php?id=" . $propertyId);
exit;