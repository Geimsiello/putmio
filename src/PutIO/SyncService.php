<?php

declare(strict_types=1);

namespace PutMio\PutIO;

use PutMio\Config;
use PutMio\Database;

final class SyncService
{
    /** @var Client */
    private $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public function sync(): array
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('put.io non collegato');
        }

        $conn = $this->client->getConnection();
        $rootId = (int) ($conn['sync_root_folder_id'] ?? -1);
        $imported = 0;
        $cursor = null;

        do {
            $response = $this->client->listFiles($rootId, $cursor);
            $files = $response['files'] ?? [];
            foreach ($files as $file) {
                $this->upsertFile($file);
                $imported++;
            }
            $cursor = $response['cursor'] ?? null;
        } while ($cursor);

        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE `' . Config::table('putio_connection') . '`
             SET last_sync_at = NOW(), last_sync_file_count = ?, updated_at = NOW() WHERE id = 1'
        )->execute([$imported]);

        $this->ensureMediaStubs();

        return ['imported' => $imported];
    }

    private function upsertFile(array $file): void
    {
        $pdo = Database::pdo();
        $putioId = (int) ($file['id'] ?? 0);
        if ($putioId <= 0) {
            return;
        }

        $isFolder = (!empty($file['is_folder']) || ($file['file_type'] ?? '') === 'FOLDER') ? 1 : 0;
        $name = (string) ($file['name'] ?? 'Senza nome');
        $mime = $file['mime_type'] ?? $file['content_type'] ?? null;

        $pdo->prepare(
            'INSERT INTO `' . Config::table('putio_files') . '`
            (putio_id, parent_putio_id, name, size, mime, is_folder, content_type, synced_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            parent_putio_id = VALUES(parent_putio_id),
            name = VALUES(name),
            size = VALUES(size),
            mime = VALUES(mime),
            is_folder = VALUES(is_folder),
            content_type = VALUES(content_type),
            synced_at = NOW()'
        )->execute([
            $putioId,
            $file['parent_id'] ?? null,
            $name,
            (int) ($file['size'] ?? 0),
            $mime,
            $isFolder,
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
