<?php

declare(strict_types=1);

namespace PutMio\Update;

/**
 * Definisce quali path del progetto sono aggiornabili dal core updater
 * e quali devono restare intatti (config locale, storage, dati utente).
 */
final class CoreManifest
{
    /** Path relativi alla root che non vanno mai sovrascritti. */
    public const PROTECTED_PATHS = [
        'config.php',
        'storage',
        '.env',
        '.git',
    ];

    /** Directory/file del core aggiornabile da release GitHub. */
    public const UPDATABLE_PATHS = [
        'src',
        'templates',
        'public',
        'lang',
        'sql',
        'vendor',
        'front.php',
        'index.php',
        'cron-sync.php',
        'sw.js',
        '.htaccess',
        'composer.json',
        'composer.lock',
        'VERSION',
        'check.php',
        'probe.php',
    ];

    public static function isProtected(string $relativePath): bool
    {
        $normalized = self::normalize($relativePath);
        if ($normalized === '') {
            return true;
        }

        foreach (self::PROTECTED_PATHS as $protected) {
            $protected = self::normalize($protected);
            if ($normalized === $protected || str_starts_with($normalized, $protected . '/')) {
                return true;
            }
        }

        return false;
    }

    public static function isUpdatable(string $relativePath): bool
    {
        $normalized = self::normalize($relativePath);
        if ($normalized === '' || self::isProtected($normalized)) {
            return false;
        }

        foreach (self::UPDATABLE_PATHS as $updatable) {
            $updatable = self::normalize($updatable);
            if ($normalized === $updatable || str_starts_with($normalized, $updatable . '/')) {
                return true;
            }
        }

        return false;
    }

    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        return $path === '.' ? '' : $path;
    }

    public static function updatesWorkDir(): string
    {
        return putmio_base_path() . '/storage/updates';
    }

    public static function backupsDir(): string
    {
        return putmio_base_path() . '/storage/backups';
    }
}
