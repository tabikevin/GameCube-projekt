<?php
session_start();
require_once "db.php";

$username = $_SESSION["username"] ?? null;
$user_id = $_SESSION["user_id"] ?? null;

$products = [];

$result = $conn->query("
    SELECT id, name, platform, short_description, price, tag
    FROM products
    WHERE is_active = 1
    ORDER BY id ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $result->free();
}

function gc_platform_badge_class(string $platform): string
{
    switch ($platform) {
        case "ps":
            return "bg-primary";
        case "xbox":
            return "bg-success";
        case "switch":
            return "bg-danger";
        default:
            return "bg-warning text-dark";
    }
}

function gc_platform_label(string $platform): string
{
    switch ($platform) {
        case "ps":
            return "PS";
        case "xbox":
            return "Xbox";
        case "switch":
            return "Switch";
        default:
            return "PC";
    }
}
function gc_image_file(string $name): string
{
    $map = [
        "Cyberpunk 2077"              => "cyberpunk2077.png",
        "Elden Ring"                  => "eldenring.png",
        "EA Sports FC 25"             => "fc25.png",
        "GTA V Premium Edition"       => "gta5.png",
        "The Witcher 3: Wild Hunt"    => "thewitcher3.png",
        "Minecraft Java & Bedrock"    => "minecraft.png",
        "Valorant Points 4750"        => "valorantpoints.png",
        "Steam Wallet 10€"            => "steam.png",
    ];

    return $map[$name] ?? "steam.png";
}

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameCube – Digitális játék kulcsok</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    >
    <link rel="stylesheet" href="main.css">
</head>
<body>
<header class="gc-header shadow-sm">
    <nav class="navbar navbar-expand-lg navbar-dark container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="main.php">
            <span class="gc-logo-square"></span>
            <span class="fw-bold">GameCube</span>
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav"
            aria-controls="navbarNav"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" href="main.php">Főoldal</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#games">Játékok</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#offers">Akciók</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="cart.php">Kosár</a>
                </li>
                <?php if ($user_id): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profilom</a>
                    </li>
                    <?php
                    $stmtRole = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
                    $stmtRole->bind_param("i", $user_id);
                    $stmtRole->execute();
                    $resRole = $stmtRole->get_result();
                    $u = $resRole->fetch_assoc();
                    $stmtRole->close();
                    if ($u && $u["role"] === "admin"): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Admin</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <form class="d-flex me-3 gc-search-form" role="search">
                <input
                    id="searchInput"
                    class="form-control form-control-sm me-2"
                    type="search"
                    placeholder="Keresés játék cím alapján..."
                    aria-label="Keresés"
                >
                <button class="btn btn-outline-light btn-sm" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </form>

            <div class="d-flex align-items-center gc-header-icons">
                <?php if ($username): ?>
                    <span class="me-2 small text-light-50">
                        Szia, <?php echo htmlspecialchars($username, ENT_QUOTES, "UTF-8"); ?>!
                    </span>
                    <a href="logout.php" class="btn btn-sm btn-outline-light gc-btn-ghost me-2">
                        Kijelentkezés
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-sm btn-outline-light gc-btn-ghost me-2">
                        Bejelentkezés
                    </a>
                    <a href="register.php" class="btn btn-sm btn-primary gc-btn-main me-2">
                        Regisztráció
                    </a>
                <?php endif; ?>

                <a href="cart.php" class="btn btn-link text-light position-relative gc-icon-btn">
                    <i class="bi bi-cart3 fs-5"></i>
                </a>
            </div>
        </div>
    </nav>
</header>

<section class="gc-hero py-5">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-md-7">
                <h1 class="display-5 fw-bold mb-3">
                    Digitális játék kulcsok<br>
                    <span class="gc-gradient-text">azonnali kézbesítéssel</span>
                </h1>
                <p class="lead text-light-50 mb-4">
                    Vásárolj biztonságos, ellenőrzött játék kulcsokat PC-re és konzolra.
                    Az aktiválás néhány kattintás, a kulcs pedig azonnal megérkezik.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="#games" class="btn btn-primary gc-btn-main">
                        Böngészés
                    </a>
                    <a href="cart.php" class="btn btn-outline-light gc-btn-ghost">
                        Kosár megnyitása
                    </a>
                </div>
            </div>
            <div class="col-md-5">
                <div class="gc-hero-card p-4">
                    <h2 class="h5 mb-3">Top ajánlatok ma</h2>
                    <ul class="list-unstyled mb-0 gc-hero-list">
                        <li class="d-flex justify-content-between align-items-center mb-2">
                            <span>Cyberpunk 2077</span>
                            <span class="gc-price">8 990 Ft</span>
                        </li>
                        <li class="d-flex justify-content-between align-items-center mb-2">
                            <span>Elden Ring</span>
                            <span class="gc-price">12 990 Ft</span>
                        </li>
                        <li class="d-flex justify-content-between align-items-center">
                            <span>EA FC 25</span>
                            <span class="gc-price">15 490 Ft</span>
                        </li>
                    </ul>
                    <small class="text-light-50 d-block mt-3">
                        Minden kulcs hivatalos partnerünktől származik.
                    </small>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-3 border-top border-dark">
    <div class="container">
        <div class="row g-3 align-items-center">
            <div class="col-md-4">
                <label for="platformFilter" class="form-label gc-filter-label mb-1">Platform</label>
                <select id="platformFilter" class="form-select form-select-sm gc-select">
                    <option value="all">Összes</option>
                    <option value="pc">PC (Steam / Epic / Uplay)</option>
                    <option value="ps">PlayStation</option>
                    <option value="xbox">Xbox</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="tagFilter" class="form-label gc-filter-label mb-1">Kategória</label>
                <select id="tagFilter" class="form-select form-select-sm gc-select">
                    <option value="all">Összes</option>
                    <option value="top">Top seller</option>
                    <option value="new">Új megjelenés</option>
                    <option value="sale">Akciós</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="sortSelect" class="form-label gc-filter-label mb-1">Rendezés</label>
                <select id="sortSelect" class="form-select form-select-sm gc-select">
                    <option value="default">Alapértelmezett</option>
                    <option value="price-asc">Ár (növekvő)</option>
                    <option value="price-desc">Ár (csökkenő)</option>
                </select>
            </div>
        </div>
    </div>
