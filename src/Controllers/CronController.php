<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Config;
use PutMio\PutIO\SyncService;

final class CronController
{
    public function sync(): void
    {
        $token = $_GET['token'] ?? '';
        if (!hash_equals((string) Config::get('app.cron_token'), $token)) {
            http_response_code(403);
            exit('Forbidden');
        }

        try {
            $result = (new SyncService(null, null, 'cron_http'))->sync();
            putmio_json([
                'ok' => true,
                'imported' => $result['imported'],
                'removed' => $result['removed'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            putmio_json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
