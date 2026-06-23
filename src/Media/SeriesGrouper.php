<?php

declare(strict_types=1);

namespace PutMio\Media;

use PutMio\CatalogService;
use PutMio\Config;
use PutMio\Database;

final class SeriesGrouper
{
    public function groupAll(): void
    {
        $pdo = Database::pdo();
        $mediaTable = Config::table('media_items');
        $filesTable = Config::table('putio_files');

        $stmt = $pdo->query(
            'SELECT mi.id, mi.series_id, mi.tmdb_id, mi.tmdb_type, mi.title, mi.media_type,
                    mi.classification_status, mi.original_title, mi.year, mi.synopsis,
                    mi.poster_local_path, mi.poster_url, mi.duration_sec,
                    pf.name AS file_name, parent_pf.name AS folder_name
             FROM `' . $mediaTable . '` mi
             JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
             LEFT JOIN `' . $filesTable . '` parent_pf ON parent_pf.putio_id = pf.parent_putio_id
             WHERE mi.putio_file_id IS NOT NULL
             ORDER BY mi.id ASC'
        );
        $rows = $stmt ? $stmt->fetchAll() : [];

        foreach ($rows as $row) {
            $parsed = $this->parseEpisodeRow($row);
            if ($parsed === null) {
                continue;
            }

            $episodeTitle = ReleaseNameParser::episodeDisplayTitle(
                $parsed['season'],
                $parsed['episode'],
                $parsed['episode_title']
            );

            $existingSeriesId = !empty($row['series_id']) ? (int) $row['series_id'] : null;
            if ($existingSeriesId !== null && $this->shouldKeepSeriesAssignment($pdo, $existingSeriesId, $parsed['show_title'])) {
                $pdo->prepare(
                    'UPDATE `' . $mediaTable . '`
                     SET season_number = ?, episode_number = ?, title = ?, media_type = \'serie\', updated_at = NOW()
                     WHERE id = ?'
                )->execute([
                    $parsed['season'],
                    $parsed['episode'],
                    $episodeTitle,
                    (int) $row['id'],
                ]);
                continue;
            }

            $seriesId = $this->resolveSeriesId($pdo, $parsed['show_title'], $row);

            $pdo->prepare(
                'UPDATE `' . $mediaTable . '`
                 SET series_id = ?, season_number = ?, episode_number = ?, title = ?, media_type = \'serie\', updated_at = NOW()
                 WHERE id = ?'
            )->execute([$seriesId, $parsed['season'], $parsed['episode'], $episodeTitle, (int) $row['id']]);

            $this->promoteSeriesMetadata($pdo, $seriesId, $row);
        }

        $this->consolidateDuplicateSeries($pdo);
        $this->runSyncSeriesFromEpisodes($pdo);

        $seriesStmt = $pdo->query(
            'SELECT id FROM `' . $mediaTable . '`
             WHERE series_id IS NULL AND putio_file_id IS NULL
               AND (
                 classification_status = \'classified\'
                 OR tmdb_id IS NOT NULL
                 OR poster_local_path IS NOT NULL
                 OR poster_url IS NOT NULL
               )'
        );
        $seriesRows = $seriesStmt ? $seriesStmt->fetchAll() : [];
        $catalog = new CatalogService();
        foreach ($seriesRows as $seriesRow) {
            $catalog->syncSeriesMetadataToEpisodes((int) $seriesRow['id']);
        }
    }

