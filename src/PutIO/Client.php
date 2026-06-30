<?php

declare(strict_types=1);

namespace PutMio\PutIO;

use PutMio\Config;
use PutMio\Database;

final class Client
{
    private const API_BASE = 'https://api.put.io/v2';

    public function getConnection(): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT * FROM `' . Config::table('putio_connection') . '` WHERE id = 1 LIMIT 1');
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function isConnected(): bool
    {
        $conn = $this->getConnection();
        return $conn && !empty($conn['access_token_enc']);
    }

    public function saveTokens(array $tokenData, ?array $account = null): void
    {
        $key = (string) Config::get('app.encryption_key');
        $access = putmio_encrypt($tokenData['access_token'], $key);
        $refresh = !empty($tokenData['refresh_token']) ? putmio_encrypt($tokenData['refresh_token'], $key) : null;
        $expiresAt = null;
        if (!empty($tokenData['expires_in'])) {
            $expiresAt = date('Y-m-d H:i:s', time() + (int) $tokenData['expires_in']);
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO `' . Config::table('putio_connection') . '`
            (id, putio_user_id, putio_username, access_token_enc, refresh_token_enc, expires_at, connected_at, updated_at)
            VALUES (1, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            putio_user_id = VALUES(putio_user_id),
            putio_username = VALUES(putio_username),
            access_token_enc = VALUES(access_token_enc),
            refresh_token_enc = VALUES(refresh_token_enc),
            expires_at = VALUES(expires_at),
            updated_at = NOW()'
        )->execute([
            $account['user_id'] ?? null,
            $account['username'] ?? null,
            $access,
            $refresh,
            $expiresAt,
        ]);
    }

    public function disconnect(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('DELETE FROM `' . Config::table('putio_connection') . '` WHERE id = 1');
    }

    public function getAccessToken(): string
    {
        $conn = $this->getConnection();
        if (!$conn || empty($conn['access_token_enc'])) {
            throw new \RuntimeException('put.io non collegato');
        }

        $key = (string) Config::get('app.encryption_key');
        if (!empty($conn['expires_at']) && strtotime($conn['expires_at']) < time() + 60) {
            $this->refreshToken($conn, $key);
            $conn = $this->getConnection();
        }

        return putmio_decrypt($conn['access_token_enc'], $key);
    }

    private function refreshToken(array $conn, string $key): void
    {
        if (empty($conn['refresh_token_enc'])) {
            throw new \RuntimeException('Token put.io scaduto — ricollega l\'account');
        }
        $refresh = putmio_decrypt($conn['refresh_token_enc'], $key);
        $response = $this->httpForm(self::API_BASE . '/oauth2/access_token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
            'client_id' => Config::get('putio.client_id'),
            'client_secret' => Config::get('putio.client_secret'),
        ], false);

        $this->saveTokens($response);
    }

