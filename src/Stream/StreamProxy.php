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

    public function stream(int $putioFileId, int $userId, string $format = 'mp4'): void
    {
        $fileInfo = $this->getFileInfo($putioFileId);
        $this->assertCanStream($putioFileId, $userId);
        $this->expireStaleSessions();
        $this->enforceConcurrencyLimit($userId, $putioFileId);

        @set_time_limit(0);
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        ignore_user_abort(false);

        if (!in_array($format, ['mp4', 'original'], true)) {
            $format = 'mp4';
        }

        try {
            $remoteUrl = $this->putio->getPlaybackRemoteUrl($putioFileId, $format);
        } catch (\Throwable $e) {
            if ($format === 'mp4') {
                try {
                    $remoteUrl = $this->putio->getPlaybackRemoteUrl($putioFileId, 'original');
                    $format = 'original';
                } catch (\Throwable $fallbackError) {
                    http_response_code(502);
                    exit('Stream non disponibile');
                }
            } else {
                http_response_code(502);
                exit('Stream non disponibile');
            }
        }

        $sessionId = $this->startSession($userId, $putioFileId);
        $fallbackMime = $format === 'mp4'
            ? 'video/mp4'
            : putmio_browser_playback_mime($fileInfo['name'] ?? null, $fileInfo['mime'] ?? null);
        $ended = false;
        $endSession = function () use (&$ended, $sessionId): void {
            if ($ended) {
                return;
            }
            $ended = true;
            $this->endSession($sessionId);
        };
        register_shutdown_function(static function () use ($endSession): void {
            $endSession();
        });

        $headers = ['Accept-Encoding: identity'];
        if (!empty($_SERVER['HTTP_RANGE'])) {
            $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
        }

        $forwardHeaders = false;
        $sentContentType = false;

        $ch = curl_init($remoteUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => 'identity',
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use ($sessionId, $endSession) {
                if (connection_aborted()) {
                    $endSession();
                    return -1;
                }
                $len = strlen($chunk);
                $this->addBytes($sessionId, $len);
                echo $chunk;
                if (function_exists('flush')) {
                    flush();
                }
                return $len;
            },
        ]);

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, string $headerLine) use (
            &$forwardHeaders,
            &$sentContentType,
            $fallbackMime
        ) {
            if (preg_match('/^HTTP\/[\d.]+\s+(\d+)/', $headerLine, $m)) {
                $code = (int) $m[1];
                $forwardHeaders = ($code === 200 || $code === 206);
                if ($forwardHeaders) {
                    http_response_code($code);
                }
                return strlen($headerLine);
            }

            if (!$forwardHeaders) {
                return strlen($headerLine);
            }

            if (preg_match('/^Content-Type:\s*(.+)$/i', trim($headerLine), $m)) {
                header('Content-Type: ' . $fallbackMime);
                $sentContentType = true;
            }
            if (preg_match('/^Content-Length:\s*(\d+)$/i', trim($headerLine), $m)) {
                header('Content-Length: ' . $m[1]);
            }
            if (preg_match('/^Content-Range:\s*(.+)$/i', trim($headerLine), $m)) {
                header('Content-Range: ' . trim($m[1]));
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

        if (!$sentContentType && !headers_sent()) {
            header('Content-Type: ' . $fallbackMime);
        }

        if ($ok === false || $httpCode >= 400) {
            if (!headers_sent()) {
                http_response_code(502);
            }
        }
    }

    /** @return array{name: ?string, mime: ?string, size: int} */
    private function getFileInfo(int $putioFileId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT name, mime, size FROM `' . Config::table('putio_files') . '` WHERE putio_id = ? LIMIT 1'
        );
        $stmt->execute([$putioFileId]);
        $row = $stmt->fetch();

        return [
            'name' => $row['name'] ?? null,
            'mime' => $row['mime'] ?? null,
            'size' => (int) ($row['size'] ?? 0),
        ];
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

    private function expireStaleSessions(): void
    {
        $pdo = Database::pdo();
        $pdo->exec(
            'UPDATE `' . Config::table('stream_sessions') . '`
             SET active = 0, ended_at = NOW()
             WHERE active = 1 AND started_at < DATE_SUB(NOW(), INTERVAL 3 MINUTE)'
        );
    }

    private function enforceConcurrencyLimit(int $userId, int $putioFileId): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $max = (int) Config::get('app.max_concurrent_streams_per_ip', 4);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            'SELECT id FROM `' . Config::table('stream_sessions') . '`
             WHERE client_ip = ? AND user_id = ? AND putio_file_id = ? AND active = 1
             LIMIT 1'
        );
        $stmt->execute([$ip, $userId, $putioFileId]);
        if ($stmt->fetch()) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(DISTINCT putio_file_id) FROM `' . Config::table('stream_sessions') . '`
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
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $stmt = $pdo->prepare(
            'SELECT id FROM `' . Config::table('stream_sessions') . '`
             WHERE user_id = ? AND putio_file_id = ? AND client_ip = ? AND active = 1
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $putioFileId, $ip]);
        $existing = $stmt->fetch();
        if ($existing) {
            return (int) $existing['id'];
        }

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
        )->execute([$userId, $putioFileId, $mediaId, $ip]);

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

    public static function terminateAllActive(): int
    {
        $pdo = Database::pdo();

        return (int) $pdo->exec(
            'UPDATE `' . Config::table('stream_sessions') . '`
             SET active = 0, ended_at = NOW()
             WHERE active = 1'
        );
    }
}
