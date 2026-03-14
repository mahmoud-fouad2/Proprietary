<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'ZACO Assets',
        // Deployed URL: https://zaco.sa/assets
        // Keep it as '/assets' on the server.
        'base_path' => '/assets',
        // Optional: full public URL (used in emails/CLI scripts to build absolute links)
        // Example: https://example.com/assets
        'public_url' => '',
        'env' => 'development', // development | production
        // CHANGE THIS on the server to a long random value (>= 32 chars)
        'secret_key' => 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET',
        'timezone' => 'Asia/Riyadh',
    ],
    'db' => [
        // Example: mysql:host=localhost;dbname=zaco_assets;charset=utf8mb4
        'dsn' => 'mysql:host=localhost;dbname=zaco_assets;charset=utf8mb4',
        'user' => 'root',
        'pass' => '',
    ],

    // Outbound email (SMTP). Disabled by default.
    // Put real credentials in config.local.php (do NOT commit secrets).
    'mail' => [
        'enabled' => false,
        'from_email' => '',
        'from_name' => 'ZACO Assets',
        'smtp' => [
            'host' => '',
            'port' => 587,
            'encryption' => 'tls', // tls | ssl | ''
            'auth' => true,
            'username' => '',
            'password' => '',
            'timeout' => 10,
        ],
        'cleaning' => [
            // Daily report submission recipient
            'daily_to' => 'f.waleed@bfi.sa',
            // Weekly report recipient
            'weekly_to' => 'm.fouad@zaco.sa',
        ],
    ],
];
