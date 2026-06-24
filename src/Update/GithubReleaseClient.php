<?php

declare(strict_types=1);

namespace PutMio\Update;

use PutMio\Config;

/**
 * Interroga l'API GitHub Releases per l'ultima versione pubblicata.
 */
final class GithubReleaseClient
{
    private const CACHE_TTL = 1800; // 30 minuti

    private string $repo;
    private string $token;
    private ?string $lastError = null;
    private int $lastHttpStatus = 0;
    private bool $fromCache = false;

    public function __construct(?string $repo = null, ?string $token = null)
    {
        $this->repo = trim($repo ?? (string) Config::get('updates.github_repo', ''));
        $this->token = trim($token ?? (string) Config::get('updates.github_token', ''));
    }

    public function isConfigured(): bool
    {
        return $this->repo !== '' && preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $this->repo) === 1;
    }

    public function repository(): string
    {
        return $this->repo;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function lastHttpStatus(): int
    {
        return $this->lastHttpStatus;
    }

    public function isFromCache(): bool
    {
        return $this->fromCache;
    }

    /**
     * @return array{
     *   version: string,
     *   tag: string,
     *   name: string,
     *   body: string,
     *   published_at: string,
     *   zip_url: string|null,
     *   html_url: string
     * }|null
     */
    public function fetchLatest(bool $forceRefresh = false): ?array
    {
        $this->lastError = null;
        $this->lastHttpStatus = 0;
        $this->fromCache = false;

        if (!$this->isConfigured()) {
            $this->lastError = 'repository_not_configured';
            return null;
        }

        if (!$forceRefresh) {
            $cached = $this->readCache(self::CACHE_TTL);
            if ($cached !== null) {
                $this->fromCache = true;
                return $cached;
            }
        }

        $latestUrl = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';
        $raw = $this->request($latestUrl);
        $data = $raw !== null ? json_decode($raw, true) : null;

        if ((!is_array($data) || empty($data['tag_name'])) && $this->lastHttpStatus === 404) {
            $listUrl = 'https://api.github.com/repos/' . $this->repo . '/releases?per_page=5';
            $listRaw = $this->request($listUrl);
            $list = $listRaw !== null ? json_decode($listRaw, true) : null;
            if (is_array($list)) {
                foreach ($list as $release) {
                    if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease'])) {
                        continue;
                    }
                    if (!empty($release['tag_name'])) {
                        $data = $release;
                        break;
                    }
                }
            }
        }

        if (!is_array($data) || empty($data['tag_name'])) {
            $stale = $this->readCache(null);
            if ($stale !== null) {
                $this->fromCache = true;
                $this->lastError = $this->lastError === 'github_rate_limit' ? 'github_rate_limit_cached' : null;
                return $stale;
            }

            if ($this->lastError === null) {
                $this->lastError = $this->lastHttpStatus === 404
                    ? 'release_not_found'
                    : 'release_fetch_failed';
            }
            return null;
        }

        $mapped = $this->mapRelease($data);
        $this->writeCache($mapped);

        return $mapped;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{
     *   version: string,
     *   tag: string,
     *   name: string,
     *   body: string,
     *   published_at: string,
     *   zip_url: string|null,
     *   html_url: string
     * }
     */
    private function mapRelease(array $data): array
    {
        $tag = (string) $data['tag_name'];
        $version = ltrim($tag, 'vV');
        $zipUrl = null;

        foreach ($data['assets'] ?? [] as $asset) {
            if (!is_array($asset)) {
                continue;
            }
            $name = (string) ($asset['name'] ?? '');
            if ($name !== '' && str_ends_with(strtolower($name), '.zip')) {
                $zipUrl = (string) ($asset['browser_download_url'] ?? '');
                if ($zipUrl !== '') {
                    break;
                }
            }
        }

        if ($zipUrl === null && !empty($data['zipball_url'])) {
            $zipUrl = (string) $data['zipball_url'];
        }

        return [
            'version' => $version,
            'tag' => $tag,
            'name' => (string) ($data['name'] ?? $tag),
            'body' => (string) ($data['body'] ?? ''),
            'published_at' => (string) ($data['published_at'] ?? ''),
            'zip_url' => $zipUrl !== '' ? $zipUrl : null,
            'html_url' => (string) ($data['html_url'] ?? ''),
        ];
    }

    private function cachePath(): string
    {
        $dir = CoreManifest::updatesWorkDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . '/github-release-' . md5($this->repo) . '.json';
    }

    /**
     * @return array{
     *   version: string,
     *   tag: string,
     *   name: string,
     *   body: string,
     *   published_at: string,
     *   zip_url: string|null,
     *   html_url: string
     * }|null
     */
    private function readCache(?int $maxAge): ?array
    {
        $path = $this->cachePath();
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || !isset($payload['fetched_at'], $payload['release']) || !is_array($payload['release'])) {
            return null;
        }

        $age = time() - (int) $payload['fetched_at'];
        if ($maxAge !== null && $age > $maxAge) {
            return null;
        }

        $release = $payload['release'];
        if (empty($release['version']) || empty($release['tag'])) {
            return null;
        }

        return $release;
    }

    /**
     * @param array{
     *   version: string,
     *   tag: string,
     *   name: string,
     *   body: string,
     *   published_at: string,
     *   zip_url: string|null,
     *   html_url: string
     * } $release
     */
    private function writeCache(array $release): void
    {
        $path = $this->cachePath();
        $payload = json_encode([
            'fetched_at' => time(),
            'release' => $release,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload !== false) {
            @file_put_contents($path, $payload, LOCK_EX);
        }
    }

    private function request(string $url): ?string
    {
        $response = $this->requestViaCurl($url);
        if ($response !== null) {
            return $response;
        }

        return $this->requestViaStream($url);
    }

    private function requestHeaders(): array
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: PutMio-Updater/' . putmio_version(),
        ];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        return $headers;
    }

    private function requestViaCurl(string $url): ?string
    {
        if (!extension_loaded('curl')) {
            $this->lastError = 'curl_missing';
            return null;
        }

        $attempts = [CURL_IPRESOLVE_WHATEVER, CURL_IPRESOLVE_V4];
        foreach ($attempts as $resolve) {
            $ch = curl_init($url);
            if ($ch === false) {
                continue;
            }

            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => $this->requestHeaders(),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_IPRESOLVE => $resolve,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            ];

            $caFile = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
            if (is_string($caFile) && $caFile !== '' && is_file($caFile)) {
                $options[CURLOPT_CAINFO] = $caFile;
            }

            curl_setopt_array($ch, $options);

            $response = curl_exec($ch);
            $this->lastHttpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);

            if ($response !== false && $this->lastHttpStatus >= 200 && $this->lastHttpStatus < 300) {
                $this->lastError = null;
                return (string) $response;
            }

            if ($curlErrno !== 0) {
                $this->lastError = 'curl_error:' . $curlErrno . ':' . $curlErr;
            } elseif ($this->lastHttpStatus === 403) {
                $this->lastError = 'github_rate_limit';
            } elseif ($this->lastHttpStatus === 404) {
                $this->lastError = 'release_not_found';
            } else {
                $this->lastError = 'http_' . $this->lastHttpStatus;
            }
        }

        return null;
    }

    private function requestViaStream(string $url): ?string
    {
        if (!ini_get('allow_url_fopen')) {
            if ($this->lastError === null) {
                $this->lastError = 'remote_fetch_blocked';
            }
            return null;
        }

        $headerLines = $this->requestHeaders();
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headerLines) . "\r\n",
                'timeout' => 20,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $this->lastHttpStatus = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', (string) $http_response_header[0], $m)) {
            $this->lastHttpStatus = (int) $m[1];
        }

        if ($response !== false && $this->lastHttpStatus >= 200 && $this->lastHttpStatus < 300) {
            $this->lastError = null;
            return (string) $response;
        }

        if ($this->lastError === null) {
            $this->lastError = $this->lastHttpStatus === 403
                ? 'github_rate_limit'
                : ($this->lastHttpStatus > 0 ? 'http_' . $this->lastHttpStatus : 'stream_fetch_failed');
        }

        return null;
    }
}
