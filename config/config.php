<?php

return [
    'app' => [
        'name' => 'Fry\'s Grocery Workspace',
        'env' => getenv('APP_ENV') ?: 'local',
        'debug' => filter_var(getenv('APP_DEBUG') ?: '1', FILTER_VALIDATE_BOOL),
    ],
    'db' => [
        'dsn' => getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=kroger;charset=utf8mb4',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'kroger' => [
        'client_id' => getenv('KROGER_CLIENT_ID') ?: '',
        'client_secret' => getenv('KROGER_CLIENT_SECRET') ?: '',
        'base_url' => getenv('KROGER_BASE_URL') ?: 'https://api.kroger.com/v1',
        'default_zip_code' => getenv('KROGER_DEFAULT_ZIP') ?: '85281',
        'default_location_id' => getenv('KROGER_LOCATION_ID') ?: '',
        'default_store_id' => getenv('KROGER_STORE_ID') ?: '',
    ],
];
