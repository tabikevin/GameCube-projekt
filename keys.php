<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

$seller_id = (int)$user_data['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT gk.id, gk.key_code, gk.is_sold, gk.is_approved, gk.created_at,
                   p.name AS product_name, p.platform, gk.seller_price
            FROM game_keys gk
            JOIN products p ON p.id = gk.product_id
            WHERE gk.seller_id = ?
            ORDER BY gk.created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$seller_id]);

        $keys = [];
        while ($row = $stmt->fetch()) {
            $keys[] = [
                'id'           => (int)$row['id'],
                'key_code'     => maskKey($row['key_code']),
                'is_sold'      => (int)$row['is_sold'],
                'is_approved'  => (int)$row['is_approved'],
                'created_at'   => $row['created_at'],
                'product_name' => $row['product_name'],
                'platform'     => $row['platform'],
                'seller_price' => $row['seller_price'] ? (int)$row['seller_price'] : null
            ];
        }

        $stmtStats = $pdo->prepare("
            SELECT
                COUNT(*) AS total_keys,
                SUM(CASE WHEN is_sold = 1 THEN 1 ELSE 0 END) AS sold_keys,
                SUM(CASE WHEN is_sold = 0 AND is_approved = 1 THEN 1 ELSE 0 END) AS available_keys,
                SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) AS pending_keys
            FROM game_keys WHERE seller_id = ?
        ");
        $stmtStats->execute([$seller_id]);
        $stats = $stmtStats->fetch();

        echo json_encode([
            'success' => true,
            'keys'    => $keys,
            'stats'   => [
                'total'     => (int)$stats['total_keys'],
                'sold'      => (int)$stats['sold_keys'],
                'available' => (int)$stats['available_keys'],
                'pending'   => (int)$stats['pending_keys']
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Hiba történt a kulcsok lekérése során']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input      = json_decode(file_get_contents('php://input'), true);
    $product_id   = (int)($input['product_id'] ?? 0);
    $key_code     = strtoupper(trim($input['key_code'] ?? ''));
    $seller_price = isset($input['seller_price']) && $input['seller_price'] !== '' ? (int)$input['seller_price'] : null;

    $errors = [];

    if ($product_id <= 0) $errors[] = 'Válassz egy terméket';
    if (empty($key_code)) $errors[] = 'Add meg a játékkulcsot';
    if ($seller_price !== null && ($seller_price < 100 || $seller_price > 99999)) {
        $errors[] = 'Az ár 100 és 99 999 Ft között kell legyen';
    }

    $platform = null;
    if ($product_id > 0) {
        try {
            $stmtP = $pdo->prepare("SELECT platform FROM products WHERE id = ? AND is_active = 1");
            $stmtP->execute([$product_id]);
            $pRow = $stmtP->fetch();
            if ($pRow) $platform = $pRow['platform'];
        } catch (Exception $e) {}
    }

    if (!empty($key_code)) {
        $formatErr = validateKeyFormat($key_code, $platform);
        if ($formatErr) $errors[] = $formatErr;
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    try {
        if (!$platform) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'A kiválasztott termék nem létezik']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM game_keys WHERE key_code = ?");
        $stmt->execute([$key_code]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Ez a kulcs már létezik az adatbázisban']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO game_keys (product_id, key_code, is_sold, seller_id, seller_price, is_approved)
            VALUES (?, ?, 0, ?, ?, 0)
        ");
        $stmt->execute([$product_id, $key_code, $seller_id, $seller_price]);

        echo json_encode([
            'success' => true,
            'message' => 'Kulcs feltöltve! Admin jóváhagyás után válik elérhetővé a vásárlók számára.',
            'key_id'  => (int)$pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Hiba történt a kulcs feltöltése során']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);


function validateKeyFormat($key, $platform) {
    if (!preg_match('/^[A-Z0-9\-]+$/', $key)) {
        return 'A kulcs csak nagybetűket, számokat és kötőjelet tartalmazhat';
    }
    if (strpos($key, '-') === false) {
        return 'A kulcsnak tartalmaznia kell kötőjelet (-)';
    }
    if (preg_match('/--/', $key) || $key[0] === '-' || $key[strlen($key)-1] === '-') {
        return 'Érvénytelen kötőjel elhelyezés a kulcsban';
    }

    $segments = explode('-', $key);
    foreach ($segments as $seg) {
        if (strlen($seg) < 4 || strlen($seg) > 6) {
            return 'A kulcs minden szegmensének 4-6 karakter hosszúnak kell lennie';
        }
    }

    $raw = str_replace('-', '', $key);
    if (strlen($raw) < 12 || strlen($raw) > 30) {
        return 'A kulcs hossza nem megfelelő (12-30 alfanumerikus karakter szükséges)';
    }

    switch ($platform) {
        case 'pc':
            if (count($segments) < 3 || count($segments) > 4) {
                return 'PC kulcsnak 3 vagy 4 szegmensből kell állnia (pl. XXXXX-XXXXX-XXXXX)';
            }
            break;
        case 'xbox':
            if (count($segments) !== 5) {
                return 'Xbox kulcsnak pontosan 5 szegmensből kell állnia (pl. XXXXX-XXXXX-XXXXX-XXXXX-XXXXX)';
            }
            break;
        case 'ps':
            if (count($segments) < 4 || count($segments) > 5) {
                return 'PlayStation kulcsnak 4 vagy 5 szegmensből kell állnia (pl. XXXX-XXXX-XXXX-XXXX)';
            }
            break;
        case 'switch':
            if (count($segments) !== 3) {
                return 'Nintendo Switch kulcsnak pontosan 3 szegmensből kell állnia (pl. XXXX-XXXX-XXXX)';
            }
            break;
    }

    return null; // ok
}

function maskKey($key) {
    $parts = explode('-', $key);
    if (count($parts) <= 2) {
        return $parts[0] . str_repeat('-****', count($parts) - 1);
    }
    $first  = $parts[0];
    $last   = end($parts);
    $middle = array_slice($parts, 1, -1);
    $masked = array_map(fn($p) => str_repeat('*', strlen($p)), $middle);
    return $first . '-' . implode('-', $masked) . '-' . $last;
}
