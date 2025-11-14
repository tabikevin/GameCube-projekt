<?php
session_start();
require_once "db.php";

$user_id = $_SESSION["user_id"] ?? null;

if (!$user_id) {
    header("Location: login.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT role, username
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || $user["role"] !== "admin") {
    http_response_code(403);
    echo "<p style='color:white;background:#050814;padding:20px;font-family:system-ui'>
            Nincs jogosultságod az admin felület megnyitásához.
          </p>";
    exit;
}

function assignKeysForOrder($conn, $order_id) {
    $order_id = (int)$order_id;

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
        $user_id = (int)$row["user_id"];
        $items[] = [
            "product_id" => (int)$row["product_id"],
            "quantity"   => (int)$row["quantity"],
        ];
    }
    $stmt->close();

    if ($user_id === null || empty($items)) {
        return;
    }

    foreach ($items as $item) {
        $pid = $item["product_id"];
        $qty = $item["quantity"];

        for ($i = 0; $i < $qty; $i++) {
            $stmtKey = $conn->prepare("
                SELECT id
                FROM game_keys
                WHERE product_id = ? AND is_sold = 0
                LIMIT 1
            ");
            $stmtKey->bind_param("i", $pid);
            $stmtKey->execute();
            $resKey = $stmtKey->get_result();
            $keyRow = $resKey->fetch_assoc();
            $stmtKey->close();

            if (!$keyRow) {
                continue;
            }

            $key_id = (int)$keyRow["id"];

            $stmtUpdate = $conn->prepare("
                UPDATE game_keys
                SET is_sold = 1,
                    sold_to_user_id = ?,
                    sold_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->bind_param("ii", $user_id, $key_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
    }
}

$action_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mark_paid"])) {
    $order_id = isset($_POST["order_id"]) ? (int)$_POST["order_id"] : 0;

    if ($order_id > 0) {
        $stmt = $conn->prepare("
            UPDATE orders
            SET status = 'paid'
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $rows = $stmt->affected_rows;
        $stmt->close();

        if ($rows > 0) {
            assignKeysForOrder($conn, $order_id);
            $action_message = "Rendelés #" . $order_id . " jóváhagyva és kulcsok kiosztva.";
        } else {
            $action_message = "A rendelés nem jóváhagyható (lehet, hogy már paid vagy törölt).";
        }
    }
}

$orders = [];

$result = $conn->query("
    SELECT o.id, u.username, o.total_price, o.status, o.payment_method, o.created_at
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
    LIMIT 50
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $result->free();
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Rendeléskezelés – GameCube Admin</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="main.css">
</head>
<body>
<header class="gc-header shadow-sm mb-4">
    <nav class="navbar navbar-expand-lg navbar-dark container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="main.php">
            <span class="gc-logo-square"></span>
            <span class="fw-bold">GameCube – Admin</span>
        </a>
        <div class="ms-auto d-flex gap-2">
            <span class="btn btn-sm btn-outline-light disabled">Rendelések</span>
            <a href="main.php" class="btn btn-sm btn-outline-light gc-btn-ghost">Bolt</a>
            <a href="profile.php" class="btn btn-sm btn-outline-light gc-btn-ghost">Profilom</a>
            <a href="logout.php" class="btn btn-sm btn-primary gc-btn-main">Kijelentkezés</a>
        </div>
    </nav>
</header>


<main class="container" style="max-width: 1000px;">
    <div class="gc-hero-card p-4 mb-4">
        <h1 class="h4 mb-3">Rendeléskezelés</h1>
        <p class="text-light-50 mb-3">
            Itt tudod a banki átutalásos rendeléseket jóváhagyni. Jóváhagyás után
            a rendszer kiosztja a hozzá tartozó játék kulcsokat a felhasználónak.
        </p>

        <?php if ($action_message !== ""): ?>
            <div class="alert alert-info py-2 small mb-3">
                <?php echo htmlspecialchars($action_message, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <p class="text-light-50 mb-0">Még nincs egyetlen rendelés sem.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Felhasználó</th>
                        <th>Dátum</th>
                        <th>Összeg</th>
                        <th>Fizetés</th>
                        <th>Státusz</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo (int)$order["id"]; ?></td>
                            <td><?php echo htmlspecialchars($order["username"], ENT_QUOTES, "UTF-8"); ?></td>
                            <td><?php echo htmlspecialchars($order["created_at"], ENT_QUOTES, "UTF-8"); ?></td>
                            <td><?php echo number_format((int)$order["total_price"], 0, ".", " "); ?> Ft</td>
                            <td><?php echo htmlspecialchars($order["payment_method"], ENT_QUOTES, "UTF-8"); ?></td>
                            <td><?php echo htmlspecialchars($order["status"], ENT_QUOTES, "UTF-8"); ?></td>
                            <td class="text-end">
                                <?php if ($order["status"] === "pending" && $order["payment_method"] === "bank_transfer"): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order["id"]; ?>">
                                        <button
                                            type="submit"
                                            name="mark_paid"
                                            class="btn btn-sm btn-success"
                                        >
                                            Jóváhagyás + kulcsok
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="small text-light-50">Nincs teendő</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>