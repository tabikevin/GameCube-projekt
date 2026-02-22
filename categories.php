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

try {
    $result = $conn->query("
        SELECT category, COUNT(*) as count
        FROM products
        WHERE is_active = 1
        GROUP BY category
        ORDER BY count DESC
    ");

    $categoryLabels = [
        'action'     => ['name' => 'Akció',       'icon' => 'bi-lightning-charge-fill'],
        'rpg'        => ['name' => 'RPG',          'icon' => 'bi-shield-shaded'],
        'fps'        => ['name' => 'FPS / Lövölde','icon' => 'bi-crosshair'],
        'sport'      => ['name' => 'Sport',        'icon' => 'bi-trophy-fill'],
        'adventure'  => ['name' => 'Kaland',       'icon' => 'bi-compass-fill'],
        'racing'     => ['name' => 'Verseny',      'icon' => 'bi-speedometer2'],
        'simulation' => ['name' => 'Szimuláció',   'icon' => 'bi-joystick'],
        'horror'     => ['name' => 'Horror',       'icon' => 'bi-emoji-dizzy-fill'],
        'sandbox'    => ['name' => 'Sandbox',      'icon' => 'bi-box-fill'],
        'other'      => ['name' => 'Egyéb',        'icon' => 'bi-grid-fill']
    ];

    $categories = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $key = $row['category'];
            $label = $categoryLabels[$key] ?? ['name' => ucfirst($key), 'icon' => 'bi-tag-fill'];
            $categories[] = [
                'key'   => $key,
                'name'  => $label['name'],
                'icon'  => $label['icon'],
                'count' => (int)$row['count']
            ];
        }
        $result->free();
    }

    echo json_encode([
        'success'    => true,
        'categories' => $categories
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Hiba történt a kategóriák lekérése során'
    ]);
}
