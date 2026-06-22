<?php

declare(strict_types=1);

namespace PutMio\TMDB;

use PutMio\Config;
use PutMio\Database;
use PutMio\Media\ReleaseNameParser;

final class ClassifyMatcher
{
    private const MIN_CONFIDENCE = 45.0;
    private const AUTO_SELECT_CONFIDENCE = 100.0;
    private const MAX_CANDIDATES = 8;

    public function __construct(private readonly Client $client = new Client())
    {
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /** @return array<string, mixed>|null */
    public function suggestForMediaId(int $mediaId): ?array
    {
        if ($mediaId <= 0) {
            return null;
        }

        $item = $this->loadItem($mediaId);
        if ($item === null) {
            return null;
        }

        return $this->suggestForItem($item);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public function suggestForItem(array $item): array
    {
        $mediaId = (int) ($item['id'] ?? 0);
        $isSeriesGroup = empty($item['putio_file_id']) && (int) ($item['episode_count'] ?? 0) > 0;
        $fileLabel = $this->fileLabel($item, $isSeriesGroup);
        $sourceName = (string) ($item['file_name'] ?? $item['title'] ?? '');
        $query = $this->buildQuery($item, $fileLabel, $isSeriesGroup);
        $preferredType = $this->preferredType($sourceName, $isSeriesGroup);
        $yearHint = $this->yearHintFromFilename($sourceName);

        $base = [
            'media_id' => $mediaId,
            'file_label' => $fileLabel,
            'query' => $query,
            'shared_by_username' => $item['shared_by_username'] ?? null,
            'is_series_group' => $isSeriesGroup,
            'year_hint' => $yearHint,
            'candidates' => [],
            'match' => null,
            'confidence' => 0.0,
            'auto_select' => false,
        ];

        if ($query === '') {
            return $base;
        }

        if (!$this->client->isConfigured()) {
            throw new \RuntimeException('API key TMDB non configurata');
        }

        $searchType = $preferredType ?? 'multi';
        $data = $this->client->search($query, $searchType);
        $results = array_values(array_filter(
            $data['results'] ?? [],
            static fn (array $row): bool => in_array($row['media_type'] ?? '', ['movie', 'tv'], true)
                || isset($row['title'], $row['id'])
                || isset($row['name'], $row['id'])
        ));

        if ($searchType === 'tv' || $searchType === 'movie') {
            foreach ($results as &$row) {
                $row['media_type'] = $searchType;
            }
            unset($row);
        }

        if ($searchType === 'multi' && $results === []) {
            foreach (['tv', 'movie'] as $fallbackType) {
                $fallback = $this->client->search($query, $fallbackType);
                $results = array_values(array_filter(
                    $fallback['results'] ?? [],
                    static fn (array $row): bool => in_array($fallbackType, ['movie', 'tv'], true)
                ));
                if ($results !== []) {
                    foreach ($results as &$row) {
                        $row['media_type'] = $fallbackType;
                    }
                    unset($row);
                    break;
                }
            }
        }

        if ($results === []) {
            return $base;
        }

        $scored = [];
        foreach ($results as $result) {
            $score = $this->scoreResult($query, $yearHint, $preferredType, $result);
            if ($score >= self::MIN_CONFIDENCE) {
                $scored[] = ['result' => $result, 'score' => $score];
            }
        }

        if ($scored === []) {
            return $base;
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $scored = array_slice($scored, 0, self::MAX_CANDIDATES);

        $candidates = [];
        foreach ($scored as $row) {
            $candidates[] = $this->formatCandidate($row['result'], $row['score']);
        }

        $best = $candidates[0];
        $base['candidates'] = $candidates;
        $base['confidence'] = (float) $best['confidence'];
        $base['auto_select'] = $base['confidence'] >= self::AUTO_SELECT_CONFIDENCE;
        $base['match'] = $best;

        return $base;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function formatCandidate(array $result, float $score): array
    {
        $tmdbType = ($result['media_type'] ?? '') === 'tv' ? 'tv' : 'movie';
        $title = (string) ($result['title'] ?? $result['name'] ?? '');
        $original = $result['original_title'] ?? $result['original_name'] ?? null;
        $date = (string) ($result['release_date'] ?? $result['first_air_date'] ?? '');
        $year = $date !== '' ? (int) substr($date, 0, 4) : null;
        $mediaType = putmio_media_type_from_tmdb(
            $tmdbType,
            array_map(
                static fn (int $id): array => ['id' => $id],
                array_map('intval', (array) ($result['genre_ids'] ?? []))
            )
        ) ?? 'altro';

        return [
            'tmdb_id' => (int) ($result['id'] ?? 0),
            'tmdb_type' => $tmdbType,
            'title' => $title,
            'original_title' => $original,
            'year' => $year,
            'poster_path' => $result['poster_path'] ?? null,
            'poster_url' => $this->client->posterUrl($result['poster_path'] ?? null, 'w92'),
            'media_type' => $mediaType,
            'overview' => (string) ($result['overview'] ?? ''),
            'vote_average' => isset($result['vote_average']) ? (float) $result['vote_average'] : null,
            'confidence' => round($score, 1),
        ];
    }

    /** @return array<string, mixed>|null */
    private function loadItem(int $mediaId): ?array
    {
        $pdo = Database::pdo();
        $mediaTable = Config::table('media_items');
        $filesTable = Config::table('putio_files');
        $stmt = $pdo->prepare(
            "SELECT mi.*, pf.name AS file_name, pf.shared_by_username,
                    (SELECT COUNT(*) FROM `{$mediaTable}` ep
                     WHERE ep.series_id = mi.id AND ep.classification_status = 'unclassified') AS episode_count
             FROM `{$mediaTable}` mi
             LEFT JOIN `{$filesTable}` pf ON pf.id = mi.putio_file_id
             WHERE mi.id = ?
               AND mi.classification_status = 'unclassified'
               AND mi.series_id IS NULL
             LIMIT 1"
        );
        $stmt->execute([$mediaId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

  /**
     * @param array<string, mixed> $item
     */
    private function fileLabel(array $item, bool $isSeriesGroup): string
    {
        $fileLabel = $item['file_name'] ?? null;
        if ($fileLabel === null && $isSeriesGroup) {
            return putmio_lang('classify_series_episodes', ['count' => (string) (int) ($item['episode_count'] ?? 0)]);
        }

        return (string) ($fileLabel ?? $item['title'] ?? '');
    }

    /**
     * @param array<string, mixed> $item
     */
    private function buildQuery(array $item, string $fileLabel, bool $isSeriesGroup): string
    {
        $source = (string) ($item['file_name'] ?? $item['title'] ?? '');
        if ($source !== '') {
            $guessed = putmio_guess_title_from_filename($source);
            if ($guessed) {
                return $guessed;
            }
        }

        $title = trim((string) ($item['title'] ?? ''));
        if ($title !== '' && $title !== $fileLabel) {
            $guessed = putmio_guess_title_from_filename($title);
            if ($guessed) {
                return $guessed;
            }

            return $title;
        }

        if ($isSeriesGroup && $title !== '') {
            $guessed = putmio_guess_title_from_filename($title);
            if ($guessed) {
                return $guessed;
            }

            return $title;
        }

        return $fileLabel !== '' ? $fileLabel : $title;
    }

    private function preferredType(string $sourceName, bool $isSeriesGroup): ?string
    {
        if ($isSeriesGroup) {
            return 'tv';
        }
        if ($sourceName !== '' && ReleaseNameParser::parseEpisode($sourceName) !== null) {
            return 'tv';
        }

        return null;
    }

    private function yearHintFromFilename(string $filename): ?int
    {
        if ($filename === '') {
            return null;
        }
        if (!preg_match_all('/\b(19|20)\d{2}\b/', $filename, $matches)) {
            return null;
        }
        $currentYear = (int) date('Y');
        foreach ($matches[0] as $yearStr) {
            $year = (int) $yearStr;
            if ($year >= 1950 && $year <= $currentYear + 1) {
                return $year;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function scoreResult(string $query, ?int $yearHint, ?string $preferredType, array $result): float
    {
        $type = ($result['media_type'] ?? '') === 'tv' ? 'tv' : 'movie';
        $title = (string) ($result['title'] ?? $result['name'] ?? '');
        $original = (string) ($result['original_title'] ?? $result['original_name'] ?? '');

        $similarity = max(
            $this->titleSimilarity($query, $title),
            $original !== '' ? $this->titleSimilarity($query, $original) : 0.0
        );

        $score = $similarity;
        if ($preferredType !== null && $type === $preferredType) {
            $score += 12.0;
        }

        $date = (string) ($result['release_date'] ?? $result['first_air_date'] ?? '');
        if ($yearHint !== null && $date !== '') {
            $resultYear = (int) substr($date, 0, 4);
            if ($resultYear === $yearHint) {
                $score += 10.0;
            } elseif (abs($resultYear - $yearHint) === 1) {
                $score += 4.0;
            }
        }

        $popularity = (float) ($result['popularity'] ?? 0);
        $score += min(8.0, $popularity / 15.0);

        $voteAverage = (float) ($result['vote_average'] ?? 0);
        $score += min(5.0, $voteAverage / 2.0);

        return min(100.0, $score);
    }

    private function titleSimilarity(string $a, string $b): float
    {
        $na = $this->normalize($a);
        $nb = $this->normalize($b);
        if ($na === '' || $nb === '') {
            return 0.0;
        }
        if ($na === $nb) {
            return 100.0;
        }
        if (str_contains($na, $nb) || str_contains($nb, $na)) {
            $shorter = min(mb_strlen($na), mb_strlen($nb));
            $longer = max(mb_strlen($na), mb_strlen($nb));
            if ($longer > 0 && ($shorter / $longer) >= 0.6) {
                return 92.0;
            }
        }

        similar_text($na, $nb, $pct);

        return (float) $pct;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value);
        $value = (string) preg_replace('/\s+/', ' ', trim($value));

        return $value;
    }
}
