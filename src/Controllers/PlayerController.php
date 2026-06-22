<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\Session;
use PutMio\CatalogService;
use PutMio\Config;
use PutMio\Database;
use PutMio\Stream\StreamProxy;
use PutMio\View;

final class PlayerController
{
    public function show(): void
    {
        Session::requireAuth();
        $id = (int) ($_GET['id'] ?? 0);
        $catalog = new CatalogService();
        $media = $catalog->findMedia($id);
        if (!$media) {
            http_response_code(404);
            exit('Media non trovato');
        }

        $userId = (int) Session::userId();
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `' . Config::table('watch_progress') . '` WHERE user_id = ? AND media_id = ?'
        );
        $stmt->execute([$userId, $id]);
        $progress = $stmt->fetch() ?: null;

        View::render('player/show', [
            'title' => $media['title'],
            'media' => $media,
            'progress' => $progress,
        ], 'player/layout');
    }

    public function stream(): void
    {
        Session::requireAuth();
        $putioId = (int) ($_GET['id'] ?? 0);
        if ($putioId <= 0) {
            http_response_code(400);
            exit('ID non valido');
        }
        (new StreamProxy())->stream($putioId, (int) Session::userId());
    }
}
