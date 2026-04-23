<?php
// src/db.php

$IS_LOCAL = (
    isset($_SERVER['HTTP_HOST']) &&
    (
        $_SERVER['HTTP_HOST'] === 'localhost' ||
        str_starts_with($_SERVER['HTTP_HOST'], '127.')
    )
);

if ($IS_LOCAL) {
    // ✅ LOCAL (XAMPP)
    $DB_HOST = 'localhost';
    $DB_NAME = 'houserader';        // ← your local DB name
    $DB_USER = 'root';
    $DB_PASS = '';                  // XAMPP default
} else {
    // 🌍 LIVE (Byet / future Hostinger)
    $DB_HOST = '';
    $DB_NAME = '';
    $DB_USER = '';
    $DB_PASS = '';
}

$DB_CHARSET = 'utf8mb4';
$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    die("Database Connection Failed");
}
