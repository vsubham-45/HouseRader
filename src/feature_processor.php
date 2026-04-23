<?php
// src/feature_processor.php
declare(strict_types=1);

function process_feature_payment(PDO $pdo, array $payload): bool {

    $payment_id = (int)($payload['payment_id'] ?? 0);
    if ($payment_id <= 0) return false;

    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment || $payment['status'] === 'succeeded') {
        return true;
    }

    $meta = json_decode((string)$payment['metadata'], true) ?: [];
    $days = (int)($meta['days'] ?? 7);
    $priority = (int)($meta['priority'] ?? 1);

    $featured_until = (new DateTime())->modify("+{$days} days")->format('Y-m-d H:i:s');

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE payments
        SET status='succeeded',
            provider_txn_id=:txn
        WHERE id=:id
    ")->execute([
        ':txn' => $payload['provider_txn_id'] ?? 'SIM-' . bin2hex(random_bytes(4)),
        ':id'  => $payment_id
    ]);

    $pdo->prepare("
        UPDATE properties
        SET is_featured=1,
            featured_until=:fu,
            featured_priority=:fp
        WHERE id=:pid
    ")->execute([
        ':fu'  => $featured_until,
        ':fp'  => $priority,
        ':pid' => $payment['property_id']
    ]);

    $pdo->commit();
    return true;
}
