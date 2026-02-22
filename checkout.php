<?php
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "../config/db.php";
require_once "../config/jwt_helper.php";
require_once "../config/smtp_mailer.php";
require_once "../config/email_templates.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$user_id = null;

if (!$user_id && !empty($input['_token'])) {
    $payload = verifyJWT($input['_token']);
    if ($payload && isset($payload['user_id'])) {
        $user_id = (int)$payload['user_id'];
    }
}

if (!$user_id) {
    $authHeader = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    elseif (!empty($_SERVER['HTTP_X_AUTHORIZATION'])) $authHeader = $_SERVER['HTTP_X_AUTHORIZATION'];
    elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    
    if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $payload = verifyJWT(trim($matches[1]));
        if ($payload && isset($payload['user_id'])) {
            $user_id = (int)$payload['user_id'];
        }
    }
}

if (!$user_id && function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization' || strtolower($key) === 'x-authorization') {
            if (preg_match('/Bearer\s+(.+)$/i', $value, $matches)) {
                $payload = verifyJWT($matches[1]);
                if ($payload && isset($payload['user_id'])) {
                    $user_id = (int)$payload['user_id'];
                    break;
                }
            }
        }
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

$billing_name = trim($input['billing_name'] ?? '');
$billing_address = trim($input['billing_address'] ?? '');
$billing_city = trim($input['billing_city'] ?? '');
$billing_zip = trim($input['billing_zip'] ?? '');
$billing_country = trim($input['billing_country'] ?? 'Magyarország');
$billing_tax_number = trim($input['billing_tax_number'] ?? '');
$payment_method = $input['payment_method'] ?? 'online_card';

$errors = [];
$allowed_methods = ['online_card', 'bank_transfer', 'paypal', 'cash'];
if (!in_array($payment_method, $allowed_methods)) {
    $errors[] = 'Érvénytelen fizetési mód';
}
if (empty($billing_name)) $errors[] = 'A számlázási név megadása kötelező';
if (empty($billing_address)) $errors[] = 'A számlázási cím megadása kötelező';
if (empty($billing_city)) $errors[] = 'A város megadása kötelező';
if (empty($billing_zip)) $errors[] = 'Az irányítószám megadása kötelező';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Load cart from DB (user-based)
$cartStmt = $conn->prepare("SELECT c.product_id, c.quantity FROM cart c WHERE c.user_id = ?");
$cartStmt->bind_param("i", $user_id);
$cartStmt->execute();
$cartResult = $cartStmt->get_result();
$cart = [];
while ($cr = $cartResult->fetch_assoc()) {
    $cart[(int)$cr['product_id']] = (int)$cr['quantity'];
}
$cartStmt->close();

if (empty($cart)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A kosár üres']);
    exit;
}

try {
    $conn->begin_transaction();

    $total_price = 0;
    $items = [];

    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $stmt = $conn->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $pid = (int)$row['id'];
        $quantity = $cart[$pid];
        $subtotal = (int)$row['price'] * $quantity;
        $total_price += $subtotal;

        $items[] = [
            'id' => $pid,
            'name' => $row['name'],
            'price' => (int)$row['price'],
            'quantity' => $quantity
        ];
    }
    $stmt->close();

    if (empty($items)) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nem található érvényes termék a kosárban']);
        exit;
    }

    $status = ($payment_method === 'bank_transfer' || $payment_method === 'cash') ? 'pending' : 'paid';

    $stmt = $conn->prepare("
        INSERT INTO orders (
            user_id, total_price, status, payment_method,
            billing_name, billing_address, billing_city, billing_zip,
            billing_country, billing_tax_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iissssssss",
        $user_id, $total_price, $status, $payment_method,
        $billing_name, $billing_address, $billing_city, $billing_zip,
        $billing_country, $billing_tax_number
    );
    $stmt->execute();
    $order_id = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $item_total = $item['price'] * $item['quantity'];
        $stmt->bind_param("iiiii", $order_id, $item['id'], $item['quantity'], $item['price'], $item_total);
        $stmt->execute();
    }
    $stmt->close();

    if ($status === 'paid') {
        assignKeysForOrder($conn, $order_id, $user_id, $items);
    }

    $conn->commit();
    // Clear DB cart
    $delCart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $delCart->bind_param("i", $user_id);
    $delCart->execute();
    $delCart->close();

    
    try {
        $stmtUser = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
        $stmtUser->bind_param("i", $user_id);
        $stmtUser->execute();
        $userRow = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();

        if ($userRow && !empty($userRow['email'])) {
            $paymentLabels = [
                'online_card'   => 'Bankkártyás fizetés',
                'bank_transfer' => 'Banki átutalás',
                'paypal'        => 'PayPal',
                'cash'          => 'Készpénz'
            ];

            $emailHtml = buildOrderReceivedEmail([
                'order_id'             => $order_id,
                'user_name'            => $userRow['username'],
                'items'                => $items,
                'total_price'          => $total_price,
                'payment_method_label' => $paymentLabels[$payment_method] ?? $payment_method
            ]);

            $mailer = new SmtpMailer('smtp.gmail.com', 465, 'Gamecube172604@gmail.com', 'acyw cyeg zpnf inmc');
            $mailer->setHtml(true);
            $mailer->send(
                'Gamecube172604@gmail.com',
                'GameCube',
                $userRow['email'],
                "[GameCube] Rendelés fogadva - #{$order_id}",
                $emailHtml
            );
        }
    } catch (Exception $mailErr) {
        
    }

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'message' => $status === 'paid' 
            ? 'Rendelés sikeresen leadva! A kulcsokat megtalálod a profilodban.' 
            : 'Rendelés sikeresen leadva! Az admin jóváhagyása után aktiváljuk.',
        'status' => $status
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Hiba történt: ' . $e->getMessage()]);
}

function assignKeysForOrder($conn, $order_id, $user_id, $items) {
    foreach ($items as $item) {
        $pid = $item['id'];
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
}
