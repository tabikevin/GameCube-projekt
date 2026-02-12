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

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $cart = $_SESSION['cart'];
            $cart_items = [];
            $total = 0;

            if (!empty($cart)) {
                $ids = array_keys($cart);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $types = str_repeat('i', count($ids));
                
                $stmt = $conn->prepare("SELECT id, name, price, platform FROM products WHERE id IN ($placeholders) AND is_active = 1");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $product_id = (int)$row['id'];
                    $quantity = $cart[$product_id];
                    $subtotal = (int)$row['price'] * $quantity;
                    $total += $subtotal;

                    $cart_items[] = [
                        'id' => $product_id,
                        'name' => $row['name'],
                        'price' => (int)$row['price'],
                        'platform' => $row['platform'],
                        'quantity' => $quantity,
                        'subtotal' => $subtotal
                    ];
                }
                $stmt->close();
            }

            echo json_encode([
                'success' => true,
                'cart' => $cart_items,
                'total' => $total,
                'item_count' => array_sum($cart)
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Hiba a kosár lekérése során']);
        }
        break;

    case 'POST':
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

            if (!isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id] = 0;
            }
            $_SESSION['cart'][$product_id] += $quantity;

            echo json_encode([
                'success' => true,
                'message' => 'Termék hozzáadva a kosárhoz',
                'item_count' => array_sum($_SESSION['cart'])
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Hiba történt']);
        }
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        $updates = $input['items'] ?? [];

        foreach ($updates as $product_id => $quantity) {
            $pid = (int)$product_id;
            $qty = (int)$quantity;

            if ($qty <= 0) {
                unset($_SESSION['cart'][$pid]);
            } else {
                $_SESSION['cart'][$pid] = $qty;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Kosár frissítve'
        ]);
        break;

    case 'DELETE':
        $_SESSION['cart'] = [];
        echo json_encode([
            'success' => true,
            'message' => 'Kosár kiürítve'
        ]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        break;
}