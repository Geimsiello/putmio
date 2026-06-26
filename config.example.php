<?php
/**
 * PutMio — Configuration template.
 * config.php is generated automatically by the installation wizard.
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
        // true = redirect to put.io CDN after auth (recommended on shared hosting)
        'stream_via_redirect' => true,
        // Player preload: none | metadata | auto (Admin → Settings)
        'player_preload' => 'none',
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
    'opensubtitles' => [
        'api_key' => '',
        'username' => '',
        'password' => '',
        'user_agent' => 'PutMio v1.0',
    ],
    // Core updates from GitHub Releases (Admin → Updates)
    'updates' => [
        'github_repo' => 'Geimsiello/putmio', // change if you maintain a fork
        // Optional PAT: raises GitHub API limit from ~60 to 5000 requests/hour per IP.
        'github_token' => '',
    ],
];
