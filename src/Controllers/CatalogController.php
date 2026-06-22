<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\Session;
use PutMio\CatalogService;
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
        $filters = [
            'type' => $_GET['type'] ?? null,
            'q' => $_GET['q'] ?? null,
        ];
        $items = $this->catalog->listMedia(array_filter($filters));

        View::render('catalog/index', [
            'title' => 'Catalogo',
            'items' => $items,
            'filters' => $filters,
            'catalog' => $this->catalog,
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

        $userId = (int) Session::userId();
        $progress = $this->getProgress($userId, $id);

        View::render('catalog/show', [
            'title' => $media['title'],
            'media' => $media,
            'progress' => $progress,
            'catalog' => $this->catalog,
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
