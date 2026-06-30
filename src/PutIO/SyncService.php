<?php

declare(strict_types=1);

namespace PutMio\PutIO;

use PutMio\Config;
use PutMio\Database;
use PutMio\Media\MediaCleanupService;

final class SyncService
{
    /** @var Client */
    private $client;

    /** @var FriendService */
    private $friends;

    /** @var SyncRunLogger */
    private $logger;

    /** @var array<int, true> putio_id visti nell'ultimo sync */
    private $seenPutioIds = [];

    public function __construct(
        ?Client $client = null,
        ?FriendService $friends = null,
        string $triggerSource = 'unknown',
        ?int $triggeredByUserId = null,
        ?SyncRunLogger $logger = null
    )
    {
        $this->client = $client ?? new Client();
        $this->friends = $friends ?? new FriendService($this->client);
        $this->logger = $logger ?? new SyncRunLogger($triggerSource, $triggeredByUserId);
    }

    public function sync(?SyncOptions $options = null): array
    {
        return $this->runCatalogSync($options ?? SyncOptions::admin());
    }

    public function syncSubtitlesOnly(?SyncOptions $options = null): array
    {
        $options = $options ?? SyncOptions::subtitlesCron();
        $coordinator = new SyncCoordinator();
        $skipped = $coordinator->preflight($options);
        if ($skipped !== null) {
            return $skipped;
        }

        $conn = $this->client->getConnection();
        $this->logger->start($conn);

        try {
            if (!$conn || empty($conn['access_token_enc'])) {
                throw new \RuntimeException('put.io non collegato');
            }

            $subtitleSync = (new SubtitleSync())->syncAll();
            $this->logger->finishSuccess();

            return [
                'imported' => 0,
                'removed' => 0,
                'subtitles_imported' => $subtitleSync['imported'],
                'subtitles_removed' => $subtitleSync['removed'],
            ];
        } catch (\Throwable $e) {
            $this->logger->finishError($e);
            throw $e;
        } finally {
            $coordinator->release();
        }
    }

    private function runCatalogSync(SyncOptions $options): array
    {
        $coordinator = new SyncCoordinator();
        $skipped = $coordinator->preflight($options);
        if ($skipped !== null) {
            return $skipped;
        }

        $conn = $this->client->getConnection();
        $this->logger->start($conn);

        try {
            if (!$conn || empty($conn['access_token_enc'])) {
                throw new \RuntimeException('put.io non collegato');
            }

            $this->seenPutioIds = [];

            $rootId = (int) ($conn['sync_root_folder_id'] ?? -1);
            $imported = 0;

            $imported += $this->syncOwnFiles($rootId);
            $imported += $this->syncSelectedFriends();

            $enabledUsernames = array_map(
                static fn (array $row): string => (string) $row['username'],
                $this->friends->listEnabled()
            );
            $removed = $this->pruneDeselectedShared($enabledUsernames);
            $removed += $this->pruneMissingOnPutio();

            $pdo = Database::pdo();
            $pdo->prepare(
                'UPDATE `' . Config::table('putio_connection') . '`
                 SET last_sync_at = NOW(), last_sync_file_count = ?, updated_at = NOW() WHERE id = 1'
            )->execute([$imported]);

            $this->ensureMediaStubs();
            (new \PutMio\Media\SeriesGrouper())->groupAll();

            $orphanSeries = (new MediaCleanupService())->pruneOrphanSeries();

            $counts = $this->logger->finishSuccess();

            $subtitleSync = ['imported' => 0, 'removed' => 0];
            if ($options->includeSubtitles) {
                $subtitleSync = (new SubtitleSync())->syncAll();
            }

            return [
                'imported' => $imported,
                'removed' => $removed,
                'added' => $counts['added'],
                'updated' => $counts['updated'],
                'deleted' => $counts['removed'],
                'subtitles_imported' => $subtitleSync['imported'],
                'subtitles_removed' => $subtitleSync['removed'],
                'orphan_series_removed' => $orphanSeries,
            ];
        } catch (\Throwable $e) {
            $this->logger->finishError($e);
            throw $e;
        } finally {
            $coordinator->release();
        }
    }

    private function syncOwnFiles(int $rootId): int
    {
        $imported = 0;
        $cursor = null;

        do {
            $response = $this->client->listFiles($rootId, $cursor);
            foreach ($response['files'] ?? [] as $file) {
                if (!empty($file['is_shared'])) {
                    $sharedId = (int) ($file['id'] ?? 0);
                    if ($sharedId > 0) {
                        $this->seenPutioIds[$sharedId] = true;
                    }
                    continue;
                }
                $this->upsertFile($file, false, null);
                $imported++;
                if (Client::isFolder($file)) {
                    $imported += $this->syncOwnFiles((int) $file['id']);
                }
            }
            $cursor = $response['cursor'] ?? null;
        } while ($cursor);

        return $imported;
    }

