<?php
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "../../config/db.php";
require_once "../../config/jwt_helper.php";
require_once "../../config/smtp_mailer.php";
require_once "../../config/email_templates.php";

$is_admin = false;

$authHeader = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
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

if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $payload = verifyJWT($matches[1]);
    if ($payload && isset($payload['role']) && $payload['role'] === 'admin') {
        $is_admin = true;
    }
}

if (!$is_admin && isset($_GET['token'])) {
    $payload = verifyJWT($_GET['token']);
    if ($payload && isset($payload['role']) && $payload['role'] === 'admin') {
        $is_admin = true;
    }
}

if (!$is_admin && !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $is_admin = true;
}

if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Nincs jogosultságod']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $result = $conn->query("
            SELECT 
                o.id,
                u.username,
                o.total_price,
                o.status,
                o.payment_method,
                o.created_at
            FROM orders o
            JOIN users u ON u.id = o.user_id
            ORDER BY o.created_at DESC
            LIMIT 50
        ");

        $orders = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $orders[] = [
                    'id' => (int)$row['id'],
                    'username' => $row['username'],
                    'total_price' => (int)$row['total_price'],
                    'status' => $row['status'],
                    'payment_method' => $row['payment_method'],
                    'created_at' => $row['created_at']
                ];
            }
            $result->free();
        }

        echo json_encode([
            'success' => true,
            'orders' => $orders
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Hiba történt']);
    }

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = (int)($input['order_id'] ?? 0);

    if ($order_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Érvénytelen rendelés azonosító']);
        exit;
    }

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("UPDATE orders SET status = 'paid' WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'A rendelés nem jóváhagyható']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT o.user_id, oi.product_id, oi.quantity
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            WHERE o.id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        $user_id = null;

        while ($row = $result->fetch_assoc()) {
            $user_id = (int)$row['user_id'];
            $items[] = [
                'product_id' => (int)$row['product_id'],
                'quantity' => (int)$row['quantity']
            ];
        }
        $stmt->close();

        foreach ($items as $item) {
            $pid = $item['product_id'];
            $qty = $item['quantity'];
            for ($i = 0; $i < $qty; $i++) {
                $stmtKey = $conn->prepare("SELECT id FROM game_keys WHERE product_id = ? AND is_sold = 0 LIMIT 1");
                $stmtKey->bind_param("i", $pid);
                $stmtKey->execute();
                $resKey = $stmtKey->get_result();
                $keyRow = $resKey->fetch_assoc();
                $stmtKey->close();
                if (!$keyRow) continue;
                $key_id = (int)$keyRow['id'];
                $stmtUpdate = $conn->prepare("UPDATE game_keys SET is_sold = 1, sold_to_user_id = ?, sold_at = NOW() WHERE id = ?");
                $stmtUpdate->bind_param("ii", $user_id, $key_id);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        }

        $conn->commit();

        
        try {
            $stmtInfo = $conn->prepare("
                SELECT o.user_id, o.total_price, u.username, u.email,
                       oi.product_id, oi.quantity, p.name as product_name
                FROM orders o
                JOIN users u ON u.id = o.user_id
                JOIN order_items oi ON oi.order_id = o.id
                JOIN products p ON p.id = oi.product_id
                WHERE o.id = ?
            ");
            $stmtInfo->bind_param("i", $order_id);
            $stmtInfo->execute();
            $resInfo = $stmtInfo->get_result();

            $emailItems = [];
            $emailUser = null;
            $emailEmail = null;
            $emailTotal = 0;

            while ($row = $resInfo->fetch_assoc()) {
                $emailUser  = $row['username'];
                $emailEmail = $row['email'];
                $emailTotal = (int)$row['total_price'];
                $emailItems[] = [
                    'name'     => $row['product_name'],
                    'quantity' => (int)$row['quantity'],
                    'price'    => 0
                ];
            }
            $stmtInfo->close();

            if ($emailEmail) {
                $emailHtml = buildOrderApprovedEmail([
                    'order_id'    => $order_id,
                    'user_name'   => $emailUser,
                    'items'       => $emailItems,
                    'total_price' => $emailTotal
                ]);

                $mailer = new SmtpMailer('smtp.gmail.com', 465, 'Gamecube172604@gmail.com', 'acyw cyeg zpnf inmc');
                $mailer->setHtml(true);
                $mailer->send(
                    'Gamecube172604@gmail.com',
                    'GameCube',
                    $emailEmail,
                    "[GameCube] Rendelés jóváhagyva - #{$order_id}",
                    $emailHtml
                );
            }
        } catch (Exception $mailErr) {
            
        }

        echo json_encode([
            'success' => true,
            'message' => 'Rendelés jóváhagyva és kulcsok kiosztva'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Hiba történt']);
    }

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
