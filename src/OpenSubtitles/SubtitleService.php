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

        $existing = $this->findBySourceFileId($mediaId, $fileId);
        if ($existing !== null) {
            return $existing;
        }

        $raw = $this->client->downloadFile($fileId);
        $vtt = $this->toVtt($raw);

        $lang = $language !== null && $language !== '' ? $language : 'und';
        $displayLabel = $label !== null && $label !== '' ? $label : putmio_subtitle_language_label($lang);

        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO `' . Config::table('media_subtitles') . '`
             (media_id, language, label, source, source_file_id, file_path, downloaded_by)
             VALUES (?, ?, ?, \'opensubtitles\', ?, \'\', ?)'
        )->execute([$mediaId, $lang, $displayLabel, $fileId, $userId > 0 ? $userId : null]);

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
        $params = [];

        if (!empty($media['tmdb_id'])) {
            $params['tmdb_id'] = (int) $media['tmdb_id'];
        }

        if (!empty($media['imdb_id'])) {
            $params['imdb_id'] = (string) $media['imdb_id'];
        }

        if (!empty($media['season_number'])) {
            $params['season_number'] = (int) $media['season_number'];
        }
        if (!empty($media['episode_number'])) {
            $params['episode_number'] = (int) $media['episode_number'];
        }

        if (empty($params['tmdb_id']) && empty($params['imdb_id'])) {
            $params['query'] = (string) ($media['title'] ?? '');
            if (!empty($media['year'])) {
                $params['query'] .= ' ' . (int) $media['year'];
            }
        }

        return $params;
    }

    /**
     * @return list<string>
     */
    private function cachedSourceFileIds(int $mediaId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT source_file_id FROM `' . Config::table('media_subtitles') . '` WHERE media_id = ?'
        );
        $stmt->execute([$mediaId]);

        return array_map(static fn ($row) => (string) $row['source_file_id'], $stmt->fetchAll() ?: []);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findBySourceFileId(int $mediaId, string $fileId): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `' . Config::table('media_subtitles') . '`
             WHERE media_id = ? AND source = \'opensubtitles\' AND source_file_id = ? LIMIT 1'
        );
        $stmt->execute([$mediaId, $fileId]);
        $row = $stmt->fetch();

        return $row ?: null;
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
        if (is_array($files) && isset($files[0]['file_id'])) {
            return (string) $files[0]['file_id'];
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
