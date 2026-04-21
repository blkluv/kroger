<?php
require __DIR__ . '/../src/Core/Database.php';
require __DIR__ . '/../src/Core/KrogerClient.php';
require __DIR__ . '/../src/Repositories/GroceryListRepository.php';
require __DIR__ . '/../src/Services/GroceryService.php';

$envPath = dirname(__DIR__) . '/.env';
if (is_file($envPath)) {
    $env = parse_ini_file($envPath);
    if (is_array($env)) {
        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
        }
    }
}

$config = require __DIR__ . '/../config/config.php';
$db = Database::getConnection();
$kroger = new KrogerClient($config['kroger']);
$service = new GroceryService($db, $kroger);
$repo = new GroceryListRepository($db);

$result = $service->refreshTrackedPriceHistory(
    1,
    $repo,
    (string) ($config['kroger']['default_location_id'] ?? ''),
    (string) ($config['kroger']['default_zip_code'] ?? ''),
    (string) ($config['kroger']['default_store_id'] ?? '')
);

echo json_encode([
    'ok' => true,
    'result' => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
