<?php
// aszf.php
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GameCube – ÁSZF</title>
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
                <li class="nav-item"><a class="nav-link active" href="aszf.php">ÁSZF</a></li>
                <li class="nav-item"><a class="nav-link" href="adatkezeles.php">Adatkezelés</a></li>
                <li class="nav-item"><a class="nav-link" href="kapcsolat.php">Kapcsolat</a></li>
            </ul>
        </div>
    </nav>
</header>

<main class="container py-5">
    <div class="gc-hero-card p-4 rounded shadow-sm bg-secondary">
        <h1 class="display-5 fw-bold mb-4">Általános Szerződési Feltételek (ÁSZF)</h1>
        <p class="lead text-light-50">Ez egy minta ÁSZF. Élesítés előtt jogi ellenőrzés szükséges.</p>

        <h2 class="h4 mt-4">1. Szolgáltató adatai</h2>
        <p>GameCube<br>Email: <a href="mailto:gamecube041726@gmail.com" class="text-decoration-underline text-light">gamecube041726@gmail.com</a></p>

        <h2 class="h4 mt-3">2. A szolgáltatás tárgya</h2>
        <p>Digitális játék kulcsok értékesítése és bemutatása.</p>

        <h2 class="h4 mt-3">3. Szerződés létrejötte</h2>
        <p>A szerződés a megrendelés visszaigazolásával jön létre.</p>

        <h2 class="h4 mt-3">4. Fizetés és szállítás</h2>
        <p>Elfogadott fizetési módok: bankkártya, PayPal. A kulcsok azonnal kézbesítésre kerülnek.</p>

        <h2 class="h4 mt-3">5. Elállás, jótállás</h2>
        <p>A fogyasztót a hatályos jogszabályok szerinti elállási jog illeti meg.</p>

        <h2 class="h4 mt-3">6. Adatkezelés</h2>
        <p>Személyes adatok kezelése az <a href="adatkezeles.php" class="text-decoration-underline text-light">Adatkezelés</a> oldalon.</p>
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
