<?php
// adatkezeles.php
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GameCube – Adatkezelés</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="main.css">
</head>
<body class="bg-dark text-light">

<header class="gc-header shadow-sm">
    <nav class="navbar navbar-expand-lg navbar-dark container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="main.php">
            <span class="gc-logo-square"></span>
            <span class="fw-bold">GameCube</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="aszf.php">ÁSZF</a></li>
                <li class="nav-item"><a class="nav-link active" href="adatkezeles.php">Adatkezelés</a></li>
                <li class="nav-item"><a class="nav-link" href="kapcsolat.php">Kapcsolat</a></li>
            </ul>
        </div>
    </nav>
</header>

<main class="container py-5">
    <div class="gc-hero-card p-4 rounded shadow-sm bg-secondary">
        <h1 class="display-5 fw-bold mb-4">Adatkezelés</h1>
        <p class="lead text-light-50">Ez az oldal bemutatja, hogyan kezeljük a felhasználók személyes adatait a GameCube weboldalon.</p>

        <h2 class="h4 mt-4">1. Adatkezelő</h2>
        <p>GameCube<br>Email: <a href="mailto:gamecube041726@gmail.com" class="text-decoration-underline text-light">gamecube041726@gmail.com</a></p>

        <h2 class="h4 mt-3">2. Milyen adatokat kezelünk</h2>
        <ul>
            <li>Felhasználói név és email</li>
            <li>Rendelési adatok és kosár tartalma</li>
            <li>Weboldal használati statisztikák</li>
        </ul>

        <h2 class="h4 mt-3">3. Adatkezelés célja</h2>
        <p>Az adatok célja a weboldal működtetése, rendelés feldolgozása és felhasználói élmény javítása.</p>

        <h2 class="h4 mt-3">4. Adatkezelés jogalapja</h2>
        <p>Az adatkezelés a felhasználó hozzájárulásán és a jogszabályi kötelezettségeken alapul.</p>

        <h2 class="h4 mt-3">5. Adatbiztonság</h2>
        <p>Minden adat titkosítva és biztonságosan kerül tárolásra. Harmadik félnek adatot nem adunk ki jogszabályi kötelezettség nélkül.</p>

        <h2 class="h4 mt-3">6. Jogok</h2>
        <p>A felhasználó kérheti adatai módosítását, törlését vagy másolatát a <a href="mailto:gamecube041726@gmail.com" class="text-decoration-underline text-light">gamecube041726@gmail.com</a> email címen.</p>
    </div>
</main>

<footer class="gc-footer py-4 border-top border-dark text-light">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        <span class="small">© <span id="yearSpan"></span> GameCube</span>
        <div class="gc-footer-links small">
            <a href="aszf.php" class="me-3">ÁSZF</a>
            <a href="adatkezeles.php" class="me-3">Adatkezelés</a>
            <a href="kapcsolat.php">Kapcsolat</a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('yearSpan').textContent = new Date().getFullYear();
</script>
</body>
</html>
