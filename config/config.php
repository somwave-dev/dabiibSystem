<?php
require_once __DIR__ . '/session.php';

if (PHP_SAPI !== 'cli') {
    requireLogin();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = '127.0.0.1';
$user = 'root';
$password = '';
$database = 'clinic';

try {
    $conn = new mysqli($host, $user, $password, $database);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    exit('Database connection failed.');
}