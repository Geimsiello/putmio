<?php

declare(strict_types=1);

namespace PutMio\PutIO;

use PutMio\Config;
use PutMio\Database;

final class SyncRunLogger
{
    /** @var string */
    private $triggerSource;

    /** @var int|null */
    private $triggeredByUserId;

    /** @var int|null */
    private $runId = null;

    /** @var array<string, mixed> */
    private $connection = [];

    /** @var array<string, string> */
    private $friendAccountCache = [];

    public function __construct(string $triggerSource = 'unknown', ?int $triggeredByUserId = null)
    {
        $allowed = ['admin', 'cron_http', 'cron_cli', 'cron_subtitles_http', 'cron_subtitles_cli', 'unknown'];
        $this->triggerSource = in_array($triggerSource, $allowed, true) ? $triggerSource : 'unknown';
        $this->triggeredByUserId = $triggeredByUserId;
    }

    /** @param array<string, mixed>|null $connection */
    public function start(?array $connection): void
    {
        $this->connection = $connection ?? [];

        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . Config::table('putio_sync_runs') . '`
                (started_at, trigger_source, triggered_by_user_id, status, putio_username, putio_user_id)
                VALUES (NOW(), ?, ?, \'running\', ?, ?)'
            );
            $stmt->execute([
                $this->triggerSource,
                $this->triggeredByUserId,
                $this->connection['putio_username'] ?? null,
                $this->connection['putio_user_id'] ?? null,
            ]);
            $this->runId = (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            $this->runId = null;
            putmio_log('Sync log start fallito: ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $file */
    public function logFile(string $action, array $file, bool $isShared, ?string $sharedBy): void
    {
        if ($this->runId === null || !in_array($action, ['added', 'updated', 'removed'], true)) {
            return;
        }

        try {
            $owner = $this->ownerInfo($isShared, $sharedBy ?? ($file['shared_by_username'] ?? null));
            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO `' . Config::table('putio_sync_run_items') . '`
                (run_id, action, putio_id, name, is_folder, is_shared, shared_by_username, owner_username, owner_account, content_type, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $this->runId,
                $action,
                (int) ($file['putio_id'] ?? $file['id'] ?? 0),
                (string) ($file['name'] ?? 'Senza nome'),
                !empty($file['is_folder']) ? 1 : (Client::isFolder($file) ? 1 : 0),
                $isShared ? 1 : (!empty($file['is_shared']) ? 1 : 0),
                $isShared ? ($sharedBy ?? ($file['shared_by_username'] ?? null)) : null,
                $owner['username'],
                $owner['account'],
                $file['content_type'] ?? $file['mime'] ?? $file['mime_type'] ?? null,
            ]);
        } catch (\Throwable $e) {
            putmio_log('Sync log item fallito: ' . $e->getMessage());
        }
    }

    /** @param list<array<string, mixed>> $files */
    public function logRemovedFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->logFile(
                'removed',
                $file,
                !empty($file['is_shared']),
                $file['shared_by_username'] ?? null
            );
        }
    }

    public function finishSuccess(): array
    {
        return $this->finish('success', null);
    }

    public function finishError(\Throwable $e): array
    {
        return $this->finish('error', $e->getMessage());
    }

    /** @return array{added: int, updated: int, removed: int} */
    public function counts(): array
    {
        $counts = ['added' => 0, 'updated' => 0, 'removed' => 0];
        if ($this->runId === null) {
            return $counts;
        }

        try {
            $stmt = Database::pdo()->prepare(
                'SELECT action, COUNT(*) AS total
                 FROM `' . Config::table('putio_sync_run_items') . '`
                 WHERE run_id = ?
                 GROUP BY action'
            );
            $stmt->execute([$this->runId]);
            foreach ($stmt->fetchAll() as $row) {
                $action = (string) ($row['action'] ?? '');
                if (array_key_exists($action, $counts)) {
                    $counts[$action] = (int) ($row['total'] ?? 0);
                }
            }
        } catch (\Throwable $e) {
            putmio_log('Sync log count fallito: ' . $e->getMessage());
        }

        return $counts;
    }

    /** @return array{added: int, updated: int, removed: int} */
    private function finish(string $status, ?string $errorMessage): array
    {
        $counts = $this->counts();
        if ($this->runId === null) {
            return $counts;
        }

        try {
            Database::pdo()->prepare(
                'UPDATE `' . Config::table('putio_sync_runs') . '`
                 SET finished_at = NOW(),
                     status = ?,
                     error_message = ?,
                     count_added = ?,
                     count_updated = ?,
                     count_removed = ?
                 WHERE id = ?'
            )->execute([
                $status,
                $errorMessage !== null ? mb_substr($errorMessage, 0, 2000) : null,
                $counts['added'],
                $counts['updated'],
                $counts['removed'],
                $this->runId,
            ]);
        } catch (\Throwable $e) {
            putmio_log('Sync log finish fallito: ' . $e->getMessage());
        }

        return $counts;
    }

    /** @return array{username: string, account: string} */
    private function ownerInfo(bool $isShared, ?string $sharedBy): array
    {
        if ($isShared) {
            $username = trim((string) $sharedBy);
            if ($username === '') {
                $username = 'put.io';
            }
            return [
                'username' => $username,
                'account' => $this->friendAccountLabel($username),
            ];
        }

        $username = trim((string) ($this->connection['putio_username'] ?? ''));
        if ($username === '') {
            $username = 'put.io';
        }

        $userId = (string) ($this->connection['putio_user_id'] ?? '');
        return [
            'username' => $username,
            'account' => $userId !== '' ? $username . ' #' . $userId : $username,
        ];
    }

    private function friendAccountLabel(string $username): string
    {
        $key = mb_strtolower(trim($username));
        if ($key === '') {
            return 'put.io';
        }
        if (isset($this->friendAccountCache[$key])) {
            return $this->friendAccountCache[$key];
        }

        $label = $username;
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT putio_friend_id FROM `' . Config::table('putio_sync_friends') . '`
                 WHERE LOWER(username) = ?
                 LIMIT 1'
            );
            $stmt->execute([$key]);
            $friendId = (string) ($stmt->fetchColumn() ?: '');
            if ($friendId !== '' && $friendId !== '0') {
                $label = $username . ' #' . $friendId;
            }
        } catch (\Throwable $e) {
            putmio_log('Sync log friend lookup fallito: ' . $e->getMessage());
        }

        return $this->friendAccountCache[$key] = $label;
    }
}
