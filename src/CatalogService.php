<?php

declare(strict_types=1);

namespace PutMio;

use PutMio\Auth\Session;
use PutMio\Config;
use PutMio\Database;
use PutMio\Media\ReleaseNameParser;
use PutMio\TMDB\Client as TmdbClient;

final class CatalogService
{
    private const PER_PAGE = 24;

    private function mediaOwnerSelectSql(): string
    {
        $filesTable = Config::table('putio_files');
        $mediaTable = Config::table('media_items');

        return 'COALESCE(
            pf.shared_by_username,
            (SELECT pf2.shared_by_username
             FROM `' . $mediaTable . '` ep
             INNER JOIN `' . $filesTable . '` pf2 ON pf2.id = ep.putio_file_id
             WHERE ep.series_id = mi.id AND pf2.shared_by_username IS NOT NULL AND pf2.shared_by_username != \'\'
             ORDER BY ep.id ASC
             LIMIT 1)
        ) AS shared_by_username';
    }

    public function perPage(): int
    {
        return self::PER_PAGE;
    }

    /** @return array{where: list<string>, params: list<mixed>} */
    private function mediaFilterClause(array $filters): array
    {
        $where = ["mi.classification_status != 'ignored'", 'mi.series_id IS NULL'];
        $params = [];

        if (!empty($filters['type'])) {
            $where[] = 'mi.media_type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['genre'])) {
            $where[] = 'EXISTS (
                SELECT 1 FROM `' . Config::table('media_genres') . '` mg
                WHERE mg.media_id = mi.id AND mg.genre_id = ?
            )';
            $params[] = (int) $filters['genre'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(mi.title LIKE ? OR mi.original_title LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['classified'])) {
            $where[] = "mi.classification_status = 'classified'";
        }
        if (!empty($filters['shared_by'])) {
            $filesTable = Config::table('putio_files');
            $mediaTable = Config::table('media_items');
            $where[] = '(
                EXISTS (
                    SELECT 1 FROM `' . $filesTable . '` pf_owner
                    WHERE pf_owner.id = mi.putio_file_id
                    AND pf_owner.shared_by_username = ?
                )
                OR EXISTS (
                    SELECT 1 FROM `' . $mediaTable . '` ep
                    INNER JOIN `' . $filesTable . '` pf_ep ON pf_ep.id = ep.putio_file_id
                    WHERE ep.series_id = mi.id
                    AND pf_ep.shared_by_username = ?
                )
            )';
            $params[] = $filters['shared_by'];
            $params[] = $filters['shared_by'];
        }

        return ['where' => $where, 'params' => $params];
    }

    private function mediaOrderClause(array $filters): string
    {
        return match ($filters['sort'] ?? 'updated_at') {
            'title' => 'mi.title ASC, mi.id ASC',
            default => 'mi.updated_at DESC',
        };
    }

    public function listMedia(array $filters = [], int $limit = 48, int $offset = 0): array
    {
        $pdo = Database::pdo();
        ['where' => $where, 'params' => $params] = $this->mediaFilterClause($filters);
        $genreTable = Config::table('genres');
        $mediaGenresTable = Config::table('media_genres');

        $sql = 'SELECT mi.*, pf.putio_id, pf.size,
                ' . $this->mediaOwnerSelectSql() . ',
                (SELECT GROUP_CONCAT(g.name ORDER BY g.name SEPARATOR \', \')
                 FROM `' . $mediaGenresTable . '` mg
                 JOIN `' . $genreTable . '` g ON g.id = mg.genre_id
                 WHERE mg.media_id = mi.id) AS genre_names
                FROM `' . Config::table('media_items') . '` mi
                LEFT JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $this->mediaOrderClause($filters) . '
                LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countMedia(array $filters = []): int
    {
        $pdo = Database::pdo();
        ['where' => $where, 'params' => $params] = $this->mediaFilterClause($filters);

        $sql = 'SELECT COUNT(*) FROM `' . Config::table('media_items') . '` mi
                WHERE ' . implode(' AND ', $where);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return list<array{id: int, name: string}> */
    public function listGenres(): array
    {
        $pdo = Database::pdo();
        $sql = 'SELECT g.id, g.name
                FROM `' . Config::table('genres') . '` g
                WHERE EXISTS (
                    SELECT 1 FROM `' . Config::table('media_genres') . '` mg
                    JOIN `' . Config::table('media_items') . '` mi ON mi.id = mg.media_id
                    WHERE mg.genre_id = g.id AND mi.classification_status != \'ignored\' AND mi.series_id IS NULL
                )
                ORDER BY g.name ASC';
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /** @return list<string> */
    public function listSharedByUsernames(): array
    {
        $pdo = Database::pdo();
        $filesTable = Config::table('putio_files');
        $mediaTable = Config::table('media_items');

        $sql = 'SELECT DISTINCT username FROM (
                    SELECT pf.shared_by_username AS username
                    FROM `' . $mediaTable . '` mi
                    INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
                    WHERE mi.classification_status != \'ignored\'
                      AND mi.series_id IS NULL
                      AND pf.shared_by_username IS NOT NULL
                      AND pf.shared_by_username != \'\'
                    UNION
                    SELECT pf.shared_by_username AS username
                    FROM `' . $mediaTable . '` mi
                    INNER JOIN `' . $mediaTable . '` ep ON ep.series_id = mi.id
                    INNER JOIN `' . $filesTable . '` pf ON pf.id = ep.putio_file_id
                    WHERE mi.classification_status != \'ignored\'
                      AND mi.series_id IS NULL
                      AND pf.shared_by_username IS NOT NULL
                      AND pf.shared_by_username != \'\'
                ) AS sharers
                ORDER BY username ASC';

        $stmt = $pdo->query($sql);
        if (!$stmt) {
            return [];
        }

        return array_map(static fn (array $row): string => (string) $row['username'], $stmt->fetchAll());
    }

    /**
     * Generi in ordine alfabetico con contenuti classificati per la home.
     *
     * @return list<array{id: int, name: string, items: list<array<string, mixed>>}>
     */
    public function homeGenresWithMedia(int $limitPerGenre = 16): array
    {
        $pdo = Database::pdo();
        $genresTable = Config::table('genres');
        $mediaGenresTable = Config::table('media_genres');
        $mediaTable = Config::table('media_items');

        $sql = 'SELECT g.id, g.name
                FROM `' . $genresTable . '` g
                WHERE EXISTS (
                    SELECT 1 FROM `' . $mediaGenresTable . '` mg
                    JOIN `' . $mediaTable . '` mi ON mi.id = mg.media_id
                    WHERE mg.genre_id = g.id
                      AND mi.classification_status = \'classified\'
                      AND mi.series_id IS NULL
                )
                ORDER BY g.name ASC';
        $stmt = $pdo->query($sql);
        if (!$stmt) {
            return [];
        }

        $rows = [];
        foreach ($stmt->fetchAll() as $genre) {
            $items = $this->listMedia([
                'genre' => (string) $genre['id'],
                'classified' => true,
            ], $limitPerGenre);
            if ($items === []) {
                continue;
            }
            $rows[] = [
                'id' => (int) $genre['id'],
                'name' => (string) $genre['name'],
                'items' => $items,
            ];
        }

        return $rows;
    }

    /** @param list<array{id?: int, name?: string}> $tmdbGenres */
    public function syncMediaGenres(int $mediaId, array $tmdbGenres): void
    {
        $pdo = Database::pdo();
        $genresTable = Config::table('genres');
        $mgTable = Config::table('media_genres');

        $pdo->prepare('DELETE FROM `' . $mgTable . '` WHERE media_id = ?')->execute([$mediaId]);

        foreach ($tmdbGenres as $genre) {
            $name = trim((string) ($genre['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $tmdbGenreId = (int) ($genre['id'] ?? 0);

            $genreId = null;
            if ($tmdbGenreId > 0) {
                $stmt = $pdo->prepare('SELECT id FROM `' . $genresTable . '` WHERE tmdb_genre_id = ? LIMIT 1');
                $stmt->execute([$tmdbGenreId]);
                $genreId = $stmt->fetchColumn() ?: null;
            }
            if ($genreId === null) {
                $stmt = $pdo->prepare('SELECT id FROM `' . $genresTable . '` WHERE name = ? LIMIT 1');
                $stmt->execute([$name]);
                $genreId = $stmt->fetchColumn() ?: null;
            }
            if ($genreId === null) {
                $pdo->prepare(
                    'INSERT INTO `' . $genresTable . '` (name, tmdb_genre_id) VALUES (?, ?)'
                )->execute([$name, $tmdbGenreId > 0 ? $tmdbGenreId : null]);
                $genreId = (int) $pdo->lastInsertId();
            }

            $pdo->prepare(
                'INSERT IGNORE INTO `' . $mgTable . '` (media_id, genre_id) VALUES (?, ?)'
            )->execute([$mediaId, (int) $genreId]);
        }
    }

    /** Allinea media_type ai metadati TMDB già salvati (contenuti collegati prima del fix). */
    public function backfillLinkedMediaTypes(): void
    {
        $pdo = Database::pdo();
        $table = Config::table('media_items');
        $genresTable = Config::table('genres');
        $mgTable = Config::table('media_genres');

        $pdo->exec(
            'UPDATE `' . $table . '` mi
             SET media_type = \'animazione\'
             WHERE mi.media_type IN (\'altro\', \'film\', \'serie\')
               AND EXISTS (
                 SELECT 1 FROM `' . $mgTable . '` mg
                 JOIN `' . $genresTable . '` g ON g.id = mg.genre_id
                 WHERE mg.media_id = mi.id AND g.tmdb_genre_id = 16
               )'
        );
        $pdo->exec(
            'UPDATE `' . $table . '`
             SET media_type = \'serie\'
             WHERE media_type = \'altro\' AND tmdb_type = \'tv\''
        );
        $pdo->exec(
            'UPDATE `' . $table . '`
             SET media_type = \'film\'
             WHERE media_type = \'altro\' AND tmdb_type = \'movie\''
        );
    }

    public function findMedia(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT mi.*, pf.putio_id, pf.name AS file_name, pf.size, pf.mime AS file_mime,
                    ' . $this->mediaOwnerSelectSql() . '
             FROM `' . Config::table('media_items') . '` mi
             LEFT JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
             WHERE mi.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function isSeries(array $media): bool
    {
        return ($media['media_type'] ?? '') === 'serie'
            && empty($media['series_id'])
            && empty($media['putio_file_id']);
    }

    /** @return array<int, list<array<string, mixed>>> */
    public function episodesBySeason(int $seriesId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT mi.*, pf.putio_id, pf.name AS file_name
             FROM `' . Config::table('media_items') . '` mi
             JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
             WHERE mi.series_id = ?
             ORDER BY mi.season_number ASC, mi.episode_number ASC, mi.id ASC'
        );
        $stmt->execute([$seriesId]);
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $season = (int) ($row['season_number'] ?? 0);
            $grouped[$season][] = $row;
        }

        return $grouped;
    }

    public function countSeriesEpisodes(int $seriesId): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM `' . Config::table('media_items') . '` WHERE series_id = ?'
        );
        $stmt->execute([$seriesId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{prev: ?array<string, mixed>, next: ?array<string, mixed>} */
    public function adjacentEpisodes(int $episodeId): array
    {
        $media = $this->findMedia($episodeId);
        if (!$media || empty($media['series_id'])) {
            return ['prev' => null, 'next' => null];
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT mi.id, mi.title, mi.season_number, mi.episode_number
             FROM `' . Config::table('media_items') . '` mi
             JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
             WHERE mi.series_id = ?
             ORDER BY mi.season_number ASC, mi.episode_number ASC, mi.id ASC'
        );
        $stmt->execute([(int) $media['series_id']]);
        $episodes = $stmt->fetchAll();

        $prev = null;
        $next = null;
        foreach ($episodes as $index => $episode) {
            if ((int) $episode['id'] !== $episodeId) {
                continue;
            }
            if ($index > 0) {
                $prev = $episodes[$index - 1];
            }
            if (isset($episodes[$index + 1])) {
                $next = $episodes[$index + 1];
            }
            break;
        }

        return ['prev' => $prev, 'next' => $next];
    }

    public function episodeProgressForUser(int $userId, int $seriesId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT wp.media_id, wp.position_sec, wp.duration_sec, wp.completed
             FROM `' . Config::table('watch_progress') . '` wp
             JOIN `' . Config::table('media_items') . '` mi ON mi.id = wp.media_id
             WHERE wp.user_id = ? AND mi.series_id = ?'
        );
        $stmt->execute([$userId, $seriesId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['media_id']] = $row;
        }

        return $map;
    }

    /** @return list<string> */
    public function mediaGenreNames(int $mediaId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT g.name
             FROM `' . Config::table('media_genres') . '` mg
             JOIN `' . Config::table('genres') . '` g ON g.id = mg.genre_id
             WHERE mg.media_id = ?
             ORDER BY g.name ASC'
        );
        $stmt->execute([$mediaId]);
        return array_column($stmt->fetchAll(), 'name');
    }

    public function markWatched(int $userId, int $mediaId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT duration_sec FROM `' . Config::table('watch_progress') . '` WHERE user_id = ? AND media_id = ?'
        );
        $stmt->execute([$userId, $mediaId]);
        $duration = (int) ($stmt->fetchColumn() ?: 0);

        if ($duration <= 0) {
            $mediaStmt = $pdo->prepare(
                'SELECT duration_sec FROM `' . Config::table('media_items') . '` WHERE id = ?'
            );
            $mediaStmt->execute([$mediaId]);
            $duration = (int) ($mediaStmt->fetchColumn() ?: 0);
        }

        $position = $duration > 0 ? $duration : 1;

        $pdo->prepare(
            'INSERT INTO `' . Config::table('watch_progress') . '`
            (user_id, media_id, position_sec, duration_sec, completed, last_watched_at)
            VALUES (?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
            position_sec = VALUES(position_sec),
            duration_sec = GREATEST(duration_sec, VALUES(duration_sec)),
            completed = 1,
            last_watched_at = NOW(),
            updated_at = NOW()'
        )->execute([$userId, $mediaId, $position, $duration]);
    }

    public function resetProgress(int $userId, int $mediaId): void
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'DELETE FROM `' . Config::table('watch_progress') . '` WHERE user_id = ? AND media_id = ?'
        )->execute([$userId, $mediaId]);
    }

    public function inProgressForUser(int $userId): array
    {
        $pdo = Database::pdo();
        $complete = (float) Config::get('app.stream_complete_ratio', 0.90);
        $min = (float) Config::get('app.stream_min_progress_ratio', 0.05);
        $mediaTable = Config::table('media_items');

        $stmt = $pdo->prepare(
            'SELECT mi.*, pf.putio_id, wp.position_sec, wp.duration_sec, wp.last_watched_at,
                    COALESCE(mi.poster_local_path, s.poster_local_path) AS poster_local_path,
                    COALESCE(mi.poster_url, s.poster_url) AS poster_url,
                    s.title AS series_title,
                    ' . $this->mediaOwnerSelectSql() . '
             FROM `' . Config::table('watch_progress') . '` wp
             JOIN `' . $mediaTable . '` mi ON mi.id = wp.media_id
             JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
             LEFT JOIN `' . $mediaTable . '` s ON s.id = mi.series_id
             WHERE wp.user_id = ? AND wp.completed = 0
               AND wp.position_sec > 0
               AND (wp.duration_sec = 0 OR (wp.position_sec / wp.duration_sec) >= ?)
               AND (wp.duration_sec = 0 OR (wp.position_sec / wp.duration_sec) < ?)
             ORDER BY wp.last_watched_at DESC'
        );
        $stmt->execute([$userId, $min, $complete]);
        return $stmt->fetchAll();
    }

    /** Propaga locandina, classificazione e metadati TMDB dalla serie a tutti gli episodi. */
    public function syncSeriesMetadataToEpisodes(int $seriesId): void
    {
        $series = $this->findMedia($seriesId);
        if (!$series || !$this->isSeries($series)) {
            return;
        }

        $pdo = Database::pdo();
        $mediaTable = Config::table('media_items');
        $stmt = $pdo->prepare(
            'SELECT mi.*, pf.name AS file_name
             FROM `' . $mediaTable . '` mi
             LEFT JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
             WHERE mi.series_id = ?
             ORDER BY mi.season_number ASC, mi.episode_number ASC, mi.id ASC'
        );
        $stmt->execute([$seriesId]);
        $episodes = $stmt->fetchAll();
        if ($episodes === []) {
            return;
        }

        $tmdbClient = null;
        $tvId = (int) ($series['tmdb_id'] ?? 0);
        if ($tvId > 0 && ($series['tmdb_type'] ?? '') === 'tv') {
            $client = new TmdbClient();
            if ($client->isConfigured()) {
                $tmdbClient = $client;
            }
        }

        $updateStmt = $pdo->prepare(
            'UPDATE `' . $mediaTable . '`
             SET title = ?, synopsis = ?, poster_local_path = ?, poster_url = ?,
                 media_type = ?, classification_status = ?, year = ?,
                 duration_sec = COALESCE(?, duration_sec),
                 updated_at = NOW()
             WHERE id = ?'
        );

        foreach ($episodes as $episode) {
            $season = (int) ($episode['season_number'] ?? 0);
            $episodeNum = (int) ($episode['episode_number'] ?? 0);
            $title = (string) ($episode['title'] ?? '');
            $synopsis = (string) ($episode['synopsis'] ?? '');
            $durationSec = !empty($episode['duration_sec']) ? (int) $episode['duration_sec'] : null;

            if ($tmdbClient !== null && $season > 0 && $episodeNum > 0) {
                try {
                    $details = $tmdbClient->episodeDetails($tvId, $season, $episodeNum);
                    $episodeName = trim((string) ($details['name'] ?? ''));
                    $title = ReleaseNameParser::episodeDisplayTitle(
                        $season,
                        $episodeNum,
                        $episodeName !== '' ? $episodeName : null
                    );
                    $overview = trim((string) ($details['overview'] ?? ''));
                    if ($overview !== '') {
                        $synopsis = $overview;
                    }
                    if (!empty($details['runtime'])) {
                        $durationSec = (int) $details['runtime'] * 60;
                    }
                } catch (\Throwable $e) {
                    // Mantieni titolo da filename se TMDB non risponde per questo episodio.
                }
            } elseif ($season > 0 && $episodeNum > 0 && !empty($episode['file_name'])) {
                $parsed = ReleaseNameParser::parseEpisode((string) $episode['file_name']);
                if ($parsed !== null) {
                    $title = ReleaseNameParser::episodeDisplayTitle(
                        $parsed['season'],
                        $parsed['episode'],
                        $parsed['episode_title']
                    );
                }
            }

            if ($synopsis === '' && !empty($series['synopsis'])) {
                $synopsis = (string) $series['synopsis'];
            }

            $updateStmt->execute([
                $title,
                $synopsis !== '' ? $synopsis : null,
                $series['poster_local_path'] ?? null,
                $series['poster_url'] ?? null,
                $series['media_type'] ?? 'serie',
                ($series['classification_status'] ?? '') === 'classified' ? 'classified' : ($episode['classification_status'] ?? 'unclassified'),
                $series['year'] ?? null,
                $durationSec,
                (int) $episode['id'],
            ]);
        }
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
