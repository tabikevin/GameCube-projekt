<?php
session_start();
require_once "db.php";

$errors = [];
$email_or_username = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email_or_username = trim($_POST["email_or_username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email_or_username === "" || $password === "") {
        $errors[] = "Minden mező kitöltése kötelező.";
    } else {
        $stmt = $conn->prepare("
            SELECT id, username, email, password_hash, is_active
            FROM users
            WHERE username = ? OR email = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $email_or_username, $email_or_username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && (int)$user["is_active"] === 1 && password_verify($password, $user["password_hash"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];

            $update = $conn->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $update->bind_param("i", $user["id"]);
            $update->execute();
            $update->close();

            header("Location: main.php");
            exit;
        } else {
            $errors[] = "Hibás adatok vagy a fiók inaktív.";
        }
    }
}

$success_message = $_SESSION["success_message"] ?? "";
unset($_SESSION["success_message"]);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Bejelentkezés – GameCube</title>
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
            <a href="register.php" class="btn btn-sm btn-outline-light gc-btn-ghost">Regisztráció</a>
            <a href="main.php" class="btn btn-sm btn-primary gc-btn-main">Főoldal</a>
        </div>
    </nav>
</header>

<main class="container" style="max-width: 520px;">
    <div class="gc-hero-card p-4 mb-4">
        <h1 class="h4 mb-3">Bejelentkezés</h1>

        <?php if ($success_message !== ""): ?>
            <div class="alert alert-success py-2 small mb-3">
                <?php echo htmlspecialchars($success_message, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

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
                <label for="email_or_username" class="form-label">Felhasználónév vagy e-mail</label>
                <input type="text" class="form-control" id="email_or_username" name="email_or_username"
                       value="<?php echo htmlspecialchars($email_or_username, ENT_QUOTES, "UTF-8"); ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Jelszó</label>
                <input type="password" class="form-control" id="password" name="password">
            </div>

            <button type="submit" class="btn btn-primary w-100 gc-btn-main mt-2">
                Bejelentkezés
            </button>
        </form>
    </div>
</main>
</body>
</html>
