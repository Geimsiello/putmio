<?php

declare(strict_types=1);

namespace PutMio\PutIO;

use PutMio\Config;
use PutMio\Database;

final class SyncService
{
    /** @var Client */
    private $client;

    /** @var FriendService */
    private $friends;

    /** @var array<int, true> putio_id visti nell'ultimo sync */
    private $seenPutioIds = [];

    public function __construct(?Client $client = null, ?FriendService $friends = null)
    {
        $this->client = $client ?? new Client();
        $this->friends = $friends ?? new FriendService($this->client);
    }

    public function sync(): array
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('put.io non collegato');
        }

        $this->seenPutioIds = [];

        $conn = $this->client->getConnection();
        $rootId = (int) ($conn['sync_root_folder_id'] ?? -1);
        $imported = 0;

        $imported += $this->syncOwnFiles($rootId);
        $imported += $this->syncSelectedFriends();

        $enabledUsernames = array_map(
            static fn (array $row): string => (string) $row['username'],
            $this->friends->listEnabled()
        );
        $this->pruneDeselectedShared($enabledUsernames);
        $removed = $this->pruneMissingOnPutio();

        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE `' . Config::table('putio_connection') . '`
             SET last_sync_at = NOW(), last_sync_file_count = ?, updated_at = NOW() WHERE id = 1'
        )->execute([$imported]);

        $this->ensureMediaStubs();
        (new \PutMio\Media\SeriesGrouper())->groupAll();

        return ['imported' => $imported, 'removed' => $removed];
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
    private function pruneDeselectedShared(array $enabledUsernames): void
    {
        $pdo = Database::pdo();
        $filesTable = Config::table('putio_files');
        $mediaTable = Config::table('media_items');

        $normalized = array_values(array_unique(array_map(
            static fn (string $name): string => mb_strtolower(trim($name)),
            $enabledUsernames
        )));

        if ($normalized === []) {
            $pdo->exec(
                'DELETE mi FROM `' . $mediaTable . '` mi
                 INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
                 WHERE pf.is_shared = 1'
            );
            $pdo->exec('DELETE FROM `' . $filesTable . '` WHERE is_shared = 1');
            return;
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        $params = $normalized;

        $pdo->prepare(
            'DELETE mi FROM `' . $mediaTable . '` mi
             INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
             WHERE pf.is_shared = 1
               AND (pf.shared_by_username IS NULL OR LOWER(pf.shared_by_username) NOT IN (' . $placeholders . '))'
        )->execute($params);

        $pdo->prepare(
            'DELETE FROM `' . $filesTable . '`
             WHERE is_shared = 1
               AND (shared_by_username IS NULL OR LOWER(shared_by_username) NOT IN (' . $placeholders . '))'
        )->execute($params);
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

            $this->deleteMediaForPutioFiles('1=1', []);
            $pdo->exec('DELETE FROM `' . $filesTable . '`');

            return $count;
        }

        $placeholders = implode(',', array_fill(0, count($seenIds), '?'));
        $where = 'putio_id NOT IN (' . $placeholders . ')';

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $filesTable . '` WHERE ' . $where);
        $stmt->execute($seenIds);
        $count = (int) $stmt->fetchColumn();
        if ($count === 0) {
            return 0;
        }

        $this->deleteMediaForPutioFiles($where, $seenIds);
        $pdo->prepare('DELETE FROM `' . $filesTable . '` WHERE ' . $where)->execute($seenIds);

        return $count;
    }

    /** @param list<int|string> $params */
    private function deleteMediaForPutioFiles(string $filesWhere, array $params): void
    {
        $pdo = Database::pdo();
        $filesTable = Config::table('putio_files');
        $mediaTable = Config::table('media_items');
        $watchTable = Config::table('watch_progress');
        $genresTable = Config::table('media_genres');
        $tagsTable = Config::table('media_tags');
        $sessionsTable = Config::table('stream_sessions');

        $pdo->prepare(
            'DELETE wp FROM `' . $watchTable . '` wp
             INNER JOIN `' . $mediaTable . '` mi ON mi.id = wp.media_id
             INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
             WHERE ' . $filesWhere
        )->execute($params);

        $pdo->prepare(
            'DELETE mg FROM `' . $genresTable . '` mg
             INNER JOIN `' . $mediaTable . '` mi ON mi.id = mg.media_id
             INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
             WHERE ' . $filesWhere
        )->execute($params);

        $pdo->prepare(
            'DELETE mt FROM `' . $tagsTable . '` mt
             INNER JOIN `' . $mediaTable . '` mi ON mi.id = mt.media_id
             INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
             WHERE ' . $filesWhere
        )->execute($params);

        $pdo->prepare(
            'DELETE ss FROM `' . $sessionsTable . '` ss
             INNER JOIN `' . $filesTable . '` pf ON pf.id = ss.putio_file_id
             WHERE ' . $filesWhere
        )->execute($params);

        $pdo->prepare(
            'DELETE mi FROM `' . $mediaTable . '` mi
             INNER JOIN `' . $filesTable . '` pf ON pf.id = mi.putio_file_id
             WHERE ' . $filesWhere
        )->execute($params);
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
