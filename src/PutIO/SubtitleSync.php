<?php

declare(strict_types=1);

namespace PutMio\PutIO;

use PutMio\Config;
use PutMio\Database;
use PutMio\OpenSubtitles\SrtToVtt;
use PutMio\OpenSubtitles\SubtitleService;

final class SubtitleSync
{
    public function __construct(
        private readonly Client $client = new Client(),
        private readonly SubtitleService $subtitles = new SubtitleService(),
    ) {
    }

    /**
     * @return array{imported: int, removed: int}
     */
    public function syncAll(): array
    {
        $pdo = Database::pdo();
        $filesTable = Config::table('putio_files');
        $mediaTable = Config::table('media_items');

        $stmt = $pdo->query(
            'SELECT mi.id AS media_id, pf.putio_id
             FROM `' . $mediaTable . '` mi
             INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
             WHERE pf.is_folder = 0 AND ' . $this->videoSqlCondition('pf') . '
             ORDER BY mi.id ASC'
        );
        $rows = $stmt ? $stmt->fetchAll() : [];

        $imported = 0;
        $removed = 0;

        foreach ($rows as $row) {
            try {
                $result = $this->syncMedia((int) $row['media_id'], (int) $row['putio_id']);
                $imported += $result['imported'];
                $removed += $result['removed'];
            } catch (\Throwable $e) {
                $this->logError((int) $row['media_id'], (int) $row['putio_id'], $e);
            }
        }

        return ['imported' => $imported, 'removed' => $removed];
    }

    /**
     * @return array{imported: int, removed: int}
     */
    public function syncMedia(int $mediaId, int $putioFileId): array
    {
        if ($putioFileId <= 0) {
            return ['imported' => 0, 'removed' => 0];
        }

        $remote = $this->client->listSubtitles($putioFileId);
        $keys = array_map(static fn (array $item): string => (string) $item['key'], $remote);
        $removed = $this->subtitles->prunePutioNotInKeys($mediaId, $keys);

        $imported = 0;
        foreach ($remote as $item) {
            $key = (string) ($item['key'] ?? '');
            if ($key === '') {
                continue;
            }

            if ($this->subtitles->findBySourceFileId($mediaId, 'putio', $key) !== null) {
                continue;
            }

            try {
                $vtt = $this->downloadAsVtt($putioFileId, $key);
                $lang = putmio_putio_subtitle_language_code((string) ($item['language'] ?? ''));
                $label = putmio_putio_subtitle_label($item);

                $this->subtitles->storeVtt($mediaId, 'putio', $key, $vtt, $lang, $label, null);
                $imported++;
            } catch (\Throwable $e) {
                $this->logError($mediaId, $putioFileId, $e);
            }
        }

        return ['imported' => $imported, 'removed' => $removed];
    }

    private function downloadAsVtt(int $putioFileId, string $key): string
    {
        try {
            $raw = $this->client->downloadSubtitle($putioFileId, $key, 'webvtt');
            if (str_starts_with(ltrim($raw), 'WEBVTT')) {
                return $raw;
            }
        } catch (\Throwable $e) {
            $raw = $this->client->downloadSubtitle($putioFileId, $key, 'srt');

            return SrtToVtt::convert($raw);
        }

        return SrtToVtt::convert($raw);
    }

    private function videoSqlCondition(string $alias): string
    {
        $exts = putmio_video_extensions();
        $parts = [];
        foreach ($exts as $ext) {
            $parts[] = "LOWER({$alias}.name) LIKE '%." . $ext . "'";
        }
        $parts[] = "{$alias}.mime LIKE 'video/%'";

        return '(' . implode(' OR ', $parts) . ')';
    }

    private function logError(int $mediaId, int $putioFileId, \Throwable $e): void
    {
        $logDir = putmio_base_path() . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        @file_put_contents(
            $logDir . '/app.log',
            '[' . date('Y-m-d H:i:s') . '] PutioSubtitleSync media=' . $mediaId
            . ' putio=' . $putioFileId . ': ' . $e->getMessage() . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
