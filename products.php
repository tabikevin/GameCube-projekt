<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "../config/db.php";

try {
    $result = $conn->query("
        SELECT id, name, platform, category, short_description, price, tag, image_url
        FROM products
        WHERE is_active = 1
        ORDER BY id ASC
    ");

    $products = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $products[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'platform' => $row['platform'],
                'category' => $row['category'] ?? 'action',
                'short_description' => $row['short_description'],
                'price' => (int)$row['price'],
                'tag' => $row['tag'],
                'image_url' => $row['image_url'] ?? 'steam.png'
            ];
        }
        $result->free();
    }

    echo json_encode([
        'success' => true,
        'products' => $products
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Hiba történt a termékek lekérése során'
    ]);
}
