<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Config;

final class PwaController
{
    public function manifest(): void
    {
        $base = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');
        $parsed = parse_url($base);
        $origin = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'localhost');
        $path = $parsed['path'] ?? '';
        $scope = ($path !== '' ? rtrim($path, '/') : '') . '/';
        $startUrl = $scope;
        $authorizePath = rtrim($scope, '/') . '/authorize-device';

        $manifest = [
            'id' => $scope,
            'name' => 'PutMio',
            'short_name' => 'PutMio',
            'description' => 'Media center personale su put.io',
            'start_url' => $startUrl,
            'scope' => $scope,
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#0b1326',
            'theme_color' => '#0b1326',
            'lang' => 'it',
            'handle_links' => 'preferred',
            'launch_handler' => [
                'client_mode' => 'focus-existing',
            ],
            'capture_links' => 'existing-client-navigate',
            'url_handlers' => [
                [
                    'origin' => $origin,
                    'paths' => [
                        $authorizePath,
                        $authorizePath . '/*',
                    ],
                ],
            ],
            'icons' => [
                [
                    'src' => $base . '/public/assets/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $base . '/public/assets/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $base . '/public/assets/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        header('Content-Type: application/manifest+json; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function originAssociation(): void
    {
        $base = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');
        $path = parse_url($base, PHP_URL_PATH) ?? '';
        $scope = ($path !== '' ? rtrim($path, '/') : '') . '/';
        $authorizePath = rtrim($scope, '/') . '/authorize-device';

        $data = [
            'web_apps' => [
                [
                    'manifest' => $base . '/manifest.webmanifest',
                    'details' => [
                        'paths' => [$authorizePath, $authorizePath . '/*'],
                        'excludePaths' => [],
                    ],
                ],
            ],
        ];

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: public, max-age=86400');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
