<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\Csrf;
use PutMio\Auth\Session;
use PutMio\Auth\TrustedDevice;
use PutMio\Catalog\CatalogSourceService;
use PutMio\CatalogService;
use PutMio\Config;
use PutMio\View;

final class AccountController
{
    public function settings(): void
    {
        Session::requireAuth();
        if (Session::isAdmin()) {
            putmio_redirect('/admin');
        }

        View::render('account/settings', [
            'title' => putmio_lang('account_settings'),
        ]);
    }

    public function devices(): void
    {
        Session::requireAuth();
        if (Session::isAdmin()) {
            putmio_redirect('/admin/dispositivi');
        }

        $userId = (int) Session::userId();
        $ctx = putmio_user_devices_context($userId);
        $appUrl = rtrim(Config::get('app.url'), '/');

        View::render('account/devices', [
            'title' => putmio_lang('account_devices'),
            'devices' => $ctx['devices'],
            'currentDeviceId' => $ctx['currentDeviceId'],
            'revokeAction' => $appUrl . '/account/dispositivi/revoca',
            'success' => $_SESSION['flash_success'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    }

    public function revokeDevice(): void
    {
        Session::requireAuth();
        if (Session::isAdmin()) {
            putmio_redirect('/admin/dispositivi');
        }

        Csrf::requireValid($_POST['_csrf'] ?? null);

        $userId = (int) Session::userId();
        $deviceId = (int) ($_POST['device_id'] ?? 0);
        if ($deviceId <= 0) {
            $_SESSION['flash_error'] = putmio_lang('account_device_revoke_error');
            putmio_redirect('/account/dispositivi');
        }

        $ctx = putmio_user_devices_context($userId);
        $isCurrent = $ctx['currentDeviceId'] !== null && $ctx['currentDeviceId'] === $deviceId;

        if (TrustedDevice::revokeById($userId, $deviceId)) {
            if ($isCurrent) {
                TrustedDevice::forget();
            }
            $_SESSION['flash_success'] = putmio_lang('account_device_revoked');
        } else {
            $_SESSION['flash_error'] = putmio_lang('account_device_revoke_error');
        }

        putmio_redirect('/account/dispositivi');
    }

    public function content(): void
    {
        Session::requireAuth();
        if (Session::isAdmin()) {
            putmio_redirect('/admin');
        }

        $catalog = new CatalogService();
        $sourceService = new CatalogSourceService();
        $sharers = $catalog->listSharedByUsernames();
        $options = $sourceService->buildSourceOptions($sharers);
        $hidden = $sourceService->hiddenKeysForUser((int) Session::userId());

        View::render('account/content', [
            'title' => putmio_lang('account_content'),
            'options' => $options,
            'hidden' => $hidden,
            'extraScripts' => '<script src="' . htmlspecialchars(
                putmio_asset('public/assets/account-content.js'),
                ENT_QUOTES,
                'UTF-8'
            ) . '" defer></script>',
            'putmioExtra' => [
                'accountContent' => [
                    'toastSaving' => putmio_lang('account_content_saving'),
                    'toastSaved' => putmio_lang('account_content_saved'),
                    'toastSaveError' => putmio_lang('account_content_save_error'),
                ],
            ],
        ]);
    }
}
