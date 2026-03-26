<?php
// ============================================================
//  EMS — Database Connection
//  config/connection.php
//
//  Usage: require_once __DIR__ . '/../config/connection.php';
//         Then use $conn for queries via PDO.
// ============================================================

// ── Configuration ────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'eduskill_db');
define('DB_USER',    'root');       // Change for production
define('DB_PASS',    '');           // Change for production
define('DB_CHARSET', 'utf8mb4');

// ── Establish PDO Connection ─────────────────────────────────
$dsn = 'mysql:host=' . DB_HOST
     . ';dbname='    . DB_NAME
     . ';charset='   . DB_CHARSET;

$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Throw exceptions on error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Return associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                   // Use real prepared statements
];

try {
    $conn = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
} catch (PDOException $e) {
    // Log the real error; show a safe message to the user
    error_log('[EMS DB Connection Error] ' . $e->getMessage());
    http_response_code(503);
    die(json_encode([
        'success' => false,
        'message' => 'Unable to connect to the database. Please try again later.'
    ]));
}
