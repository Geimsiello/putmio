<?php

declare(strict_types=1);

namespace PutMio\PutIO;

use PutMio\Config;
use PutMio\Database;

final class FriendService
{
    /** @var Client */
    private $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    /** @return list<array<string, mixed>> */
    public function listStored(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            'SELECT * FROM `' . Config::table('putio_sync_friends') . '` ORDER BY username ASC'
        );

        return $stmt ? $stmt->fetchAll() : [];
    }

    /** @return list<array<string, mixed>> */
    public function listEnabled(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            'SELECT * FROM `' . Config::table('putio_sync_friends') . '`
             WHERE sync_enabled = 1
             ORDER BY username ASC'
        );

        return $stmt ? $stmt->fetchAll() : [];
    }

    public function refreshFromApi(): int
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('put.io non collegato');
        }

        $apiFriends = $this->client->listFriends();
        $folderMap = $this->discoverFriendFolderMap($apiFriends);
        $pdo = Database::pdo();
        $table = Config::table('putio_sync_friends');

        $existing = [];
        foreach ($this->listStored() as $row) {
            $existing[strtolower((string) $row['username'])] = $row;
        }

        $seen = [];
        $updated = 0;

        foreach ($apiFriends as $friend) {
            $username = $this->friendUsername($friend);
            if ($username === '') {
                continue;
            }

            $key = strtolower($username);
            $seen[$key] = true;
            $friendId = (int) ($friend['id'] ?? 0);
            $folderId = $folderMap[$key] ?? null;
            $avatar = $friend['avatar_url'] ?? null;
            $syncEnabled = !empty($existing[$key]['sync_enabled']) ? 1 : 0;

            $pdo->prepare(
                'INSERT INTO `' . $table . '`
                (putio_friend_id, username, folder_putio_id, avatar_url, sync_enabled, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                putio_friend_id = VALUES(putio_friend_id),
                folder_putio_id = VALUES(folder_putio_id),
                avatar_url = VALUES(avatar_url),
                updated_at = NOW()'
            )->execute([$friendId, $username, $folderId, $avatar, $syncEnabled]);

            $updated++;
        }

        foreach (array_keys($existing) as $key) {
            if (isset($seen[$key])) {
                continue;
            }
            $pdo->prepare('DELETE FROM `' . $table . '` WHERE LOWER(username) = ?')->execute([$key]);
        }

        return $updated;
    }

    /** @param list<int|string> $enabledFriendIds */
    public function saveSyncSelection(array $enabledFriendIds): void
    {
        $pdo = Database::pdo();
        $table = Config::table('putio_sync_friends');
        $enabled = array_map('intval', $enabledFriendIds);

        $pdo->exec('UPDATE `' . $table . '` SET sync_enabled = 0');
        if ($enabled === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($enabled), '?'));
        $pdo->prepare(
            'UPDATE `' . $table . '` SET sync_enabled = 1, updated_at = NOW() WHERE id IN (' . $placeholders . ')'
        )->execute($enabled);
    }

    public function clearAll(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('DELETE FROM `' . Config::table('putio_sync_friends') . '`');
    }

    /**
     * @param list<array<string, mixed>> $apiFriends
     * @return array<string, int> username lowercase => folder putio id
     */
    private function discoverFriendFolderMap(array $apiFriends): array
    {
        $usernames = [];
        foreach ($apiFriends as $friend) {
            $username = $this->friendUsername($friend);
            if ($username !== '') {
                $usernames[] = mb_strtolower($username);
            }
        }

        if ($usernames === []) {
            return [];
        }

        $rootFromHidden = $this->listAllFilesAt(0, true);
        $rootFromVisible = $this->listAllFilesAt(0, false);
        $flatHidden = $this->listAllFilesAt(-1, true);
        $flatVisible = $flatHidden === [] ? $this->listAllFilesAt(-1, false) : [];

        $map = [];
        $fromSharedRoot = $this->discoverFromSharedRoot($usernames, array_merge($rootFromHidden, $rootFromVisible));
        $map = array_merge($map, $fromSharedRoot);
        $fromRoot = $this->discoverFromRootListing($usernames, $rootFromHidden, $rootFromVisible);
        $map = array_merge($map, $fromRoot);
        $fromFlat = $this->discoverFromFlatListing($usernames, $flatHidden !== [] ? $flatHidden : $flatVisible);
        $map = array_merge($map, $fromFlat);

        $filtered = [];
        foreach ($usernames as $username) {
            if (isset($map[$username])) {
                $filtered[$username] = $map[$username];
            }
        }

        return $filtered;
    }

    /**
     * @param list<string> $usernames
     * @param list<array<string, mixed>> $rootFiles
     * @return array<string, int>
     */
    private function discoverFromSharedRoot(array $usernames, array $rootFiles): array
    {
        $map = [];
        $sharedRootIds = [];

        foreach ($rootFiles as $file) {
            if (!$this->isSharedRootFolder($file)) {
                continue;
            }
            $sharedRootIds[(int) ($file['id'] ?? 0)] = true;
        }

        if ($sharedRootIds === []) {
            $sharedRootIds[-2] = true;
        }

        foreach (array_keys($sharedRootIds) as $sharedRootId) {
            $map = array_merge($map, $this->listFriendSubfolders($sharedRootId));
        }

        $filtered = [];
        foreach ($usernames as $username) {
            if (isset($map[$username])) {
                $filtered[$username] = $map[$username];
            }
        }

        return $filtered;
    }

    /** @param array<string, mixed> $file */
    private function isSharedRootFolder(array $file): bool
    {
        if (!Client::isFolder($file)) {
            return false;
        }

        $folderType = mb_strtoupper(trim((string) ($file['folder_type'] ?? '')));
        if ($folderType === 'SHARED_ROOT') {
            return true;
        }

        return $this->isSharedRootFolderName(mb_strtolower(trim((string) ($file['name'] ?? ''))));
    }

    private function isSharedRootFolderName(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        $patterns = [
            'items shared with you',
            'shared with you',
            'elementi condivisi con te',
            'condivisi con te',
            'your friends\' files',
            'your friends files',
            'friends files',
            'file dei tuoi amici',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($name, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $usernames
     * @param list<array<string, mixed>> $rootHidden
     * @param list<array<string, mixed>> $rootVisible
     * @return array<string, int>
     */
    private function discoverFromRootListing(array $usernames, array $rootHidden, array $rootVisible): array
    {
        $map = [];
        $rootFolders = [];

        foreach (array_merge($rootHidden, $rootVisible) as $file) {
            if (!Client::isFolder($file)) {
                continue;
            }
            $rootFolders[(int) $file['id']] = $file;
        }

        foreach ($rootFolders as $file) {
            $name = mb_strtolower(trim((string) ($file['name'] ?? '')));
            if ($name !== '' && in_array($name, $usernames, true)) {
                $map[$name] = (int) $file['id'];
            }
        }

        foreach ($rootFolders as $file) {
            if (!$this->isFriendsRootFolder($file)) {
                continue;
            }
            $map = array_merge($map, $this->listFriendSubfolders((int) $file['id']));
        }

        foreach ($rootFolders as $file) {
            if (empty($file['is_hidden']) || $this->isFriendsRootFolder($file)) {
                continue;
            }
            $subfolders = $this->listFriendSubfolders((int) $file['id']);
            if (array_intersect(array_keys($subfolders), $usernames) === []) {
                continue;
            }
            $map = array_merge($map, $subfolders);
        }

        return $map;
    }

    /**
     * @param list<string> $usernames
     * @param list<array<string, mixed>> $allFiles
     * @return array<string, int>
     */
    private function discoverFromFlatListing(array $usernames, array $allFiles): array
    {
        $foldersById = [];
        foreach ($allFiles as $file) {
            if (!Client::isFolder($file)) {
                continue;
            }
            $foldersById[(int) $file['id']] = $file;
        }

        $map = [];
        foreach ($foldersById as $folder) {
            $name = mb_strtolower(trim((string) ($folder['name'] ?? '')));
            if ($name === '' || !in_array($name, $usernames, true)) {
                continue;
            }
            if ($this->looksLikeFriendFolder($folder, $allFiles, $foldersById)) {
                $map[$name] = (int) $folder['id'];
            }
        }

        foreach ($allFiles as $file) {
            if (empty($file['is_shared'])) {
                continue;
            }
            $parentId = (int) ($file['parent_id'] ?? 0);
            if ($parentId <= 0) {
                continue;
            }
            $ancestor = $this->findNamedAncestorFolder($parentId, $usernames, $foldersById);
            if ($ancestor !== null) {
                $map[$ancestor['name']] = $ancestor['id'];
            }
        }

        return $map;
    }

    /** @return array<string, int> */
    private function listFriendSubfolders(int $friendsRootId): array
    {
        $map = [];

        foreach ([true, false] as $hidden) {
            foreach ($this->listAllFilesAt($friendsRootId, $hidden) as $file) {
                if (!Client::isFolder($file)) {
                    continue;
                }
                $username = mb_strtolower(trim((string) ($file['name'] ?? '')));
                if ($username !== '') {
                    $map[$username] = (int) $file['id'];
                }
            }
        }

        return $map;
    }

    /** @return list<array<string, mixed>> */
    private function listAllFilesAt(int $parentId, bool $hidden): array
    {
        $files = [];
        $cursor = null;

        do {
            $response = $this->client->listFiles(
                $parentId,
                $cursor,
                $hidden ? ['hidden' => true] : []
            );
            $batch = $response['files'] ?? [];
            if ($batch !== []) {
                $files = array_merge($files, $batch);
            }
            $cursor = $response['cursor'] ?? null;
        } while ($cursor);

        return $files;
    }

    /** @param array<string, mixed> $file */
    private function isFriendsRootFolder(array $file): bool
    {
        if ($this->isSharedRootFolder($file)) {
            return true;
        }

        if (!Client::isFolder($file)) {
            return false;
        }

        $folderType = mb_strtolower(trim((string) ($file['folder_type'] ?? '')));
        if ($folderType !== '' && str_contains($folderType, 'friend')) {
            return true;
        }

        return $this->isFriendsRootFolderName(mb_strtolower(trim((string) ($file['name'] ?? ''))));
    }

    private function isFriendsRootFolderName(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        $patterns = [
            'your friends\' files',
            'your friends files',
            'your friend\'s files',
            'friends\' files',
            'friends files',
            'file dei tuoi amici',
            'i file dei tuoi amici',
            'i file dei miei amici',
            'file degli amici',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($name, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $folder
     * @param list<array<string, mixed>> $allFiles
     * @param array<int, array<string, mixed>> $foldersById
     */
    private function looksLikeFriendFolder(array $folder, array $allFiles, array $foldersById): bool
    {
        if (!empty($folder['is_shared']) || !empty($folder['is_hidden'])) {
            return true;
        }

        $folderType = mb_strtolower(trim((string) ($folder['folder_type'] ?? '')));
        if ($folderType !== '' && str_contains($folderType, 'friend')) {
            return true;
        }

        $folderId = (int) ($folder['id'] ?? 0);
        foreach ($allFiles as $file) {
            if ((int) ($file['parent_id'] ?? 0) === $folderId && !empty($file['is_shared'])) {
                return true;
            }
        }

        $parentId = (int) ($folder['parent_id'] ?? 0);
        if ($parentId !== 0 && isset($foldersById[$parentId]) && $this->isFriendsRootFolder($foldersById[$parentId])) {
            return true;
        }

        if ($parentId !== 0 && isset($foldersById[$parentId]) && $this->isSharedRootFolder($foldersById[$parentId])) {
            return true;
        }

        return false;
    }

    /**
     * @param list<string> $usernames
     * @param array<int, array<string, mixed>> $foldersById
     * @return array{name: string, id: int}|null
     */
    private function findNamedAncestorFolder(int $startId, array $usernames, array $foldersById): ?array
    {
        $currentId = $startId;
        $visited = [];

        while ($currentId !== 0 && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            $folder = $foldersById[$currentId] ?? null;
            if (!$folder) {
                break;
            }

            $name = mb_strtolower(trim((string) ($folder['name'] ?? '')));
            if ($name !== '' && in_array($name, $usernames, true)) {
                return ['name' => $name, 'id' => $currentId];
            }

            $currentId = (int) ($folder['parent_id'] ?? 0);
        }

        return null;
    }

    /** @param array<string, mixed> $friend */
    private function friendUsername(array $friend): string
    {
        $candidates = [
            $friend['name'] ?? null,
            $friend['username'] ?? null,
        ];

        foreach ($candidates as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