    private function syncSelectedFriends(): int
    {
        $imported = 0;

        foreach ($this->friends->listEnabled() as $friend) {
            $username = (string) $friend['username'];
            $folderId = (int) ($friend['folder_putio_id'] ?? 0);
            if ($folderId !== 0) {
                $imported += $this->syncFolderTree($folderId, $username);
                continue;
            }
            $imported += $this->syncSharedFilesForFriend($username);
        }

        return $imported;
    }

    private function syncSharedFilesForFriend(string $username): int
    {
        $imported = 0;
        $needle = mb_strtolower(trim($username));
        $foldersById = $this->buildFolderIndex();
        $cursor = null;

        do {
            $response = $this->client->listFiles(-1, $cursor, ['hidden' => true]);
            foreach ($response['files'] ?? [] as $file) {
                if (empty($file['is_shared'])) {
                    continue;
                }
                if (!$this->fileBelongsToFriend($file, $needle, $foldersById)) {
                    continue;
                }
                $this->upsertFile($file, true, $username);
                $imported++;
            }
            $cursor = $response['cursor'] ?? null;
        } while ($cursor);

        return $imported;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildFolderIndex(): array
    {
        $foldersById = [];
        $cursor = null;

        do {
            $response = $this->client->listFiles(-1, $cursor, ['hidden' => true]);
            foreach ($response['files'] ?? [] as $entry) {
                if (Client::isFolder($entry)) {
                    $foldersById[(int) $entry['id']] = $entry;
                }
            }
            $cursor = $response['cursor'] ?? null;
        } while ($cursor);

        return $foldersById;
    }

    /**
     * @param array<string, mixed> $file
     * @param array<int, array<string, mixed>> $foldersById
     */
    private function fileBelongsToFriend(array $file, string $friendUsername, array $foldersById): bool
    {
        $parentId = (int) ($file['parent_id'] ?? 0);
        $visited = [];

        while ($parentId !== 0 && !isset($visited[$parentId])) {
            $visited[$parentId] = true;
            $folder = $foldersById[$parentId] ?? null;
            if (!$folder) {
                break;
            }
            $name = mb_strtolower(trim((string) ($folder['name'] ?? '')));
            if ($name === $friendUsername) {
                return true;
            }
            $parentId = (int) ($folder['parent_id'] ?? 0);
        }

        return false;
    }

    private function syncFolderTree(int $parentId, string $sharedBy): int
    {
        $imported = 0;
        $cursor = null;

        do {
            $response = $this->client->listFiles($parentId, $cursor, ['hidden' => true]);
            foreach ($response['files'] ?? [] as $file) {
                $this->upsertFile($file, true, $sharedBy);
                $imported++;
                if (Client::isFolder($file)) {
                    $imported += $this->syncFolderTree((int) $file['id'], $sharedBy);
                }
            }
            $cursor = $response['cursor'] ?? null;
        } while ($cursor);

        return $imported;
    }

    /** @param list<string> $enabledUsernames */
    private function pruneDeselectedShared(array $enabledUsernames): int
    {
        $pdo = Database::pdo();
        $filesTable = Config::table('putio_files');

        $normalized = array_values(array_unique(array_map(
            static fn (string $name): string => mb_strtolower(trim($name)),
            $enabledUsernames
        )));

        if ($normalized === []) {
            $removedFiles = $this->selectFilesForLog('is_shared = 1', []);
            $this->logger->logRemovedFiles($removedFiles);
            $this->deleteMediaForPutioFiles('is_shared = 1', []);
            $pdo->exec('DELETE FROM `' . $filesTable . '` WHERE is_shared = 1');

            return count($removedFiles);
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        $params = $normalized;
        $where = 'is_shared = 1
               AND (shared_by_username IS NULL OR LOWER(shared_by_username) NOT IN (' . $placeholders . '))';

        $removedFiles = $this->selectFilesForLog($where, $params);
        if ($removedFiles === []) {
            return 0;
        }

        $this->logger->logRemovedFiles($removedFiles);
        $this->deleteMediaForPutioFiles($where, $params);
        $pdo->prepare('DELETE FROM `' . $filesTable . '` WHERE ' . $where)->execute($params);

        return count($removedFiles);
    }

    /** Rimuove dal catalogo i file non più presenti su put.io. */
    private function pruneMissingOnPutio(): int
    {
        $pdo = Database::pdo();
        $filesTable = Config::table('putio_files');
        $seenIds = array_keys($this->seenPutioIds);

        if ($seenIds === []) {
            $stmt = $pdo->query('SELECT COUNT(*) FROM `' . $filesTable . '`');
            $count = $stmt ? (int) $stmt->fetchColumn() : 0;
            if ($count === 0) {
                return 0;
            }

            $removedFiles = $this->selectFilesForLog('1=1', []);
            $this->logger->logRemovedFiles($removedFiles);
            $this->deleteMediaForPutioFiles('1=1', []);
            $pdo->exec('DELETE FROM `' . $filesTable . '`');

            return count($removedFiles);
        }

        $placeholders = implode(',', array_fill(0, count($seenIds), '?'));
        $where = 'putio_id NOT IN (' . $placeholders . ')';

        $removedFiles = $this->selectFilesForLog($where, $seenIds);
        if ($removedFiles === []) {
            return 0;
        }

        $this->logger->logRemovedFiles($removedFiles);
        $this->deleteMediaForPutioFiles($where, $seenIds);
        $pdo->prepare('DELETE FROM `' . $filesTable . '` WHERE ' . $where)->execute($seenIds);

        return count($removedFiles);
    }

    /**
     * @param list<int|string> $params
     * @return list<array<string, mixed>>
     */
    private function selectFilesForLog(string $filesWhere, array $params): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT putio_id, name, is_folder, is_shared, shared_by_username, content_type, mime
             FROM `' . Config::table('putio_files') . '`
             WHERE ' . $filesWhere . '
             ORDER BY is_folder ASC, name ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @param list<int|string> $params */
    private function deleteMediaForPutioFiles(string $filesWhere, array $params): void
    {
        (new MediaCleanupService())->purgeForPutioFiles($filesWhere, $params);
    }

    private function upsertFile(array $file, bool $isShared, ?string $sharedBy): void
    {
        $pdo = Database::pdo();
        $putioId = (int) ($file['id'] ?? 0);
        if ($putioId === 0) {
            return;
        }

        $this->seenPutioIds[$putioId] = true;

        $isFolder = Client::isFolder($file) ? 1 : 0;
        $name = (string) ($file['name'] ?? 'Senza nome');
        $mime = $file['mime_type'] ?? $file['content_type'] ?? null;
        $sharedFlag = $isShared || !empty($file['is_shared']) ? 1 : 0;
        $sharedByValue = $sharedFlag ? ($sharedBy ?? null) : null;
        $existsStmt = $pdo->prepare(
            'SELECT id FROM `' . Config::table('putio_files') . '` WHERE putio_id = ? LIMIT 1'
        );
        $existsStmt->execute([$putioId]);
        $exists = (bool) $existsStmt->fetchColumn();

        $pdo->prepare(
            'INSERT INTO `' . Config::table('putio_files') . '`
            (putio_id, parent_putio_id, name, size, mime, is_folder, is_shared, shared_by_username, content_type, synced_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            parent_putio_id = VALUES(parent_putio_id),
            name = VALUES(name),
            size = VALUES(size),
            mime = VALUES(mime),
            is_folder = VALUES(is_folder),
            is_shared = VALUES(is_shared),
            shared_by_username = VALUES(shared_by_username),
            content_type = VALUES(content_type),
            synced_at = NOW()'
        )->execute([
            $putioId,
            $file['parent_id'] ?? null,
            $name,
            (int) ($file['size'] ?? 0),
            $mime,
            $isFolder,
            $sharedFlag,
            $sharedByValue,
            $file['content_type'] ?? null,
        ]);

        if (!$exists) {
            $this->logger->logFile('added', $file, $sharedFlag === 1, $sharedByValue);
        }
    }

    private function ensureMediaStubs(): void
    {
        $pdo = Database::pdo();
        $filesTable = Config::table('putio_files');
        $mediaTable = Config::table('media_items');

        $sql = "INSERT INTO `{$mediaTable}` (putio_file_id, media_type, title, classification_status)
                SELECT pf.id, 'altro', pf.name, 'unclassified'
                FROM `{$filesTable}` pf
                LEFT JOIN `{$mediaTable}` mi ON mi.putio_file_id = pf.id
                WHERE pf.is_folder = 0 AND " . $this->videoSqlCondition('pf') . " AND mi.id IS NULL";
        $pdo->exec($sql);
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
}
