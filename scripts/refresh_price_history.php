<?php
declare(strict_types=1);

use App\Repositories\ProductRepository;

$config = require __DIR__ . '/../bootstrap/app.php';
$legacy = $config['legacy'];

$pdo = new PDO(
    $legacy['db']['dsn'],
    $legacy['db']['username'] ?? ($legacy['db']['user'] ?? null),
    $legacy['db']['password'] ?? ($legacy['db']['pass'] ?? null),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$repo = new ProductRepository($pdo);
$sample = $repo->latest(5);

echo json_encode([
    'ok' => true,
    'result' => [
        'products_checked' => count($sample),
        'timestamp' => date('c'),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
