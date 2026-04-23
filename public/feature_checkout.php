<?php
// public/feature_checkout.php
declare(strict_types=1);

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Razorpay\Api\Api;

/* ===============================
   🔐 RAZORPAY TEST KEYS
   =============================== */
$key_id = 'rzp_test_SIT0Qo1MijTLeJ';
$key_secret = 'C8xhHqdfp5lb2D8Bf6Rupcmr';
$api = new Api($key_id, $key_secret);

/* ===============================
   Seller Guard
   =============================== */
$seller = $_SESSION['seller'] ?? null;
if (!$seller || empty($seller['id'])) {
    header('Location: user/login.php');
    exit;
}
$seller_id = (int)$seller['id'];

/* ===============================
   Helper
   =============================== */
function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ===============================
   CSRF
   =============================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(18));
}
$csrf = $_SESSION['csrf_token'];

/* ===============================
   Plans
   =============================== */
$plans = [
    1 => ['title'=>'Boost','days'=>7,'price'=>1499,'desc'=>'7 days • higher placement','priority'=>1],
    2 => ['title'=>'Premium Spotlight','days'=>30,'price'=>3999,'desc'=>'30 days • top carousel placement','priority'=>5],
    3 => ['title'=>'Elite Advantage','days'=>60,'price'=>7999,'desc'=>'60 days • top priority boost','priority'=>10],
];

/* ===============================
   Property Validation
   =============================== */
$property_id = (int)($_GET['property_id'] ?? $_POST['property_id'] ?? 0);
if ($property_id <= 0) {
    http_response_code(400);
    exit('Invalid property ID');
}

$stmt = $pdo->prepare("SELECT id,title,seller_id FROM properties WHERE id=:id LIMIT 1");
$stmt->execute([':id'=>$property_id]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$property || (int)$property['seller_id'] !== $seller_id) {
    http_response_code(403);
    exit('Unauthorized');
}

/* ===============================
   UI State
   =============================== */
$step = 'select';
$message = null;
$payment_id = null;
$order_id = null;
$amount_paise = null;

/* ===============================
   POST: Create Payment + Razorpay Order
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token.';
    } else {

        $tier = (int)($_POST['tier'] ?? 0);

        if (!isset($plans[$tier])) {
            $message = 'Invalid plan selected.';
        } else {

            $plan = $plans[$tier];
            $expires_at = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                INSERT INTO payments
                (property_id, payer_id, payer_role, provider, amount, currency, status, expires_at, metadata)
                VALUES (:pid, :payer_id, :payer_role, :prov, :amt, 'INR', 'initiated', :exp, :meta)
            ");

            $stmt->execute([
                ':pid'        => $property_id,
                ':payer_id'   => $seller_id,
                ':payer_role' => 'seller',
                ':prov'       => 'razorpay',
                ':amt'        => $plan['price'],
                ':exp'        => $expires_at,
                ':meta'       => json_encode([
                    'tier'=>$tier,
                    'days'=>$plan['days'],
                    'priority'=>$plan['priority']
                ])
            ]);

            $payment_id = (int)$pdo->lastInsertId();

            $amount_paise = (int)($plan['price'] * 100);

            $order = $api->order->create([
                'receipt'  => 'feat_'.$payment_id,
                'amount'   => $amount_paise,
                'currency' => 'INR'
            ]);

            $order_id = $order['id'];

            $pdo->prepare("
                UPDATE payments
                SET provider_txn_id = :oid
                WHERE id = :id
            ")->execute([
                ':oid'=>$order_id,
                ':id'=>$payment_id
            ]);

            $step = 'gateway';
        }
    }
}

$prop_title = h($property['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Feature Checkout • <?= $prop_title ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/index.css">
<link rel="stylesheet" href="assets/css/feature_checkout.css">
</head>

<body>

<header class="hr-navbar">
  <div class="container">
    <a href="index.php" class="brand">🏠 HouseRader</a>
  </div>
</header>

<main class="container fc-container">
  <div class="fc-panel">

    <h1>Feature Listing</h1>
    <div class="muted"><?= $prop_title ?></div>

    <?php if ($message): ?>
      <div class="fc-msg fc-error"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($step === 'select'): ?>

      <div class="plans">
        <?php foreach ($plans as $id => $p): ?>
          <div class="plan-card">
            <div>
              <div class="plan-title"><?= h($p['title']) ?></div>
              <div class="muted"><?= h($p['desc']) ?></div>
            </div>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
              <input type="hidden" name="tier" value="<?= $id ?>">
              <input type="hidden" name="property_id" value="<?= $property_id ?>">
              <button class="btn-primary">
                Continue • ₹<?= number_format($p['price']) ?>
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>

    <?php elseif ($step === 'gateway'): ?>

      <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

      <script>
      var options = {
          key: "<?= $key_id ?>",
          amount: "<?= $amount_paise ?>",
          currency: "INR",
          name: "HouseRader",
          description: "Feature Listing",
          order_id: "<?= $order_id ?>",

          handler: function (response) {

              fetch("/HouseRader/api/verify_payment.php", {
                  method: "POST",
                  headers: {"Content-Type": "application/json"},
                  body: JSON.stringify({
                      payment_id: <?= $payment_id ?>,
                      razorpay_payment_id: response.razorpay_payment_id,
                      razorpay_order_id: response.razorpay_order_id,
                      razorpay_signature: response.razorpay_signature
                  })
              })
              .then(res => res.json())
              .then(data => {

                  if (data.ok) {
                      window.location.href =
                          "seller/seller_index.php?featured=success";
                  } else {
                      alert("Payment verification failed: " + data.message);
                      window.location.href =
                          "seller/seller_index.php?featured=failed";
                  }

              });

          },

          modal: {
              ondismiss: function() {
                  window.location.href =
                      "seller/seller_index.php?featured=cancelled";
              }
          }
      };

      var rzp = new Razorpay(options);
      rzp.open();
      </script>

    <?php endif; ?>

  </div>
</main>

</body>
</html>