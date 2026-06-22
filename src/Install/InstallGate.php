<?php

declare(strict_types=1);

namespace PutMio\Install;

use PutMio\Auth\Session;

final class InstallGate
{
    public static function isInstalled(): bool
    {
        return putmio_is_installed();
    }

    public static function ensureInstalled(): void
    {
        if (!self::isInstalled()) {
            return;
        }
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        if (preg_match('#/(install|installazione)(/|$)#i', $uri)) {
            putmio_redirect('login');
        }
    }

    public static function handleIfNotInstalled(): bool
    {
        if (self::isInstalled()) {
            return false;
        }

        Session::start();

        $controller = new InstallController();
        $controller->handle();
        return true;
    }
}
