<?php
$db_host = "localhost";
$db_user = "root";
$db_password = "root";
$db_name = "gamecube";
$db_port = 8889;

$conn = new mysqli($db_host, $db_user, $db_password, $db_name, $db_port);

if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'error' => 'Adatbázis kapcsolati hiba'
    ]));
}

$conn->set_charset("utf8mb4");

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode([
        'success' => false,
        'error' => 'Adatbázis kapcsolati hiba (PDO)'
    ]));
}
