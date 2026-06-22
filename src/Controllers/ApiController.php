<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\AuthService;
use PutMio\Auth\Csrf;
use PutMio\Auth\Session;
use PutMio\CatalogService;
use PutMio\Config;
use PutMio\Database;
use PutMio\TMDB\Client as TmdbClient;

final class ApiController
{
    public function theme(): void
    {
        Session::requireAuth();
        Csrf::requireValid($_POST['_csrf'] ?? null);
        $theme = $_POST['theme'] ?? 'dark';
        if (!in_array($theme, ['light', 'dark'], true)) {
            putmio_json(['ok' => false], 400);
        }
        (new AuthService())->updateTheme((int) Session::userId(), $theme);
        $_SESSION['user_theme'] = $theme;
        setcookie('putmio_theme', $theme, [
            'expires' => time() + 86400 * 365,
            'path' => '/',
            'secure' => true,
            'httponly' => false,
            'samesite' => 'Strict',
        ]);
        putmio_json(['ok' => true, 'theme' => $theme]);
    }

    public function watchProgress(): void
    {
        Session::requireAuth();
        Csrf::requireValid($_POST['_csrf'] ?? null);
        $mediaId = (int) ($_POST['media_id'] ?? 0);
        $position = max(0, (int) ($_POST['position_sec'] ?? 0));
        $duration = max(0, (int) ($_POST['duration_sec'] ?? 0));
        (new CatalogService())->saveProgress((int) Session::userId(), $mediaId, $position, $duration);
        putmio_json(['ok' => true]);
    }

    public function tmdbSearch(): void
    {
        Session::requireAuth();
        Session::requireAdmin();
        $q = trim((string) ($_GET['q'] ?? ''));
        $type = (string) ($_GET['type'] ?? 'multi');
        if ($q === '') {
            putmio_json(['results' => []]);
        }
        try {
            $client = new TmdbClient();
            $data = $client->search($q, $type);
            putmio_json(['results' => $data['results'] ?? []]);
        } catch (\Throwable $e) {
            putmio_json(['error' => $e->getMessage()], 400);
        }
    }

    public function tmdbApply(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);
        $mediaId = (int) ($_POST['media_id'] ?? 0);
        $tmdbId = (int) ($_POST['tmdb_id'] ?? 0);
        $tmdbType = $_POST['tmdb_type'] === 'tv' ? 'tv' : 'movie';

        $client = new TmdbClient();
        $details = $client->details($tmdbType, $tmdbId);

        $title = $details['title'] ?? $details['name'] ?? '';
        $original = $details['original_title'] ?? $details['original_name'] ?? null;
        $year = null;
        $date = $details['release_date'] ?? $details['first_air_date'] ?? '';
        if ($date) {
            $year = (int) substr($date, 0, 4);
        }
        $synopsis = $details['overview'] ?? '';
        $posterPath = $client->downloadPoster($details['poster_path'] ?? null, $mediaId);
        $posterUrl = $client->posterUrl($details['poster_path'] ?? null);

        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE `' . Config::table('media_items') . '`
             SET title = ?, original_title = ?, year = ?, synopsis = ?,
                 poster_local_path = ?, poster_url = ?, tmdb_id = ?, tmdb_type = ?,
                 classification_status = \'classified\', updated_at = NOW()
             WHERE id = ?'
        )->execute([$title, $original, $year, $synopsis, $posterPath, $posterUrl, $tmdbId, $tmdbType, $mediaId]);

        putmio_json(['ok' => true, 'title' => $title]);
    }
}
