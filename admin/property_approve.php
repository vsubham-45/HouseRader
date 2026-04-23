<?php
// admin/property_approve.php
declare(strict_types=1);

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/db.php';

// ---- Admin guard ----
if (empty($_SESSION['admin']['id'])) {
    header('Location: admin_login.php');
    exit;
}

$adminId = (int)$_SESSION['admin']['id'];
$propertyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($propertyId <= 0) {
    header('Location: properties.php?status=pending');
    exit;
}

// ---- Approve property ----
$pdo->beginTransaction();

try {
    // Update property status
    $stmt = $pdo->prepare("
        UPDATE properties
        SET status = 'live'
        WHERE id = :id AND status = 'pending'
    ");
    $stmt->execute([':id' => $propertyId]);

    // Log admin action
    $log = $pdo->prepare("
        INSERT INTO admin_actions
            (admin_user_id, action_type, target_table, target_id, details, created_at)
        VALUES
            (:admin, 'approve', 'properties', :pid, :details, NOW())
    ");
    $log->execute([
        ':admin'  => $adminId,
        ':pid'    => $propertyId,
        ':details'=> json_encode([
            'property_id' => $propertyId
        ])
    ]);

    $pdo->commit();

} catch (Throwable $e) {
    $pdo->rollBack();
    // Optional: log error somewhere
}

header('Location: properties.php?status=pending');
exit;
