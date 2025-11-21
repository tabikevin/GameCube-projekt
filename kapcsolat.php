<?php
// kapcsolat.php
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GameCube – Kapcsolat</title>
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
                <li class="nav-item"><a class="nav-link" href="adatkezeles.php">Adatkezelés</a></li>
                <li class="nav-item"><a class="nav-link active" href="kapcsolat.php">Kapcsolat</a></li>
            </ul>
        </div>
    </nav>
</header>

<main class="container py-5">
    <div class="gc-hero-card p-4 rounded shadow-sm bg-secondary">
        <h1 class="display-5 fw-bold mb-4">Kapcsolat</h1>
        <p class="lead text-light-50">Írj nekünk, és mi gyorsan válaszolunk.</p>

        <form action="contact.php" method="post" class="mt-4">
            <div class="mb-3">
                <label for="name" class="form-label">Név</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email cím</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="message" class="form-label">Üzenet</label>
                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary gc-btn-main">Küldés</button>
        </form>
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
