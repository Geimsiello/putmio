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
        // true = redirect al CDN put.io dopo auth (consigliato su hosting condiviso OVH)
        'stream_via_redirect' => true,
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
    // Aggiornamenti core da GitHub Releases (Admin → Aggiornamenti)
    'updates' => [
        'github_repo' => '', // es. 'Geimsiello/putmio'
        // Consigliato su hosting condiviso (OVH): senza token GitHub limita a ~60 richieste/ora per IP.
        // Crea un PAT su GitHub → Settings → Developer settings → Personal access tokens
        // (scope "public_repo" o fine-grained read-only sul repository).
        'github_token' => '',
    ],
];
