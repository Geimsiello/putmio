<?php

declare(strict_types=1);

namespace PutMio\Router;

use PutMio\Controllers\Tv\AuthController;
use PutMio\Controllers\Tv\HomeController;
use PutMio\View;

final class TvRouter
{
    public function dispatch(string $path, string $method): void
    {
        if ($path === '/tv') {
            $path = '/tv/';
        }

        $routes = [
            'GET' => [
                '/tv/' => [HomeController::class, 'index'],
                '/tv/login' => [AuthController::class, 'loginForm'],
                '/tv/logout' => [AuthController::class, 'logout'],
            ],
        ];

        $handler = $routes[$method][$path] ?? null;
        if (!$handler) {
            http_response_code(404);
            View::render('tv/errors/404', ['title' => 'Pagina non trovata'], 'tv/layout');
            return;
        }

        [$class, $action] = $handler;
        (new $class())->$action();
    }
}
