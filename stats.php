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
    
    $stmtMonthly = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
               COUNT(*) AS order_count,
               SUM(CASE WHEN status='paid' THEN total_price ELSE 0 END) AS revenue
        FROM orders
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $monthly = $stmtMonthly->fetchAll();

    
    $stmtTop = $pdo->query("
        SELECT p.name, p.platform, COUNT(gk.id) AS sold_count,
               SUM(p.price) AS total_revenue
        FROM game_keys gk
        JOIN products p ON p.id = gk.product_id
        WHERE gk.is_sold = 1
        GROUP BY p.id
        ORDER BY sold_count DESC
        LIMIT 5
    ");
    $topProducts = $stmtTop->fetchAll();

    
    $stmtTotals = $pdo->query("
        SELECT
            COUNT(*) AS total_orders,
            SUM(CASE WHEN status='paid' THEN total_price ELSE 0 END) AS total_revenue,
            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_orders
        FROM orders
    ");
    $totals = $stmtTotals->fetch();

    
    $stmtUsers = $pdo->query("SELECT COUNT(*) AS cnt FROM users WHERE role='user'");
    $userCount = $stmtUsers->fetch()['cnt'];

    
    $stmtKeys = $pdo->query("SELECT COUNT(*) AS cnt FROM game_keys WHERE is_sold=0");
    $keysAvail = $stmtKeys->fetch()['cnt'];

    echo json_encode([
        'success'     => true,
        'totals'      => [
            'total_orders'   => (int)$totals['total_orders'],
            'total_revenue'  => (int)$totals['total_revenue'],
            'pending_orders' => (int)$totals['pending_orders'],
            'paid_orders'    => (int)$totals['paid_orders'],
            'user_count'     => (int)$userCount,
            'keys_available' => (int)$keysAvail,
        ],
        'monthly'     => array_map(fn($r) => [
            'month'       => $r['month'],
            'order_count' => (int)$r['order_count'],
            'revenue'     => (int)$r['revenue'],
        ], $monthly),
        'top_products' => array_map(fn($r) => [
            'name'          => $r['name'],
            'platform'      => $r['platform'],
            'sold_count'    => (int)$r['sold_count'],
            'total_revenue' => (int)$r['total_revenue'],
        ], $topProducts),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Hiba történt: '.$e->getMessage()]);
}
