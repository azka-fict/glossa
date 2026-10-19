<?php
$host = 'localhost';
$db   = 'glossa';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    if ($e->getCode() == 1049 && file_exists(__DIR__ . '/../schema.sql')) {
        $tempPdo = new PDO("mysql:host=$host", $user, $pass, $options);
        $tempPdo->exec(file_get_contents(__DIR__ . '/../schema.sql'));
        $pdo = new PDO($dsn, $user, $pass, $options);
    } else {
        die("Koneksi database gagal: " . $e->getMessage());
    }
}
