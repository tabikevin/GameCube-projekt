<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once "../../config/db.php";
require_once "../../config/seller_auth.php";

$user_data = getSellerAuth($pdo);

if (!$user_data) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Bejelentkezés szükséges']);
    exit;
}
}

try {
    $stmt = $pdo->query("
        SELECT id, name, platform, price, image_url, category, tag
        FROM products
        WHERE is_active = 1
        ORDER BY name ASC
    ");
    $products = [];
    while ($row = $stmt->fetch()) {
        $products[] = [
            'id'        => (int)$row['id'],
            'name'      => $row['name'],
            'platform'  => $row['platform'],
            'price'     => (int)$row['price'],
            'image_url' => $row['image_url'],
            'category'  => $row['category'],
            'tag'       => $row['tag']
        ];
    }
    echo json_encode(['success' => true, 'products' => $products]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Hiba történt a termékek lekérése során']);
}
