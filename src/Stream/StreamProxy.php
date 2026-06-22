<?php

declare(strict_types=1);

namespace PutMio\Stream;

use PutMio\Config;
use PutMio\Database;
use PutMio\PutIO\Client;

final class StreamProxy
{
    /** @var Client */
    private $putio;

    public function __construct(?Client $putio = null)
    {
        $this->putio = $putio ?? new Client();
    }

    public function stream(int $putioFileId, int $userId): void
    {
        $this->assertCanStream($putioFileId, $userId);
        $this->enforceConcurrencyLimit();

        @set_time_limit(0);
        @ini_set('output_buffering', 'off');

        $remoteUrl = $this->putio->getDownloadUrl($putioFileId);
        $sessionId = $this->startSession($userId, $putioFileId);

        $headers = [];
        if (!empty($_SERVER['HTTP_RANGE'])) {
            $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
        }

        $ch = curl_init($remoteUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use ($sessionId) {
                $len = strlen($chunk);
                $this->addBytes($sessionId, $len);
                echo $chunk;
                if (function_exists('flush')) {
                    flush();
                }
                return $len;
            },
        ]);

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, string $headerLine) {
            if (preg_match('/^Content-Type:\s*(.+)$/i', trim($headerLine), $m)) {
                header('Content-Type: ' . trim($m[1]));
            }
            if (preg_match('/^Content-Length:\s*(\d+)$/i', trim($headerLine), $m)) {
                header('Content-Length: ' . $m[1]);
            }
            if (preg_match('/^Content-Range:\s*(.+)$/i', trim($headerLine), $m)) {
                header('Content-Range: ' . trim($m[1]));
                http_response_code(206);
            }
            if (preg_match('/^Accept-Ranges:\s*(.+)$/i', trim($headerLine), $m)) {
                header('Accept-Ranges: ' . trim($m[1]));
            }
            return strlen($headerLine);
        });

        header('Cache-Control: private, no-store');
        header('Accept-Ranges: bytes');

        $ok = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->endSession($sessionId);

        if ($ok === false || $httpCode >= 400) {
            if (!headers_sent()) {
                http_response_code(502);
            }
        }
    }

    private function assertCanStream(int $putioFileId, int $userId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT mi.id FROM `' . Config::table('media_items') . '` mi
             JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
             WHERE pf.putio_id = ? AND mi.classification_status != \'ignored\' LIMIT 1'
        );
        $stmt->execute([$putioFileId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            exit('Contenuto non trovato');
        }
    }

    private function enforceConcurrencyLimit(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $max = (int) Config::get('app.max_concurrent_streams_per_ip', 2);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM `' . Config::table('stream_sessions') . '`
             WHERE client_ip = ? AND active = 1'
        );
        $stmt->execute([$ip]);
        if ((int) $stmt->fetchColumn() >= $max) {
            http_response_code(429);
            exit('Troppi stream simultanei');
        }
    }

    private function startSession(int $userId, int $putioFileId): int
    {
        $pdo = Database::pdo();
        $mediaId = null;
        $stmt = $pdo->prepare(
            'SELECT mi.id FROM `' . Config::table('media_items') . '` mi
             JOIN `' . Config::table('putio_files') . '` pf ON pf.id = mi.putio_file_id
             WHERE pf.putio_id = ? LIMIT 1'
        );
        $stmt->execute([$putioFileId]);
        $row = $stmt->fetch();
        if ($row) {
            $mediaId = (int) $row['id'];
        }

        $pdo->prepare(
            'INSERT INTO `' . Config::table('stream_sessions') . '`
            (user_id, putio_file_id, media_id, client_ip, active) VALUES (?, ?, ?, ?, 1)'
        )->execute([$userId, $putioFileId, $mediaId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);

        return (int) $pdo->lastInsertId();
    }

    private function addBytes(int $sessionId, int $bytes): void
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE `' . Config::table('stream_sessions') . '` SET bytes_sent = bytes_sent + ? WHERE id = ?'
        )->execute([$bytes, $sessionId]);
    }

    private function endSession(int $sessionId): void
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE `' . Config::table('stream_sessions') . '` SET active = 0, ended_at = NOW() WHERE id = ?'
        )->execute([$sessionId]);
    }
}
