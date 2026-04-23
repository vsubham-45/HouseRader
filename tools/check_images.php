<?php
// tools/check_images.php  — run from project root (php tools/check_images.php) or open in browser (careful to secure)
require_once __DIR__ . '/../src/db.php'; // adjust path if needed

$rows = $pdo->query("SELECT id, img1, img2, img3, img4 FROM properties ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$base = realpath(__DIR__ . '/..'); // project/public parent
echo "Project root (for file checks): $base\n\n";

foreach ($rows as $r) {
    echo "Property ID: {$r['id']}\n";
    for ($i=1;$i<=4;$i++) {
        $col = 'img' . $i;
        $val = $r[$col] ?? '';
        if (!$val) { echo "  $col: <empty>\n"; continue; }
        // treat remote urls separately
        if (preg_match('#^(https?:)?//#i', $val)) {
            echo "  $col: REMOTE URL — $val\n";
            continue;
        }
        // normalize (strip leading slash)
        $rel = ltrim(str_replace('\\','/',$val), '/');
        $path = $base . DIRECTORY_SEPARATOR . $rel;
        $exists = is_file($path) ? 'OK' : 'MISSING';
        echo "  $col: $val => $path => $exists\n";
    }
    echo str_repeat('-',40) . "\n";
}
