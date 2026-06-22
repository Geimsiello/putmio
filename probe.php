<?php

declare(strict_types=1);

/**
 * Test minimo identico al flusso index.php (senza Router).
 * Se probe.php funziona ma index.php no, il problema è nel Router o in .htaccess.
 * Rimuovi dopo l'installazione.
 */
require __DIR__ . '/src/Bootstrap.php';

PutMio\Bootstrap::init();

if (PutMio\Install\InstallGate::handleIfNotInstalled()) {
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "PutMio già installato. Usa index.php.\n";
