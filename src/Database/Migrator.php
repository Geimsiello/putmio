<?php

declare(strict_types=1);

namespace PutMio\Database;

use PutMio\Config;
use PutMio\Database;
use PutMio\CatalogService;
use PutMio\Media\SeriesGrouper;

final class Migrator
{
    private static bool $ran = false;

    public static function runPending(): void
    {
        if (self::$ran) {
            return;
        }
        self::$ran = true;

        try {
            $pdo = Database::pdo();
            $table = Config::table('media_items');

            if (!self::columnExists($pdo, $table, 'series_id')) {
                $pdo->exec('ALTER TABLE `' . $table . '` MODIFY `putio_file_id` INT UNSIGNED NULL');
                $pdo->exec(
                    'ALTER TABLE `' . $table . '`
                     ADD COLUMN `series_id` INT UNSIGNED NULL AFTER `putio_file_id`,
                     ADD COLUMN `season_number` SMALLINT UNSIGNED NULL AFTER `series_id`,
                     ADD COLUMN `episode_number` SMALLINT UNSIGNED NULL AFTER `season_number`,
                     ADD KEY `idx_series` (`series_id`),
                     ADD KEY `idx_series_episode` (`series_id`, `season_number`, `episode_number`)'
                );
                (new SeriesGrouper())->groupAll();
            } else {
                self::runSeriesVisibilityFix();
                self::runSeriesEpisodeMetadataSync();
            }

            self::runPutioFriendsMigration($pdo);
            self::runSubtitlesMigration($pdo);
        } catch (\Throwable $e) {
            self::logMigrationError($e);
        }
    }

    private static function runSubtitlesMigration(\PDO $pdo): void
    {
        $mediaTable = Config::table('media_items');
        if (!self::columnExists($pdo, $mediaTable, 'imdb_id')) {
            $pdo->exec(
                'ALTER TABLE `' . $mediaTable . '`
                 ADD COLUMN `imdb_id` VARCHAR(20) NULL AFTER `tmdb_type`'
            );
        }

        $subtitlesTable = Config::table('media_subtitles');
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$subtitlesTable]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec(
                'CREATE TABLE `' . $subtitlesTable . '` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `media_id` INT UNSIGNED NOT NULL,
                    `language` VARCHAR(10) NOT NULL,
                    `label` VARCHAR(80) NOT NULL,
                    `source` ENUM(\'opensubtitles\') NOT NULL DEFAULT \'opensubtitles\',
                    `source_file_id` VARCHAR(64) NOT NULL,
                    `file_path` VARCHAR(255) NOT NULL,
                    `downloaded_by` INT UNSIGNED NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_media_source_file` (`media_id`, `source`, `source_file_id`),
                    KEY `idx_media` (`media_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        $prefsTable = Config::table('user_subtitle_prefs');
        $stmt->execute([$prefsTable]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec(
                'CREATE TABLE `' . $prefsTable . '` (
                    `user_id` INT UNSIGNED NOT NULL,
                    `media_id` INT UNSIGNED NOT NULL,
                    `subtitle_id` INT UNSIGNED NULL,
                    `offset_ms` INT NOT NULL DEFAULT 0,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`user_id`, `media_id`),
                    KEY `idx_subtitle` (`subtitle_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }

    private static function runPutioFriendsMigration(\PDO $pdo): void
    {
        $friendsTable = Config::table('putio_sync_friends');
        $filesTable = Config::table('putio_files');

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$friendsTable]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec(
                'CREATE TABLE `' . $friendsTable . '` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `putio_friend_id` BIGINT NOT NULL,
                    `username` VARCHAR(120) NOT NULL,
                    `folder_putio_id` BIGINT NULL,
                    `avatar_url` VARCHAR(512) NULL,
                    `sync_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                    `updated_at` DATETIME NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_putio_friend_id` (`putio_friend_id`),
                    UNIQUE KEY `uq_username` (`username`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        if (!self::columnExists($pdo, $filesTable, 'is_shared')) {
            $pdo->exec(
                'ALTER TABLE `' . $filesTable . '`
                 ADD COLUMN `is_shared` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_folder`,
                 ADD COLUMN `shared_by_username` VARCHAR(120) NULL AFTER `is_shared`,
                 ADD KEY `idx_shared_by` (`shared_by_username`)'
            );
        }
    }

    private static function logMigrationError(\Throwable $e): void
    {
        $logDir = putmio_base_path() . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents(
            $logDir . '/app.log',
            '[' . date('Y-m-d H:i:s') . '] Migrator: ' . $e->getMessage() . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function runSeriesEpisodeMetadataSync(): void
    {
        $flag = putmio_base_path() . '/storage/.migration_series_episode_metadata';
        if (is_file($flag)) {
            return;
        }

        try {
            $pdo = Database::pdo();
            $table = Config::table('media_items');
            $stmt = $pdo->query(
                'SELECT id FROM `' . $table . '`
                 WHERE series_id IS NULL AND putio_file_id IS NULL
                   AND classification_status = \'classified\'
                   AND (poster_local_path IS NOT NULL OR poster_url IS NOT NULL OR tmdb_id IS NOT NULL)'
            );
            $rows = $stmt ? $stmt->fetchAll() : [];
            $catalog = new CatalogService();
            foreach ($rows as $row) {
                $catalog->syncSeriesMetadataToEpisodes((int) $row['id']);
            }
            @file_put_contents($flag, date('c'));
        } catch (\Throwable $e) {
            self::logMigrationError($e);
        }
    }

    private static function runSeriesVisibilityFix(): void
    {
        $flag = putmio_base_path() . '/storage/.migration_series_visibility';
        if (is_file($flag)) {
            return;
        }

        try {
            (new SeriesGrouper())->syncSeriesFromEpisodes();
            @file_put_contents($flag, date('c'));
        } catch (\Throwable $e) {
            self::logMigrationError($e);
        }
    }

    private static function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
