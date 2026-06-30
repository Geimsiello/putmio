<?php

declare(strict_types=1);

namespace PutMio\Media;

use PutMio\Config;
use PutMio\Database;
use PutMio\OpenSubtitles\SubtitleService;

/**
 * Rimuove dal database e da storage/ i dati locali legati a un contenuto del catalogo.
 */
final class MediaCleanupService
{
    public function __construct(
        private readonly SubtitleService $subtitles = new SubtitleService(),
    ) {
    }

    /**
     * Pulizia batch per file put.io rimossi dal sync (prima di DELETE su putio_files).
     *
     * @param list<int|string> $params
     */
    public function purgeForPutioFiles(string $filesWhere, array $params): void
    {
        $pdo = Database::pdo();
        $filesTable = Config::table('putio_files');
        $sessionsTable = Config::table('stream_sessions');

        $pdo->prepare(
            'DELETE ss FROM `' . $sessionsTable . '` ss
             INNER JOIN `' . $filesTable . '` pf ON pf.id = ss.putio_file_id
             WHERE ' . $filesWhere
        )->execute($params);

        $mediaIds = $this->selectMediaIdsForPutioFiles($filesWhere, $params);
        $this->purgeMediaIds($mediaIds);
    }

    /** @param list<int> $mediaIds */
    public function purgeMediaIds(array $mediaIds): void
    {
        foreach (array_values(array_unique(array_filter($mediaIds))) as $mediaId) {
            $this->purgeMedia((int) $mediaId);
        }
    }

    public function purgeMedia(int $mediaId): void
    {
        if ($mediaId <= 0) {
            return;
        }

        $pdo = Database::pdo();
        $mediaTable = Config::table('media_items');
        $stmt = $pdo->prepare(
            'SELECT id, poster_local_path, backdrop_local_path FROM `' . $mediaTable . '` WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$mediaId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $this->subtitles->deleteAllForMedia($mediaId);
        $this->unlinkLocalArtwork($row['poster_local_path'] ?? null);
        $this->unlinkLocalArtwork($row['backdrop_local_path'] ?? null);

        $watchTable = Config::table('watch_progress');
        $genresTable = Config::table('media_genres');
        $tagsTable = Config::table('media_tags');
        $prefsTable = Config::table('user_subtitle_prefs');

        $pdo->prepare('DELETE FROM `' . $watchTable . '` WHERE media_id = ?')->execute([$mediaId]);
        $pdo->prepare('DELETE FROM `' . $genresTable . '` WHERE media_id = ?')->execute([$mediaId]);
        $pdo->prepare('DELETE FROM `' . $tagsTable . '` WHERE media_id = ?')->execute([$mediaId]);
        $pdo->prepare('DELETE FROM `' . $prefsTable . '` WHERE media_id = ?')->execute([$mediaId]);
        $pdo->prepare('DELETE FROM `' . $mediaTable . '` WHERE id = ?')->execute([$mediaId]);
    }

    /** Rimuove contenitori serie senza episodi (orfani dopo sync). */
    public function pruneOrphanSeries(): int
    {
        $pdo = Database::pdo();
        $mediaTable = Config::table('media_items');
        $stmt = $pdo->query(
            'SELECT s.id
             FROM `' . $mediaTable . '` s
             WHERE s.putio_file_id IS NULL
               AND s.series_id IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM `' . $mediaTable . '` e WHERE e.series_id = s.id
               )'
        );
        $rows = $stmt ? $stmt->fetchAll() : [];

        $removed = 0;
        foreach ($rows as $row) {
            $this->purgeMedia((int) $row['id']);
            $removed++;
        }

        return $removed;
    }

    /**
     * @param list<int|string> $params
     * @return list<int>
     */
    private function selectMediaIdsForPutioFiles(string $filesWhere, array $params): array
    {
        $pdo = Database::pdo();
        $filesTable = Config::table('putio_files');
        $mediaTable = Config::table('media_items');
        $stmt = $pdo->prepare(
            'SELECT mi.id
             FROM `' . $mediaTable . '` mi
             INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
             WHERE ' . $filesWhere
        );
        $stmt->execute($params);

        return array_map(static fn (array $row): int => (int) $row['id'], $stmt->fetchAll() ?: []);
    }

    private function unlinkLocalArtwork(?string $localPath): void
    {
        if ($localPath === null || $localPath === '') {
            return;
        }

        $path = putmio_base_path() . '/' . ltrim($localPath, '/');
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
