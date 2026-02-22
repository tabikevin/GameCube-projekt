<?php
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "../config/db.php";
require_once "../config/jwt_helper.php";

$user_id = null;

$authHeader = '';
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            $authHeader = $value;
            break;
        }
    }
}
if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
}
if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $payload = verifyJWT($matches[1]);
    if ($payload && isset($payload['user_id'])) {
        $user_id = (int)$payload['user_id'];
    }
}

if (!$user_id && isset($_GET['token'])) {
    $payload = verifyJWT($_GET['token']);
    if ($payload && isset($payload['user_id'])) {
        $user_id = (int)$payload['user_id'];
    }
}

if (!$user_id && !empty($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
}

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Bejelentkezés szükséges']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, phone, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Felhasználó nem található']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, total_price, status, payment_method, created_at
        FROM orders WHERE user_id = ?
        ORDER BY created_at DESC LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT gk.key_code, gk.sold_at, p.name as product_name, p.platform
        FROM game_keys gk
        JOIN products p ON gk.product_id = p.id
        WHERE gk.sold_to_user_id = ?
        ORDER BY gk.sold_at DESC
    ");
    $stmt->execute([$user_id]);
    $purchased_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'user' => $user,
        'orders' => $orders,
        'purchased_keys' => $purchased_keys
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Adatbázis hiba: ' . $e->getMessage()]);
}
