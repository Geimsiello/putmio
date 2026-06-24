<?php

declare(strict_types=1);

namespace PutMio\Update;

use PutMio\Config;

/**
 * Interroga l'API GitHub Releases per l'ultima versione pubblicata.
 */
final class GithubReleaseClient
{
    private string $repo;
    private string $token;

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
    public function fetchLatest(): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $url = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';
        $raw = $this->request($url);
        if ($raw === null) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['tag_name'])) {
            return null;
        }

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

    private function request(string $url): ?string
    {
        if (!extension_loaded('curl')) {
            return null;
        }

        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: PutMio-Updater/' . putmio_version(),
        ];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            return null;
        }

        return (string) $response;
    }
}
