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
$pdo = null;

// Try MySQL connection
try {
    $charset = 'utf8mb4';
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // If MySQL is not available (e.g. on Render with zero-setup),
    // automatically fall back to the self-contained SQLite database!
    $sqliteFile = __DIR__ . '/hotel_management.sqlite';
    if (file_exists($sqliteFile)) {
        try {
            $pdo = new PDO("sqlite:{$sqliteFile}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $sqle) {
            http_response_code(503);
            die('Database connection failed: ' . $sqle->getMessage());
        }
    } else {
        http_response_code(503);
        die('Database connection failed. Set MYSQL_URL environment variable or ensure db/hotel_management.sqlite exists.');
    }
}
?>
