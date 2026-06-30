<?php

declare(strict_types=1);

namespace PutMio\OpenSubtitles;

use PutMio\Config;

final class Client
{
    private const API_BASE = 'https://api.opensubtitles.com/api/v1';

    private ?string $token = null;

    public function isConfigured(): bool
    {
        return trim((string) Config::get('opensubtitles.api_key', '')) !== ''
            && trim((string) Config::get('opensubtitles.username', '')) !== ''
            && trim((string) Config::get('opensubtitles.password', '')) !== '';
    }

    public function testConnection(): void
    {
        $this->token = null;
        @unlink($this->tokenCachePath());
        $this->login(true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(array $params): array
    {
        $this->login();

        $query = array_filter([
            'tmdb_id' => $params['tmdb_id'] ?? null,
            'imdb_id' => $params['imdb_id'] ?? null,
            'parent_tmdb_id' => $params['parent_tmdb_id'] ?? null,
            'parent_imdb_id' => $params['parent_imdb_id'] ?? null,
            'query' => $params['query'] ?? null,
            'season_number' => $params['season_number'] ?? null,
            'episode_number' => $params['episode_number'] ?? null,
            'languages' => $params['languages'] ?? null,
            'type' => $params['type'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $response = $this->request('GET', '/subtitles?' . http_build_query($query));
        $data = $response['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    public function downloadFile(string $fileId): string
    {
        $this->login();

        $response = $this->request('POST', '/download', [
            'file_id' => (int) $fileId,
        ]);

        $link = (string) ($response['link'] ?? '');
        if ($link === '') {
            throw new \RuntimeException('Link download sottotitoli non disponibile');
        }

        return $this->fetchUrl($link);
    }

    private function login(bool $force = false): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException(putmio_lang('subtitles_not_configured'));
        }

        if (!$force && $this->token !== null) {
            return;
        }

        $cached = $this->readCachedToken();
        if (!$force && $cached !== null) {
            $this->token = $cached;
            return;
        }

        $response = $this->request('POST', '/login', [
            'username' => (string) Config::get('opensubtitles.username'),
            'password' => (string) Config::get('opensubtitles.password'),
        ], false);

        $token = (string) ($response['token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('Autenticazione OpenSubtitles fallita');
        }

        $this->token = $token;
        $this->writeCachedToken($token);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null, bool $withAuth = true, bool $allowAuthRetry = true): array
    {
        $url = self::API_BASE . $path;
        $headers = [
            'Api-Key: ' . (string) Config::get('opensubtitles.api_key'),
            'User-Agent: ' . (string) Config::get('opensubtitles.user_agent', 'PutMio v1.0'),
            'Accept: application/json',
        ];

        if ($withAuth && $this->token !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Impossibile inizializzare cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($body !== null) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('Errore OpenSubtitles: ' . $err);
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Risposta OpenSubtitles non valida');
        }

        if ($code === 401 && $withAuth && $allowAuthRetry) {
            $this->token = null;
            @unlink($this->tokenCachePath());
            $this->login(true);

            return $this->request($method, $path, $body, true, false);
        }

        if ($code >= 400) {
            $message = (string) ($decoded['message'] ?? $decoded['error'] ?? 'Errore API OpenSubtitles');
            throw new \RuntimeException($message);
        }

        return $decoded;
    }

    private function fetchUrl(string $url): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Impossibile scaricare il file sottotitoli');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . (string) Config::get('opensubtitles.user_agent', 'PutMio v1.0'),
            ],
        ]);

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $code >= 400) {
            throw new \RuntimeException('Download file sottotitoli fallito');
        }

        return $raw;
    }

    private function tokenCachePath(): string
    {
        return putmio_base_path() . '/storage/.opensubtitles_token';
    }

    private function readCachedToken(): ?string
    {
        $path = $this->tokenCachePath();
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $expires = (int) ($data['expires_at'] ?? 0);
        if ($expires > 0 && $expires < time()) {
            @unlink($path);
            return null;
        }

        $token = (string) ($data['token'] ?? '');

        return $token !== '' ? $token : null;
    }

    private function writeCachedToken(string $token): void
    {
        $dir = putmio_base_path() . '/storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $payload = json_encode([
            'token' => $token,
            'expires_at' => time() + 3600 * 20,
        ]);

        if ($payload !== false) {
            @file_put_contents($this->tokenCachePath(), $payload, LOCK_EX);
        }
    }
}