    public function groupMedia(int $mediaId): void
    {
        $pdo = Database::pdo();
        $mediaTable = Config::table('media_items');
        $filesTable = Config::table('putio_files');

        $stmt = $pdo->prepare(
            'SELECT mi.*, pf.name AS file_name, parent_pf.name AS folder_name
             FROM `' . $mediaTable . '` mi
             JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
             LEFT JOIN `' . $filesTable . '` parent_pf ON parent_pf.putio_id = pf.parent_putio_id
             WHERE mi.id = ? LIMIT 1'
        );
        $stmt->execute([$mediaId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $parsed = $this->parseEpisodeRow($row);
        if ($parsed === null) {
            return;
        }

        $episodeTitle = ReleaseNameParser::episodeDisplayTitle(
            $parsed['season'],
            $parsed['episode'],
            $parsed['episode_title']
        );

        $existingSeriesId = !empty($row['series_id']) ? (int) $row['series_id'] : null;
        if ($existingSeriesId !== null && $this->shouldKeepSeriesAssignment($pdo, $existingSeriesId, $parsed['show_title'])) {
            $pdo->prepare(
                'UPDATE `' . $mediaTable . '`
                 SET season_number = ?, episode_number = ?, title = ?, media_type = \'serie\', updated_at = NOW()
                 WHERE id = ?'
            )->execute([
                $parsed['season'],
                $parsed['episode'],
                $episodeTitle,
                $mediaId,
            ]);
            $this->consolidateDuplicateSeries($pdo);
            $this->runSyncSeriesFromEpisodes($pdo);
            $this->syncEpisodesIfSeriesReady($existingSeriesId);

            return;
        }

        $seriesId = $this->resolveSeriesId($pdo, $parsed['show_title'], $row);

        $pdo->prepare(
            'UPDATE `' . $mediaTable . '`
             SET series_id = ?, season_number = ?, episode_number = ?, title = ?, media_type = \'serie\', updated_at = NOW()
             WHERE id = ?'
        )->execute([$seriesId, $parsed['season'], $parsed['episode'], $episodeTitle, $mediaId]);

        $this->promoteSeriesMetadata($pdo, $seriesId, $row);
        $this->consolidateDuplicateSeries($pdo);
        $this->runSyncSeriesFromEpisodes($pdo);
        $this->syncEpisodesIfSeriesReady($seriesId);
    }

    private function syncEpisodesIfSeriesReady(int $seriesId): void
    {
        $series = (new CatalogService())->findMedia($seriesId);
        if (!$series) {
            return;
        }

        $isReady = ($series['classification_status'] ?? '') === 'classified'
            || !empty($series['tmdb_id'])
            || !empty($series['poster_local_path'])
            || !empty($series['poster_url']);

        if ($isReady) {
            (new CatalogService())->syncSeriesMetadataToEpisodes($seriesId);
        }
    }

    /** @param array<string, mixed> $episodeRow */
    private function resolveSeriesId(\PDO $pdo, string $showTitle, array $episodeRow): int
    {
        $existing = $this->findSeriesStubId($pdo, $showTitle);
        if ($existing !== null) {
            return $existing;
        }

        $mediaTable = Config::table('media_items');
        $pdo->prepare(
            'INSERT INTO `' . $mediaTable . '`
             (putio_file_id, media_type, title, classification_status, created_at, updated_at)
             VALUES (NULL, \'serie\', ?, \'unclassified\', NOW(), NOW())'
        )->execute([$showTitle]);

        return (int) $pdo->lastInsertId();
    }

    private function shouldKeepSeriesAssignment(\PDO $pdo, int $seriesId, string $parsedShowTitle): bool
    {
        $mediaTable = Config::table('media_items');
        $stmt = $pdo->prepare(
            'SELECT id, title FROM `' . $mediaTable . '`
             WHERE id = ? AND series_id IS NULL AND putio_file_id IS NULL
             LIMIT 1'
        );
        $stmt->execute([$seriesId]);
        $parent = $stmt->fetch();
        if (!$parent) {
            return false;
        }

        $preferredId = $this->findSeriesStubId($pdo, $parsedShowTitle);

        return $preferredId === null || $preferredId === $seriesId;
    }

    private function findSeriesStubId(\PDO $pdo, string $showTitle): ?int
    {
        $mediaTable = Config::table('media_items');
        $normalized = $this->normalizeTitle($showTitle);
        $stmt = $pdo->query(
            'SELECT id, title, tmdb_id, classification_status
             FROM `' . $mediaTable . '`
             WHERE series_id IS NULL AND putio_file_id IS NULL'
        );
        $rows = $stmt ? $stmt->fetchAll() : [];

        $bestId = null;
        $bestScore = -1;
        foreach ($rows as $series) {
            if ($this->normalizeTitle((string) $series['title']) !== $normalized) {
                continue;
            }

            $score = 0;
            if (!empty($series['tmdb_id'])) {
                $score += 100;
            }
            if (($series['classification_status'] ?? '') === 'classified') {
                $score += 10;
            }

            if ($score > $bestScore || ($score === $bestScore && ($bestId === null || (int) $series['id'] < $bestId))) {
                $bestScore = $score;
                $bestId = (int) $series['id'];
            }
        }

        return $bestId;
    }

    /** @param array<string, mixed> $episodeRow */
    private function promoteSeriesMetadata(\PDO $pdo, int $seriesId, array $episodeRow): void
    {
        $mediaTable = Config::table('media_items');
        $stmt = $pdo->prepare('SELECT * FROM `' . $mediaTable . '` WHERE id = ? LIMIT 1');
        $stmt->execute([$seriesId]);
        $series = $stmt->fetch();
        if (!$series) {
            return;
        }

        $showTitle = ReleaseNameParser::guessTitle(
            (string) ($episodeRow['file_name'] ?? $episodeRow['title'] ?? ''),
            isset($episodeRow['folder_name']) ? (string) $episodeRow['folder_name'] : null
        ) ?? (string) ($episodeRow['title'] ?? 'Serie TV');

        if (($episodeRow['tmdb_type'] ?? '') === 'tv') {
            $showTitle = (string) ($episodeRow['title'] ?? $showTitle);
        }

        if (!empty($episodeRow['tmdb_id']) && empty($series['tmdb_id'])) {
            $pdo->prepare(
                'UPDATE `' . $mediaTable . '`
                 SET title = ?, original_title = ?, year = ?, synopsis = ?,
                     poster_local_path = ?, poster_url = ?,
                     backdrop_local_path = ?, backdrop_url = ?,
                     tmdb_id = ?, tmdb_type = ?,
                     duration_sec = COALESCE(?, duration_sec),
                     media_type = \'serie\',
                     classification_status = ?, updated_at = NOW()
                 WHERE id = ?'
            )->execute([
                $showTitle,
                $episodeRow['original_title'] ?? null,
                $episodeRow['year'] ?? null,
                $episodeRow['synopsis'] ?? null,
                $episodeRow['poster_local_path'] ?? null,
                $episodeRow['poster_url'] ?? null,
                $episodeRow['backdrop_local_path'] ?? null,
                $episodeRow['backdrop_url'] ?? null,
                $episodeRow['tmdb_id'] ?? null,
                $episodeRow['tmdb_type'] ?? null,
                $episodeRow['duration_sec'] ?? null,
                $episodeRow['classification_status'] ?? 'classified',
                $seriesId,
            ]);

            $catalog = new CatalogService();
            $genreStmt = $pdo->prepare(
                'SELECT genre_id FROM `' . Config::table('media_genres') . '` WHERE media_id = ?'
            );
            $genreStmt->execute([(int) $episodeRow['id']]);
            $genreIds = array_column($genreStmt->fetchAll(), 'genre_id');
            if ($genreIds !== []) {
                $genresTable = Config::table('genres');
                $placeholders = implode(',', array_fill(0, count($genreIds), '?'));
                $genreRows = $pdo->prepare(
                    'SELECT name, tmdb_genre_id AS id FROM `' . $genresTable . '` WHERE id IN (' . $placeholders . ')'
                );
                $genreRows->execute($genreIds);
                $catalog->syncMediaGenres($seriesId, $genreRows->fetchAll());
            }

            $catalog->syncSeriesMetadataToEpisodes($seriesId);

            return;
        }

        if (($episodeRow['classification_status'] ?? '') === 'classified' && ($series['classification_status'] ?? '') === 'unclassified') {
            $pdo->prepare(
                'UPDATE `' . $mediaTable . '`
                 SET title = ?, media_type = \'serie\', classification_status = \'classified\', updated_at = NOW()
                 WHERE id = ?'
            )->execute([$showTitle, $seriesId]);

            $catalog = new CatalogService();
            $catalog->syncSeriesMetadataToEpisodes($seriesId);
        }
    }

    private function consolidateDuplicateSeries(\PDO $pdo): void
    {
        $mediaTable = Config::table('media_items');
        $stmt = $pdo->query(
            'SELECT id, title, tmdb_id, classification_status
             FROM `' . $mediaTable . '`
             WHERE series_id IS NULL AND putio_file_id IS NULL'
        );
        $rows = $stmt ? $stmt->fetchAll() : [];

        $groups = [];
        foreach ($rows as $series) {
            $key = $this->normalizeTitle((string) $series['title']);
            if ($key === '') {
                continue;
            }
            $groups[$key][] = $series;
        }

        foreach ($groups as $members) {
            if (count($members) < 2) {
                continue;
            }

            usort($members, static function (array $a, array $b): int {
                $scoreA = (!empty($a['tmdb_id']) ? 100 : 0)
                    + (($a['classification_status'] ?? '') === 'classified' ? 10 : 0);
                $scoreB = (!empty($b['tmdb_id']) ? 100 : 0)
                    + (($b['classification_status'] ?? '') === 'classified' ? 10 : 0);
                if ($scoreA !== $scoreB) {
                    return $scoreB <=> $scoreA;
                }

                return (int) $a['id'] <=> (int) $b['id'];
            });

            $keepId = (int) $members[0]['id'];
            foreach (array_slice($members, 1) as $duplicate) {
                $duplicateId = (int) $duplicate['id'];
                $pdo->prepare(
                    'UPDATE `' . $mediaTable . '` SET series_id = ? WHERE series_id = ?'
                )->execute([$keepId, $duplicateId]);
                $pdo->prepare('DELETE FROM `' . $mediaTable . '` WHERE id = ?')->execute([$duplicateId]);
            }
        }
    }

    public function syncSeriesFromEpisodes(): void
    {
        $this->runSyncSeriesFromEpisodes(Database::pdo());
    }

    private function runSyncSeriesFromEpisodes(\PDO $pdo): void
    {
        $mediaTable = Config::table('media_items');

        $pdo->exec(
            'UPDATE `' . $mediaTable . '` s
             INNER JOIN (
                 SELECT series_id,
                        MAX(classification_status = \'classified\') AS has_classified,
                        MAX(tmdb_id IS NOT NULL) AS has_tmdb
                 FROM `' . $mediaTable . '`
                 WHERE series_id IS NOT NULL
                 GROUP BY series_id
             ) ep ON ep.series_id = s.id
             SET s.classification_status = \'classified\',
                 s.media_type = \'serie\',
                 s.updated_at = NOW()
             WHERE s.putio_file_id IS NULL
               AND s.series_id IS NULL
               AND s.classification_status = \'unclassified\'
               AND (ep.has_classified = 1 OR ep.has_tmdb = 1)'
        );

        $stmt = $pdo->query(
            'SELECT s.id AS series_id, e.id AS episode_id
             FROM `' . $mediaTable . '` s
             JOIN `' . $mediaTable . '` e ON e.series_id = s.id
             WHERE s.putio_file_id IS NULL AND s.series_id IS NULL AND s.tmdb_id IS NULL
               AND e.tmdb_id IS NOT NULL
             ORDER BY s.id ASC, (e.classification_status = \'classified\') DESC, e.id ASC'
        );
        $rows = $stmt ? $stmt->fetchAll() : [];
        $seen = [];
        foreach ($rows as $row) {
            $seriesId = (int) $row['series_id'];
            if (isset($seen[$seriesId])) {
                continue;
            }
            $seen[$seriesId] = true;

            $epStmt = $pdo->prepare(
                'SELECT mi.*, pf.name AS file_name, parent_pf.name AS folder_name
                 FROM `' . $mediaTable . '` mi
                 LEFT JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
                 LEFT JOIN `' . Config::table('putio_files') . '` parent_pf ON parent_pf.putio_id = pf.parent_putio_id
                 WHERE mi.id = ? LIMIT 1'
            );
            $epStmt->execute([(int) $row['episode_id']]);
            $episode = $epStmt->fetch();
            if ($episode) {
                $this->promoteSeriesMetadata($pdo, $seriesId, $episode);
            }
        }
    }

    private function normalizeTitle(string $title): string
    {
        $title = mb_strtolower(trim($title));
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title) ?? $title;
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return trim($title);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{show_title: string, season: int, episode: int, episode_title: ?string}|null
     */
    private function parseEpisodeRow(array $row): ?array
    {
        $folderName = isset($row['folder_name']) ? trim((string) $row['folder_name']) : '';

        return ReleaseNameParser::parseEpisode(
            (string) ($row['file_name'] ?? ''),
            $folderName !== '' ? $folderName : null
        );
    }
}
