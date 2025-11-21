<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["cart"])) {
    $_SESSION["cart"] = [];
}

$cart = &$_SESSION["cart"];
$user_id = $_SESSION["user_id"] ?? null;
$username = $_SESSION["username"] ?? null;

$errors = [];
$order_success = "";
$last_order_id = null;

$billing_name = "";
$billing_address = "";
$billing_city = "";
$billing_zip = "";
$billing_country = "Magyarország";
$billing_tax_number = "";
$payment_method = "online_card";

$card_number = "";
$card_exp = "";
$card_cvc = "";

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

if ($user_id) {
    $stmtUser = $conn->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
    $stmtUser->bind_param("i", $user_id);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result();
    if ($rowUser = $resUser->fetch_assoc()) {
        if ($billing_name === "") {
            $billing_name = $rowUser["full_name"];
        }
    }
    $stmtUser->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_to_cart"])) {
    $product_id = isset($_POST["product_id"]) ? (int)$_POST["product_id"] : 0;

    if ($product_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            if (!isset($cart[$product_id])) {
                $cart[$product_id] = 0;
            }
            $cart[$product_id] += 1;
        }

        $stmt->close();
    }

    header("Location: cart.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_cart"])) {
    if (isset($_POST["qty"]) && is_array($_POST["qty"])) {
        foreach ($_POST["qty"] as $pid => $qty) {
            $pid_int = (int)$pid;
            $qty_int = (int)$qty;

            if ($qty_int <= 0) {
                unset($cart[$pid_int]);
            } else {
                $cart[$pid_int] = $qty_int;
            }
        }
    }

    header("Location: cart.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["checkout"])) {
    if (!$user_id) {
        header("Location: login.php");
        exit;
    }

    $billing_name = trim($_POST["billing_name"] ?? "");
    $billing_address = trim($_POST["billing_address"] ?? "");
    $billing_city = trim($_POST["billing_city"] ?? "");
    $billing_zip = trim($_POST["billing_zip"] ?? "");
    $billing_country = trim($_POST["billing_country"] ?? "Magyarország");
    $billing_tax_number = trim($_POST["billing_tax_number"] ?? "");
    $payment_method = $_POST["payment_method"] ?? "online_card";
    $terms = isset($_POST["terms"]) ? $_POST["terms"] : "";

    $allowed_methods = ["online_card","bank_transfer","paypal"];
    if (!in_array($payment_method, $allowed_methods, true)) {
        $errors[] = "Érvénytelen fizetési mód.";
    }

    if ($billing_name === "") {
        $errors[] = "A számlázási név megadása kötelező.";
    }
    if ($billing_address === "") {
        $errors[] = "A számlázási cím megadása kötelező.";
    }
    if ($billing_city === "") {
        $errors[] = "A város megadása kötelező.";
    }
    if ($billing_zip === "") {
        $errors[] = "Az irányítószám megadása kötelező.";
    }
    if ($billing_country === "") {
        $errors[] = "Az ország megadása kötelező.";
    }

    if ($terms !== "on") {
        $errors[] = "El kell fogadnod az ÁSZF-et a rendelés leadásához.";
    }

    if ($payment_method === "online_card") {
        $card_number = trim($_POST["card_number"] ?? "");
        $card_exp = trim($_POST["card_exp"] ?? "");
        $card_cvc = trim($_POST["card_cvc"] ?? "");

        if ($card_number === "" || strlen(str_replace(" ", "", $card_number)) < 12) {
            $errors[] = "A kártyaszám érvénytelen.";
        }
        if ($card_exp === "" || strlen($card_exp) !== 5 || strpos($card_exp, "/") !== 2) {
            $errors[] = "A lejárati dátum hibás (MM/YY).";
        }
        if ($card_cvc === "" || strlen($card_cvc) !== 3) {
            $errors[] = "A CVC kód hibás.";
        }
    }

    $items = [];
    $total = 0;

    if (empty($cart)) {
        $errors[] = "A kosarad üres, nem tudsz rendelést leadni.";
    }

    if (empty($errors)) {
        $ids = array_keys($cart);
        $placeholders = implode(",", array_fill(0, count($ids), "?"));
        $types = str_repeat("i", count($ids));

        $stmt = $conn->prepare("
            SELECT id, name, price
            FROM products
            WHERE id IN ($placeholders) AND is_active = 1
        ");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $pid = (int)$row["id"];
            $qty = $cart[$pid] ?? 0;

            if ($qty <= 0) {
                continue;
            }

            $price = (int)$row["price"];
            $line_total = $price * $qty;
            $total += $line_total;

            $items[] = [
                "id" => $pid,
                "name" => $row["name"],
                "price" => $price,
                "qty" => $qty
            ];
        }

        $stmt->close();

        if ($total <= 0 || empty($items)) {
            $errors[] = "Nem sikerült a kosár tételeit beolvasni.";
        }
    }

    if (empty($errors)) {
        $status = ($payment_method === "bank_transfer") ? "pending" : "paid";

        $stmt = $conn->prepare("
            INSERT INTO orders (
                user_id,
                total_price,
                billing_name,
                billing_address,
                billing_city,
                billing_zip,
                billing_country,
                billing_tax_number,
                payment_method,
                status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");
        $stmt->bind_param(
            "iissssssss",
            $user_id,
            $total,
            $billing_name,
            $billing_address,
            $billing_city,
            $billing_zip,
            $billing_country,
            $billing_tax_number,
            $payment_method,
            $status
        );
        $stmt = $conn->prepare("
            INSERT INTO orders (
                user_id,
                total_price,
                billing_name,
                billing_address,
                billing_city,
                billing_zip,
                billing_country,
                billing_tax_number,
                payment_method,
                status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");
        $stmt->bind_param(
            "iissssssss",
            $user_id,
            $total,
            $billing_name,
            $billing_address,
            $billing_city,
            $billing_zip,
            $billing_country,
            $billing_tax_number,
            $payment_method,
            $status
        );

        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();

        $stmtItem = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, unit_price)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $pid = $item["id"];
            $qty = $item["qty"];
            $price = $item["price"];
            $stmtItem->bind_param("iiii", $order_id, $pid, $qty, $price);
            $stmtItem->execute();
        }

        $stmtItem->close();

        if ($status === "paid") {
            assignKeysForOrder($conn, $order_id);
        }

        $cart = [];
        $last_order_id = $order_id;

        if ($status === "paid") {
            $order_success = "A rendelés sikeresen rögzítve és kifizetve. Rendelés azonosító: #" . $order_id . ".";
        } else {
            $order_success = "A rendelés sikeresen rögzítve. Fizetési mód: banki átutalás. Rendelés azonosító: #" . $order_id . ".";
        }

        $card_number = "";
        $card_exp = "";
        $card_cvc = "";
    }
}

$products_in_cart = [];
$total_price = 0;

if (!empty($cart)) {
    $ids = array_keys($cart);
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $types = str_repeat("i", count($ids));

    $stmt = $conn->prepare("
        SELECT id, name, price
        FROM products
        WHERE id IN ($placeholders) AND is_active = 1
    ");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $pid = (int)$row["id"];
        $qty = $cart[$pid] ?? 0;

        if ($qty <= 0) {
            continue;
        }

        $price = (int)$row["price"];
        $line_total = $price * $qty;
        $total_price += $line_total;

        $products_in_cart[] = [
            "id" => $pid,
            "name" => $row["name"],
            "price" => $price,
            "qty" => $qty,
            "line_total" => $line_total
        ];
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Kosár – GameCube</title>
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
            <span class="fw-bold">GameCube</span>
        </a>
        <div class="ms-auto d-flex gap-2">
            <a href="main.php" class="btn btn-sm btn-outline-light gc-btn-ghost">
                Vissza a boltba
            </a>
            <?php if ($username): ?>
                <a href="profile.php" class="btn btn-sm btn-outline-light gc-btn-ghost">
                    Profilom
                </a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<main class="container" style="max-width: 900px;">
    <div class="gc-hero-card p-4 mb-4">
        <h1 class="h4 mb-3">Kosár</h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 small">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($order_success !== ""): ?>
            <div class="alert alert-success py-2 small mb-3">
                <?php echo htmlspecialchars($order_success, ENT_QUOTES, "UTF-8"); ?>
                <?php if ($payment_method === "bank_transfer" && $last_order_id !== null): ?>
                    <hr class="my-2">
                    <small class="text-light-50 d-block">
                        Kérjük, 3 munkanapon belül utald át a végösszeget az alábbi számlaszámra:<br>
                        <strong>HU00 12345678-12345678-00000000</strong> (GameCube Kft.)<br>
                        Közlemény: <strong>GameCube rendelés #<?php echo (int)$last_order_id; ?></strong><br>
                        A fizetés beérkezése után az admin jóváhagyja a rendelést, és a kulcsok
                        automatikusan megjelennek a profilodban.
                    </small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($products_in_cart)): ?>
            <p class="text-light-50 mb-0">
                A kosarad jelenleg üres. Nézz körül a
                <a href="main.php">játékok között</a>!
            </p>
        <?php else: ?>
            <form method="post">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="table-responsive mb-3">
                            <table class="table table-dark table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Termék</th>
                                    <th class="text-center" style="width: 90px;">Menny.</th>
                                    <th class="text-end">Egységár</th>
                                    <th class="text-end">Összesen</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($products_in_cart as $item): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($item["name"], ENT_QUOTES, "UTF-8"); ?>
                                        </td>
                                        <td class="text-center">
                                            <input
                                                type="number"
                                                name="qty[<?php echo (int)$item["id"]; ?>]"
                                                min="0"
                                                class="form-control form-control-sm text-center"
                                                value="<?php echo (int)$item["qty"]; ?>"
                                            >
                                        </td>
                                        <td class="text-end">
                                            <?php echo number_format($item["price"], 0, ".", " "); ?> Ft
                                        </td>
                                        <td class="text-end">
                                            <?php echo number_format($item["line_total"], 0, ".", " "); ?> Ft
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold">Végösszeg:</span>
                            <span class="gc-price fs-5">
                                <?php echo number_format($total_price, 0, ".", " "); ?> Ft
                            </span>
                        </div>

                        <button
                            type="submit"
                            name="update_cart"
                            class="btn btn-outline-light gc-btn-ghost mt-2"
                        >
                            Kosár frissítése
                        </button>
                    </div>

                    <div class="col-lg-5">
                        <h2 class="h6 mb-3">Számlázási adatok</h2>

                        <div class="mb-2">
                            <label class="form-label">Számlázási név</label>
                            <input
                                type="text"
                                name="billing_name"
                                class="form-control form-control-sm"
                                value="<?php echo htmlspecialchars($billing_name, ENT_QUOTES, "UTF-8"); ?>"
                            >
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Cím (utca, házszám)</label>
                            <input
                                type="text"
                                name="billing_address"
                                class="form-control form-control-sm"
                                value="<?php echo htmlspecialchars($billing_address, ENT_QUOTES, "UTF-8"); ?>"
                            >
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">Város</label>
                                <input
                                    type="text"
                                    name="billing_city"
                                    class="form-control form-control-sm"
                                    value="<?php echo htmlspecialchars($billing_city, ENT_QUOTES, "UTF-8"); ?>"
                                >
                            </div>
                            <div class="col-6">
                                <label class="form-label">Irányítószám</label>
                                <input
                                    type="text"
                                    name="billing_zip"
                                    class="form-control form-control-sm"
                                    value="<?php echo htmlspecialchars($billing_zip, ENT_QUOTES, "UTF-8"); ?>"
                                >
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Ország</label>
                            <input
                                type="text"
                                name="billing_country"
                                class="form-control form-control-sm"
                                value="<?php echo htmlspecialchars($billing_country, ENT_QUOTES, "UTF-8"); ?>"
                            >
                        </div>

                        <div class="mb-2">
                            <label class="form-label">
                                Adószám (ha céges számlát kérsz)
                            </label>
                            <input
                                type="text"
                                name="billing_tax_number"
                                class="form-control form-control-sm"
                                value="<?php echo htmlspecialchars($billing_tax_number, ENT_QUOTES, "UTF-8"); ?>"
                            >
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Fizetési mód</label>
                            <select
                                name="payment_method"
                                class="form-select form-select-sm"
                            >
                                <option value="online_card" <?php echo $payment_method === "online_card" ? "selected" : ""; ?>>
                                    Online bankkártya
                                </option>
                                <option value="bank_transfer" <?php echo $payment_method === "bank_transfer" ? "selected" : ""; ?>>
                                    Banki átutalás
                                </option>
                                <option value="paypal" <?php echo $payment_method === "paypal" ? "selected" : ""; ?>>
                                    PayPal
                                </option>
                            </select>
                        </div>

                        <div id="cardPaymentBox" style="display:none; margin-top: 15px;">
                            <h2 class="h6 mb-2">Bankkártya adatok</h2>

                            <div class="mb-2">
                                <label class="form-label">Kártyaszám</label>
                                <input
                                    type="text"
                                    name="card_number"
                                    class="form-control form-control-sm"
                                    placeholder="1234 5678 9012 3456"
                                    maxlength="19"
                                    value="<?php echo htmlspecialchars($card_number, ENT_QUOTES, "UTF-8"); ?>"
                                >
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label">Lejárat (MM/YY)</label>
                                    <input
                                        type="text"
                                        name="card_exp"
                                        class="form-control form-control-sm"
                                        placeholder="08/27"
                                        maxlength="5"
                                        value="<?php echo htmlspecialchars($card_exp, ENT_QUOTES, "UTF-8"); ?>"
                                    >
                                </div>
                                <div class="col-6">
                                    <label class="form-label">CVC</label>
                                    <input
                                        type="text"
                                        name="card_cvc"
                                        class="form-control form-control-sm"
                                        placeholder="123"
                                        maxlength="3"
                                        value="<?php echo htmlspecialchars($card_cvc, ENT_QUOTES, "UTF-8"); ?>"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="form-check my-2">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="terms"
                                id="termsCheck"
                                <?php echo isset($_POST["terms"]) && $_POST["terms"] === "on" ? "checked" : ""; ?>
                            >
                            <label class="form-check-label small" for="termsCheck">
                                Elfogadom az ÁSZF-et és az adatkezelési tájékoztatót.
                            </label>
                        </div>

                        <button
                            type="submit"
                            name="checkout"
                            class="btn btn-primary gc-btn-main mt-2 w-100"
                            id="checkoutButton"
                            <?php echo $total_price <= 0 ? "disabled" : ""; ?>
                        >
                            Megrendelés véglegesítése
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<div
    id="paypalModalBackdrop"
    style="
        display:none;
        position:fixed;
        inset:0;
        background:rgba(0,0,0,0.7);
        z-index:1050;
        justify-content:center;
        align-items:center;
    "
>
    <div
        style="
            background:#111827;
            color:#f9fafb;
            padding:20px;
            border-radius:12px;
            max-width:360px;
            width:100%;
            box-shadow:0 20px 40px rgba(0,0,0,0.5);
            border:1px solid #1f2937;
        "
    >
        <h2 class="h6 mb-2">PayPal fizetés (bemutató)</h2>
        <p class="small mb-3 text-light-50">
            Ez egy demo PayPal ablak. Élesben itt jelentkeznél be PayPalra,
            és a sikeres fizetés után visszairányítanánk a GameCube oldalra.
        </p>
        <div class="d-flex justify-content-end gap-2">
            <button
                type="button"
                class="btn btn-sm btn-outline-light"
                id="paypalCancel"
            >
                Mégse
            </button>
            <button
                type="button"
                class="btn btn-sm btn-primary"
                id="paypalConfirm"
            >
                Fizetés jóváhagyása
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const paySelect = document.querySelector("select[name='payment_method']");
    const cardBox = document.getElementById("cardPaymentBox");
    const checkoutBtn = document.getElementById("checkoutButton");
    const paypalModal = document.getElementById("paypalModalBackdrop");
    const paypalConfirm = document.getElementById("paypalConfirm");
    const paypalCancel = document.getElementById("paypalCancel");
    const form = checkoutBtn ? checkoutBtn.closest("form") : null;

    function updateCardBox() {
        if (!paySelect || !cardBox) return;
        if (paySelect.value === "online_card") {
            cardBox.style.display = "block";
        } else {
            cardBox.style.display = "none";
        }
    }

    if (paySelect) {
        paySelect.addEventListener("change", updateCardBox);
        updateCardBox();
    }

    if (checkoutBtn && form && paySelect && paypalModal) {
        checkoutBtn.addEventListener("click", (event) => {
            if (paySelect.value === "paypal") {
                event.preventDefault();
                paypalModal.style.display = "flex";
            }
        });

        if (paypalCancel) {
            paypalCancel.addEventListener("click", () => {
                paypalModal.style.display = "none";
            });
        }

        if (paypalConfirm) {
            paypalConfirm.addEventListener("click", () => {
                paypalModal.style.display = "none";
                form.submit();
            });
        }
    }
});
</script>
</body>
</html>
