<?php

/**
 * Diagnostica rapida (compatibile PHP 7+). Rimuovi o proteggi dopo l'installazione.
 */
header('Content-Type: text/plain; charset=utf-8');

$perm = static function (string $path): string {
    if (!file_exists($path)) {
        return 'MANCANTE';
    }
    $oct = substr(sprintf('%o', fileperms($path)), -4);
    $flags = (is_readable($path) ? 'R' : '-') . (is_writable($path) ? 'W' : '-') . (is_executable($path) ? 'X' : '-');
    return $oct . ' ' . $flags;
};

echo "PutMio check\n";
echo 'PHP: ' . PHP_VERSION . (version_compare(PHP_VERSION, '7.4.0', '>=') ? ' OK' : ' INSUFFICIENTE (serve 7.4+)') . "\n";
echo 'SAPI: ' . PHP_SAPI . "\n";
echo 'index.php: ' . (is_file(__DIR__ . '/index.php') ? 'OK' : 'MANCANTE') . ' [' . $perm(__DIR__ . '/index.php') . "]\n";
echo 'front.php: ' . (is_file(__DIR__ . '/front.php') ? 'OK' : 'MANCANTE') . ' [' . $perm(__DIR__ . '/front.php') . "]\n";
$indexBody = is_file(__DIR__ . '/index.php') ? (string) file_get_contents(__DIR__ . '/index.php') : '';
echo 'index.php delega front.php: ' . (strpos($indexBody, 'front.php') !== false ? 'OK' : 'VECCHIO — ricarica index.php e front.php') . "\n";
echo '.htaccess: ' . (is_file(__DIR__ . '/.htaccess') ? 'OK' : 'MANCANTE') . ' [' . $perm(__DIR__ . '/.htaccess') . "]\n";
echo 'templates/: [' . $perm(__DIR__ . '/templates') . "]\n";
echo 'templates/install/: [' . $perm(__DIR__ . '/templates/install') . "]\n";
echo 'src/: [' . $perm(__DIR__ . '/src') . "]\n";
echo 'storage/: [' . $perm(__DIR__ . '/storage') . "]\n";
$sessionsDir = __DIR__ . '/storage/sessions';
if (!is_dir($sessionsDir)) {
    @mkdir($sessionsDir, 0755, true);
}
echo 'storage/sessions: [' . $perm($sessionsDir) . "]\n";
echo 'storage/logs: [' . $perm(__DIR__ . '/storage/logs') . "]\n";
echo 'config.php: ' . (is_file(__DIR__ . '/config.php') ? 'presente' : 'assente') . "\n";
echo 'vendor/autoload.php: ' . (is_file(__DIR__ . '/vendor/autoload.php') ? 'OK' : 'MANCANTE — esegui composer install') . "\n";
echo '.installed: ' . (is_file(__DIR__ . '/storage/.installed') ? 'presente' : 'assente') . "\n";
echo "Permessi consigliati OVH: cartelle 755, file 644, storage/ 755 o 775\n";

$extensions = ['pdo_mysql', 'curl', 'openssl', 'mbstring', 'json'];
foreach ($extensions as $ext) {
    echo "ext {$ext}: " . (extension_loaded($ext) ? 'OK' : 'MANCANTE') . "\n";
}

if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    require __DIR__ . '/src/Bootstrap.php';
    PutMio\Bootstrap::init();
    echo "Bootstrap: caricato OK\n";
    echo 'installato: ' . (putmio_is_installed() ? 'sì' : 'no (wizard atteso)') . "\n";

    echo "\n--- Test sessione ---\n";
    try {
        PutMio\Auth\Session::start();
        echo 'sessione: OK (save_path=' . session_save_path() . ")\n";
    } catch (Throwable $e) {
        echo 'sessione: ERRORE — ' . $e->getMessage() . "\n";
    }

    if (!putmio_is_installed()) {
        echo "\n--- Test wizard ---\n";
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            unset($_GET['step']);
            ob_start();
            $handled = PutMio\Install\InstallGate::handleIfNotInstalled();
            $html = (string) ob_get_clean();
            echo 'wizard: ' . ($handled ? 'OK' : 'non gestito') . "\n";
            echo 'output HTML: ' . strlen($html) . " byte\n";
            if ($html !== '' && stripos($html, 'PutMio') !== false) {
                echo "anteprima: pagina installazione generata correttamente\n";
            }
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo 'wizard: ERRORE — ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
        }

        echo "\n--- Test Router ---\n";
        try {
            ob_start();
            (new PutMio\Router())->dispatch();
            $routerHtml = (string) ob_get_clean();
            echo 'router: output ' . strlen($routerHtml) . " byte\n";
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo 'router: ERRORE — ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
        }
    }

    echo "\n--- Prossimi passi ---\n";
    echo "1. Apri https://renatoarmenio.it/putmio/front.php (entry point principale)\n";
    echo "2. Poi https://renatoarmenio.it/putmio/ (usa front.php via .htaccess)\n";
    echo "3. index.php è solo un alias verso front.php\n";
    echo "4. Permessi file PHP consigliati: 644 (non 604)\n";
}
