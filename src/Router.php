<?php

declare(strict_types=1);

namespace PutMio;

use PutMio\Auth\AuthService;
use PutMio\Auth\Csrf;
use PutMio\Auth\Session;
use PutMio\Controllers\AdminController;
use PutMio\Controllers\AccountController;
use PutMio\Controllers\ApiController;
use PutMio\Controllers\AuthController;
use PutMio\Controllers\CatalogController;
use PutMio\Controllers\CronController;
use PutMio\Controllers\DeviceAuthController;
use PutMio\Controllers\HomeController;
use PutMio\Controllers\PlayerController;
use PutMio\Controllers\PwaController;
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
                '/authorize-device' => [AuthController::class, 'authorizeDeviceForm'],
                '/logout' => [AuthController::class, 'logout'],
                '/forgot-password' => [AuthController::class, 'forgotForm'],
                '/reset-password' => [AuthController::class, 'resetForm'],
                '/registrati' => [AuthController::class, 'registerForm'],
                '/in-corso' => [CatalogController::class, 'inProgress'],
                '/account' => [AccountController::class, 'settings'],
                '/account/dispositivi' => [AccountController::class, 'devices'],
                '/account/contenuti' => [AccountController::class, 'content'],
                '/catalogo' => [CatalogController::class, 'index'],
                '/media' => [CatalogController::class, 'show'],
                '/play' => [PlayerController::class, 'show'],
                '/admin' => [AdminController::class, 'dashboard'],
                '/admin/impostazioni' => [AdminController::class, 'settings'],
                '/admin/classificazione' => [AdminController::class, 'classify'],
                '/admin/streaming' => [AdminController::class, 'streaming'],
                '/admin/sincronizzazioni' => [AdminController::class, 'syncLog'],
                '/admin/utenti' => [AdminController::class, 'users'],
                '/admin/dispositivi' => [AdminController::class, 'devices'],
                '/admin/aggiornamenti' => [AdminController::class, 'updates'],
                '/admin/oauth/putio/callback' => [AdminController::class, 'putioCallback'],
                '/stream' => [PlayerController::class, 'stream'],
                '/cron/sync' => [CronController::class, 'sync'],
                '/cron/sync-subtitles' => [CronController::class, 'syncSubtitles'],
                '/api/tmdb/search' => [ApiController::class, 'tmdbSearch'],
                '/api/tmdb/details' => [ApiController::class, 'tmdbDetails'],
                '/api/tmdb/classify-suggest' => [ApiController::class, 'tmdbClassifySuggest'],
                '/api/auth/device/status' => [DeviceAuthController::class, 'status'],
                '/api/catalog/items' => [ApiController::class, 'catalogItems'],
                '/api/subtitles' => [SubtitleController::class, 'list'],
                '/api/subtitles/search' => [SubtitleController::class, 'search'],
                '/subtitles/serve' => [SubtitleController::class, 'serve'],
                '/poster' => [CatalogController::class, 'poster'],
                '/backdrop' => [CatalogController::class, 'backdrop'],
                '/manifest.webmanifest' => [PwaController::class, 'manifest'],
                '/.well-known/web-app-origin-association' => [PwaController::class, 'originAssociation'],
            ],
            'POST' => [
                '/login' => [AuthController::class, 'login'],
                '/api/auth/device/start' => [DeviceAuthController::class, 'start'],
                '/api/auth/device/complete' => [DeviceAuthController::class, 'complete'],
                '/api/auth/device/approve' => [DeviceAuthController::class, 'approve'],
                '/api/auth/device/deny' => [DeviceAuthController::class, 'deny'],
                '/forgot-password' => [AuthController::class, 'forgot'],
                '/reset-password' => [AuthController::class, 'reset'],
                '/registrati' => [AuthController::class, 'register'],
                '/account/dispositivi/revoca' => [AccountController::class, 'revokeDevice'],
                '/api/preferences/locale' => [ApiController::class, 'locale'],
                '/api/account/catalog-sources' => [ApiController::class, 'catalogSources'],
                '/api/watch-progress' => [ApiController::class, 'watchProgress'],
                '/api/tmdb/apply' => [ApiController::class, 'tmdbApply'],
                '/api/tmdb/classify-apply' => [ApiController::class, 'tmdbClassifyApplyBulk'],
                '/api/series/merge-duplicates' => [ApiController::class, 'mergeDuplicateSeries'],
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
                '/admin/utenti/elimina' => [AdminController::class, 'deleteUser'],
                '/admin/disconnect-putio' => [AdminController::class, 'disconnectPutio'],
                '/admin/streaming/stop-all' => [AdminController::class, 'stopAllStreams'],
                '/admin/dispositivi/revoca' => [AdminController::class, 'revokeDevice'],
                '/admin/aggiornamenti/applica' => [AdminController::class, 'applyUpdate'],
                '/admin/aggiornamenti/ricontrolla' => [AdminController::class, 'refreshUpdates'],
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
