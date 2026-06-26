<?php

/**
 * Quick diagnostics (PHP 7+). Remove or protect after installation.
 */
header('Content-Type: text/plain; charset=utf-8');

$perm = static function (string $path): string {
    if (!file_exists($path)) {
        return 'MISSING';
    }
    $oct = substr(sprintf('%o', fileperms($path)), -4);
    $flags = (is_readable($path) ? 'R' : '-') . (is_writable($path) ? 'W' : '-') . (is_executable($path) ? 'X' : '-');
    return $oct . ' ' . $flags;
};

echo "PutMio check\n";
echo 'PHP: ' . PHP_VERSION . (version_compare(PHP_VERSION, '7.4.0', '>=') ? ' OK' : ' INSUFFICIENT (requires 7.4+)') . "\n";
echo 'SAPI: ' . PHP_SAPI . "\n";
echo 'index.php: ' . (is_file(__DIR__ . '/index.php') ? 'OK' : 'MISSING') . ' [' . $perm(__DIR__ . '/index.php') . "]\n";
echo 'front.php: ' . (is_file(__DIR__ . '/front.php') ? 'OK' : 'MISSING') . ' [' . $perm(__DIR__ . '/front.php') . "]\n";
$indexBody = is_file(__DIR__ . '/index.php') ? (string) file_get_contents(__DIR__ . '/index.php') : '';
echo 'index.php delegates to front.php: ' . (strpos($indexBody, 'front.php') !== false ? 'OK' : 'OUTDATED — re-upload index.php and front.php') . "\n";
echo '.htaccess: ' . (is_file(__DIR__ . '/.htaccess') ? 'OK' : 'MISSING') . ' [' . $perm(__DIR__ . '/.htaccess') . "]\n";
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
echo 'config.php: ' . (is_file(__DIR__ . '/config.php') ? 'present' : 'missing') . "\n";
echo 'vendor/autoload.php: ' . (is_file(__DIR__ . '/vendor/autoload.php') ? 'OK' : 'MISSING — run composer install') . "\n";
echo '.installed: ' . (is_file(__DIR__ . '/storage/.installed') ? 'present' : 'missing') . "\n";
echo "Suggested permissions: directories 755, files 644, storage/ 755 or 775\n";

$extensions = ['pdo_mysql', 'curl', 'openssl', 'mbstring', 'json'];
foreach ($extensions as $ext) {
    echo "ext {$ext}: " . (extension_loaded($ext) ? 'OK' : 'MISSING') . "\n";
}

if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    require __DIR__ . '/src/Bootstrap.php';
    PutMio\Bootstrap::init();
    echo "Bootstrap: loaded OK\n";
    echo 'installed: ' . (putmio_is_installed() ? 'yes' : 'no (wizard expected)') . "\n";

    echo "\n--- Session test ---\n";
    try {
        PutMio\Auth\Session::start();
        echo 'session: OK (save_path=' . session_save_path() . ")\n";
    } catch (Throwable $e) {
        echo 'session: ERROR — ' . $e->getMessage() . "\n";
    }

    if (putmio_is_installed()) {
        PutMio\Config::load();
        echo "\n--- GitHub updates test ---\n";
        $client = new PutMio\Update\GithubReleaseClient();
        echo 'github_repo: ' . ($client->isConfigured() ? $client->repository() : 'not configured') . "\n";
        if ($client->isConfigured()) {
            $release = $client->fetchLatest();
            if ($release) {
                echo 'latest release: ' . $release['version'] . ' (' . $release['tag'] . ")\n";
            } else {
                echo 'latest release: ERROR — ' . ($client->lastError() ?? 'unknown');
                $status = $client->lastHttpStatus();
                if ($status > 0) {
                    echo ' HTTP ' . $status;
                }
                echo "\n";
            }
        }
    }

    if (!putmio_is_installed()) {
        echo "\n--- Wizard test ---\n";
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            unset($_GET['step']);
            ob_start();
            $handled = PutMio\Install\InstallGate::handleIfNotInstalled();
            $html = (string) ob_get_clean();
            echo 'wizard: ' . ($handled ? 'OK' : 'not handled') . "\n";
            echo 'HTML output: ' . strlen($html) . " bytes\n";
            if ($html !== '' && stripos($html, 'PutMio') !== false) {
                echo "preview: installation page generated correctly\n";
            }
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo 'wizard: ERROR — ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
        }

        echo "\n--- Router test ---\n";
        try {
            ob_start();
            (new PutMio\Router())->dispatch();
            $routerHtml = (string) ob_get_clean();
            echo 'router: output ' . strlen($routerHtml) . " bytes\n";
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo 'router: ERROR — ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
        }
    }

    echo "\n--- Next steps ---\n";
    echo "1. Open https://yourdomain.example/putmio/front.php (main entry point)\n";
    echo "2. Then https://yourdomain.example/putmio/ (routes via front.php through .htaccess)\n";
    echo "3. index.php is only an alias to front.php\n";
    echo "4. Suggested PHP file permissions: 644 (not 604)\n";
}
