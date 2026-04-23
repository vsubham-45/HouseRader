<?php
// tools/find_image_and_fix_db.php
// Search project for an image basename and optionally update DB rows to the found public path.
//
// Runs from CLI:
//   php tools/find_image_and_fix_db.php property_1764613443_54d16671390f.jpeg
//
// Or (browser) - use ?basename=... but THIS IS DANGEROUS if left public.
// The browser mode checks for a session admin flag to reduce risk (you must be logged-in admin).
// Make sure to remove or secure this file after use.

declare(strict_types=1);

// --- helper to detect CLI vs Web ---
$isCli = (php_sapi_name() === 'cli' || (defined('STDIN')));

// Get basename from argv (CLI) or $_GET['basename'] (web)
$basename = null;
if ($isCli) {
    global $argc, $argv;
    if (!isset($argc) || $argc < 2) {
        echo "Usage: php tools" . DIRECTORY_SEPARATOR . "find_image_and_fix_db.php <basename>\n";
        exit(1);
    }
    $basename = $argv[1];
} else {
    // Web mode: require session admin
    session_start();
    if (empty($_SESSION['admin']) || $_SESSION['admin'] !== true) {
        http_response_code(403);
        echo "Forbidden: admin login required to run this tool via web.";
        exit;
    }
    if (empty($_GET['basename'])) {
        echo "Usage (web): /tools/find_image_and_fix_db.php?basename=property_1764613443_54d16671390f.jpeg";
        exit;
    }
    $basename = $_GET['basename'];
}

$basename = trim((string)$basename);
if ($basename === '') {
    echo "Empty basename provided.\n";
    exit(1);
}

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    echo "Unable to determine project root.\n";
    exit(1);
}

echo "Project root: {$projectRoot}\n";
echo "Searching for basename: {$basename}\n\n";

// recursive search for matching file names (case-insensitive)
$found = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    if (strcasecmp($f->getFilename(), $basename) === 0) {
        $found[] = $f->getPathname();
    }
}

if (empty($found)) {
    echo "No files found with basename: {$basename}\n";
    exit(0);
}

echo "Found files:\n";
foreach ($found as $p) echo "  $p\n";
echo "\n";

// --- connect to DB to optionally update properties pointing to this basename ---
// adjust path to src/db.php if your layout is different
$dbPathCandidates = [
    __DIR__ . '/../src/db.php',
    __DIR__ . '/../../src/db.php', // just in case the script lives elsewhere
];
$dbPath = null;
foreach ($dbPathCandidates as $c) { if (file_exists($c)) { $dbPath = $c; break; } }
if (!$dbPath) {
    echo "Unable to find src/db.php. Please update path in this script.\n";
    exit(1);
}
require_once $dbPath; // expects $pdo (PDO instance)

foreach ($found as $fullPath) {
    // produce candidate public path(s) relative to project root
    $rel = str_replace('\\','/', substr($fullPath, strlen($projectRoot) + 1));
    echo "Candidate public path: $rel\n";

    // For safety: show what UPDATE statements WOULD run; require confirmation in CLI
    $updates = [];
    $like = '%' . $basename . '%';
    for ($i = 1; $i <= 4; $i++) {
        $col = "img{$i}";
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM properties WHERE {$col} LIKE :like");
        $stmt->execute([':like' => $like]);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) $updates[$col] = $count;
    }

    if (empty($updates)) {
        echo "No properties currently reference this basename in img1..img4 columns. You can still update manually if needed.\n";
        continue;
    }

    echo "The following columns reference this basename:\n";
    foreach ($updates as $col => $cnt) {
        echo "  {$col}: {$cnt} row(s)\n";
    }

    $doUpdate = false;
    if ($isCli) {
        // prompt user
        echo "\nDo you want to update those rows to point to '{$rel}' ? (y/N): ";
        $handle = fopen("php://stdin","r");
        $line = fgets($handle);
        fclose($handle);
        if (strtolower(trim((string)$line)) === 'y') $doUpdate = true;
    } else {
        // in web mode, require explicit confirmation param ?confirm=1
        if (!empty($_GET['confirm']) && $_GET['confirm'] === '1') $doUpdate = true;
    }

    if (!$doUpdate) {
        echo "Skipping update for {$rel}\n\n";
        continue;
    }

    // perform updates for img1..img4
    foreach ($updates as $col => $cnt) {
        $stmt = $pdo->prepare("UPDATE properties SET {$col} = :path WHERE {$col} LIKE :like");
        $stmt->execute([':path' => $rel, ':like' => $like]);
        $affected = $stmt->rowCount();
        echo "Updated {$affected} rows for {$col} => {$rel}\n";
    }
    echo "\n";
}

echo "Done.\n";
