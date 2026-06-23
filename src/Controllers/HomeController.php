<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\Csrf;
use PutMio\Auth\Session;
use PutMio\CatalogService;
use PutMio\PutIO\Client;
use PutMio\View;

final class HomeController
{
    public function index(): void
    {
        Session::requireAuth();
        $catalog = new CatalogService();
        $catalog->backfillLinkedMediaTypes();
        $userId = (int) Session::userId();
        $inProgress = $catalog->inProgressForUser($userId);
        $recent = $catalog->listMedia(['classified' => true], 24);
        $putio = new Client();

        View::render('home', [
            'title' => putmio_lang('home'),
            'inProgress' => array_slice($inProgress, 0, 10),
            'recent' => $recent,
            'genreRows' => putmio_tv_mode()
                ? array_slice($catalog->homeGenresWithMedia(), 0, 2)
                : $catalog->homeGenresWithMedia(),
            'putioConnected' => $putio->isConnected(),
            'catalog' => $catalog,
            'showSearchFab' => true,
        ]);
    }
}
