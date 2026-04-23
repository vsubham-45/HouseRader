<?php
// admin/properties.php
declare(strict_types=1);

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/db.php';

// ---- Admin guard ----
if (empty($_SESSION['admin']['id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin = $_SESSION['admin'];

// ---- Status filter ----
$allowed = ['pending', 'live', 'rejected'];
$status = $_GET['status'] ?? 'pending';
if (!in_array($status, $allowed, true)) {
    $status = 'pending';
}

// ---- Fetch properties ----
$stmt = $pdo->prepare("
    SELECT id, title, city, locality, img1, created_at
    FROM properties
    WHERE status = :status
    ORDER BY created_at DESC
");
$stmt->execute([':status' => $status]);
$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Escaper ----
function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/**
 * FINAL image resolver
 * Images LIVE in: /HouseRader/public/assets/img/
 * Browser MUST request that exact path.
 */
function property_img(?string $img): string
{
    // Hard truth: this is the real public path
    $base = '/HouseRader/public/assets/img/';

    if (!$img) {
        return $base . 'placeholder.jpg';
    }

    // If DB accidentally contains path, strip it
    $img = basename($img);

    return $base . $img;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Properties • HouseRader</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="../admin/css/admin_index.css">
<link rel="stylesheet" href="../admin/css/admin_properties.css">
</head>

<body>

<header class="hr-navbar">
  <div class="brand">
    🏠HouseRader <span class="admin-badge">ADMIN</span>
  </div>

  <div class="nav-right">
    <a href="admin_dashboard.php" class="btn-secondary">Dashboard</a>
    <div class="admin-name"><?= e($admin['name']) ?></div>
    <a href="admin_logout.php" class="btn-logout">Logout</a>
  </div>
</header>

<main class="container admin-properties">

<header class="page-header">
  <h1><?= ucfirst($status) ?> Properties</h1>

  <div class="status-tabs">
    <a class="<?= $status === 'pending' ? 'active' : '' ?>" href="?status=pending">Pending</a>
    <a class="<?= $status === 'live' ? 'active' : '' ?>" href="?status=live">Live</a>
    <a class="<?= $status === 'rejected' ? 'active' : '' ?>" href="?status=rejected">Rejected</a>
  </div>
</header>

<?php if (!$properties): ?>
  <div class="empty-state">
    No <?= e($status) ?> properties found.
  </div>
<?php endif; ?>

<div class="property-grid">
<?php foreach ($properties as $p): ?>
  <div class="property-card">

    <div class="property-img"
         style="background-image:url('<?= e(property_img($p['img1'])) ?>')">
    </div>

    <div class="property-body">
      <div class="property-title"><?= e($p['title']) ?></div>
      <div class="property-meta"><?= e($p['locality']) ?>, <?= e($p['city']) ?></div>

      <div class="property-actions">
        <a href="admin_property_details.php?id=<?= (int)$p['id'] ?>" class="btn-view">View</a>

        <?php if ($status === 'pending'): ?>
          <a href="property_approve.php?id=<?= (int)$p['id'] ?>" class="btn-approve">Approve</a>
          <a href="property_reject.php?id=<?= (int)$p['id'] ?>" class="btn-reject">Reject</a>
        <?php endif; ?>
      </div>
    </div>

  </div>
<?php endforeach; ?>
</div>

</main>

<footer class="hr-footer">
  Admin Panel • HouseRader
</footer>

</body>
</html>
