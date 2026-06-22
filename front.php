<?php

declare(strict_types=1);

require __DIR__ . '/src/Bootstrap.php';

PutMio\Bootstrap::init();

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo 'PutMio richiede PHP 7.4+. Versione attuale: ' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8');
    exit;
}

if (PutMio\Install\InstallGate::handleIfNotInstalled()) {
    exit;
}

try {
    (new PutMio\Router())->dispatch();
} catch (Throwable $e) {
    $logDir = __DIR__ . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents(
        $logDir . '/app.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<h1>Errore PutMio</h1><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
}
