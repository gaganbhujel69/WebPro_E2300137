<?php
// ============================================================
//  EMS — Database Configuration
//  config/db.php
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'eduskill_db');
define('DB_USER', 'root');         // change for production
define('DB_PASS', '');             // change for production
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO connection.
 * All queries must use prepared statements.
 */
function get_db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production, log the error and show a friendly page.
            error_log('[EMS DB Error] ' . $e->getMessage());
            die('Database connection failed. Please try again later.');
        }
    }

    return $pdo;
}
