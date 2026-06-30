<?php

declare(strict_types=1);

namespace PutMio\OpenSubtitles;

use PutMio\CatalogService;
use PutMio\Config;
use PutMio\Database;

final class SubtitleService
{
    public function __construct(
        private readonly Client $client = new Client(),
        private readonly CatalogService $catalog = new CatalogService(),
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForMedia(int $mediaId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT ms.*, u.display_name AS downloaded_by_name
             FROM `' . Config::table('media_subtitles') . '` ms
             LEFT JOIN `' . Config::table('users') . '` u ON u.id = ms.downloaded_by
             WHERE ms.media_id = ?
             ORDER BY ms.language ASC, ms.created_at DESC'
        );
        $stmt->execute([$mediaId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array{subtitle_id: ?int, offset_ms: int}
     */
    public function userPrefs(int $userId, int $mediaId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT subtitle_id, offset_ms FROM `' . Config::table('user_subtitle_prefs') . '`
             WHERE user_id = ? AND media_id = ?'
        );
        $stmt->execute([$userId, $mediaId]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['subtitle_id' => null, 'offset_ms' => 0];
        }

        return [
            'subtitle_id' => $row['subtitle_id'] !== null ? (int) $row['subtitle_id'] : null,
            'offset_ms' => (int) ($row['offset_ms'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchRemote(int $mediaId): array
    {
        if (!$this->client->isConfigured()) {
            throw new \RuntimeException(putmio_lang('subtitles_not_configured'));
        }

        $media = $this->catalog->findMedia($mediaId);
        if (!$media) {
            throw new \RuntimeException('Media non trovato');
        }

        $params = $this->buildSearchParams($media);
        $results = $this->client->search($params);
        $cachedIds = $this->cachedSourceFileIds($mediaId);

        $normalized = [];
        foreach ($results as $item) {
            if (!is_array($item)) {
                continue;
            }
            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : $item;
            $fileId = $this->extractFileId($item, $attributes);
            if ($fileId === null) {
                continue;
            }

            $lang = (string) ($attributes['language'] ?? $attributes['lang'] ?? '');
            $normalized[] = [
                'file_id' => $fileId,
                'language' => $lang,
                'label' => putmio_subtitle_language_label($lang),
                'release' => (string) ($attributes['release'] ?? $attributes['feature_details']['title'] ?? ''),
                'download_count' => (int) ($attributes['download_count'] ?? 0),
                'uploader' => (string) ($attributes['uploader']['name'] ?? $attributes['uploader_name'] ?? ''),
                'hearing_impaired' => !empty($attributes['hearing_impaired']),
                'cached' => in_array($fileId, $cachedIds, true),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function download(int $mediaId, string $fileId, int $userId, ?string $language = null, ?string $label = null): array
    {
        if (!$this->client->isConfigured()) {
            throw new \RuntimeException(putmio_lang('subtitles_not_configured'));
        }

        $media = $this->catalog->findMedia($mediaId);
        if (!$media) {
            throw new \RuntimeException('Media non trovato');
        }

        $fileId = trim($fileId);
        if ($fileId === '') {
            throw new \RuntimeException('ID file sottotitoli non valido');
        }

        $existing = $this->findBySourceFileId($mediaId, 'opensubtitles', $fileId);
        if ($existing !== null) {
            return $existing;
        }

        $raw = $this->client->downloadFile($fileId);
        $vtt = $this->toVtt($raw);

        $lang = $language !== null && $language !== '' ? $language : 'und';
        $displayLabel = $label !== null && $label !== '' ? $label : putmio_subtitle_language_label($lang);

        return $this->storeVtt($mediaId, 'opensubtitles', $fileId, $vtt, $lang, $displayLabel, $userId > 0 ? $userId : null);
    }

    /**
     * @return array<string, mixed>
     */
    public function storeVtt(
        int $mediaId,
        string $source,
        string $sourceFileId,
        string $vtt,
        string $language,
        string $label,
        ?int $userId = null
    ): array {
        $existing = $this->findBySourceFileId($mediaId, $source, $sourceFileId);
        if ($existing !== null) {
            return $existing;
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO `' . Config::table('media_subtitles') . '`
             (media_id, language, label, source, source_file_id, file_path, downloaded_by)
             VALUES (?, ?, ?, ?, ?, \'\', ?)'
        )->execute([$mediaId, $language, $label, $source, $sourceFileId, $userId]);

        $subtitleId = (int) $pdo->lastInsertId();
        $relativePath = 'subtitles/' . $mediaId . '/' . $subtitleId . '.vtt';
        $fullPath = putmio_base_path() . '/storage/' . $relativePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (@file_put_contents($fullPath, $vtt, LOCK_EX) === false) {
            $pdo->prepare('DELETE FROM `' . Config::table('media_subtitles') . '` WHERE id = ?')->execute([$subtitleId]);
            throw new \RuntimeException('Impossibile salvare il file sottotitoli');
        }

        $pdo->prepare(
            'UPDATE `' . Config::table('media_subtitles') . '` SET file_path = ? WHERE id = ?'
        )->execute([$relativePath, $subtitleId]);

        $row = $this->findById($subtitleId);
        if ($row === null) {
            throw new \RuntimeException('Sottotitolo salvato ma non trovato');
        }

        return $row;
    }

    public function prunePutioNotInKeys(int $mediaId, int $putioFileId, array $bareKeys): int
    {
        $bareKeys = array_values(array_unique(array_map(static fn ($key): string => trim((string) $key), $bareKeys)));
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, source_file_id FROM `' . Config::table('media_subtitles') . '`
             WHERE media_id = ? AND source = \'putio\'
               AND (putio_file_id IS NULL OR putio_file_id = ?)'
        );
        $stmt->execute([$mediaId, $putioFileId]);

        $removed = 0;
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $bareKey = putmio_putio_subtitle_bare_key((string) $row['source_file_id']);
            if (in_array($bareKey, $bareKeys, true)) {
                continue;
            }
            $this->delete((int) $row['id']);
            $removed++;
        }

        return $removed;
    }

    /**
     * @return array{removed: int, migrated: int}
     */
    public function repairPutioIntegrity(): array
    {
        $pdo = Database::pdo();
        $subtitlesTable = Config::table('media_subtitles');
        $mediaTable = Config::table('media_items');
        $filesTable = Config::table('putio_files');

        $removed = 0;
        $migrated = 0;

        $stmt = $pdo->query(
            'SELECT ms.id, ms.media_id, ms.source_file_id, ms.putio_file_id, ms.file_path,
                    pf.putio_id AS expected_putio_id
             FROM `' . $subtitlesTable . '` ms
             INNER JOIN `' . $mediaTable . '` mi ON mi.id = ms.media_id
             LEFT JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
             WHERE ms.source = \'putio\''
        );
        $rows = $stmt ? $stmt->fetchAll() : [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $mediaId = (int) $row['media_id'];
            $expectedPutioId = $row['expected_putio_id'] !== null ? (int) $row['expected_putio_id'] : null;
            $storedPutioId = $row['putio_file_id'] !== null ? (int) $row['putio_file_id'] : null;
            $bareKey = putmio_putio_subtitle_bare_key((string) $row['source_file_id']);
            $expectedPathPrefix = 'subtitles/' . $mediaId . '/';

            $invalid = $expectedPutioId === null
                || $expectedPutioId <= 0
                || $storedPutioId !== $expectedPutioId
                || !$this->isValidStoredPutioKey($bareKey)
                || !str_starts_with((string) $row['file_path'], $expectedPathPrefix);

            if ($invalid) {
                $this->delete($id);
                $removed++;
                continue;
            }

            $compositeId = putmio_putio_subtitle_source_id($expectedPutioId, $bareKey);
            if ((string) $row['source_file_id'] !== $compositeId) {
                $pdo->prepare(
                    'UPDATE `' . $subtitlesTable . '`
                     SET source_file_id = ?, putio_file_id = ?
                     WHERE id = ?'
                )->execute([$compositeId, $expectedPutioId, $id]);
                $migrated++;
            }
        }

        $this->removeDuplicatePutioSubtitles();

        return ['removed' => $removed, 'migrated' => $migrated];
    }

    /**
     * @return array{putio_id: int, putio_subtitles_sync_hash: ?string, file_name: string}|null
     */
    public function getMediaPutioContext(int $mediaId): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT pf.putio_id, mi.putio_subtitles_sync_hash, pf.name AS file_name
             FROM `' . Config::table('media_items') . '` mi
             INNER JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
             WHERE mi.id = ? LIMIT 1'
        );
        $stmt->execute([$mediaId]);
        $row = $stmt->fetch();
        if (!$row || empty($row['putio_id'])) {
            return null;
        }

        return [
            'putio_id' => (int) $row['putio_id'],
            'putio_subtitles_sync_hash' => $row['putio_subtitles_sync_hash'] !== null
                ? (string) $row['putio_subtitles_sync_hash']
                : null,
            'file_name' => (string) ($row['file_name'] ?? ''),
        ];
    }

    /** @return list<string> */
    public function listPutioBareKeysForMedia(int $mediaId, int $putioFileId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT source_file_id FROM `' . Config::table('media_subtitles') . '`
             WHERE media_id = ? AND source = \'putio\'
               AND (putio_file_id IS NULL OR putio_file_id = ?)'
        );
        $stmt->execute([$mediaId, $putioFileId]);

        $keys = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $bareKey = putmio_putio_subtitle_bare_key((string) $row['source_file_id']);
            if ($bareKey !== '') {
                $keys[] = $bareKey;
            }
        }

        sort($keys, SORT_STRING);

        return $keys;
    }

    public function updatePutioSyncState(int $mediaId, int $putioFileId, string $hash): void
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE `' . Config::table('media_items') . '`
             SET putio_subtitles_sync_hash = ?, putio_subtitles_sync_at = NOW(), updated_at = NOW()
             WHERE id = ?'
        )->execute([$hash, $mediaId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function storePutioVtt(
        int $mediaId,
        int $putioFileId,
        string $bareKey,
        string $vtt,
        string $language,
        string $label
    ): array {
        $this->assertMediaPutioBinding($mediaId, $putioFileId);

        $bareKey = trim($bareKey);
        if ($bareKey === '' || !$this->isValidStoredPutioKey($bareKey)) {
            throw new \RuntimeException('Chiave sottotitolo put.io non valida');
        }

        $foreign = $this->findPutioSubtitleOnWrongMedia($putioFileId, $bareKey, $mediaId);
        if ($foreign !== null) {
            $this->delete((int) $foreign['id']);
        }

        $existing = $this->findPutioSubtitle($mediaId, $putioFileId, $bareKey);
        if ($existing !== null) {
            return $existing;
        }

        $sourceFileId = putmio_putio_subtitle_source_id($putioFileId, $bareKey);
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO `' . Config::table('media_subtitles') . '`
             (media_id, language, label, source, source_file_id, putio_file_id, file_path, downloaded_by)
             VALUES (?, ?, ?, \'putio\', ?, ?, \'\', NULL)'
        )->execute([$mediaId, $language, $label, $sourceFileId, $putioFileId]);

        $subtitleId = (int) $pdo->lastInsertId();
        $relativePath = 'subtitles/' . $mediaId . '/' . $subtitleId . '.vtt';
        $fullPath = putmio_base_path() . '/storage/' . $relativePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (@file_put_contents($fullPath, $vtt, LOCK_EX) === false) {
            $pdo->prepare('DELETE FROM `' . Config::table('media_subtitles') . '` WHERE id = ?')->execute([$subtitleId]);
            throw new \RuntimeException('Impossibile salvare il file sottotitoli');
        }

        $pdo->prepare(
            'UPDATE `' . Config::table('media_subtitles') . '` SET file_path = ? WHERE id = ?'
        )->execute([$relativePath, $subtitleId]);

        $row = $this->findById($subtitleId);
        if ($row === null) {
            throw new \RuntimeException('Sottotitolo salvato ma non trovato');
        }

        return $row;
    }

    public function belongsToMediaPutioFile(array $subtitleRow, int $mediaId): bool
    {
        if ((int) ($subtitleRow['media_id'] ?? 0) !== $mediaId) {
            return false;
        }

        if (($subtitleRow['source'] ?? '') !== 'putio') {
            return true;
        }

        $context = $this->getMediaPutioContext($mediaId);
        if ($context === null) {
            return false;
        }

        $expectedPutioId = (int) $context['putio_id'];
        $storedPutioId = isset($subtitleRow['putio_file_id']) ? (int) $subtitleRow['putio_file_id'] : 0;
        if ($storedPutioId > 0 && $storedPutioId !== $expectedPutioId) {
            return false;
        }

        $bareKey = putmio_putio_subtitle_bare_key((string) ($subtitleRow['source_file_id'] ?? ''));
        if (!$this->isValidStoredPutioKey($bareKey)) {
            return false;
        }

        $expectedPath = 'subtitles/' . $mediaId . '/';
        $filePath = (string) ($subtitleRow['file_path'] ?? '');

        return str_starts_with($filePath, $expectedPath);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPutioSubtitle(int $mediaId, int $putioFileId, string $bareKey): ?array
    {
        $compositeId = putmio_putio_subtitle_source_id($putioFileId, $bareKey);
        $row = $this->findBySourceFileId($mediaId, 'putio', $compositeId);
        if ($row !== null) {
            return $row;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `' . Config::table('media_subtitles') . '`
             WHERE media_id = ? AND source = \'putio\' AND source_file_id = ?
               AND (putio_file_id IS NULL OR putio_file_id = ?)
             LIMIT 1'
        );
        $stmt->execute([$mediaId, $bareKey, $putioFileId]);
        $legacy = $stmt->fetch();

        return $legacy ?: null;
    }

    public function savePreference(int $userId, int $mediaId, ?int $subtitleId, int $offsetMs): void
    {
        if ($subtitleId !== null) {
            $row = $this->findById($subtitleId);
            if ($row === null || (int) $row['media_id'] !== $mediaId) {
                throw new \RuntimeException('Sottotitolo non valido per questo contenuto');
            }
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO `' . Config::table('user_subtitle_prefs') . '`
             (user_id, media_id, subtitle_id, offset_ms)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE subtitle_id = VALUES(subtitle_id), offset_ms = VALUES(offset_ms), updated_at = NOW()'
        )->execute([$userId, $mediaId, $subtitleId, $offsetMs]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `' . Config::table('media_subtitles') . '` WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function delete(int $id): void
    {
        $row = $this->findById($id);
        if ($row === null) {
            return;
        }

        $fullPath = putmio_base_path() . '/storage/' . ltrim((string) $row['file_path'], '/');
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }

        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM `' . Config::table('user_subtitle_prefs') . '` WHERE subtitle_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM `' . Config::table('media_subtitles') . '` WHERE id = ?')->execute([$id]);
    }

    /** Rimuove tutte le tracce (put.io, OpenSubtitles) e la cartella locale del media. */
    public function deleteAllForMedia(int $mediaId): void
    {
        foreach ($this->listForMedia($mediaId) as $row) {
            $this->delete((int) $row['id']);
        }

        $dir = putmio_base_path() . '/storage/subtitles/' . $mediaId;
        if (is_dir($dir)) {
            @rmdir($dir);
        }
    }

    public function servePath(int $id): ?string
    {
        $row = $this->findById($id);
        if ($row === null || empty($row['file_path'])) {
            return null;
        }

        $path = putmio_base_path() . '/storage/' . ltrim((string) $row['file_path'], '/');
        if (!is_file($path)) {
            return null;
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $media
     * @return array<string, mixed>
     */
    private function buildSearchParams(array $media): array
    {
        $season = !empty($media['season_number']) ? (int) $media['season_number'] : 0;
        $episode = !empty($media['episode_number']) ? (int) $media['episode_number'] : 0;
        $isTvEpisode = $season > 0 && $episode > 0 && !empty($media['series_id']);

        $series = null;
        if ($isTvEpisode) {
            $series = $this->catalog->findMedia((int) $media['series_id']);
        }

        $params = [];

        if ($isTvEpisode) {
            $params['season_number'] = $season;
            $params['episode_number'] = $episode;
            $params['type'] = 'episode';

            $parentTmdbId = $this->seriesTmdbId($series, $media);
            $parentImdbId = $this->seriesImdbId($series, $media);

            if ($parentTmdbId !== null) {
                $params['parent_tmdb_id'] = $parentTmdbId;
            }
            if ($parentImdbId !== null) {
                $params['parent_imdb_id'] = $parentImdbId;
            }
        } else {
            if (!empty($media['tmdb_id'])) {
                $params['tmdb_id'] = (int) $media['tmdb_id'];
            }

            $imdbId = $this->normalizeImdbId(isset($media['imdb_id']) ? (string) $media['imdb_id'] : null);
            if ($imdbId !== null) {
                $params['imdb_id'] = $imdbId;
            }

            if ($season > 0) {
                $params['season_number'] = $season;
            }
            if ($episode > 0) {
                $params['episode_number'] = $episode;
            }

            if (!empty($media['tmdb_id']) || $imdbId !== null) {
                $params['type'] = ($season > 0 || $episode > 0) ? 'episode' : 'movie';
            }
        }

        $hasId = !empty($params['parent_tmdb_id'])
            || !empty($params['parent_imdb_id'])
            || !empty($params['tmdb_id'])
            || !empty($params['imdb_id']);

        if (!$hasId) {
            $queryTitle = (string) ($media['title'] ?? '');
            if ($isTvEpisode && is_array($series) && !empty($series['title'])) {
                $queryTitle = (string) $series['title'];
            }
            $params['query'] = $queryTitle;

            $year = null;
            if (!empty($media['year'])) {
                $year = (int) $media['year'];
            } elseif (is_array($series) && !empty($series['year'])) {
                $year = (int) $series['year'];
            }
            if ($year !== null && $year > 0) {
                $params['query'] .= ' ' . $year;
            }
        }

        return $params;
    }

    /**
     * @param array<string, mixed>|null $series
     * @param array<string, mixed> $media
     */
    private function seriesTmdbId(?array $series, array $media): ?int
    {
        if (is_array($series) && !empty($series['tmdb_id'])) {
            return (int) $series['tmdb_id'];
        }

        if (!empty($media['tmdb_id']) && ($media['tmdb_type'] ?? '') === 'tv') {
            return (int) $media['tmdb_id'];
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $series
     * @param array<string, mixed> $media
     */
    private function seriesImdbId(?array $series, array $media): ?string
    {
        if (is_array($series) && !empty($series['imdb_id'])) {
            return $this->normalizeImdbId((string) $series['imdb_id']);
        }

        if (!empty($media['imdb_id'])) {
            return $this->normalizeImdbId((string) $media['imdb_id']);
        }

        return null;
    }

    private function normalizeImdbId(?string $imdbId): ?string
    {
        if ($imdbId === null) {
            return null;
        }

        $imdbId = trim($imdbId);
        if ($imdbId === '') {
            return null;
        }

        if (str_starts_with(strtolower($imdbId), 'tt')) {
            $imdbId = substr($imdbId, 2);
        }

        return $imdbId !== '' ? $imdbId : null;
    }

    /**
     * @return list<string>
     */
    private function cachedSourceFileIds(int $mediaId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT source_file_id FROM `' . Config::table('media_subtitles') . '`
             WHERE media_id = ? AND source = \'opensubtitles\''
        );
        $stmt->execute([$mediaId]);

        return array_map(static fn ($row) => (string) $row['source_file_id'], $stmt->fetchAll() ?: []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySourceFileId(int $mediaId, string $source, string $fileId): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `' . Config::table('media_subtitles') . '`
             WHERE media_id = ? AND source = ? AND source_file_id = ? LIMIT 1'
        );
        $stmt->execute([$mediaId, $source, $fileId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function assertMediaPutioBinding(int $mediaId, int $putioFileId): void
    {
        $context = $this->getMediaPutioContext($mediaId);
        if ($context === null || (int) $context['putio_id'] !== $putioFileId) {
            throw new \RuntimeException('Contenuto non associato al file put.io indicato');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPutioSubtitleOnWrongMedia(int $putioFileId, string $bareKey, int $mediaId): ?array
    {
        $compositeId = putmio_putio_subtitle_source_id($putioFileId, $bareKey);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `' . Config::table('media_subtitles') . '`
             WHERE source = \'putio\' AND putio_file_id = ? AND media_id != ?
               AND (source_file_id = ? OR source_file_id = ?)
             LIMIT 1'
        );
        $stmt->execute([$putioFileId, $mediaId, $compositeId, $bareKey]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function removeDuplicatePutioSubtitles(): void
    {
        $pdo = Database::pdo();
        $subtitlesTable = Config::table('media_subtitles');
        $mediaTable = Config::table('media_items');
        $filesTable = Config::table('putio_files');

        $stmt = $pdo->query(
            'SELECT ms.id
             FROM `' . $subtitlesTable . '` ms
             INNER JOIN `' . $mediaTable . '` mi ON mi.id = ms.media_id
             INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
             INNER JOIN `' . $subtitlesTable . '` ms2
               ON ms2.source = \'putio\'
              AND ms2.putio_file_id = ms.putio_file_id
              AND ms2.source_file_id = ms.source_file_id
              AND ms2.id < ms.id
             WHERE ms.source = \'putio\'
               AND ms.putio_file_id = pf.putio_id'
        );
        $rows = $stmt ? $stmt->fetchAll() : [];
        foreach ($rows as $row) {
            $this->delete((int) $row['id']);
        }
    }

    private function isValidStoredPutioKey(string $bareKey): bool
    {
        $bareKey = trim($bareKey);
        if ($bareKey === '' || strlen($bareKey) < 8) {
            return false;
        }

        $blocked = ['index', 'all', 'default', 'vtt', 'media', 'stream', 'm3u8', 'playlist', 'master'];
        if (in_array(strtolower($bareKey), $blocked, true)) {
            return false;
        }

        return preg_match('/\.(m3u8|vtt|srt)$/i', $bareKey) !== 1;
    }

    private function toVtt(string $raw): string
    {
        $trimmed = ltrim($raw);
        if (str_starts_with($trimmed, 'WEBVTT')) {
            return $raw;
        }

        $unzipped = $this->maybeGunzip($raw);
        if ($unzipped !== null) {
            $trimmed = ltrim($unzipped);
            if (str_starts_with($trimmed, 'WEBVTT')) {
                return $unzipped;
            }
            return SrtToVtt::convert($unzipped);
        }

        return SrtToVtt::convert($raw);
    }

    private function maybeGunzip(string $raw): ?string
    {
        if (strlen($raw) < 2 || !str_starts_with($raw, "\x1f\x8b")) {
            return null;
        }

        $decoded = @gzdecode($raw);
        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $attributes
     */
    private function extractFileId(array $item, array $attributes): ?string
    {
        $files = $attributes['files'] ?? null;
        if (is_array($files) && isset($files[0]) && is_array($files[0])) {
            if (isset($files[0]['file_id'])) {
                return (string) $files[0]['file_id'];
            }
            if (isset($files[0]['id'])) {
                return (string) $files[0]['id'];
            }
        }

        if (isset($attributes['file_id'])) {
            return (string) $attributes['file_id'];
        }

        if (isset($item['id'])) {
            return (string) $item['id'];
        }

        return null;
    }
}
