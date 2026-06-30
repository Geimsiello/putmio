<?php

declare(strict_types=1);

namespace PutMio\PutIO;

use PutMio\Config;
use PutMio\Database;

/**
 * Mutex sync, pulizia run bloccati e rilevamento stream attivi.
 */
final class SyncCoordinator
{
    private const LOCK_NAME = 'putmio_sync';

    private bool $held = false;

    public function expireStaleRuns(): void
    {
        $minutes = max(30, (int) Config::get('app.sync_stale_run_minutes', 180));
        $table = Config::table('putio_sync_runs');

        try {
            Database::pdo()->prepare(
                'UPDATE `' . $table . '`
                 SET status = \'error\',
                     finished_at = NOW(),
                     error_message = ?
                 WHERE status = \'running\'
                   AND started_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)'
            )->execute(['Sync interrotta (timeout)', $minutes]);
        } catch (\Throwable $e) {
            putmio_log('Sync stale cleanup fallito: ' . $e->getMessage());
        }
    }

    public function tryAcquire(): bool
    {
        if ($this->held) {
            return true;
        }

        try {
            $stmt = Database::pdo()->query(
                "SELECT GET_LOCK('" . self::LOCK_NAME . "', 0)"
            );
            $this->held = (int) $stmt->fetchColumn() === 1;
        } catch (\Throwable $e) {
            putmio_log('Sync lock acquire fallito: ' . $e->getMessage());
            $this->held = false;
        }

        return $this->held;
    }

    public function release(): void
    {
        if (!$this->held) {
            return;
        }

        try {
            Database::pdo()->query("SELECT RELEASE_LOCK('" . self::LOCK_NAME . "')");
        } catch (\Throwable $e) {
            putmio_log('Sync lock release fallito: ' . $e->getMessage());
        }

        $this->held = false;
    }

    public function hasActiveStreams(): bool
    {
        $pdo = Database::pdo();
        $sessionsTable = Config::table('stream_sessions');

        try {
            $pdo->exec(
                'UPDATE `' . $sessionsTable . '`
                 SET active = 0, ended_at = NOW()
                 WHERE active = 1 AND started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
            );
            $stmt = $pdo->query(
                'SELECT COUNT(*) FROM `' . $sessionsTable . '` WHERE active = 1'
            );

            return (int) ($stmt ? $stmt->fetchColumn() : 0) > 0;
        } catch (\Throwable $e) {
            putmio_log('Sync stream check fallito: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @return array{skipped: true, reason: string}|null
     */
    public function preflight(SyncOptions $options): ?array
    {
        $this->expireStaleRuns();

        if (!$this->tryAcquire()) {
            return ['skipped' => true, 'reason' => 'locked'];
        }

        if ($options->deferOnActiveStreams && $this->hasActiveStreams()) {
            $this->release();

            return ['skipped' => true, 'reason' => 'streams_active'];
        }

        return null;
    }
}
