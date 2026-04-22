<?php
$config = require __DIR__ . '/../config/config.php';
$appName = htmlspecialchars($config['app']['name'], ENT_QUOTES, 'UTF-8');
$defaultZip = htmlspecialchars($config['kroger']['default_zip_code'], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $appName ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand-lockup">
            <div class="brand-mark">F</div>
            <div>
                <div class="brand-name">Fry's</div>
                <div class="brand-subtitle">Grocery Workspace</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <button class="nav-item is-active" data-panel="dashboard">Dashboard</button>
            <button class="nav-item" data-panel="stores">Stores</button>
            <button class="nav-item" data-panel="products">Products</button>
            <button class="nav-item" data-panel="users">Users</button>
            <button class="nav-item" data-panel="sync">Sync</button>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <div class="eyebrow">Live data workspace</div>
                <h1>Fry's Grocery Workspace</h1>
                <p>Search products, inspect stores, and watch pricing movement with a retail-grade layout.</p>
            </div>
            <div class="topbar-actions">
                <label class="searchbox">
                    <span>Search</span>
                    <input id="global-search" type="search" placeholder="Products, stores, users">
                </label>
                <div class="badge">ZIP <?= $defaultZip ?></div>
            </div>
        </header>

        <section class="hero">
            <div class="hero-copy">
                <div class="eyebrow">Built for retail ops</div>
                <h2>One shell for catalog, stores, users, and pricing.</h2>
                <p>Clean navigation, strong contrast, and dense information built around grocery workflows.</p>
            </div>
            <div class="hero-panel">
                <div class="metric"><span>Products</span><strong data-summary="products">0</strong></div>
                <div class="metric"><span>Stores</span><strong data-summary="stores">0</strong></div>
                <div class="metric"><span>Users</span><strong data-summary="users">0</strong></div>
                <div class="metric"><span>24h Price Updates</span><strong data-summary="price_changes_24h">0</strong></div>
            </div>
        </section>

        <section class="workspace-grid">
            <section class="panel panel-wide" data-view="dashboard">
                <div class="panel-head">
                    <h3>Dashboard</h3>
                    <span>Recent products and price motion</span>
                </div>
                <div class="chart" id="price-chart"></div>
                <div class="table" id="dashboard-products"></div>
            </section>

            <section class="panel" data-view="stores">
                <div class="panel-head">
                    <h3>Stores</h3>
                    <span>Locations and coverage</span>
                </div>
                <div class="table" id="stores-list"></div>
            </section>

            <section class="panel" data-view="products">
                <div class="panel-head">
                    <h3>Products</h3>
                    <span>Catalog details</span>
                </div>
                <div class="table" id="products-list"></div>
            </section>

            <section class="panel" data-view="users">
                <div class="panel-head">
                    <h3>Users</h3>
                    <span>Identity and list activity</span>
                </div>
                <div class="table" id="users-list"></div>
            </section>

            <section class="panel" data-view="sync">
                <div class="panel-head">
                    <h3>Sync</h3>
                    <span>Operational status</span>
                </div>
                <div class="sync-card">
                    <p>Connect ingestion scripts and cron jobs here when you’re ready to populate the catalog at scale.</p>
                    <code>php scripts/refresh_price_history.php</code>
                </div>
            </section>
        </section>
    </main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
