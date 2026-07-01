<?php

declare(strict_types=1);

namespace PutMio\Controllers\Tv;

use PutMio\Auth\Session;
use PutMio\View;

abstract class TvController
{
    protected function requireAuth(): void
    {
        Session::requireTvAuth();
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = [], bool $authenticated = true): void
    {
        putmio_tv_security_headers($authenticated);
        View::render('tv/' . $template, $data, 'tv/layout');
    }
}
