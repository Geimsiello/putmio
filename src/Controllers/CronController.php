<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Config;
use PutMio\PutIO\SyncOptions;
use PutMio\PutIO\SyncService;

final class CronController
{
    public function sync(): void
    {
        $this->runCatalogSync();
    }

    public function syncSubtitles(): void
    {
        if (!$this->authorize()) {
            return;
        }

        @set_time_limit(0);

        try {
            $result = (new SyncService(null, null, 'cron_subtitles_http'))->syncSubtitlesOnly(SyncOptions::subtitlesCron());
            $this->jsonResult($result);
        } catch (\Throwable $e) {
            putmio_json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function runCatalogSync(): void
    {
        if (!$this->authorize()) {
            return;
        }

        @set_time_limit(0);

        try {
            $result = (new SyncService(null, null, 'cron_http'))->sync(SyncOptions::cronHttp());
            $this->jsonResult($result);
        } catch (\Throwable $e) {
            putmio_json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function authorize(): bool
    {
        $token = $_GET['token'] ?? '';
        if (!hash_equals((string) Config::get('app.cron_token'), $token)) {
            http_response_code(403);
            exit('Forbidden');
        }

        return true;
    }

    /** @param array<string, mixed> $result */
    private function jsonResult(array $result): void
    {
        if (!empty($result['skipped'])) {
            putmio_json([
                'ok' => true,
                'skipped' => true,
                'reason' => (string) ($result['reason'] ?? 'unknown'),
                'message' => putmio_lang('putio_sync_skipped_' . ($result['reason'] ?? 'unknown')),
            ]);
        }

        putmio_json([
            'ok' => true,
            'imported' => (int) ($result['imported'] ?? 0),
            'removed' => (int) ($result['removed'] ?? 0),
            'subtitles_imported' => (int) ($result['subtitles_imported'] ?? 0),
            'subtitles_removed' => (int) ($result['subtitles_removed'] ?? 0),
        ]);
    }
}
