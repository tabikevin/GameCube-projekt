<?php
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once "../../config/db.php";
require_once "../../config/jwt_helper.php";

$is_admin = false;
$authHeader = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) {
        if (strtolower($k) === 'authorization') { $authHeader = $v; break; }
    }
}
if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $m)) {
    $p = verifyJWT($m[1]);
    if ($p && isset($p['role']) && $p['role'] === 'admin') $is_admin = true;
}
if (!$is_admin && !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') $is_admin = true;

if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Nincs jogosultságod']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT gk.id, gk.key_code, gk.is_approved, gk.created_at,
                   gk.seller_price, p.name AS product_name, p.platform, p.price,
                   u.username AS seller_name
            FROM game_keys gk
            JOIN products p ON p.id = gk.product_id
            JOIN users u ON u.id = gk.seller_id
            WHERE gk.seller_id IS NOT NULL AND gk.is_sold = 0
            ORDER BY gk.is_approved ASC, gk.created_at DESC
            LIMIT 200
        ");

        $keys = [];
        while ($row = $stmt->fetch()) {
            $keys[] = [
                'id'           => (int)$row['id'],
                'key_code'     => maskKeyAdmin($row['key_code']),
                'is_approved'  => (int)$row['is_approved'],
                'created_at'   => $row['created_at'],
                'seller_price' => $row['seller_price'] ? (int)$row['seller_price'] : null,
                'product_name' => $row['product_name'],
                'platform'     => $row['platform'],
                'base_price'   => (int)$row['price'],
                'seller_name'  => $row['seller_name']
            ];
        }

        echo json_encode(['success' => true, 'keys' => $keys]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Hiba történt']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true);
    $key_id = (int)($input['key_id'] ?? 0);
    $action = $input['action'] ?? '';

    if ($key_id <= 0 || !in_array($action, ['approve', 'reject'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Érvénytelen kérés']);
        exit;
    }

    try {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE game_keys SET is_approved = 1 WHERE id = ? AND seller_id IS NOT NULL");
            $stmt->execute([$key_id]);
            echo json_encode(['success' => true, 'message' => 'Kulcs jóváhagyva, mostantól elérhető a vásárlók számára']);
        } else {
            $stmt = $pdo->prepare("DELETE FROM game_keys WHERE id = ? AND seller_id IS NOT NULL AND is_sold = 0");
            $stmt->execute([$key_id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'A kulcs nem törölhető (már eladva vagy nem létezik)']);
                exit;
            }
            echo json_encode(['success' => true, 'message' => 'Kulcs elutasítva és törölve']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Hiba történt']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);

function maskKeyAdmin($key) {
    $parts = explode('-', $key);
    if (count($parts) <= 2) return $key;
    $first  = $parts[0];
    $last   = end($parts);
    $cnt    = count($parts) - 2;
    return $first . '-' . implode('-', array_fill(0, $cnt, '****')) . '-' . $last;
}
