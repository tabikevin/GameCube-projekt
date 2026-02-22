<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "../config/db.php";

$discounts = [40, 25, 30, 50, 35, 20, 45, 15, 55, 33];

try {
    $result = $conn->query("
        SELECT id, name, price, original_price, discount_percent, image_url, platform, tag
        FROM products
        WHERE is_active = 1 AND tag IN ('top', 'sale', 'new')
        ORDER BY FIELD(tag, 'top', 'sale', 'new'), id ASC
        LIMIT 10
    ");

    $topProducts = [];
    $i = 0;
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $discount = (int)$row['discount_percent'];
            $originalPrice = $row['original_price'] ? (int)$row['original_price'] : null;
            $price = (int)$row['price'];

            
            if ($discount <= 0) {
                $discount = $discounts[$i % count($discounts)];
                $originalPrice = round($price / (1 - $discount / 100));
            }

            $topProducts[] = [
                'id'               => (int)$row['id'],
                'name'             => $row['name'],
                'price'            => $price,
                'original_price'   => $originalPrice,
                'discount_percent' => $discount,
                'image_url'        => $row['image_url'] ?? 'steam.png',
                'platform'         => $row['platform'],
                'tag'              => $row['tag']
            ];
            $i++;
        }
        $result->free();
    }

    echo json_encode([
        'success'  => true,
        'products' => $topProducts
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Hiba történt a top termékek lekérése során'
    ]);
}
