-- PutMio schema (placeholder {{prefix}} sostituito in installazione)

CREATE TABLE IF NOT EXISTS `{{prefix}}users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(120) NOT NULL,
  `role` ENUM('admin','user') NOT NULL DEFAULT 'user',
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `theme` ENUM('light','dark') NOT NULL DEFAULT 'dark',
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}invites` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token_hash`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_ip_time` (`email`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}putio_connection` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `putio_user_id` BIGINT NULL,
  `putio_username` VARCHAR(120) NULL,
  `access_token_enc` TEXT NULL,
  `refresh_token_enc` TEXT NULL,
  `expires_at` DATETIME NULL,
  `sync_root_folder_id` BIGINT NOT NULL DEFAULT -1,
  `last_sync_at` DATETIME NULL,
  `last_sync_file_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `connected_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}putio_sync_friends` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}putio_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `putio_id` BIGINT NOT NULL,
  `parent_putio_id` BIGINT NULL,
  `name` VARCHAR(512) NOT NULL,
  `path_cache` VARCHAR(1024) NULL,
  `size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `mime` VARCHAR(120) NULL,
  `is_folder` TINYINT(1) NOT NULL DEFAULT 0,
  `is_shared` TINYINT(1) NOT NULL DEFAULT 0,
  `shared_by_username` VARCHAR(120) NULL,
  `content_type` VARCHAR(80) NULL,
  `synced_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_putio_id` (`putio_id`),
  KEY `idx_parent` (`parent_putio_id`),
  KEY `idx_folder` (`is_folder`),
  KEY `idx_shared_by` (`shared_by_username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}media_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `putio_file_id` INT UNSIGNED NULL,
  `series_id` INT UNSIGNED NULL,
  `season_number` SMALLINT UNSIGNED NULL,
  `episode_number` SMALLINT UNSIGNED NULL,
  `media_type` ENUM('film','serie','animazione','altro') NOT NULL DEFAULT 'altro',
  `title` VARCHAR(255) NOT NULL,
  `original_title` VARCHAR(255) NULL,
  `year` SMALLINT UNSIGNED NULL,
  `synopsis` TEXT NULL,
  `poster_local_path` VARCHAR(255) NULL,
  `poster_url` VARCHAR(512) NULL,
  `tmdb_id` INT UNSIGNED NULL,
  `tmdb_type` ENUM('movie','tv') NULL,
  `imdb_id` VARCHAR(20) NULL,
  `duration_sec` INT UNSIGNED NULL,
  `classification_status` ENUM('unclassified','classified','ignored') NOT NULL DEFAULT 'unclassified',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_putio_file` (`putio_file_id`),
  KEY `idx_status` (`classification_status`),
  KEY `idx_type` (`media_type`),
  KEY `idx_series` (`series_id`),
  KEY `idx_series_episode` (`series_id`, `season_number`, `episode_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}genres` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `tmdb_genre_id` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}media_genres` (
  `media_id` INT UNSIGNED NOT NULL,
  `genre_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`media_id`, `genre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}media_tags` (
  `media_id` INT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`media_id`, `tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}watch_progress` (
  `user_id` INT UNSIGNED NOT NULL,
  `media_id` INT UNSIGNED NOT NULL,
  `position_sec` INT UNSIGNED NOT NULL DEFAULT 0,
  `duration_sec` INT UNSIGNED NOT NULL DEFAULT 0,
  `completed` TINYINT(1) NOT NULL DEFAULT 0,
  `last_watched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `media_id`),
  KEY `idx_in_progress` (`user_id`, `completed`, `last_watched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}audit_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(120) NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}stream_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `putio_file_id` INT UNSIGNED NOT NULL,
  `media_id` INT UNSIGNED NULL,
  `client_ip` VARCHAR(45) NOT NULL,
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` DATETIME NULL,
  `bytes_sent` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`active`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}stream_daily_stats` (
  `date` DATE NOT NULL,
  `total_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `peak_concurrent` INT UNSIGNED NOT NULL DEFAULT 0,
  `stream_count` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}media_subtitles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `media_id` INT UNSIGNED NOT NULL,
  `language` VARCHAR(10) NOT NULL,
  `label` VARCHAR(80) NOT NULL,
  `source` ENUM('opensubtitles') NOT NULL DEFAULT 'opensubtitles',
  `source_file_id` VARCHAR(64) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `downloaded_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_media_source_file` (`media_id`, `source`, `source_file_id`),
  KEY `idx_media` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}user_subtitle_prefs` (
  `user_id` INT UNSIGNED NOT NULL,
  `media_id` INT UNSIGNED NOT NULL,
  `subtitle_id` INT UNSIGNED NULL,
  `offset_ms` INT NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `media_id`),
  KEY `idx_subtitle` (`subtitle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
