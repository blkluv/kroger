<?php
require __DIR__ . '/../src/Core/Database.php';
require __DIR__ . '/../src/Repositories/GroceryListRepository.php';

$envPath = dirname(__DIR__) . '/.env';
if (is_file($envPath)) {
    $env = parse_ini_file($envPath);
    if (is_array($env)) {
        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
        }
    }
}

$db = Database::getConnection();
$repo = new GroceryListRepository($db);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die('Invalid item ID.');
}

$item = $repo->getItemById($id);
if (!$item) {
    die('Item not found.');
}

$rawJson = $item['raw_json'] ?? null;
$decodedJson = null;
if (is_string($rawJson) && $rawJson !== '') {
    $decodedJson = json_decode($rawJson, true);
}

$primaryAisleLocation = null;
if (is_array($decodedJson['aisleLocations'] ?? null) && !empty($decodedJson['aisleLocations'][0])) {
    $primaryAisleLocation = $decodedJson['aisleLocations'][0];
}

$aisleDisplay = $item['aisle_locations'] ?? 'N/A';
if (is_array($primaryAisleLocation) && !empty($primaryAisleLocation['description'])) {
    $aisleDisplay = (string) $primaryAisleLocation['description'];
}

$shelfParts = [];
if (is_array($primaryAisleLocation)) {
    if (!empty($primaryAisleLocation['shelfNumber'])) {
        $shelfParts[] = 'Shelf ' . $primaryAisleLocation['shelfNumber'];
    }
    if (!empty($primaryAisleLocation['bayNumber'])) {
        $shelfParts[] = 'Bay ' . $primaryAisleLocation['bayNumber'];
    }
    if (!empty($primaryAisleLocation['shelfPositionInBay'])) {
        $shelfParts[] = 'Position ' . $primaryAisleLocation['shelfPositionInBay'];
    }
    if (!empty($primaryAisleLocation['side'])) {
        $side = strtoupper((string) $primaryAisleLocation['side']) === 'R' ? 'Right Side' : ((string) $primaryAisleLocation['side'] === 'L' ? 'Left Side' : (string) $primaryAisleLocation['side']);
        $shelfParts[] = $side;
    }
}
$shelfDisplay = !empty($shelfParts) ? implode(' · ', $shelfParts) : 'N/A';

$temperatureDisplay = $item['temperature'] ?? 'N/A';
if (is_array($decodedJson['temperature'] ?? null)) {
    $temperatureParts = [];
    if (!empty($decodedJson['temperature']['indicator'])) {
        $temperatureParts[] = (string) $decodedJson['temperature']['indicator'];
    }
    if (array_key_exists('heatSensitive', $decodedJson['temperature'])) {
        $temperatureParts[] = !empty($decodedJson['temperature']['heatSensitive']) ? 'Heat Sensitive' : 'Not Heat Sensitive';
    }
    $temperatureDisplay = !empty($temperatureParts) ? implode(' · ', $temperatureParts) : 'N/A';
}

