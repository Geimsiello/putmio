<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\AuthService;
use PutMio\Auth\Csrf;
use PutMio\Auth\Session;
use PutMio\Catalog\CatalogSourceService;
use PutMio\CatalogService;
use PutMio\Config;
use PutMio\TMDB\Client as TmdbClient;
use PutMio\TMDB\ClassifyMatcher;
use PutMio\TMDB\LinkService;
use PutMio\Media\SeriesGrouper;
use PutMio\View;

final class ApiController
{
    public function catalogSources(): void
    {
        Session::requireAuth();
        if (Session::isAdmin()) {
            putmio_json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        Csrf::requireValid($_POST['_csrf'] ?? null);

        $enabled = array_map('strval', (array) ($_POST['sources'] ?? []));
        $userId = (int) Session::userId();
        (new CatalogSourceService())->saveForUser($userId, $enabled);

        putmio_json([
            'ok' => true,
            'message' => putmio_lang('account_content_saved'),
        ]);
    }

    public function locale(): void
    {
        Csrf::requireValid($_POST['_csrf'] ?? null);
        $locale = (string) ($_POST['locale'] ?? 'it');
        if (!isset(putmio_available_locales()[$locale])) {
            putmio_json(['ok' => false], 400);
        }

        if (Session::userId()) {
            (new AuthService())->updateLocale((int) Session::userId(), $locale);
        }

        putmio_set_locale($locale);
        putmio_json(['ok' => true, 'locale' => $locale]);
    }

    public function watchProgress(): void
    {
        Session::requireAuth();
        Csrf::requireValid($_POST['_csrf'] ?? null);
        $mediaId = (int) ($_POST['media_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        $catalog = new CatalogService();
        $userId = (int) Session::userId();
        $media = $catalog->findMedia($mediaId);
        if (!$media || !$catalog->isMediaVisibleForUser($userId, $media)) {
            putmio_json(['ok' => false], 404);
        }
        Session::release();

        if ($action === 'complete') {
            $catalog->markWatched($userId, $mediaId);
            putmio_json(['ok' => true]);
        }
        if ($action === 'reset') {
            $catalog->resetProgress($userId, $mediaId);
            putmio_json(['ok' => true]);
        }

        $position = max(0, (int) ($_POST['position_sec'] ?? 0));
        $duration = max(0, (int) ($_POST['duration_sec'] ?? 0));
        $catalog->saveProgress($userId, $mediaId, $position, $duration);
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
            $results = array_values(array_filter(
                $data['results'] ?? [],
                static fn (array $row): bool => in_array($row['media_type'] ?? '', ['movie', 'tv'], true)
            ));
            putmio_json(['results' => $results]);
        } catch (\Throwable $e) {
            putmio_json(['error' => $e->getMessage()], 400);
        }
    }

    public function tmdbDetails(): void
    {
        Session::requireAuth();
        Session::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $tmdbType = ($_GET['type'] ?? '') === 'tv' ? 'tv' : 'movie';
        if ($id <= 0) {
            putmio_json(['error' => putmio_lang('admin_invalid_id')], 400);
        }
        try {
            $client = new TmdbClient();
            $details = $client->details($tmdbType, $id, ['credits']);
            $director = null;
            foreach ($details['credits']['crew'] ?? [] as $crew) {
                if (($crew['job'] ?? '') === 'Director') {
                    $director = $crew['name'] ?? null;
                    break;
                }
            }
            if ($director === null && $tmdbType === 'tv') {
                foreach ($details['created_by'] ?? [] as $creator) {
                    $director = $creator['name'] ?? null;
                    if ($director !== null) {
                        break;
                    }
                }
            }
            $runtime = $details['runtime'] ?? null;
            if ($runtime === null && !empty($details['episode_run_time'][0])) {
                $runtime = (int) $details['episode_run_time'][0];
            }
            putmio_json([
                'id' => $details['id'] ?? $id,
                'media_type' => $tmdbType,
                'title' => $details['title'] ?? $details['name'] ?? '',
                'original_title' => $details['original_title'] ?? $details['original_name'] ?? null,
                'overview' => $details['overview'] ?? '',
                'poster_path' => $details['poster_path'] ?? null,
                'release_date' => $details['release_date'] ?? $details['first_air_date'] ?? null,
                'genres' => array_map(static fn (array $g): string => (string) ($g['name'] ?? ''), $details['genres'] ?? []),
                'vote_average' => $details['vote_average'] ?? null,
                'runtime' => $runtime,
                'number_of_seasons' => $details['number_of_seasons'] ?? null,
                'director' => $director,
            ]);
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

        try {
            $title = (new LinkService())->apply($mediaId, $tmdbId, $tmdbType);
            putmio_json(['ok' => true, 'title' => $title]);
        } catch (\Throwable $e) {
            putmio_json(['error' => $e->getMessage()], 400);
        }
    }

    public function tmdbClassifySuggest(): void
    {
        Session::requireAuth();
        Session::requireAdmin();
        $mediaId = (int) ($_GET['media_id'] ?? 0);
        if ($mediaId <= 0) {
            putmio_json(['error' => putmio_lang('admin_invalid_id')], 400);
        }

        try {
            $matcher = new ClassifyMatcher();
            if (!$matcher->isConfigured()) {
                putmio_json(['error' => putmio_lang('classify_tmdb_not_configured')], 400);
            }
            $suggestion = $matcher->suggestForMediaId($mediaId);
            if ($suggestion === null) {
                putmio_json(['error' => putmio_lang('classify_tmdb_item_missing')], 404);
            }
            putmio_json(['suggestion' => $suggestion]);
        } catch (\Throwable $e) {
            putmio_json(['error' => $e->getMessage()], 400);
        }
    }

    public function tmdbClassifyApplyBulk(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        $raw = $_POST['items'] ?? '[]';
        $items = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($items) || $items === []) {
            putmio_json(['error' => putmio_lang('classify_tmdb_nothing_selected')], 400);
        }

        $service = new LinkService();
        $applied = [];
        $errors = [];

        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mediaId = (int) ($row['media_id'] ?? 0);
            $tmdbId = (int) ($row['tmdb_id'] ?? 0);
            $tmdbType = ($row['tmdb_type'] ?? '') === 'tv' ? 'tv' : 'movie';
            if ($mediaId <= 0 || $tmdbId <= 0) {
                continue;
            }
            try {
                $title = $service->apply($mediaId, $tmdbId, $tmdbType);
                $applied[] = ['media_id' => $mediaId, 'title' => $title];
            } catch (\Throwable $e) {
                $errors[] = ['media_id' => $mediaId, 'error' => $e->getMessage()];
            }
        }

        if ($applied === []) {
            putmio_json([
                'ok' => false,
                'error' => putmio_lang('classify_tmdb_apply_failed'),
                'errors' => $errors,
            ], 400);
        }

        putmio_json([
            'ok' => true,
            'applied' => $applied,
            'count' => count($applied),
            'errors' => $errors,
            'message' => putmio_lang('classify_tmdb_applied', ['count' => (string) count($applied)]),
        ]);
    }

    public function mergeDuplicateSeries(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        try {
            $result = (new SeriesGrouper())->repairDuplicateSeries();
            $merged = (int) ($result['merged'] ?? 0);
            $containers = (int) ($result['containers'] ?? 0);
            $message = $merged > 0
                ? putmio_lang('series_merge_done', [
                    'merged' => (string) $merged,
                    'containers' => (string) $containers,
                ])
                : putmio_lang('series_merge_none', ['containers' => (string) $containers]);

            putmio_json([
                'ok' => true,
                'merged' => $merged,
                'containers' => $containers,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            putmio_json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function putioSyncFriends(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        if (!(new \PutMio\PutIO\Client())->isConnected()) {
            putmio_json(['ok' => false, 'error' => putmio_lang('admin_putio_not_connected')], 400);
        }

        $enabledFriends = array_map('intval', (array) ($_POST['sync_friends'] ?? []));
        (new \PutMio\PutIO\FriendService())->saveSyncSelection($enabledFriends);

        putmio_json([
            'ok' => true,
            'enabled' => $enabledFriends,
            'message' => putmio_lang('putio_friends_saved'),
        ]);
    }

    public function putioSync(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        try {
            $result = (new \PutMio\PutIO\SyncService(null, null, 'admin', (int) Session::userId()))->sync();
            $message = putmio_lang('putio_sync_toast', [
                'imported' => (string) ($result['imported'] ?? 0),
                'removed' => (string) ($result['removed'] ?? 0),
            ]);
            putmio_json([
                'ok' => true,
                'imported' => (int) ($result['imported'] ?? 0),
                'removed' => (int) ($result['removed'] ?? 0),
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            putmio_json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function catalogItems(): void
    {
        Session::requireAuth();
        $filters = [
            'type' => trim((string) ($_GET['type'] ?? '')) ?: null,
            'genre' => trim((string) ($_GET['genre'] ?? '')) ?: null,
            'shared_by' => trim((string) ($_GET['shared_by'] ?? '')) ?: null,
            'q' => trim((string) ($_GET['q'] ?? '')) ?: null,
        ];
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        $catalog = new CatalogService();
        $perPage = $catalog->perPage();
        $activeFilters = array_filter($filters, static fn ($v) => $v !== null && $v !== '');
        $activeFilters['sort'] = 'title';
        $items = $catalog->listMedia($activeFilters, $perPage, $offset);
        $total = $catalog->countMedia($activeFilters);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $catalogReturnPath = putmio_sanitize_catalog_return($_GET['from'] ?? null)
            ?? putmio_catalog_path($filters, $page);
        $appUrl = rtrim(Config::get('app.url'), '/');
        $html = View::capture('catalog/_grid-items', [
            'items' => $items,
            'catalog' => $catalog,
            'appUrl' => $appUrl,
            'catalogReturnPath' => $catalogReturnPath,
        ]);
        $loaded = $offset + count($items);
        putmio_json([
            'html' => $html,
            'hasMore' => $loaded < $total,
            'nextOffset' => $loaded,
        ]);
    }
}
