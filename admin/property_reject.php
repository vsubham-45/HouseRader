<?php
// admin/property_reject.php
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
$reason = trim($_POST['reason'] ?? '');

if ($propertyId <= 0) {
    header('Location: properties.php?status=pending');
    exit;
}

// ---- Reject property ----
$pdo->beginTransaction();

try {
    // Update property status
    $stmt = $pdo->prepare("
        UPDATE properties
        SET status = 'rejected'
        WHERE id = :id AND status IN ('pending','live')
    ");
    $stmt->execute([':id' => $propertyId]);

    // Log admin action
    $log = $pdo->prepare("
        INSERT INTO admin_actions
            (admin_user_id, action_type, target_table, target_id, details, created_at)
        VALUES
            (:admin, 'reject', 'properties', :pid, :details, NOW())
    ");
    $log->execute([
        ':admin'   => $adminId,
        ':pid'     => $propertyId,
        ':details' => json_encode([
            'property_id' => $propertyId,
            'reason'      => $reason
        ])
    ]);

    $pdo->commit();

} catch (Throwable $e) {
    $pdo->rollBack();
}

header('Location: properties.php?status=pending');
exit;
