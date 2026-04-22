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
    $stmt = $pdo->query("SELECT id, name, category FROM products WHERE is_active = 1");

    $suffixes = [' PC', ' PS', ' PS4', ' PS5', ' Xbox', ' Switch'];

    $categoryCounts = [];
    while ($row = $stmt->fetch()) {
        $cat  = $row['category'];
        $base = $row['name'];

        foreach ($suffixes as $s) {
            if (substr($base, -strlen($s)) === $s) {
                $base = substr($base, 0, -strlen($s));
                break;
            }
        }

        if ($base === 'Minecraft Java & Bedrock') $base = 'Minecraft';
        if (strtolower($base) === 'fifa 25')      $base = 'EA Sports FC 25';

        if (!isset($categoryCounts[$cat])) {
            $categoryCounts[$cat] = [];
        }
        $categoryCounts[$cat][$base] = true;
    }

    $categoryLabels = [
        'action'     => ['name' => 'Akció',         'icon' => 'bi-lightning-charge-fill'],
        'rpg'        => ['name' => 'RPG',            'icon' => 'bi-shield-shaded'],
        'fps'        => ['name' => 'FPS / Lövölde',  'icon' => 'bi-crosshair'],
        'sport'      => ['name' => 'Sport',          'icon' => 'bi-trophy-fill'],
        'adventure'  => ['name' => 'Kaland',         'icon' => 'bi-compass-fill'],
        'racing'     => ['name' => 'Verseny',        'icon' => 'bi-speedometer2'],
        'simulation' => ['name' => 'Szimuláció',     'icon' => 'bi-joystick'],
        'horror'     => ['name' => 'Horror',         'icon' => 'bi-emoji-dizzy-fill'],
        'sandbox'    => ['name' => 'Sandbox',        'icon' => 'bi-box-fill'],
        'other'      => ['name' => 'Egyéb',          'icon' => 'bi-grid-fill'],
    ];

    $categories = [];
    foreach ($categoryCounts as $key => $baseNames) {
        $label = $categoryLabels[$key] ?? ['name' => ucfirst($key), 'icon' => 'bi-tag-fill'];
        $categories[] = [
            'key'   => $key,
            'name'  => $label['name'],
            'icon'  => $label['icon'],
            'count' => count($baseNames),
        ];
    }

    usort($categories, fn($a, $b) => $b['count'] - $a['count']);

    echo json_encode(['success' => true, 'categories' => $categories], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Hiba történt a kategóriák lekérése során']);
}
