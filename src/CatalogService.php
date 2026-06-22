<?php

declare(strict_types=1);

namespace PutMio;

use PutMio\Auth\Session;
use PutMio\Config;
use PutMio\Database;

final class CatalogService
{
    public function listMedia(array $filters = [], int $limit = 48, int $offset = 0): array
    {
        $pdo = Database::pdo();
        $where = ["mi.classification_status != 'ignored'"];
        $params = [];

        if (!empty($filters['type'])) {
            $where[] = 'mi.media_type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(mi.title LIKE ? OR mi.original_title LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['classified'])) {
            $where[] = "mi.classification_status = 'classified'";
        }

        $sql = 'SELECT mi.*, pf.putio_id, pf.size
                FROM `' . Config::table('media_items') . '` mi
                JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY mi.updated_at DESC
                LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findMedia(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT mi.*, pf.putio_id, pf.name AS file_name, pf.size
             FROM `' . Config::table('media_items') . '` mi
             JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
             WHERE mi.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function inProgressForUser(int $userId): array
    {
        $pdo = Database::pdo();
        $complete = (float) Config::get('app.stream_complete_ratio', 0.90);
        $min = (float) Config::get('app.stream_min_progress_ratio', 0.05);

        $stmt = $pdo->prepare(
            'SELECT mi.*, pf.putio_id, wp.position_sec, wp.duration_sec, wp.last_watched_at
             FROM `' . Config::table('watch_progress') . '` wp
             JOIN `' . Config::table('media_items') . '` mi ON mi.id = wp.media_id
             JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
             WHERE wp.user_id = ? AND wp.completed = 0
               AND wp.position_sec > 0
               AND (wp.duration_sec = 0 OR (wp.position_sec / wp.duration_sec) >= ?)
               AND (wp.duration_sec = 0 OR (wp.position_sec / wp.duration_sec) < ?)
             ORDER BY wp.last_watched_at DESC'
        );
        $stmt->execute([$userId, $min, $complete]);
        return $stmt->fetchAll();
    }

    public function saveProgress(int $userId, int $mediaId, int $position, int $duration): void
    {
        $pdo = Database::pdo();
        $completeRatio = (float) Config::get('app.stream_complete_ratio', 0.90);
        $completed = ($duration > 0 && ($position / $duration) >= $completeRatio) ? 1 : 0;

        $pdo->prepare(
            'INSERT INTO `' . Config::table('watch_progress') . '`
            (user_id, media_id, position_sec, duration_sec, completed, last_watched_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            position_sec = VALUES(position_sec),
            duration_sec = VALUES(duration_sec),
            completed = VALUES(completed),
            last_watched_at = NOW(),
            updated_at = NOW()'
        )->execute([$userId, $mediaId, $position, $duration, $completed]);
    }

    public function posterWebPath(?string $local, ?string $remote): string
    {
        if ($local && is_file(putmio_base_path() . '/' . $local)) {
            return rtrim(Config::get('app.url'), '/') . '/poster?f=' . urlencode(basename($local));
        }
        if ($remote) {
            return $remote;
        }
        return rtrim(Config::get('app.url'), '/') . '/public/assets/no-poster.svg';
    }
}
