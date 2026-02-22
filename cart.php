<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "../config/db.php";
require_once "../config/jwt_helper.php";

$user_id = null;

// Try all possible ways to get the token
$authHeader = '';
if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (!empty($_SERVER['HTTP_X_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_X_AUTHORIZATION'];
} elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            $authHeader = $value;
            break;
        }
    }
}

if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $m)) {
    $p = verifyJWT(trim($m[1]));
    if ($p && isset($p['user_id'])) $user_id = (int)$p['user_id'];
}
if (!$user_id && !empty($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (!$user_id) {
            echo json_encode(['success' => true, 'cart' => [], 'total' => 0, 'item_count' => 0]);
            exit;
        }
        try {
            $stmt = $conn->prepare("
                SELECT c.product_id, c.quantity, p.name, p.price, p.platform
                FROM cart c
                JOIN products p ON p.id = c.product_id AND p.is_active = 1
                WHERE c.user_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $cart_items = [];
            $total = 0;
            $item_count = 0;
            while ($row = $result->fetch_assoc()) {
                $subtotal = (int)$row['price'] * (int)$row['quantity'];
                $total += $subtotal;
                $item_count += (int)$row['quantity'];
                $cart_items[] = [
                    'id' => (int)$row['product_id'],
                    'name' => $row['name'],
                    'price' => (int)$row['price'],
                    'platform' => $row['platform'],
                    'quantity' => (int)$row['quantity'],
                    'subtotal' => $subtotal
                ];
            }
            $stmt->close();
            echo json_encode(['success' => true, 'cart' => $cart_items, 'total' => $total, 'item_count' => $item_count]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Hiba a kosár lekérése során']);
        }
        break;

    case 'POST':
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Bejelentkezés szükséges a kosárhoz']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $product_id = (int)($input['product_id'] ?? 0);
        $quantity = (int)($input['quantity'] ?? 1);

        if ($product_id <= 0 || $quantity <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Érvénytelen termék vagy mennyiség']);
            exit;
        }

        try {
            $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'A termék nem található']);
                exit;
            }
            $stmt->close();

            $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $user_id, $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();

            if ($existing) {
                $new_qty = (int)$existing['quantity'] + $quantity;
                $stmt = $conn->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("ii", $new_qty, $existing['id']);
            } else {
                $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $user_id, $product_id, $quantity);
            }
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("SELECT SUM(quantity) as cnt FROM cart WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            echo json_encode(['success' => true, 'message' => 'Termék hozzáadva', 'item_count' => (int)$r['cnt']]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Hiba történt']);
        }
        break;

    case 'PUT':
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Bejelentkezés szükséges']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $updates = $input['items'] ?? [];

        foreach ($updates as $product_id => $quantity) {
            $pid = (int)$product_id;
            $qty = (int)$quantity;
            if ($qty <= 0) {
                $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $user_id, $pid);
            } else {
                $stmt = $conn->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param("iii", $qty, $user_id, $pid);
            }
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true, 'message' => 'Kosár frissítve']);
        break;

    case 'DELETE':
        if ($user_id) {
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true, 'message' => 'Kosár kiürítve']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        break;
}
