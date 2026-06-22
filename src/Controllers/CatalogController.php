<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\Session;
use PutMio\CatalogService;
use PutMio\Config;
use PutMio\View;

final class CatalogController
{
    private CatalogService $catalog;

    public function __construct()
    {
        $this->catalog = new CatalogService();
    }

    public function index(): void
    {
        Session::requireAuth();
        $this->catalog->backfillLinkedMediaTypes();
        $filters = [
            'type' => trim((string) ($_GET['type'] ?? '')) ?: null,
            'genre' => trim((string) ($_GET['genre'] ?? '')) ?: null,
            'shared_by' => trim((string) ($_GET['shared_by'] ?? '')) ?: null,
            'q' => trim((string) ($_GET['q'] ?? '')) ?: null,
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $this->catalog->perPage();
        $limit = $page * $perPage;
        $activeFilters = array_filter($filters, static fn ($v) => $v !== null && $v !== '');
        $activeFilters['sort'] = 'title';
        $items = $this->catalog->listMedia($activeFilters, $limit, 0);
        $total = $this->catalog->countMedia($activeFilters);

        View::render('catalog/index', [
            'title' => putmio_lang('catalog'),
            'items' => $items,
            'filters' => $filters,
            'genres' => $this->catalog->listGenres(),
            'sharers' => $this->catalog->listSharedByUsernames(),
            'page' => $page,
            'total' => $total,
            'hasMore' => $total > count($items),
            'catalog' => $this->catalog,
            'extraScripts' => '<script src="' . htmlspecialchars(
                rtrim(Config::get('app.url'), '/') . '/public/assets/catalog.js',
                ENT_QUOTES,
                'UTF-8'
            ) . '" defer></script>',
        ]);
    }

    public function show(): void
    {
        Session::requireAuth();
        $id = (int) ($_GET['id'] ?? 0);
        $media = $this->catalog->findMedia($id);
        if (!$media) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Non trovato']);
            return;
        }

        if (!empty($media['series_id'])) {
            $from = isset($_GET['from']) ? '&from=' . rawurlencode((string) $_GET['from']) : '';
            putmio_redirect('media?id=' . (int) $media['series_id'] . $from);
        }

        $userId = (int) Session::userId();
        $progress = $this->getProgress($userId, $id);
        $isSeries = $this->catalog->isSeries($media);
        $episodesBySeason = $isSeries ? $this->catalog->episodesBySeason($id) : [];
        $episodeProgress = $isSeries ? $this->catalog->episodeProgressForUser($userId, $id) : [];

        $fileName = (string) ($media['file_name'] ?? '');
        $tmdbSuggestedQuery = putmio_guess_title_from_filename($fileName) ?? $media['title'];
        $isLinked = putmio_media_is_linked($media);
        $genres = $isLinked ? $this->catalog->mediaGenreNames($id) : [];

        View::render('catalog/show', [
            'title' => $media['title'],
            'media' => $media,
            'progress' => $progress,
            'catalog' => $this->catalog,
            'tmdbSuggestedQuery' => $tmdbSuggestedQuery,
            'isLinked' => $isLinked,
            'genres' => $genres,
            'isSeries' => $isSeries,
            'episodesBySeason' => $episodesBySeason,
            'episodeProgress' => $episodeProgress,
            'catalogReturnUrl' => putmio_catalog_return_url($_GET['from'] ?? null),
        ]);
    }

    public function inProgress(): void
    {
        Session::requireAuth();
        $items = $this->catalog->inProgressForUser((int) Session::userId());

        View::render('catalog/in-progress', [
            'title' => putmio_lang('in_progress'),
            'items' => $items,
            'catalog' => $this->catalog,
        ]);
    }

    public function poster(): void
    {
        $file = basename((string) ($_GET['f'] ?? ''));
        if ($file === '' || preg_match('/[^a-zA-Z0-9._-]/', $file)) {
            http_response_code(404);
            exit;
        }
        $path = putmio_base_path() . '/storage/posters/' . $file;
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }
        $mime = mime_content_type($path) ?: 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=604800');
        readfile($path);
        exit;
    }

    private function getProgress(int $userId, int $mediaId): ?array
    {
        $pdo = \PutMio\Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `' . \PutMio\Config::table('watch_progress') . '` WHERE user_id = ? AND media_id = ?'
        );
        $stmt->execute([$userId, $mediaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
