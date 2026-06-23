<?php

declare(strict_types=1);

namespace PutMio\TMDB;

use PutMio\Config;

final class Client
{
    private const BASE = 'https://api.themoviedb.org/3';

    public function isConfigured(): bool
    {
        return (string) Config::get('tmdb.api_key', '') !== '';
    }

    public function search(string $query, string $type = 'multi'): array
    {
        $key = (string) Config::get('tmdb.api_key');
        if ($key === '') {
            throw new \RuntimeException('API key TMDB non configurata');
        }

        $endpoint = $type === 'movie' ? '/search/movie' : ($type === 'tv' ? '/search/tv' : '/search/multi');
        $url = self::BASE . $endpoint . '?' . http_build_query([
            'api_key' => $key,
            'language' => Config::get('tmdb.language', 'it-IT'),
            'query' => $query,
        ]);

        return $this->get($url);
    }

    public function details(string $type, int $id, array $append = []): array
    {
        $key = (string) Config::get('tmdb.api_key');
        $path = $type === 'tv' ? '/tv/' . $id : '/movie/' . $id;
        $params = [
            'api_key' => $key,
            'language' => Config::get('tmdb.language', 'it-IT'),
        ];
        if ($append !== []) {
            $params['append_to_response'] = implode(',', $append);
        }
        $url = self::BASE . $path . '?' . http_build_query($params);
        return $this->get($url);
    }

    public function episodeDetails(int $tvId, int $season, int $episode): array
    {
        $key = (string) Config::get('tmdb.api_key');
        if ($key === '') {
            throw new \RuntimeException('API key TMDB non configurata');
        }

        $url = self::BASE . '/tv/' . $tvId . '/season/' . $season . '/episode/' . $episode . '?' . http_build_query([
            'api_key' => $key,
            'language' => Config::get('tmdb.language', 'it-IT'),
        ]);

        return $this->get($url);
    }

    public function posterWebPath(?string $path, string $size = 'w500'): ?string
    {
        return $this->posterUrl($path, $size);
    }

    public function posterUrl(?string $path, string $size = 'w500'): ?string
    {
        if (!$path) {
            return null;
        }
        return 'https://image.tmdb.org/t/p/' . $size . $path;
    }

    public function downloadPoster(string $posterPath, int $mediaId): ?string
    {
        return $this->downloadImage($this->posterUrl($posterPath), 'posters', 'media_' . $mediaId);
    }

    public function backdropUrl(?string $path, string $size = 'w1280'): ?string
    {
        if (!$path) {
            return null;
        }

        return 'https://image.tmdb.org/t/p/' . $size . $path;
    }

    public function downloadBackdrop(?string $backdropPath, int $mediaId): ?string
    {
        return $this->downloadImage($this->backdropUrl($backdropPath), 'backdrops', 'media_' . $mediaId . '_backdrop');
    }

    private function downloadImage(?string $url, string $subdir, string $basename): ?string
    {
        if (!$url) {
            return null;
        }

        $dir = putmio_base_path() . '/storage/' . $subdir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pathPart = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = pathinfo($pathPart, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = $basename . '.' . $ext;
        $dest = $dir . '/' . $filename;

        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        $data = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data === false || $code >= 400) {
            return null;
        }

        file_put_contents($dest, $data);

        return 'storage/' . $subdir . '/' . $filename;
    }

    private function get(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : [];
    }
}
