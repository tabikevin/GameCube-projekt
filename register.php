<?php
session_start();
require_once "db.php";

$errors = [];

$username = "";
$email = "";
$full_name = "";
$phone = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $password_confirm = $_POST["password_confirm"] ?? "";

    if ($full_name === "") {
        $errors[] = "A teljes név megadása kötelező.";
    }

    if ($username === "" || strlen($username) < 3) {
        $errors[] = "A felhasználónév legalább 3 karakter legyen.";
    }

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Érvényes e-mail cím megadása kötelező.";
    }

    if ($password === "" || strlen($password) < 6) {
        $errors[] = "A jelszó legalább 6 karakter legyen.";
    }

    if ($password !== $password_confirm) {
        $errors[] = "A két jelszó nem egyezik.";
    }

    if ($phone !== "" && strlen($phone) < 7) {
        $errors[] = "Ha megadsz telefonszámot, legyen legalább 7 karakter.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Már létezik ilyen felhasználónév vagy e-mail cím.";
        }

        $stmt->close();
    }

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $role = "user";
        $is_active = 1;

        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password_hash, full_name, phone, role, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssi", $username, $email, $password_hash, $full_name, $phone, $role, $is_active);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $_SESSION["success_message"] = "Sikeres regisztráció, most már bejelentkezhetsz.";
            header("Location: login.php");
            exit;
        } else {
            $errors[] = "Váratlan hiba történt a regisztráció során.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Regisztráció – GameCube</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            <a href="login.php" class="btn btn-sm btn-outline-light gc-btn-ghost">Bejelentkezés</a>
            <a href="main.php" class="btn btn-sm btn-primary gc-btn-main">Főoldal</a>
        </div>
    </nav>
</header>

<main class="container" style="max-width: 560px;">
    <div class="gc-hero-card p-4 mb-4">
        <h1 class="h4 mb-3">Új fiók létrehozása</h1>

        <?php if (!empty($errors)) : ?>
            <div class="alert alert-danger py-2">
                <ul class="mb-0 small">
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label for="full_name" class="form-label">Teljes név</label>
                <input type="text" class="form-control" id="full_name" name="full_name"
                       value="<?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="mb-3">
                <label for="username" class="form-label">Felhasználónév</label>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">E-mail cím</label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">Telefonszám (opcionális)</label>
                <input type="text" class="form-control" id="phone" name="phone"
                       value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Jelszó</label>
                <input type="password" class="form-control" id="password" name="password">
            </div>

            <div class="mb-3">
                <label for="password_confirm" class="form-label">Jelszó megerősítése</label>
                <input type="password" class="form-control" id="password_confirm" name="password_confirm">
            </div>

            <button type="submit" class="btn btn-primary w-100 gc-btn-main mt-2">
                Regisztráció
            </button>
        </form>
    </div>
</main>
</body>
</html>
