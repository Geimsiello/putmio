<?php
/**
 * PutMio — Template configurazione.
 * Il file config.php viene generato automaticamente dal wizard di installazione.
 */
return [
    'app' => [
        'name' => 'PutMio',
        'url' => 'https://example.com/putmio',
        'timezone' => 'Europe/Rome',
        'encryption_key' => 'CHANGE_ME_64_char_hex',
        'cron_token' => 'CHANGE_ME_random_token',
        'stream_complete_ratio' => 0.90,
        'stream_min_progress_ratio' => 0.05,
        'max_concurrent_streams_per_ip' => 4,
    ],
    'db' => [
        'host' => 'localhost',
        'name' => 'database',
        'user' => 'user',
        'pass' => 'password',
        'prefix' => 'pm_',
        'charset' => 'utf8mb4',
    ],
    'smtp' => [
        'enabled' => false,
        'host' => '',
        'port' => 587,
        'user' => '',
        'pass' => '',
        'from_email' => '',
        'from_name' => 'PutMio',
    ],
    'putio' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => '',
    ],
    'tmdb' => [
        'api_key' => '',
        'language' => 'it-IT',
    ],
];
