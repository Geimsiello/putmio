<?php

declare(strict_types=1);

namespace PutMio;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(?array $db = null): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $db = $db ?? Config::get('db');
        if (!is_array($db)) {
            throw new \RuntimeException('Configurazione database mancante');
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $db['host'],
            $db['name'],
            $db['charset'] ?? 'utf8mb4'
        );

        try {
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Connessione database fallita: ' . $e->getMessage());
        }

        return self::$pdo;
    }

    public static function pdo(): PDO
    {
        return self::connect();
    }

    public static function testConnection(array $db): bool
    {
        try {
            self::assertDatabaseConnection($db);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Verifica connessione a un database MySQL già esistente (come WordPress).
     * PutMio non crea il database: va creato dal pannello hosting (es. OVH).
     */
    public static function assertDatabaseConnection(array $db): void
    {
        $name = putmio_sanitize_db_name($db['name']);
        if ($name === '') {
            throw new \RuntimeException('Nome database non valido');
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $db['host'],
            $name,
            $db['charset'] ?? 'utf8mb4'
        );

        try {
            new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Unknown database') || str_contains($msg, '1049')) {
                throw new \RuntimeException(
                    'Database non trovato. Crea prima un database vuoto dal pannello OVH, poi inserisci qui nome, utente e password assegnati.'
                );
            }
            throw new \RuntimeException('Connessione database fallita: ' . $msg);
        }
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
