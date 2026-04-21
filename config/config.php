<?php
if (!function_exists('env_value')) {
    function env_value(string $key, ?string $default = null): ?string {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!function_exists('env_kroger_location_id')) {
    function env_kroger_location_id(): string {
        $locationId = trim((string) env_value('KROGER_LOCATION_ID', ''));
        if (preg_match('/^[A-Za-z0-9]{8}$/', $locationId)) {
            return $locationId;
        }

        $storeId = trim((string) env_value('KROGER_STORE_ID', ''));
        if (preg_match('/^[A-Za-z0-9]{8}$/', $storeId)) {
            return $storeId;
        }

        return '';
    }
}

return [
    'db' => [
        'dsn' => env_value('DB_DSN', 'mysql:host=localhost;dbname=kroger;charset=utf8mb4'),
        'user' => env_value('DB_USER', 'root'),
        'pass' => env_value('DB_PASS', ''),
    ],
    'kroger' => [
        'client_id' => env_value('KROGER_CLIENT_ID', ''),
        'client_secret' => env_value('KROGER_CLIENT_SECRET', ''),
        'base_url' => env_value('KROGER_BASE_URL', 'https://api.kroger.com/v1'),
        'default_location_id' => env_kroger_location_id(),
        'default_store_id' => trim((string) env_value('KROGER_STORE_ID', '')),
        'default_zip_code' => env_value('KROGER_ZIP_CODE', '85281'),
    ],
];
