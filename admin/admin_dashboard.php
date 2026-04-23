<?php
// admin/admin_dashboard.php

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/db.php';

/* ---------- Admin Guard ---------- */
if (empty($_SESSION['admin']['id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin = $_SESSION['admin'];

/* ---------- Helpers ---------- */
function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/**
 * Resolve property image safely (ABSOLUTE PATH)
 * Mirrors logic used in admin/properties.php
 */
function property_img(?string $img): string
{
    $base = '/HouseRader/public/assets/img/';

    if (!$img) {
        return $base . 'placeholder.jpg';
    }

    // if DB already has assets/img/...
    if (str_starts_with($img, 'assets/')) {
        return '/HouseRader/public/' . ltrim($img, '/');
    }

    // if DB already has public/assets/img/...
    if (str_starts_with($img, 'public/assets/')) {
        return '/HouseRader/' . ltrim($img, '/');
    }

    // filename only
    return $base . ltrim($img, '/');
}


/* ---------- Stats ---------- */
$stats = [
    'total_properties' => (int)$pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn(),
    'pending'          => (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status='pending'")->fetchColumn(),
    'live'             => (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status='live'")->fetchColumn(),
    'rejected'         => (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status='rejected'")->fetchColumn(),
    'users'            => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'sellers'          => (int)$pdo->query("SELECT COUNT(*) FROM sellers")->fetchColumn(),
];

/* ---------- Recent Pending Properties ---------- */
$stmt = $pdo->prepare("
    SELECT id, title, city, locality, created_at, img1
    FROM properties
    WHERE status = 'pending'
    ORDER BY created_at DESC
    LIMIT 8
");
$stmt->execute();
$pending_properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Recent Admin Actions ---------- */
$logs = $pdo->prepare("
    SELECT action_type, target_table, target_id, created_at
    FROM admin_actions
    WHERE admin_user_id = ?
    ORDER BY created_at DESC
    LIMIT 6
");
$logs->execute([$admin['id']]);
$recent_logs = $logs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Dashboard • HouseRader</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="../admin/css/admin_index.css">
<link rel="stylesheet" href="../admin/css/admin_dashboard.css">
</head>

<body>

<header class="hr-navbar">
  <div class="brand">🏠HouseRadar <span class="admin-badge">ADMIN</span></div>
  <div class="nav-right">
    <a href="admin_actions_log.php" class="btn-secondary">Action Logs</a>
    <div class="admin-name"><?= e($admin['name'] ?? 'Admin') ?></div>
    <a href="admin_logout.php" class="btn-logout">Logout</a>
  </div>
</header>

<main class="container admin-dashboard">

<!-- ===== Stats ===== -->
<section class="admin-stats">
  <div class="stat-card"><div class="stat-label">Total Properties</div><div class="stat-value"><?= $stats['total_properties'] ?></div></div>
  <div class="stat-card pending"><div class="stat-label">Pending</div><div class="stat-value"><?= $stats['pending'] ?></div></div>
  <div class="stat-card live"><div class="stat-label">Live</div><div class="stat-value"><?= $stats['live'] ?></div></div>
  <div class="stat-card rejected"><div class="stat-label">Rejected</div><div class="stat-value"><?= $stats['rejected'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Users</div><div class="stat-value"><?= $stats['users'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Sellers</div><div class="stat-value"><?= $stats['sellers'] ?></div></div>
</section>

<!-- ===== Pending Properties ===== -->
<section class="admin-section">
  <div class="section-header">
    <h2>Pending Properties</h2>
    <a href="properties.php?status=pending" class="section-link">View all</a>
  </div>

  <div class="pending-grid">
    <?php if (!$pending_properties): ?>
      <div class="empty-state">No pending properties 🎉</div>
    <?php endif; ?>

    <?php foreach ($pending_properties as $p): ?>
      <div class="pending-card">
        <div class="pending-img"
             style="background-image:url('<?= property_img($p['img1']) ?>')">
        </div>

        <div class="pending-body">
          <div class="pending-title"><?= e($p['title']) ?></div>
          <div class="pending-meta"><?= e($p['locality'] . ', ' . $p['city']) ?></div>

          <div class="pending-actions">
            <a href="admin_property_details.php?id=<?= (int)$p['id'] ?>" class="btn-view">Review</a>
            <a href="property_approve.php?id=<?= (int)$p['id'] ?>" class="btn-approve">Approve</a>
            <a href="property_reject.php?id=<?= (int)$p['id'] ?>" class="btn-reject">Reject</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ===== Recent Actions ===== -->
<section class="admin-section">
  <div class="section-header"><h2>Your Recent Actions</h2></div>

  <ul class="activity-list">
    <?php if (!$recent_logs): ?>
      <li class="activity-item muted">No recent actions</li>
    <?php endif; ?>

    <?php foreach ($recent_logs as $log): ?>
      <li class="activity-item">
        <span class="activity-type"><?= e($log['action_type']) ?></span>
        <span class="activity-meta"><?= e($log['target_table']) ?> #<?= (int)$log['target_id'] ?></span>
        <span class="activity-time"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

</main>

<footer class="hr-footer">Admin Panel • HouseRader</footer>
</body>
</html>
