<?php
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($k, $_SERVER) && !array_key_exists($k, $_ENV)) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'glossa';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Jakarta');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    if ($e->getCode() == 1049 && file_exists(__DIR__ . '/../schema.sql')) {
        $tempPdo = new PDO("mysql:host=$host", $user, $pass, $options);
        $tempPdo->exec(file_get_contents(__DIR__ . '/../schema.sql'));
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET time_zone = '+07:00'");
    } else {
        die("Koneksi database gagal: " . $e->getMessage());
    }
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER email");
} catch (PDOException $e) {}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(36) NOT NULL,
        email VARCHAR(255) NOT NULL,
        code VARCHAR(6) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_code (user_id, code)
    )");
} catch (PDOException $e) {}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        rate_key VARCHAR(100) PRIMARY KEY,
        attempts INT NOT NULL DEFAULT 1,
        last_attempt INT NOT NULL
    ) ENGINE=InnoDB");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE scan_history ADD COLUMN face_shape VARCHAR(50) NULL AFTER undertone");
} catch (PDOException $e) {}

