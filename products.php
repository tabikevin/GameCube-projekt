<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once "../../config/db.php";
require_once "../../config/jwt_helper.php";

$is_admin = false;
$authHeader = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
elseif (function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) {
        if (strtolower($k) === 'authorization') { $authHeader = $v; break; }
    }
}
if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $m)) {
    $p = verifyJWT($m[1]);
    if ($p && isset($p['role']) && $p['role'] === 'admin') $is_admin = true;
}
if (!$is_admin && !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') $is_admin = true;
if (!$is_admin) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Nincs jogosultságod']); exit; }

try {
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.platform, p.tag, p.price, p.is_active,
               COUNT(CASE WHEN gk.is_sold=0 THEN 1 END) AS keys_available,
               COUNT(CASE WHEN gk.is_sold=1 THEN 1 END) AS keys_sold,
               COUNT(gk.id) AS keys_total
        FROM products p
        LEFT JOIN game_keys gk ON gk.product_id = p.id
        GROUP BY p.id
        ORDER BY p.id ASC
    ");
    $products = [];
    while ($row = $stmt->fetch()) {
        $products[] = [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'platform'       => $row['platform'],
            'tag'            => $row['tag'],
            'price'          => (int)$row['price'],
            'is_active'      => (int)$row['is_active'],
            'keys_available' => (int)$row['keys_available'],
            'keys_sold'      => (int)$row['keys_sold'],
            'keys_total'     => (int)$row['keys_total'],
        ];
    }
    echo json_encode(['success'=>true,'products'=>$products]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Hiba történt']);
}
