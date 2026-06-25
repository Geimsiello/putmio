<?php

declare(strict_types=1);

/**
 * Sync catalogo put.io da cron del hosting.
 * Eseguire solo da CLI: percorso indicato in Admin → Impostazioni (es. ./putmio/cron-sync.php).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/src/Bootstrap.php';

PutMio\Bootstrap::init();

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    fwrite(STDERR, 'PutMio richiede PHP 7.4+. Versione attuale: ' . PHP_VERSION . PHP_EOL);
    exit(1);
}

if (PutMio\Install\InstallGate::handleIfNotInstalled()) {
    fwrite(STDERR, "PutMio non installato\n");
    exit(1);
}

PutMio\Install\InstallGate::ensureInstalled();
PutMio\Config::load();
PutMio\Database\Migrator::runPending();
date_default_timezone_set(PutMio\Config::get('app.timezone', 'Europe/Rome'));

try {
    $result = (new PutMio\PutIO\SyncService(null, null, 'cron_cli'))->sync();
    echo json_encode([
        'ok' => true,
        'imported' => $result['imported'],
        'removed' => $result['removed'] ?? 0,
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    $logDir = __DIR__ . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents(
        $logDir . '/app.log',
        '[' . date('Y-m-d H:i:s') . '] cron-sync: ' . $e->getMessage() . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
