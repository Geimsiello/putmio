<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Config;

final class PwaController
{
    public function manifest(): void
    {
        $base = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');
        $path = parse_url($base, PHP_URL_PATH) ?? '';
        $scope = ($path !== '' ? rtrim($path, '/') : '') . '/';
        $startUrl = $scope;

        $manifest = [
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
}
