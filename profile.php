<?php
session_start();
require_once "db.php";

$user_id = $_SESSION["user_id"] ?? null;

if (!$user_id) {
    header("Location: login.php");
    exit;
}

$errors  = [];
$success = "";

$stmt = $conn->prepare("
    SELECT username, email, full_name, phone, role, created_at, last_login_at
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_profile"])) {
    $full_name = trim($_POST["full_name"] ?? "");
    $phone     = trim($_POST["phone"] ?? "");

    if ($full_name === "") {
        $errors[] = "A teljes név megadása kötelező.";
    }

    if ($phone !== "" && strlen($phone) < 7) {
        $errors[] = "Ha megadsz telefonszámot, legyen legalább 7 karakter.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE users
            SET full_name = ?, phone = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssi", $full_name, $phone, $user_id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $success           = "A profil adatai frissültek.";
            $user["full_name"] = $full_name;
            $user["phone"]     = $phone;
        } else {
            $errors[] = "Váratlan hiba történt mentés közben.";
        }
    }
}

$orders = [];

$stmt = $conn->prepare("
    SELECT id, total_price, status, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();

$keys = [];

$stmt = $conn->prepare("
    SELECT g.key_code, p.name, g.sold_at
    FROM game_keys g
    JOIN products p ON p.id = g.product_id
    WHERE g.sold_to_user_id = ?
    ORDER BY g.sold_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultKeys = $stmt->get_result();

while ($row = $resultKeys->fetch_assoc()) {
    $keys[] = $row;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Profilom – GameCube</title>
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
            <a href="main.php" class="btn btn-sm btn-outline-light gc-btn-ghost">Bolt</a>
            <a href="cart.php" class="btn btn-sm btn-outline-light gc-btn-ghost">Kosár</a>
            <a href="logout.php" class="btn btn-sm btn-primary gc-btn-main">Kijelentkezés</a>
        </div>
    </nav>
</header>

<main class="container" style="max-width: 780px;">
    <div class="gc-hero-card p-4 mb-4">
        <h1 class="h4 mb-3">Profilom</h1>
        <p class="text-light-50 mb-3">
            Itt tudod módosítani a főbb adataidat, és megnézheted a rendeléseidet,
            valamint a kiosztott játék kulcsaidat.
        </p>

        <?php if (!empty($errors)) : ?>
            <div class="alert alert-danger py-2">
                <ul class="mb-0 small">
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success !== "") : ?>
            <div class="alert alert-success py-2 small mb-3">
                <?php echo htmlspecialchars($success, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate class="mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Felhasználónév</label>
                    <input
                        type="text"
                        class="form-control"
                        value="<?php echo htmlspecialchars($user["username"], ENT_QUOTES, "UTF-8"); ?>"
                        disabled
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-mail</label>
                    <input
                        type="email"
                        class="form-control"
                        value="<?php echo htmlspecialchars($user["email"], ENT_QUOTES, "UTF-8"); ?>"
                        disabled
                    >
                </div>
                <div class="col-md-6">
                    <label for="full_name" class="form-label">Teljes név</label>
                    <input
                        type="text"
                        class="form-control"
                        id="full_name"
                        name="full_name"
                        value="<?php echo htmlspecialchars($user["full_name"], ENT_QUOTES, "UTF-8"); ?>"
                    >
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Telefonszám</label>
                    <input
                        type="text"
                        class="form-control"
                        id="phone"
                        name="phone"
                        value="<?php echo htmlspecialchars($user["phone"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label">Regisztráció ideje</label>
                    <input
                        type="text"
                        class="form-control"
                        value="<?php echo htmlspecialchars($user["created_at"], ENT_QUOTES, "UTF-8"); ?>"
                        disabled
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label">Utolsó bejelentkezés</label>
                    <input
                        type="text"
                        class="form-control"
                        value="<?php echo htmlspecialchars($user["last_login_at"] ?? "-", ENT_QUOTES, "UTF-8"); ?>"
                        disabled
                    >
                </div>
            </div>

            <button type="submit" name="save_profile" class="btn btn-primary gc-btn-main mt-3">
                Mentés
            </button>
        </form>

        <h2 class="h5 mb-3">Legutóbbi rendeléseim</h2>

        <?php if (empty($orders)): ?>
            <p class="text-light-50 mb-3">Még nem adtál le rendelést.</p>
        <?php else: ?>
            <div class="table-responsive mb-3">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Rendelés #</th>
                        <th>Dátum</th>
                        <th>Állapot</th>
                        <th class="text-end">Összeg</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo (int)$order["id"]; ?></td>
                            <td><?php echo htmlspecialchars($order["created_at"], ENT_QUOTES, "UTF-8"); ?></td>
                            <td><?php echo htmlspecialchars($order["status"], ENT_QUOTES, "UTF-8"); ?></td>
                            <td class="text-end">
                                <?php echo number_format((int)$order["total_price"], 0, ".", " "); ?> Ft
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h2 class="h5 mt-4 mb-3">Megvásárolt kulcsaim</h2>

        <?php if (empty($keys)): ?>
            <p class="text-light-50 mb-0">
                Még nem lett kiosztva neked egyetlen kulcs sem.
                Banki átutalásnál az admin jóváhagyása után jelennek meg itt.
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Játék</th>
                        <th>Kulcs</th>
                        <th>Kiosztva</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($keys as $k): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($k["name"], ENT_QUOTES, "UTF-8"); ?></td>
                            <td>
                                <code><?php echo htmlspecialchars($k["key_code"], ENT_QUOTES, "UTF-8"); ?></code>
                            </td>
                            <td><?php echo htmlspecialchars($k["sold_at"], ENT_QUOTES, "UTF-8"); ?></td>
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