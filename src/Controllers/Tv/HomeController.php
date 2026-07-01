<?php

declare(strict_types=1);

namespace PutMio\Controllers\Tv;

use PutMio\CatalogService;

final class HomeController extends TvController
{
    public function index(): void
    {
        $this->requireAuth();

        $catalog = new CatalogService();
        $userId = (int) \PutMio\Auth\Session::userId();
        $previewItems = array_slice($catalog->inProgressForUser($userId), 0, 8);

        $this->render('home', [
            'title' => putmio_lang('home'),
            'previewItems' => $previewItems,
            'catalog' => $catalog,
        ]);
    }
}
