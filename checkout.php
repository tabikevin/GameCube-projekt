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

$input   = json_decode(file_get_contents('php://input'), true);
$user_id = null;

if (!empty($input['_token'])) {
    $payload = verifyJWT($input['_token']);
    if ($payload && isset($payload['user_id'])) $user_id = (int)$payload['user_id'];
}

if (!$user_id) {
    $authHeader = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION']))               $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    elseif (!empty($_SERVER['HTTP_X_AUTHORIZATION']))         $authHeader = $_SERVER['HTTP_X_AUTHORIZATION'];
    elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))  $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];

    if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $m)) {
        $payload = verifyJWT(trim($m[1]));
        if ($payload && isset($payload['user_id'])) $user_id = (int)$payload['user_id'];
    }
}

if (!$user_id && function_exists('getallheaders')) {
    foreach (getallheaders() as $key => $value) {
        if (in_array(strtolower($key), ['authorization', 'x-authorization'])) {
            if (preg_match('/Bearer\s+(.+)$/i', $value, $m)) {
                $payload = verifyJWT($m[1]);
                if ($payload && isset($payload['user_id'])) { $user_id = (int)$payload['user_id']; break; }
            }
        }
    }
}

if (!$user_id && !empty($_SESSION['user_id'])) $user_id = (int)$_SESSION['user_id'];

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Bejelentkezés szükséges']);
    exit;
}

$billing_name       = trim($input['billing_name'] ?? '');
$billing_address    = trim($input['billing_address'] ?? '');
$billing_city       = trim($input['billing_city'] ?? '');
$billing_zip        = trim($input['billing_zip'] ?? '');
$billing_country    = trim($input['billing_country'] ?? 'Magyarország');
$billing_tax_number = trim($input['billing_tax_number'] ?? '');
$payment_method     = $input['payment_method'] ?? 'online_card';

$errors = [];
$allowed = ['online_card', 'bank_transfer', 'paypal', 'cash'];
if (!in_array($payment_method, $allowed)) $errors[] = 'Érvénytelen fizetési mód';
if (empty($billing_name))    $errors[] = 'A számlázási név megadása kötelező';
if (empty($billing_address)) $errors[] = 'A számlázási cím megadása kötelező';
if (empty($billing_city))    $errors[] = 'A város megadása kötelező';
if (empty($billing_zip))     $errors[] = 'Az irányítószám megadása kötelező';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$cartStmt = $pdo->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
$cartStmt->execute([$user_id]);
$cart = [];
while ($cr = $cartStmt->fetch()) {
    $cart[(int)$cr['product_id']] = (int)$cr['quantity'];
}

if (empty($cart)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A kosár üres']);
    exit;
}

try {
    $pdo->beginTransaction();

    $ids          = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt         = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($ids);

    $productMap = [];
    while ($row = $stmt->fetch()) {
        $productMap[(int)$row['id']] = ['name' => $row['name'], 'price' => (int)$row['price']];
    }

    $keyAssignments = [];
    $total_price    = 0;
    $items          = [];

    foreach ($cart as $pid => $quantity) {
        if (!isset($productMap[$pid])) continue;
        $basePrice = $productMap[$pid]['price'];

        $stmtKeys = $pdo->prepare("
            SELECT id, COALESCE(seller_price, ?) AS unit_price
            FROM game_keys
            WHERE product_id = ? AND is_sold = 0 AND is_approved = 1
            ORDER BY COALESCE(seller_price, ?) ASC
            LIMIT ?
        ");
        $stmtKeys->execute([$basePrice, $pid, $basePrice, $quantity]);
        $keys = $stmtKeys->fetchAll();

        if (count($keys) < $quantity) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => "Nincs elég készlet: {$productMap[$pid]['name']} — elérhető: " . count($keys) . " db, te {$quantity} db-ot kértél"
            ]);
            exit;
        }

        $keyAssignments[$pid] = $keys;

        $productTotal = 0;
        foreach ($keys as $k) {
            $productTotal += (int)$k['unit_price'];
        }
        $total_price += $productTotal;

        $items[] = [
            'id'       => $pid,
            'name'     => $productMap[$pid]['name'],
            'price'    => (int)round($productTotal / $quantity),
            'quantity' => $quantity,
        ];
    }

    $status = ($payment_method === 'bank_transfer' || $payment_method === 'cash') ? 'pending' : 'paid';

    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, total_price, status, payment_method,
            billing_name, billing_address, billing_city, billing_zip, billing_country, billing_tax_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id, $total_price, $status, $payment_method,
        $billing_name, $billing_address, $billing_city, $billing_zip, $billing_country, $billing_tax_number
    ]);
    $order_id = $pdo->lastInsertId();

    $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $itemStmt->execute([$order_id, $item['id'], $item['quantity'], $item['price'], $item['price'] * $item['quantity']]);
    }

    if ($status === 'paid') {
        assignKeysForOrder($pdo, $order_id, $user_id, $keyAssignments);
    }

    $pdo->commit();

    $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);

    try {
        $stmtUser = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
        $stmtUser->execute([$user_id]);
        $userRow = $stmtUser->fetch();

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
            $mailer->send('Gamecube172604@gmail.com', 'GameCube', $userRow['email'], "[GameCube] Rendelés fogadva - #{$order_id}", $emailHtml);
        }
    } catch (Exception $e) {}

    echo json_encode([
        'success'  => true,
        'order_id' => $order_id,
        'message'  => $status === 'paid'
            ? 'Rendelés sikeresen leadva! A kulcsokat megtalálod a profilodban.'
            : 'Rendelés sikeresen leadva! Az admin jóváhagyása után aktiváljuk.',
        'status' => $status
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Hiba történt: ' . $e->getMessage()]);
}

function assignKeysForOrder($pdo, $order_id, $user_id, $keyAssignments) {
    $upd = $pdo->prepare("UPDATE game_keys SET is_sold = 1, sold_to_user_id = ?, sold_at = NOW(), order_id = ? WHERE id = ?");
    foreach ($keyAssignments as $pid => $keys) {
        foreach ($keys as $k) {
            $upd->execute([$user_id, $order_id, $k['id']]);
        }
    }
}
