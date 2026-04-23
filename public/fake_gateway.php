<?php
// public/fake_gateway.php
declare(strict_types=1);

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/db.php';

// ---- Seller guard ----
$who = hr_is_logged_in();
if (!$who || $who['role'] !== 'seller') {
    header('Location: user/login.php');
    exit;
}


// ---- Helpers ----
function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---- Validate payment ----
$payment_id = (int)($_GET['payment_id'] ?? 0);
$method = $_GET['method'] ?? 'upi';

if ($payment_id <= 0) {
    http_response_code(400);
    exit('Invalid payment reference');
}

$stmt = $pdo->prepare("
    SELECT p.*, pr.title AS property_title
    FROM payments p
    JOIN properties pr ON pr.id = p.property_id
    WHERE p.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment || $payment['status'] !== 'initiated') {
    http_response_code(404);
    exit('Payment not found or already processed');
}

$amount = number_format((float)$payment['amount'], 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Secure Payment • HouseRadar</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="assets/css/index.css">
<link rel="stylesheet" href="assets/css/fake_gateway.css">
</head>

<body>

<header class="hr-navbar">
  <div class="container">
    <a href="index.php" class="brand">🏠 HouseRadar</a>
  </div>
</header>

<main class="gateway-wrap">
  <div class="gateway-box">

    <div class="gateway-header">
      <div class="muted">Paying for</div>
      <strong><?= h($payment['property_title']) ?></strong>
      <div class="gateway-amount">₹<?= $amount ?></div>
    </div>

    <form method="post" action="fake_gateway_process.php">
      <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
      <input type="hidden" name="method" id="methodInput" value="<?= h($method) ?>">

      <!-- Tabs -->
      <div class="method-tabs">
        <div class="method-tab <?= $method==='upi'?'active':'' ?>" data-method="upi">UPI</div>
        <div class="method-tab <?= $method==='card'?'active':'' ?>" data-method="card">Card</div>
        <div class="method-tab <?= $method==='netbanking'?'active':'' ?>" data-method="netbanking">Net Banking</div>
      </div>

      <!-- UPI -->
      <div class="method-panel <?= $method==='upi'?'active':'' ?>" id="upi">
        <div class="field">
          <label>UPI ID</label>
          <input type="text" name="upi_id" placeholder="name@upi">
        </div>
      </div>

      <!-- Card -->
      <div class="method-panel <?= $method==='card'?'active':'' ?>" id="card">
        <div class="field">
          <label>Card Number</label>
          <input type="text" placeholder="XXXX XXXX XXXX XXXX" disabled>
        </div>
        <div class="field row">
          <input type="text" placeholder="MM / YY" disabled>
          <input type="password" placeholder="CVV" disabled>
        </div>
        <div class="muted">Card payments are simulated</div>
      </div>

      <!-- Net Banking -->
      <div class="method-panel <?= $method==='netbanking'?'active':'' ?>" id="netbanking">
        <div class="field">
          <label>Select Bank</label>
          <select disabled>
            <option>SBI</option>
            <option>HDFC</option>
            <option>ICICI</option>
            <option>Axis</option>
          </select>
        </div>
        <div class="muted">Bank redirect is simulated</div>
      </div>

      <button class="btn-primary pay-btn">Pay ₹<?= $amount ?></button>
    </form>

  </div>
</main>

<script>
const tabs = document.querySelectorAll('.method-tab');
const panels = document.querySelectorAll('.method-panel');
const methodInput = document.getElementById('methodInput');

tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    const method = tab.dataset.method;

    tabs.forEach(t => t.classList.remove('active'));
    panels.forEach(p => p.classList.remove('active'));

    tab.classList.add('active');
    document.getElementById(method).classList.add('active');
    methodInput.value = method;
  });
});
</script>

</body>
</html>