</section>

<main class="py-4" id="games">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">Kiemelt játékok</h2>
            <span class="text-light-50 small">
                <span id="productCount"><?php echo count($products); ?></span> találat
            </span>
        </div>

        <div id="productGrid" class="row g-4">
            <?php foreach ($products as $product): ?>
                <?php
                $platform     = $product["platform"];
                $tag          = $product["tag"];
                $badgeClass   = gc_platform_badge_class($platform);
                $badgeLabel   = gc_platform_label($platform);
                $imageFile    = gc_image_file($product["name"]);
                $price        = (int)$product["price"];
                ?>
                <div
                    class="col-sm-6 col-md-4 col-lg-3 gc-product-wrapper"
                    data-platform="<?php echo htmlspecialchars($platform, ENT_QUOTES, "UTF-8"); ?>"
                    data-tag="<?php echo htmlspecialchars($tag, ENT_QUOTES, "UTF-8"); ?>"
                    data-price="<?php echo $price; ?>"
                >
                    <div class="card gc-product-card h-100">
                        <div class="gc-product-img <?php echo htmlspecialchars($imageClass, ENT_QUOTES, "UTF-8"); ?>">
                            <span class="badge <?php echo $badgeClass; ?> gc-badge">
                                <?php echo htmlspecialchars($badgeLabel, ENT_QUOTES, "UTF-8"); ?>
                            </span>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h3 class="h6 card-title mb-1">
                                <?php echo htmlspecialchars($product["name"], ENT_QUOTES, "UTF-8"); ?>
                            </h3>
                            <p class="card-text gc-platform mb-2">
                                <?php echo htmlspecialchars($product["short_description"] ?? "", ENT_QUOTES, "UTF-8"); ?>
                            </p>
                            <p class="card-text gc-price fs-5 mb-3">
                                <?php echo number_format($price, 0, ".", " "); ?> Ft
                            </p>
                            <form method="post" action="cart.php" class="mt-auto">
                                <input type="hidden" name="product_id"
                                       value="<?php echo (int)$product["id"]; ?>">
                                <button
                                    type="submit"
                                    name="add_to_cart"
                                    class="btn btn-primary w-100 gc-add-cart-btn"
                                >
                                    <i class="bi bi-cart-plus me-1"></i>
                                    Kosárba
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<section class="gc-offers py-5" id="offers">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-md-7">
                <h2 class="h3 mb-3">Villám akciók</h2>
                <p class="text-light-50 mb-3">
                    Limitált ideig elérhető ajánlatok, extra kedvezménnyel.
                    Az akciós árak automatikusan érvényesülnek a kosárban.
                </p>
                <ul class="gc-hero-list mb-0">
                    <li class="d-flex justify-content-between mb-2">
                        <span>GTA V Premium Edition (Xbox)</span>
                        <span class="gc-price">–40%</span>
                    </li>
                    <li class="d-flex justify-content-between mb-2">
                        <span>The Witcher 3: Wild Hunt (PC)</span>
                        <span class="gc-price">–45%</span>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span>Steam Wallet 10€</span>
                        <span class="gc-price">Speciális ár</span>
                    </li>
                </ul>
            </div>
            <div class="col-md-5">
                <div class="gc-offers-card p-4">
                    <h3 class="h6 mb-2">Biztonságos vásárlás</h3>
                    <ul class="mb-0 gc-offer-bullets">
                        <li>
                            <i class="bi bi-shield-check me-2"></i>
                            Pénz-visszafizetési garancia
                        </li>
                        <li>
                            <i class="bi bi-lightning-charge me-2"></i>
                            Azonnali kulcsküldés e-mailben
                        </li>
                        <li>
                            <i class="bi bi-headset me-2"></i>
                            Magyar nyelvű ügyfélszolgálat
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="gc-footer py-4 border-top border-dark">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        <span class="small text-light-50">
            © <span id="yearSpan"></span> GameCube – Digitális játék kulcsok
        </span>
        <div class="gc-footer-links small">
            <a href="#" class="me-3">ÁSZF</a>
            <a href="#" class="me-3">Adatkezelés</a>
            <a href="#">Kapcsolat</a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="main.js"></script>
</body>
</html>
