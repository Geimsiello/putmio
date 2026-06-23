<?php

declare(strict_types=1);

namespace PutMio;

use PutMio\Auth\AuthService;
use PutMio\Auth\Csrf;
use PutMio\Auth\Session;
use PutMio\Controllers\AdminController;
use PutMio\Controllers\ApiController;
use PutMio\Controllers\AuthController;
use PutMio\Controllers\CatalogController;
use PutMio\Controllers\CronController;
use PutMio\Controllers\HomeController;
use PutMio\Controllers\PlayerController;
use PutMio\Controllers\SubtitleController;
use PutMio\Install\InstallGate;

final class Router
{
    public function dispatch(): void
    {
        if (InstallGate::handleIfNotInstalled()) {
            return;
        }

        InstallGate::ensureInstalled();
        Config::load();
        \PutMio\Database\Migrator::runPending();
        date_default_timezone_set(Config::get('app.timezone', 'Europe/Rome'));
        Session::start();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $this->path();

        if ($path === '/index.php' || $path === '/front.php') {
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            $base = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');
            header('Location: ' . $base . '/' . ($qs !== '' ? '?' . $qs : ''), true, 302);
            exit;
        }

        if ($method === 'GET' && ($path === '' || $path === '/')) {
            (new HomeController())->index();
            return;
        }

        $routes = [
            'GET' => [
                '/login' => [AuthController::class, 'loginForm'],
                '/logout' => [AuthController::class, 'logout'],
                '/forgot-password' => [AuthController::class, 'forgotForm'],
                '/reset-password' => [AuthController::class, 'resetForm'],
                '/registrati' => [AuthController::class, 'registerForm'],
                '/in-corso' => [CatalogController::class, 'inProgress'],
                '/catalogo' => [CatalogController::class, 'index'],
                '/media' => [CatalogController::class, 'show'],
                '/play' => [PlayerController::class, 'show'],
                '/admin' => [AdminController::class, 'dashboard'],
                '/admin/impostazioni' => [AdminController::class, 'settings'],
                '/admin/classificazione' => [AdminController::class, 'classify'],
                '/admin/streaming' => [AdminController::class, 'streaming'],
                '/admin/utenti' => [AdminController::class, 'users'],
                '/admin/oauth/putio/callback' => [AdminController::class, 'putioCallback'],
                '/stream' => [PlayerController::class, 'stream'],
                '/cron/sync' => [CronController::class, 'sync'],
                '/api/tmdb/search' => [ApiController::class, 'tmdbSearch'],
                '/api/tmdb/details' => [ApiController::class, 'tmdbDetails'],
                '/api/tmdb/classify-suggest' => [ApiController::class, 'tmdbClassifySuggest'],
                '/api/catalog/items' => [ApiController::class, 'catalogItems'],
                '/api/subtitles' => [SubtitleController::class, 'list'],
                '/api/subtitles/search' => [SubtitleController::class, 'search'],
                '/subtitles/serve' => [SubtitleController::class, 'serve'],
                '/poster' => [CatalogController::class, 'poster'],
                '/backdrop' => [CatalogController::class, 'backdrop'],
            ],
            'POST' => [
                '/login' => [AuthController::class, 'login'],
                '/forgot-password' => [AuthController::class, 'forgot'],
                '/reset-password' => [AuthController::class, 'reset'],
                '/registrati' => [AuthController::class, 'register'],
                '/api/preferences/theme' => [ApiController::class, 'theme'],
                '/api/watch-progress' => [ApiController::class, 'watchProgress'],
                '/api/tmdb/apply' => [ApiController::class, 'tmdbApply'],
                '/api/tmdb/classify-apply' => [ApiController::class, 'tmdbClassifyApplyBulk'],
                '/api/putio/sync-friends' => [ApiController::class, 'putioSyncFriends'],
                '/api/putio/sync' => [ApiController::class, 'putioSync'],
                '/api/subtitles/download' => [SubtitleController::class, 'download'],
                '/api/subtitles/preference' => [SubtitleController::class, 'preference'],
                '/api/subtitles/delete' => [SubtitleController::class, 'delete'],
                '/api/opensubtitles/test' => [SubtitleController::class, 'testOpenSubtitles'],
                '/admin/impostazioni' => [AdminController::class, 'saveSettings'],
                '/admin/sync' => [AdminController::class, 'sync'],
                '/admin/refresh-putio-friends' => [AdminController::class, 'refreshPutioFriends'],
                '/admin/classificazione' => [AdminController::class, 'saveClassification'],
                '/admin/inviti' => [AdminController::class, 'createInvite'],
                '/admin/disconnect-putio' => [AdminController::class, 'disconnectPutio'],
                '/admin/streaming/stop-all' => [AdminController::class, 'stopAllStreams'],
            ],
        ];

        $handler = $routes[$method][$path] ?? null;
        if (!$handler) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Non trovato'], 'layout');
            return;
        }

        [$class, $action] = $handler;
        (new $class())->$action();
    }

    private function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $base = rtrim(parse_url(Config::get('app.url', putmio_detect_base_url()), PHP_URL_PATH) ?? '/putmio', '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }
        $uri = '/' . trim($uri, '/');
        if ($uri === '//') {
            $uri = '/';
        }
        if ($uri !== '/' && str_contains($uri, '?')) {
            $uri = explode('?', $uri, 2)[0];
        }
        return $uri === '' ? '/' : $uri;
    }
}
