<?php
// db.php
$host = '127.0.0.1';
$db = 'badminton_db';
$user = 'admin';
$pass = 'admin';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    if ($e->getCode() == 1049) {
        // Database not found, we might be calling this from init_db.php before creation
        // Handled in init_db.php separately
    } else {
        throw new \PDOException($e->getMessage(), (int) $e->getCode());
    }
}
?>