    public function exchangeCode(string $code): array
    {
        return $this->httpForm(self::API_BASE . '/oauth2/access_token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => Config::get('putio.client_id'),
            'client_secret' => Config::get('putio.client_secret'),
            'redirect_uri' => Config::get('putio.redirect_uri'),
        ], false);
    }

    public function accountInfo(): array
    {
        return $this->apiGet('/account/info');
    }

    public function listFiles(int $parentId = -1, ?string $cursor = null, array $options = []): array
    {
        $query = ['parent_id' => $parentId, 'per_page' => 1000];
        if ($cursor) {
            $query['cursor'] = $cursor;
        }
        if (!empty($options['hidden'])) {
            $query['hidden'] = 1;
        }
        return $this->apiGet('/files/list', $query);
    }

    public function listFriends(): array
    {
        $data = $this->apiGet('/friends/list');
        return $data['friends'] ?? [];
    }

    public static function isFolder(array $file): bool
    {
        if (!empty($file['is_folder']) || ($file['file_type'] ?? '') === 'FOLDER') {
            return true;
        }

        return ($file['content_type'] ?? '') === 'application/x-directory';
    }

    public function getDownloadUrl(int $fileId): string
    {
        $data = $this->apiGet('/files/' . $fileId . '/url');
        if (empty($data['url'])) {
            throw new \RuntimeException('URL streaming non disponibile');
        }
        return $data['url'];
    }

    /** @return array{status: string, percent_done?: int, size?: int} */
    public function getMp4Status(int $fileId): array
    {
        $data = $this->apiGet('/files/' . $fileId . '/mp4');
        return is_array($data['mp4'] ?? null) ? $data['mp4'] : [];
    }

    public function isMp4Available(int $fileId): bool
    {
        try {
            $status = $this->getMp4Status($fileId);
            return strtoupper((string) ($status['status'] ?? '')) === 'COMPLETED';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getPlaybackRemoteUrl(int $fileId, string $format = 'mp4'): string
    {
        if ($format === 'mp4') {
            if (!$this->isMp4Available($fileId)) {
                throw new \RuntimeException('Versione MP4 non disponibile su put.io');
            }
            return $this->resolveRedirect('/files/' . $fileId . '/mp4/stream');
        }

        return $this->getDownloadUrl($fileId);
    }

    /**
     * @return list<array{key: string, language?: string, name?: string, source?: string}>
     */
    public function listSubtitles(int $fileId): array
    {
        try {
            $data = $this->apiGet('/files/' . $fileId . '/subtitles');
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'HTTP 404')) {
                return [];
            }
            throw $e;
        }

        $items = $data['subtitles'] ?? [];

        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['key'])) {
                continue;
            }
            $normalized[] = [
                'key' => (string) $item['key'],
                'language' => isset($item['language']) ? (string) $item['language'] : '',
                'name' => isset($item['name']) ? (string) $item['name'] : '',
                'source' => isset($item['source']) ? (string) $item['source'] : '',
            ];
        }

        return $normalized;
    }

    public function downloadSubtitle(int $fileId, string $key, string $format = 'webvtt'): string
    {
        $token = $this->getAccessToken();
        $url = self::API_BASE . '/files/' . $fileId . '/subtitles/' . rawurlencode($key) . '?' . http_build_query([
            'format' => $format,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: text/plain, text/vtt, */*',
            ],
            CURLOPT_TIMEOUT => 120,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('Errore cURL sottotitoli put.io: ' . $err);
        }
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException('Download sottotitoli put.io non disponibile (HTTP ' . $code . ')');
        }

        $content = trim((string) $body);
        if ($content === '') {
            throw new \RuntimeException('Sottotitolo put.io vuoto');
        }

        return $content;
    }

    public function getHlsManifest(int $fileId, string $subtitleKey = 'all'): string
    {
        $token = $this->getAccessToken();
        $url = self::API_BASE . '/files/' . $fileId . '/hls/media.m3u8?' . http_build_query([
            'subtitle_key' => $subtitleKey,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT => 120,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300) {
            throw new \RuntimeException('Manifest HLS put.io non disponibile (HTTP ' . $code . ')');
        }

        $manifest = trim((string) $body);
        if ($manifest === '' || stripos($manifest, '#EXTM3U') !== 0) {
            throw new \RuntimeException('Manifest HLS put.io non valido');
        }

        return $manifest;
    }

    private function resolveRedirect(string $path, array $query = []): string
    {
        $url = self::API_BASE . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $token = $this->getAccessToken();
        $location = null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
            ],
        ]);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, string $line) use (&$location) {
            if (preg_match('/^Location:\s*(.+)$/i', trim($line), $m)) {
                $location = trim($m[1]);
            }
            return strlen($line);
        });
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($location) {
            return $location;
        }
        if ($code >= 200 && $code < 300) {
            return $url;
        }

        throw new \RuntimeException('URL stream put.io non disponibile (HTTP ' . $code . ')');
    }

    public function apiGet(string $path, array $query = []): array
    {
        $url = self::API_BASE . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        return $this->httpJson($url, 'GET');
    }

    private function httpJson(string $url, string $method = 'GET', ?array $body = null): array
    {
        $token = $this->getAccessToken();
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 120,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
                'Content-Type: application/json',
            ]);
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('Errore cURL: ' . $err);
        }
        $data = json_decode($raw, true);
        if ($code >= 400) {
            $msg = is_array($data) ? ($data['error_message'] ?? $data['message'] ?? 'Errore API') : 'Errore API';
            throw new \RuntimeException('put.io HTTP ' . $code . ': ' . $msg);
        }
        return is_array($data) ? $data : [];
    }

    private function httpForm(string $url, array $fields, bool $auth = true): array
    {
        $headers = ['Accept: application/json'];
        if ($auth) {
            $headers[] = 'Authorization: Bearer ' . $this->getAccessToken();
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $raw, true);
        if ($code >= 400 || !is_array($data)) {
            throw new \RuntimeException('OAuth put.io fallito (HTTP ' . $code . ')');
        }
        return $data;
    }

    public function authorizeUrl(): string
    {
        $params = http_build_query([
            'client_id' => Config::get('putio.client_id'),
            'response_type' => 'code',
            'redirect_uri' => Config::get('putio.redirect_uri'),
        ]);
        return self::API_BASE . '/oauth2/authenticate?' . $params;
    }
}
