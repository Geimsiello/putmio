<?php

declare(strict_types=1);

namespace PutMio\Database;

use PutMio\Config;
use PutMio\Database;
use PutMio\CatalogService;
use PutMio\Media\SeriesGrouper;
use PutMio\TMDB\Client as TmdbClient;

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
            self::runPutioSyncLogMigration($pdo);
            self::runPutioSyncKindMigration($pdo);
            self::runSubtitlesMigration($pdo);
            self::runRememberTokensMigration($pdo);
            self::runDeviceLoginMigration($pdo);
            self::runUserDevicesMigration($pdo);
            self::runLocaleMigration($pdo);
            self::runBackdropsMigration($pdo);
            self::runUserCatalogSourcesMigration($pdo);
            self::runUserWatchlistMigration($pdo);
            self::runPutioSubtitleSourceMigration($pdo);
            self::runPutioSubtitleIntegrityMigration($pdo);
        } catch (\Throwable $e) {
            self::logMigrationError($e);
        }
    }

    private static function runPutioSyncKindMigration(\PDO $pdo): void
    {
        $runsTable = Config::table('putio_sync_runs');

        try {
            $pdo->exec(
                'ALTER TABLE `' . $runsTable . '`
                 MODIFY `trigger_source` ENUM(
                    \'admin\',\'cron_http\',\'cron_cli\',
                    \'cron_subtitles_http\',\'cron_subtitles_cli\',\'unknown\'
                 ) NOT NULL DEFAULT \'unknown\''
            );
        } catch (\Throwable $e) {
            self::logMigrationError($e);
        }

        if (!self::columnExists($pdo, $runsTable, 'sync_kind')) {
            try {
                $pdo->exec(
                    'ALTER TABLE `' . $runsTable . '`
                     ADD COLUMN `sync_kind` ENUM(\'catalog\',\'subtitles\') NOT NULL DEFAULT \'catalog\'
                     AFTER `trigger_source`'
                );
                $pdo->exec(
                    'UPDATE `' . $runsTable . '`
                     SET `sync_kind` = \'subtitles\'
                     WHERE `trigger_source` IN (\'cron_subtitles_http\', \'cron_subtitles_cli\')'
                );
            } catch (\Throwable $e) {
                self::logMigrationError($e);
            }
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
                    `source` ENUM(\'opensubtitles\', \'putio\') NOT NULL DEFAULT \'opensubtitles\',
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

    private static function runRememberTokensMigration(\PDO $pdo): void
    {
        $table = Config::table('remember_tokens');
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec(
                'CREATE TABLE `' . $table . '` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` INT UNSIGNED NOT NULL,
                    `selector` VARCHAR(32) NOT NULL,
                    `token_hash` VARCHAR(64) NOT NULL,
                    `expires_at` DATETIME NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_selector` (`selector`),
                    KEY `idx_user` (`user_id`),
                    KEY `idx_expires` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }

    private static function runDeviceLoginMigration(\PDO $pdo): void
    {
        $table = Config::table('device_login_requests');
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec(
                'CREATE TABLE `' . $table . '` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `code_hash` VARCHAR(64) NOT NULL,
                    `device_token` VARCHAR(64) NOT NULL,
                    `status` ENUM(\'pending\',\'approved\',\'denied\',\'expired\',\'consumed\') NOT NULL DEFAULT \'pending\',
                    `user_id` INT UNSIGNED NULL,
                    `approved_by` INT UNSIGNED NULL,
                    `client_ip` VARCHAR(45) NOT NULL,
                    `user_agent` VARCHAR(512) NULL,
                    `expires_at` DATETIME NOT NULL,
                    `approved_at` DATETIME NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_device_token` (`device_token`),
                    KEY `idx_code_hash` (`code_hash`),
                    KEY `idx_client_ip_time` (`client_ip`, `created_at`),
                    KEY `idx_expires` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }

    private static function runUserDevicesMigration(\PDO $pdo): void
    {
        $table = Config::table('user_devices');
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec(
                'CREATE TABLE `' . $table . '` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` INT UNSIGNED NOT NULL,
                    `selector` VARCHAR(32) NOT NULL,
                    `token_hash` VARCHAR(64) NOT NULL,
                    `label` VARCHAR(64) NOT NULL,
                    `user_agent` VARCHAR(512) NULL,
                    `client_ip` VARCHAR(45) NULL,
                    `expires_at` DATETIME NOT NULL,
                    `last_used_at` DATETIME NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_selector` (`selector`),
                    KEY `idx_user` (`user_id`),
                    KEY `idx_expires` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }

    private static function runLocaleMigration(\PDO $pdo): void
    {
        $table = Config::table('users');
        if (!self::columnExists($pdo, $table, 'locale')) {
            $pdo->exec(
                'ALTER TABLE `' . $table . '`
                 ADD COLUMN `locale` VARCHAR(5) NOT NULL DEFAULT \'it\' AFTER `theme`'
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

    private static function runPutioSyncLogMigration(\PDO $pdo): void
    {
        $runsTable = Config::table('putio_sync_runs');
        $itemsTable = Config::table('putio_sync_run_items');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `' . $runsTable . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `finished_at` DATETIME NULL,
                `trigger_source` ENUM(\'admin\',\'cron_http\',\'cron_cli\',\'cron_subtitles_http\',\'cron_subtitles_cli\',\'unknown\') NOT NULL DEFAULT \'unknown\',
                `sync_kind` ENUM(\'catalog\',\'subtitles\') NOT NULL DEFAULT \'catalog\',
                `triggered_by_user_id` INT UNSIGNED NULL,
                `status` ENUM(\'running\',\'success\',\'error\') NOT NULL DEFAULT \'running\',
                `error_message` TEXT NULL,
                `putio_username` VARCHAR(120) NULL,
                `putio_user_id` BIGINT NULL,
                `count_added` INT UNSIGNED NOT NULL DEFAULT 0,
                `count_updated` INT UNSIGNED NOT NULL DEFAULT 0,
                `count_removed` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_started` (`started_at`),
                KEY `idx_status` (`status`),
                KEY `idx_triggered_by` (`triggered_by_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `' . $itemsTable . '` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `run_id` INT UNSIGNED NOT NULL,
                `action` ENUM(\'added\',\'updated\',\'removed\') NOT NULL,
                `putio_id` BIGINT NOT NULL,
                `name` VARCHAR(512) NOT NULL,
                `is_folder` TINYINT(1) NOT NULL DEFAULT 0,
                `is_shared` TINYINT(1) NOT NULL DEFAULT 0,
                `shared_by_username` VARCHAR(120) NULL,
                `owner_username` VARCHAR(120) NOT NULL,
                `owner_account` VARCHAR(160) NOT NULL,
                `content_type` VARCHAR(120) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_run` (`run_id`),
                KEY `idx_run_action` (`run_id`, `action`),
                KEY `idx_putio_id` (`putio_id`),
                KEY `idx_owner` (`owner_username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function runBackdropsMigration(\PDO $pdo): void
    {
        $table = Config::table('media_items');
        if (!self::columnExists($pdo, $table, 'backdrop_url')) {
            $pdo->exec(
                'ALTER TABLE `' . $table . '`
                 ADD COLUMN `backdrop_local_path` VARCHAR(255) NULL AFTER `poster_url`,
                 ADD COLUMN `backdrop_url` VARCHAR(512) NULL AFTER `backdrop_local_path`'
            );
        }

        self::runBackdropBackfill();
    }

    private static function runPutioSubtitleSourceMigration(\PDO $pdo): void
    {
        $table = Config::table('media_subtitles');
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        if ((int) $stmt->fetchColumn() === 0) {
            return;
        }

        $column = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE \'source\'');
        $row = $column ? $column->fetch() : false;
        if (!$row || !is_string($row['Type'] ?? null) || str_contains($row['Type'], 'putio')) {
            return;
        }

        $pdo->exec(
            'ALTER TABLE `' . $table . '`
             MODIFY `source` ENUM(\'opensubtitles\', \'putio\') NOT NULL DEFAULT \'opensubtitles\''
        );
    }

    private static function runPutioSubtitleIntegrityMigration(\PDO $pdo): void
    {
        $subtitlesTable = Config::table('media_subtitles');
        $mediaTable = Config::table('media_items');
        $filesTable = Config::table('putio_files');

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$subtitlesTable]);
        if ((int) $stmt->fetchColumn() === 0) {
            return;
        }

        if (!self::columnExists($pdo, $subtitlesTable, 'putio_file_id')) {
            $pdo->exec(
                'ALTER TABLE `' . $subtitlesTable . '`
                 ADD COLUMN `putio_file_id` BIGINT NULL AFTER `source_file_id`,
                 ADD KEY `idx_putio_subtitle` (`putio_file_id`, `source`)'
            );
        }

        if (!self::columnExists($pdo, $mediaTable, 'putio_subtitles_sync_at')) {
            $pdo->exec(
                'ALTER TABLE `' . $mediaTable . '`
                 ADD COLUMN `putio_subtitles_sync_at` DATETIME NULL AFTER `classification_status`,
                 ADD COLUMN `putio_subtitles_sync_hash` VARCHAR(64) NULL AFTER `putio_subtitles_sync_at`'
            );
        }

        $flag = putmio_base_path() . '/storage/.migration_putio_subtitle_integrity';
        if (is_file($flag)) {
            return;
        }

        try {
            $pdo->exec(
                'UPDATE `' . $subtitlesTable . '` ms
                 INNER JOIN `' . $mediaTable . '` mi ON mi.id = ms.media_id
                 INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
                 SET ms.putio_file_id = pf.putio_id
                 WHERE ms.source = \'putio\' AND ms.putio_file_id IS NULL'
            );

            $pdo->exec(
                'UPDATE `' . $subtitlesTable . '` ms
                 INNER JOIN `' . $mediaTable . '` mi ON mi.id = ms.media_id
                 INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
                 SET ms.source_file_id = CONCAT(pf.putio_id, \':\', ms.source_file_id)
                 WHERE ms.source = \'putio\'
                   AND ms.source_file_id NOT LIKE \'%:%\''
            );

            (new \PutMio\OpenSubtitles\SubtitleService())->repairPutioIntegrity();
            @file_put_contents($flag, date('c'));
        } catch (\Throwable $e) {
            self::logMigrationError($e);
        }
    }

    private static function runUserCatalogSourcesMigration(\PDO $pdo): void
    {
        $table = Config::table('user_catalog_hidden_sources');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `' . $table . '` (
              `user_id` INT UNSIGNED NOT NULL,
              `source_key` VARCHAR(120) NOT NULL,
              PRIMARY KEY (`user_id`, `source_key`),
              KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function runUserWatchlistMigration(\PDO $pdo): void
    {
        $table = Config::table('user_watchlist');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `' . $table . '` (
              `user_id` INT UNSIGNED NOT NULL,
              `media_id` INT UNSIGNED NOT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`user_id`, `media_id`),
              KEY `idx_user_created` (`user_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function runBackdropBackfill(): void
    {
        $flag = putmio_base_path() . '/storage/.migration_backdrop_backfill';
        if (is_file($flag)) {
            return;
        }

        try {
            $client = new TmdbClient();
            if (!$client->isConfigured()) {
                @file_put_contents($flag, date('c'));
                return;
            }

            $pdo = Database::pdo();
            $table = Config::table('media_items');
            $stmt = $pdo->query(
                'SELECT id, tmdb_id, tmdb_type FROM `' . $table . '`
                 WHERE tmdb_id IS NOT NULL
                   AND backdrop_url IS NULL
                   AND series_id IS NULL'
            );
            $rows = $stmt ? $stmt->fetchAll() : [];
            $update = $pdo->prepare(
                'UPDATE `' . $table . '`
                 SET backdrop_local_path = ?, backdrop_url = ?, updated_at = NOW()
                 WHERE id = ?'
            );

            foreach ($rows as $row) {
                $tmdbId = (int) ($row['tmdb_id'] ?? 0);
                $tmdbType = (string) ($row['tmdb_type'] ?? 'movie');
                if ($tmdbId <= 0) {
                    continue;
                }
                if (!in_array($tmdbType, ['movie', 'tv'], true)) {
                    $tmdbType = 'movie';
                }

                try {
                    $details = $client->details($tmdbType, $tmdbId);
                    $backdropPath = $client->downloadBackdrop($details['backdrop_path'] ?? null, (int) $row['id']);
                    $backdropUrl = $client->backdropUrl($details['backdrop_path'] ?? null);
                    if ($backdropPath || $backdropUrl) {
                        $update->execute([$backdropPath, $backdropUrl, (int) $row['id']]);
                    }
                } catch (\Throwable $e) {
                    self::logMigrationError($e);
                }

                usleep(250000);
            }

            $catalog = new CatalogService();
            $seriesStmt = $pdo->query(
                'SELECT id FROM `' . $table . '`
                 WHERE series_id IS NULL AND putio_file_id IS NULL
                   AND classification_status = \'classified\'
                   AND backdrop_url IS NOT NULL'
            );
            $seriesRows = $seriesStmt ? $seriesStmt->fetchAll() : [];
            foreach ($seriesRows as $seriesRow) {
                $catalog->syncSeriesMetadataToEpisodes((int) $seriesRow['id']);
            }

            @file_put_contents($flag, date('c'));
        } catch (\Throwable $e) {
            self::logMigrationError($e);
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
