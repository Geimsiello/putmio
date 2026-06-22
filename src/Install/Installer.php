<?php

declare(strict_types=1);

namespace PutMio\Install;

use PutMio\Database;

final class Installer
{
    public static function requirements(): array
    {
        $checks = [
            'php_version' => [
                'label' => 'PHP 7.4+',
                'ok' => version_compare(PHP_VERSION, '7.4.0', '>='),
                'message' => 'Versione attuale: ' . PHP_VERSION,
            ],
            'pdo_mysql' => [
                'label' => 'Estensione PDO MySQL',
                'ok' => extension_loaded('pdo_mysql'),
                'message' => extension_loaded('pdo_mysql') ? 'OK' : 'Mancante',
            ],
            'curl' => [
                'label' => 'Estensione cURL',
                'ok' => extension_loaded('curl'),
                'message' => extension_loaded('curl') ? 'OK' : 'Mancante',
            ],
            'openssl' => [
                'label' => 'Estensione OpenSSL',
                'ok' => extension_loaded('openssl'),
                'message' => extension_loaded('openssl') ? 'OK' : 'Mancante',
            ],
            'mbstring' => [
                'label' => 'Estensione mbstring',
                'ok' => extension_loaded('mbstring'),
                'message' => extension_loaded('mbstring') ? 'OK' : 'Mancante',
            ],
            'json' => [
                'label' => 'Estensione JSON',
                'ok' => extension_loaded('json'),
                'message' => extension_loaded('json') ? 'OK' : 'Mancante',
            ],
            'storage_writable' => [
                'label' => 'Cartella storage/ scrivibile',
                'ok' => is_writable(putmio_base_path() . '/storage')
                    || @mkdir(putmio_base_path() . '/storage/logs', 0755, true),
                'message' => is_writable(putmio_base_path() . '/storage') ? 'OK' : 'Non scrivibile',
            ],
            'session_writable' => [
                'label' => 'Cartella storage/sessions/ scrivibile',
                'ok' => self::isSessionPathWritable(),
                'message' => self::isSessionPathWritable() ? 'OK' : 'Non scrivibile',
            ],
        ];

        $allOk = true;
        foreach ($checks as $check) {
            if (!$check['ok']) {
                $allOk = false;
                break;
            }
        }

        return ['checks' => $checks, 'all_ok' => $allOk];
    }

    private static function isSessionPathWritable(): bool
    {
        $path = putmio_base_path() . '/storage/sessions';
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        return is_dir($path) && is_writable($path);
    }

    public static function runSchema(array $db): void
    {
        Database::assertDatabaseConnection($db);
        Database::reset();

        $prefix = putmio_normalize_table_prefix($db['prefix'] ?? 'pm_');
        $db['prefix'] = $prefix;
        $sqlFile = putmio_base_path() . '/sql/schema.sql';
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new \RuntimeException('schema.sql non trovato');
        }
        $sql = str_replace('{{prefix}}', $prefix, $sql);

        $pdo = Database::connect([
            'host' => $db['host'],
            'name' => $db['name'],
            'user' => $db['user'],
            'pass' => $db['pass'],
            'charset' => $db['charset'] ?? 'utf8mb4',
        ]);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    public static function writeConfig(array $db, array $app, array $smtp = []): void
    {
        $config = [
            'app' => [
                'name' => 'PutMio',
                'url' => rtrim($app['url'], '/'),
                'timezone' => 'Europe/Rome',
                'encryption_key' => $app['encryption_key'],
                'cron_token' => $app['cron_token'],
                'stream_complete_ratio' => 0.90,
                'stream_min_progress_ratio' => 0.05,
                'max_concurrent_streams_per_ip' => 4,
            ],
            'db' => [
                'host' => $db['host'],
                'name' => $db['name'],
                'user' => $db['user'],
                'pass' => $db['pass'],
                'prefix' => $db['prefix'] ?? 'pm_',
                'charset' => $db['charset'] ?? 'utf8mb4',
            ],
            'smtp' => [
                'enabled' => !empty($smtp['enabled']),
                'host' => $smtp['host'] ?? '',
                'port' => (int) ($smtp['port'] ?? 587),
                'user' => $smtp['user'] ?? '',
                'pass' => $smtp['pass'] ?? '',
                'from_email' => $smtp['from_email'] ?? '',
                'from_name' => $smtp['from_name'] ?? 'PutMio',
            ],
            'putio' => [
                'client_id' => '',
                'client_secret' => '',
                'redirect_uri' => rtrim($app['url'], '/') . '/admin/oauth/putio/callback',
            ],
            'tmdb' => [
                'api_key' => '',
                'language' => 'it-IT',
            ],
        ];

        $export = var_export($config, true);
        $php = "<?php\n\nreturn " . $export . ";\n";
        $path = putmio_config_path();
        if (file_put_contents($path, $php) === false) {
            throw new \RuntimeException('Impossibile scrivere config.php');
        }
        @chmod($path, 0640);
    }

    public static function createAdmin(array $db, string $email, string $displayName, string $password): void
    {
        $prefix = putmio_normalize_table_prefix($db['prefix'] ?? 'pm_');
        $pdo = Database::connect([
            'host' => $db['host'],
            'name' => $db['name'],
            'user' => $db['user'],
            'pass' => $db['pass'],
            'charset' => $db['charset'] ?? 'utf8mb4',
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO `' . $prefix . 'users` (email, password_hash, display_name, role, status, theme)
             VALUES (?, ?, ?, \'admin\', \'active\', \'dark\')'
        );
        $stmt->execute([
            strtolower(trim($email)),
            password_hash($password, PASSWORD_DEFAULT),
            trim($displayName),
        ]);
    }

    public static function finalizeInstall(): void
    {
        $lock = putmio_installed_lock();
        $dir = dirname($lock);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($lock, date('c'));
    }
}
