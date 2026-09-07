<?php
// config/database.php
// This file creates ONE database connection using PDO (PHP Data Objects).
// PDO is preferred over mysqli because it supports multiple database types
// and — most importantly — it uses prepared statements to prevent SQL injection.

// Database credentials — change these to match your XAMPP setup.
define('DB_HOST', 'localhost');
define('DB_NAME', 'tz_rooms');
define('DB_USER', 'root');
define('DB_PASS', '');       // XAMPP default has no password for root
define('DB_CHARSET', 'utf8mb4');

// PDO DSN (Data Source Name) — tells PDO where to connect and what charset to use.
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// PDO options:
// ERRMODE_EXCEPTION  → throws exceptions on errors (easier to catch and handle)
// DEFAULT_FETCH_MODE → returns rows as associative arrays ['column' => 'value']
// EMULATE_PREPARES   → false means use REAL prepared statements (more secure)
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $db = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Never expose the real error message to the browser — log it server-side instead.
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(503);
    echo json_encode(["message" => "Database connection failed. Please try again later."]);
    exit();
}
