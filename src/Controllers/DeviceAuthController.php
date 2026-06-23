<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\Csrf;
use PutMio\Auth\DeviceLoginService;
use PutMio\Auth\Session;
use PutMio\Config;

final class DeviceAuthController
{
    private DeviceLoginService $deviceLogin;

    public function __construct()
    {
        $this->deviceLogin = new DeviceLoginService();
    }

    public function start(): void
    {
        if (Session::userId()) {
            putmio_json(['ok' => false, 'error' => 'already_logged_in'], 400);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null;
        $result = $this->deviceLogin->startRequest($ip, $ua);

        if (!$result) {
            putmio_json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        putmio_json([
            'ok' => true,
            'code' => $result['code'],
            'device_token' => $result['device_token'],
            'expires_at' => $result['expires_at'],
            'authorize_url' => $result['authorize_url'],
        ]);
    }

    public function status(): void
    {
        $deviceToken = trim((string) ($_GET['device_token'] ?? ''));
        if ($deviceToken === '' || !preg_match('/^[a-f0-9]{64}$/', $deviceToken)) {
            putmio_json(['ok' => false, 'error' => 'invalid_token'], 400);
        }

        $status = $this->deviceLogin->pollStatus($deviceToken);
        putmio_json(['ok' => true, 'status' => $status['status']]);
    }

    public function complete(): void
    {
        $deviceToken = trim((string) ($_POST['device_token'] ?? ''));
        if ($deviceToken === '' || !preg_match('/^[a-f0-9]{64}$/', $deviceToken)) {
            putmio_json(['ok' => false, 'error' => 'invalid_token'], 400);
        }

        $user = $this->deviceLogin->complete($deviceToken);
        if (!$user) {
            putmio_json(['ok' => false, 'error' => 'not_ready'], 400);
        }

        Session::login($user);
        $userLocale = $user['locale'] ?? putmio_locale();
        putmio_set_locale($userLocale);

        putmio_json(['ok' => true, 'redirect' => rtrim(Config::get('app.url', putmio_detect_base_url()), '/') . '/']);
    }

    public function approve(): void
    {
        Session::requireAuth();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        $code = trim((string) ($_POST['code'] ?? ''));
        if ($code === '') {
            putmio_json(['ok' => false, 'error' => 'invalid_code'], 400);
        }

        $ok = $this->deviceLogin->approve($code, (int) Session::userId());
        if (!$ok) {
            putmio_json(['ok' => false, 'error' => 'invalid_code'], 400);
        }

        putmio_json(['ok' => true]);
    }

    public function deny(): void
    {
        Session::requireAuth();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        $code = trim((string) ($_POST['code'] ?? ''));
        if ($code === '') {
            putmio_json(['ok' => false, 'error' => 'invalid_code'], 400);
        }

        $ok = $this->deviceLogin->deny($code);
        putmio_json(['ok' => $ok]);
    }
}
