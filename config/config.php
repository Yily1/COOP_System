<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Email configuration (for verification)
define('MAIL_FROM', 'noreply@coopserve.ics-dev.io');
define('BASE_URL', 'https://ics-dev.io/coopserve');

define('DB_HOST', 'localhost');
define('DB_NAME', 'u442411629_coopserve_db');
define('DB_USER', 'u442411629_dev_coop');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log("DB connection failed: " . $e->getMessage());
    http_response_code(500);
    die("Hindi makakonekta sa database. Subukan muli mamaya.");
}