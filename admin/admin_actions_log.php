<?php
// admin/admin_action_logs.php
declare(strict_types=1);

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

function q(string $k, string $default = ''): string {
    return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $default;
}

function qi(string $k, int $default = 0): int {
    return isset($_GET[$k]) ? (int)$_GET[$k] : $default;
}

/* ---------- Filters ---------- */
$filter_action = q('action_type');
$filter_table  = q('target_table');
$filter_q      = q('q');
$filter_from   = q('from');
$filter_to     = q('to');

/* ---------- Pagination ---------- */
$perPage = 25;
$page = max(1, qi('page', 1));
$offset = ($page - 1) * $perPage;

/* ---------- WHERE ---------- */
$where = 'WHERE 1=1';
$params = [];

if ($filter_action !== '') {
    $where .= ' AND a.action_type = :action';
    $params[':action'] = $filter_action;
}
if ($filter_table !== '') {
    $where .= ' AND a.target_table = :table';
    $params[':table'] = $filter_table;
}
if ($filter_q !== '') {
    $where .= ' AND (a.action_type LIKE :q OR a.target_table LIKE :q OR a.details LIKE :q)';
    $params[':q'] = "%{$filter_q}%";
}
if ($filter_from !== '') {
    $where .= ' AND a.created_at >= :from';
    $params[':from'] = date('Y-m-d 00:00:00', strtotime($filter_from));
}
if ($filter_to !== '') {
    $where .= ' AND a.created_at <= :to';
    $params[':to'] = date('Y-m-d 23:59:59', strtotime($filter_to));
}

/* ---------- Count ---------- */
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_actions a {$where}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

/* ---------- CSV Export (UNCHANGED) ---------- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csvStmt = $pdo->prepare("
        SELECT a.*
        FROM admin_actions a {$where}
        ORDER BY a.created_at DESC
        LIMIT 5000
    ");
    $csvStmt->execute($params);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=admin_action_logs.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','admin','action','table','target_id','details','created_at']);
    while ($r = $csvStmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

/* ---------- Data (ENRICHED) ---------- */
$dataStmt = $pdo->prepare("
    SELECT 
        a.*,
        p.title        AS property_title,
        p.seller_id    AS property_seller_id,
        s.name         AS seller_name,
        s.email        AS seller_email
    FROM admin_actions a
    LEFT JOIN properties p 
        ON a.target_table = 'properties' AND a.target_id = p.id
    LEFT JOIN sellers s 
        ON p.seller_id = s.id
    {$where}
    ORDER BY a.created_at DESC
    LIMIT :limit OFFSET :offset
");

foreach ($params as $k => $v) {
    $dataStmt->bindValue($k, $v);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Filters Data ---------- */
$actions = $pdo->query("SELECT DISTINCT action_type FROM admin_actions ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);
$tables  = $pdo->query("SELECT DISTINCT target_table FROM admin_actions ORDER BY target_table")->fetchAll(PDO::FETCH_COLUMN);

/* ---------- Query Helper ---------- */
function keep(array $overrides = []): string {
    $q = $_GET;

    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }

    return http_build_query($q);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Action Logs • HouseRader</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../admin/css/admin_index.css">
<link rel="stylesheet" href="../admin/css/admin_actions_log.css">
</head>

<body>

<header class="hr-navbar">
  <div class="brand">🏠HouseRader <span class="admin-badge">ADMIN</span></div>
  <div class="nav-right">
    <a href="admin_dashboard.php" class="btn-secondary">Dashboard</a>
    <div class="admin-name"><?= e($admin['name']) ?></div>
    <a href="admin_logout.php" class="btn-logout">Logout</a>
  </div>
</header>

<main class="container admin-logs">

<h1>Action Logs</h1>

<form class="log-filters" method="get">
  <input name="q" placeholder="Search logs…" value="<?= e($filter_q) ?>">
  <select name="action_type">
    <option value="">All actions</option>
    <?php foreach ($actions as $a): ?>
      <option value="<?= e($a) ?>" <?= $a === $filter_action ? 'selected' : '' ?>><?= e($a) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="target_table">
    <option value="">All tables</option>
    <?php foreach ($tables as $t): ?>
      <option value="<?= e($t) ?>" <?= $t === $filter_table ? 'selected' : '' ?>><?= e($t) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="from" value="<?= e($filter_from) ?>">
  <input type="date" name="to" value="<?= e($filter_to) ?>">
  <button class="btn-primary">Filter</button>
  <a class="btn-secondary" href="?<?= keep(['export'=>'csv','page'=>null]) ?>">Export CSV</a>
</form>

<div class="log-meta">
  <span>Total: <strong><?= $total ?></strong></span>
  <span>Page <?= $page ?> / <?= $totalPages ?></span>
</div>

<div class="log-table">
<?php if (!$rows): ?>
  <div class="empty-state">No actions found.</div>
<?php else: ?>
<?php foreach ($rows as $r): ?>
  <div class="log-row">
    <div class="log-action"><?= e(ucfirst($r['action_type'])) ?></div>

    <div class="log-target">
      <?php if ($r['target_table'] === 'properties'): ?>
        <strong><?= e($r['property_title'] ?? 'Deleted property') ?></strong>
        <span class="muted">(Property #<?= (int)$r['target_id'] ?>)</span><br>
        <span class="muted">Seller: <?= e($r['seller_name'] ?? 'Unknown') ?></span>
      <?php else: ?>
        <?= e($r['target_table']) ?> #<?= (int)$r['target_id'] ?>
      <?php endif; ?>
    </div>

    <div class="log-time">
      <?= e(date('d M Y, H:i', strtotime($r['created_at']))) ?>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<nav class="pagination">
<?php if ($page > 1): ?>
  <a href="?<?= keep(['page'=>$page-1]) ?>">‹ Prev</a>
<?php endif; ?>
<?php if ($page < $totalPages): ?>
  <a href="?<?= keep(['page'=>$page+1]) ?>">Next ›</a>
<?php endif; ?>
</nav>

</main>

<footer class="hr-footer">Admin Panel • HouseRader</footer>
</body>
</html>
