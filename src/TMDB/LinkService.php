<?php

declare(strict_types=1);

namespace PutMio\TMDB;

use PutMio\CatalogService;
use PutMio\Config;
use PutMio\Database;
use PutMio\Media\SeriesGrouper;

final class LinkService
{
    public function __construct(private readonly Client $client = new Client())
    {
    }

    public function apply(int $mediaId, int $tmdbId, string $tmdbType): string
    {
        if ($tmdbId <= 0) {
            throw new \InvalidArgumentException('ID TMDB non valido');
        }
        if (!in_array($tmdbType, ['movie', 'tv'], true)) {
            $tmdbType = 'movie';
        }

        $details = $this->client->details($tmdbType, $tmdbId, ['external_ids']);

        $title = (string) ($details['title'] ?? $details['name'] ?? '');
        $original = $details['original_title'] ?? $details['original_name'] ?? null;
        $year = null;
        $date = $details['release_date'] ?? $details['first_air_date'] ?? '';
        if ($date) {
            $year = (int) substr((string) $date, 0, 4);
        }
        $synopsis = (string) ($details['overview'] ?? '');
        $posterPath = $this->client->downloadPoster($details['poster_path'] ?? null, $mediaId);
        $posterUrl = $this->client->posterUrl($details['poster_path'] ?? null);
        $backdropPath = $this->client->downloadBackdrop($details['backdrop_path'] ?? null, $mediaId);
        $backdropUrl = $this->client->backdropUrl($details['backdrop_path'] ?? null);

        $runtime = $details['runtime'] ?? null;
        if ($runtime === null && !empty($details['episode_run_time'][0])) {
            $runtime = (int) $details['episode_run_time'][0];
        }
        $durationSec = $runtime !== null ? (int) $runtime * 60 : null;
        $mediaType = putmio_media_type_from_tmdb($tmdbType, $details['genres'] ?? []) ?? 'altro';
        $imdbId = null;
        if (!empty($details['external_ids']['imdb_id'])) {
            $imdbId = (string) $details['external_ids']['imdb_id'];
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE `' . Config::table('media_items') . '`
             SET title = ?, original_title = ?, year = ?, synopsis = ?,
                 poster_local_path = ?, poster_url = ?,
                 backdrop_local_path = ?, backdrop_url = ?,
                 tmdb_id = ?, tmdb_type = ?,
                 imdb_id = ?,
                 media_type = ?,
                 duration_sec = COALESCE(?, duration_sec),
                 classification_status = \'classified\', updated_at = NOW()
             WHERE id = ?'
        )->execute([$title, $original, $year, $synopsis, $posterPath, $posterUrl, $backdropPath, $backdropUrl, $tmdbId, $tmdbType, $imdbId, $mediaType, $durationSec, $mediaId]);

        $catalog = new CatalogService();
        $catalog->syncMediaGenres($mediaId, $details['genres'] ?? []);

        $grouper = new SeriesGrouper();
        $media = $catalog->findMedia($mediaId);
        if ($media && $catalog->isSeries($media)) {
            $catalog->syncSeriesMetadataToEpisodes($mediaId);
        } else {
            $grouper->groupMedia($mediaId);
            $updated = $catalog->findMedia($mediaId);
            if ($updated && !empty($updated['series_id'])) {
                $catalog->syncSeriesMetadataToEpisodes((int) $updated['series_id']);
            }
        }

        return $title !== '' ? $title : 'Senza titolo';
    }
}
