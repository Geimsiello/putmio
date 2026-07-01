<?php

declare(strict_types=1);

namespace PutMio\Controllers\Tv;

use PutMio\Auth\RememberMe;
use PutMio\Auth\Session;
use PutMio\Auth\TrustedDevice;

final class AuthController extends TvController
{
    public function loginForm(): void
    {
        if (Session::userId()) {
            putmio_redirect('tv/');
        }

        $_SESSION['device_login_return'] = '/tv/';

        $this->render('login', [
            'title' => putmio_lang('login'),
            'guest' => true,
            'putmioExtra' => [
                'deviceLogin' => [
                    'rateLimited' => putmio_lang('device_login_rate_limited'),
                    'expired' => putmio_lang('device_login_expired'),
                    'denied' => putmio_lang('device_login_denied'),
                    'error' => putmio_lang('device_login_error'),
                    'completing' => putmio_lang('device_login_completing'),
                ],
            ],
            'extraScripts' => '<script src="' . htmlspecialchars(putmio_tv_asset('vendor/qrcode.min.js'), ENT_QUOTES, 'UTF-8') . '" defer></script>'
                . '<script src="' . htmlspecialchars(putmio_tv_asset('tv-login.js'), ENT_QUOTES, 'UTF-8') . '" defer></script>',
        ], false);
    }

    public function logout(): void
    {
        RememberMe::forget();
        TrustedDevice::forget();
        Session::logout();
        putmio_redirect('tv/login');
    }
}
