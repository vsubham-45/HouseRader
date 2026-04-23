<?php
// public/fake_gateway_process.php
declare(strict_types=1);

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/db.php';

// ---- Seller guard ----
$seller = $_SESSION['seller'] ?? null;
if (!$seller || empty($seller['id'])) {
    header('Location: user/login.php');
    exit;
}

// ---- Helper ----
function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---- Input ----
$payment_id = (int)($_POST['payment_id'] ?? 0);
$method = $_POST['method'] ?? 'upi';
$upi_id = trim($_POST['upi_id'] ?? '');

if ($payment_id <= 0) {
    http_response_code(400);
    exit('Invalid payment reference');
}

// ---- Fetch payment ----
$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment || $payment['status'] !== 'initiated') {
    http_response_code(404);
    exit('Payment not available');
}

// ---- Fake transaction ----
$fake_txn_id = 'FAKE-' . strtoupper(bin2hex(random_bytes(4)));

// ---- Prepare webhook payload ----
$meta = json_decode((string)$payment['metadata'], true) ?: [];
$meta['method'] = $method;
$meta['upi_id'] = $upi_id ?: null;
$meta['dummy_gateway'] = true;

$payload = [
    'payment_id'      => $payment_id,
    'provider_txn_id' => $fake_txn_id,
    'status'          => 'succeeded',
    'metadata'        => $meta
];

// ---- Call webhook (server-to-server) ----
$webhookUrl =
    (isset($_SERVER['HTTPS']) ? 'https' : 'http') .
    '://' . $_SERVER['HTTP_HOST'] .
    '/HouseRader/api/feature_webhook.php';

require_once __DIR__ . '/../src/feature_processor.php';

process_feature_payment($pdo, $payload);


// ---- Redirect ----
$redirect = 'seller/seller_property_details.php?id=' . (int)$payment['property_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Processing Payment • HouseRader</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/fake_gateway_process.css">
<meta http-equiv="refresh" content="3;url=<?= h($redirect) ?>">
</head>

<body>
  <div class="process-box">
    <div class="spinner"></div>
    <h2>Processing payment…</h2>
    <p class="muted">Please wait while we confirm your payment</p>
    <div class="txn">
      Transaction ID: <strong><?= h($fake_txn_id) ?></strong>
    </div>
  </div>
</body>
</html>
