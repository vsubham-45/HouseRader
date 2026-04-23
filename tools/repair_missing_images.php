<?php
// tools/repair_missing_images.php
// Usage (CLI): php tools/repair_missing_images.php
// Scans all properties img1..img4, and sets columns to NULL if the referenced file is missing on disk.
// CAUTION: Back up DB before running.

require_once __DIR__ . '/../src/db.php'; // adjust if needed

$projectRoot = realpath(__DIR__ . '/..');
$rows = $pdo->query("SELECT id, img1, img2, img3, img4 FROM properties")->fetchAll(PDO::FETCH_ASSOC);
$fixes = 0;
foreach ($rows as $r) {
    $id = (int)$r['id'];
    $updates = [];
    for ($i=1;$i<=4;$i++) {
        $col = 'img' . $i;
        $val = $r[$col] ?? '';
        if (!$val) continue;
        // treat remote urls as fine
        if (preg_match('#^(https?:)?//#i', $val)) continue;
        // normalize path relative to project root
        $rel = ltrim(str_replace('\\','/',$val), '/');
        $fs = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($fs)) {
            echo "Property {$id} - {$col} missing file: {$val} -> clearing\n";
            $updates[$col] = null;
        }
    }
    if (!empty($updates)) {
        // build SET clause
        $sets = [];
        $params = [':id' => $id];
        foreach ($updates as $col => $v) {
            $sets[] = "`{$col}` = NULL";
        }
        $sql = "UPDATE properties SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $fixes++;
    }
}
echo "Done. Rows updated: {$fixes}\n";
