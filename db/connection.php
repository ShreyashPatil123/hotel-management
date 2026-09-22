<?php
// Supports Render/external MySQL through MYSQL_URL or DB_* environment variables.
$mysqlUrl = getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
if ($mysqlUrl) {
    $parts = parse_url($mysqlUrl);
    $host = $parts['host'] ?? 'localhost';
    $db = ltrim($parts['path'] ?? '/hotel_management', '/');
    $user = urldecode($parts['user'] ?? 'root');
    $pass = urldecode($parts['pass'] ?? '');
    $port = $parts['port'] ?? 3306;
} else {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db = getenv('DB_NAME') ?: 'hotel_management';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: '';
    $port = getenv('DB_PORT') ?: 3306;
}
$charset = 'utf8mb4';
$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(503);
    die('Database connection failed. Check the Render MySQL environment variables and import db/hotel_management.sql.');
}
?>