$title = htmlspecialchars($item['custom_name'] ?: ($item['description'] ?? 'Cart Item'), ENT_QUOTES, 'UTF-8');
$imageUrl = htmlspecialchars($item['image_url'] ?: 'https://via.placeholder.com/320x320?text=No+Image', ENT_QUOTES, 'UTF-8');
$itemId = (int) $item['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kroger Cart Item Detail</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-dark">
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/img/kroger_logo.svg" alt="Kroger" class="brand-logo">
            <span class="brand-wordmark">Kroger Cart</span>
        </div>

        <div class="sidebar-account-action">
            <button class="btn-primary sidebar-signin-btn" data-external-url="https://www.frysfood.com/auth/signin">
                <i data-feather="user"></i>
                <span>Sign In To Fry's</span>
            </button>
        </div>

        <nav class="sidebar-nav">
            <button class="nav-item" onclick="window.location='index.php'">
                <i data-feather="activity"></i>
                <span>Overview</span>
            </button>

            <button class="nav-item" onclick="window.location='index.php#search-panel'">
                <i data-feather="search"></i>
                <span>Browse Items</span>
            </button>

            <button class="nav-item nav-item-active" onclick="window.location='index.php#cart-panel'">
                <i data-feather="list"></i>
                <span>Cart</span>
            </button>

            <button class="nav-item" onclick="window.location='index.php#deals-panel'">
                <i data-feather="tag"></i>
                <span>Deals</span>
            </button>

            <button class="nav-item" onclick="window.location='index.php#account-panel'">
                <i data-feather="external-link"></i>
                <span>Fry's Links</span>
            </button>
        </nav>
    </aside>

    <main class="main">
        <header class="main-header">
            <div class="header-left">
                <h1 class="main-title"><?= $title ?></h1>
                <p class="main-subtitle">
                    Price tracking and product detail for your saved item.
                </p>
            </div>
        </header>

        <section class="card card-tall">
            <header class="card-header">
                <h2 class="card-title">Product Overview</h2>
                <span class="card-meta">
                    <!-- Back Button -->
                    <button class="btn btn-back" onclick="window.history.back()">
                        <i data-feather="arrow-left"></i>
                        Back
                    </button>
                </span>
            </header>

            <div class="card-body item-detail-body">
                <div class="item-image-wrapper">
                    <img src="<?= $imageUrl ?>" class="item-image" alt="<?= $title ?>">
                </div>

                <div class="item-details">
                    <div class="evidence-card detail-grid">
                        <div class="evidence-label">Description</div>
                        <div class="evidence-value"><?= htmlspecialchars($item['description'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="evidence-label">Brand</div>
                        <div class="evidence-value"><?= htmlspecialchars($item['brand'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="evidence-label">Size</div>
                        <div class="evidence-value"><?= htmlspecialchars($item['size'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="evidence-label">UPC</div>
                        <div class="evidence-value"><?= htmlspecialchars($item['upc'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="evidence-label">Quantity</div>
                        <div class="evidence-value"><?= (int) ($item['quantity'] ?? 1) ?></div>

                        <div class="evidence-label">Regular Price</div>
                        <div class="evidence-value"><?= $item['regular_price'] !== null ? '$' . number_format((float) $item['regular_price'], 2) : 'N/A' ?></div>

                        <div class="evidence-label">Sale Price</div>
                        <div class="evidence-value"><?= $item['sale_price'] !== null ? '$' . number_format((float) $item['sale_price'], 2) : 'N/A' ?></div>

                        <div class="evidence-label">Promo</div>
                        <div class="evidence-value"><?= htmlspecialchars($item['promo_description'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="evidence-label">Aisle Location</div>
                        <div class="evidence-value"><?= htmlspecialchars($aisleDisplay ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="evidence-label">Shelf</div>
                        <div class="evidence-value"><?= htmlspecialchars($shelfDisplay, ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="evidence-label">Categories</div>
                        <div class="evidence-value"><?= htmlspecialchars($item['categories'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="evidence-label">Country Of Origin</div>
                        <div class="evidence-value"><?= htmlspecialchars($item['country_origin'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="evidence-label">Temperature</div>
                        <div class="evidence-value"><?= htmlspecialchars($temperatureDisplay ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Price History</h2>
                <span class="card-meta">Daily snapshots for your selected store</span>
            </header>

            <div class="card-body">
                <div class="price-history-summary" id="price-history-summary">
                    <div class="summary-pill">
                        <span>Latest Price</span>
                        <strong id="price-history-latest">$0.00</strong>
                    </div>
                    <div class="summary-pill">
                        <span>Starting Price</span>
                        <strong id="price-history-start">$0.00</strong>
                    </div>
                    <div class="summary-pill">
                        <span>Change</span>
                        <strong id="price-history-change">No change</strong>
                    </div>
                </div>

                <div class="price-history-chart-container">
                    <canvas id="priceHistoryChart" data-item-id="<?= $itemId ?>"></canvas>
                </div>
            </div>
        </section>

        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Raw Data</h2>
                <span class="card-meta">Stored Kroger payload</span>
            </header>

            <div class="card-body">
                <div class="json-toggle" onclick="toggleJSON(this)">View Raw JSON</div>
                <div class="json-panel"><?= htmlspecialchars(json_encode($decodedJson ?: $item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </section>
    </main>
</div>

<script src="assets/js/app.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/feather-icons"></script>
<script>feather.replace();</script>
</body>
</html>
