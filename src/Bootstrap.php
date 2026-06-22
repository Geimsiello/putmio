<?php

declare(strict_types=1);

namespace PutMio;

final class Bootstrap
{
    public static function init(): void
    {
        require dirname(__DIR__) . '/src/polyfills.php';
        require dirname(__DIR__) . '/src/helpers.php';

        spl_autoload_register(function (string $class): void {
            $prefix = 'PutMio\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = putmio_base_path() . '/src/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
