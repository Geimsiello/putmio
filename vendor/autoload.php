<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'PHPMailer\\PHPMailer\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/phpmailer/phpmailer/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
