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
     * @return array{imported: int, removed: int, skipped: int, repaired: int}
     */
    public function syncAll(): array
    {
        $repair = $this->subtitles->repairPutioIntegrity();

        $pdo = Database::pdo();
        $filesTable = Config::table('putio_files');
        $mediaTable = Config::table('media_items');

        $stmt = $pdo->query(
            'SELECT mi.id AS media_id, pf.putio_id, pf.synced_at
             FROM `' . $mediaTable . '` mi
             INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
             WHERE pf.is_folder = 0 AND ' . $this->videoSqlCondition('pf') . '
             ORDER BY mi.id ASC'
        );
        $rows = $stmt ? $stmt->fetchAll() : [];

        $imported = 0;
        $removed = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            try {
                $result = $this->syncMedia((int) $row['media_id'], (int) $row['putio_id']);
                $imported += $result['imported'];
                $removed += $result['removed'];
                $skipped += $result['skipped'];
            } catch (\Throwable $e) {
                $this->logError((int) $row['media_id'], (int) $row['putio_id'], $e);
            }
        }

        return [
            'imported' => $imported,
            'removed' => $removed,
            'skipped' => $skipped,
            'repaired' => $repair['removed'] + $repair['migrated'],
        ];
    }

    /**
     * @return array{imported: int, removed: int, skipped: int}
     */
    public function syncMedia(int $mediaId, int $putioFileId): array
    {
        if ($putioFileId <= 0) {
            return ['imported' => 0, 'removed' => 0, 'skipped' => 0];
        }

        $context = $this->subtitles->getMediaPutioContext($mediaId);
        if ($context === null || (int) $context['putio_id'] !== $putioFileId) {
            $this->logError($mediaId, $putioFileId, new \RuntimeException('Media non allineato al file put.io'));

            return ['imported' => 0, 'removed' => 0, 'skipped' => 0];
        }

        $remote = $this->client->listSubtitlesForSync($putioFileId);
        $remoteKeys = $this->extractBareKeys($remote);
        $remoteHash = putmio_putio_subtitle_sync_hash($putioFileId, $remoteKeys);
        $localKeys = $this->subtitles->listPutioBareKeysForMedia($mediaId, $putioFileId);
        $storedHash = $context['putio_subtitles_sync_hash'] ?? null;

        if ($storedHash === $remoteHash && $this->sameKeySet($remoteKeys, $localKeys)) {
            return ['imported' => 0, 'removed' => 0, 'skipped' => 1];
        }

        $removed = $this->subtitles->prunePutioNotInKeys($mediaId, $putioFileId, $remoteKeys);

        $imported = 0;
        foreach ($remote as $item) {
            $bareKey = trim((string) ($item['key'] ?? ''));
            if ($bareKey === '') {
                continue;
            }

            if ($this->subtitles->findPutioSubtitle($mediaId, $putioFileId, $bareKey) !== null) {
                continue;
            }

            try {
                $vtt = $this->downloadAsVtt($putioFileId, $bareKey);
                $lang = putmio_putio_subtitle_language_code((string) ($item['language'] ?? ''));
                $label = putmio_putio_subtitle_label($item);

                $this->subtitles->storePutioVtt($mediaId, $putioFileId, $bareKey, $vtt, $lang, $label);
                $imported++;
            } catch (\Throwable $e) {
                $this->logError($mediaId, $putioFileId, $e);
            }
        }

        $this->subtitles->updatePutioSyncState($mediaId, $putioFileId, $remoteHash);

        return ['imported' => $imported, 'removed' => $removed, 'skipped' => 0];
    }

    /**
     * @param list<array{key?: string}> $items
     * @return list<string>
     */
    private function extractBareKeys(array $items): array
    {
        $keys = [];
        foreach ($items as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     */
    private function sameKeySet(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        $left = $a;
        $right = $b;
        sort($left, SORT_STRING);
        sort($right, SORT_STRING);

        return $left === $right;
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
