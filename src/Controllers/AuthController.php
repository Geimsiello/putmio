<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\AuthService;
use PutMio\Auth\Csrf;
use PutMio\Auth\DeviceLoginService;
use PutMio\Auth\RememberMe;
use PutMio\Auth\Session;
use PutMio\Config;
use PutMio\Database;
use PutMio\View;

final class AuthController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function loginForm(): void
    {
        if (Session::userId()) {
            putmio_redirect('');
        }

        $next = trim((string) ($_GET['next'] ?? ''));
        if ($next !== '' && str_starts_with($next, 'authorize-device')) {
            $_SESSION['login_next'] = '/' . ltrim($next, '/');
        }

        View::render('auth/login', [
            'title' => putmio_lang('login'),
            'error' => $_SESSION['flash_error'] ?? null,
            'authShell' => true,
        ]);
        unset($_SESSION['flash_error']);
    }

    public function login(): void
    {
        Csrf::requireValid($_POST['_csrf'] ?? null);
        $user = $this->auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if (!$user) {
            $_SESSION['flash_error'] = 'Credenziali non valide o troppi tentativi.';
            putmio_redirect('login');
        }
        Session::login($user);
        if (!empty($_POST['remember'])) {
            RememberMe::issue((int) $user['id']);
        }
        setcookie('putmio_theme', $user['theme'] ?? 'dark', [
            'expires' => time() + 86400 * 365,
            'path' => '/',
            'secure' => true,
            'httponly' => false,
            'samesite' => 'Strict',
        ]);
        $userLocale = $user['locale'] ?? putmio_locale();
        putmio_set_locale($userLocale);
        putmio_redirect($this->consumeLoginNext());
    }

    public function authorizeDeviceForm(): void
    {
        $code = trim((string) ($_GET['code'] ?? ''));
        $forceBrowser = isset($_GET['browser']);

        if (!Session::userId()) {
            if ($code !== '' && !$forceBrowser) {
                View::render('auth/authorize-device-launch', [
                    'title' => putmio_lang('device_authorize_title'),
                    'authShell' => true,
                    'code' => $code,
                ]);
                return;
            }

            $next = '/authorize-device';
            if ($code !== '') {
                $next .= '?code=' . rawurlencode($code);
            }
            $_SESSION['login_next'] = $next;
            putmio_redirect('login');
        }

        $request = null;
        if ($code !== '') {
            $request = (new DeviceLoginService())->findPendingByCode($code);
        }

        View::render('auth/authorize-device', [
            'title' => putmio_lang('device_authorize_title'),
            'authShell' => true,
            'code' => $code,
            'request' => $request,
            'authorizeUrl' => $code !== ''
                ? rtrim(Config::get('app.url', putmio_detect_base_url()), '/')
                    . '/authorize-device?code=' . rawurlencode($code)
                : '',
            'success' => $_SESSION['flash_device_success'] ?? null,
            'error' => $_SESSION['flash_device_error'] ?? null,
        ]);
        unset($_SESSION['flash_device_success'], $_SESSION['flash_device_error']);
    }

    private function consumeLoginNext(): string
    {
        $next = (string) ($_SESSION['login_next'] ?? '');
        unset($_SESSION['login_next']);

        if ($next === '' || !str_starts_with($next, '/authorize-device')) {
            return '';
        }

        $appPath = parse_url(Config::get('app.url', putmio_detect_base_url()), PHP_URL_PATH) ?? '';
        $appPath = rtrim((string) $appPath, '/');
        if ($appPath !== '' && str_starts_with($next, $appPath)) {
            $next = substr($next, strlen($appPath)) ?: '/';
        }

        return ltrim($next, '/');
    }

    public function logout(): void
    {
        RememberMe::forget();
        Session::logout();
        putmio_redirect('login');
    }

    public function forgotForm(): void
    {
        View::render('auth/forgot', ['title' => putmio_lang('forgot_password')]);
    }

    public function forgot(): void
    {
        Csrf::requireValid($_POST['_csrf'] ?? null);
        $this->auth->createPasswordReset((string) ($_POST['email'] ?? ''));
        View::render('auth/forgot', [
            'title' => putmio_lang('forgot_password'),
            'success' => 'Se l\'email esiste, riceverai le istruzioni per il reset.',
        ]);
    }

    public function resetForm(): void
    {
        View::render('auth/reset', [
            'title' => 'Reimposta password',
            'token' => $_GET['token'] ?? '',
        ]);
    }

    public function reset(): void
    {
        Csrf::requireValid($_POST['_csrf'] ?? null);
        $ok = $this->auth->resetPassword((string) ($_POST['token'] ?? ''), (string) ($_POST['password'] ?? ''));
        if (!$ok) {
            View::render('auth/reset', [
                'title' => 'Reimposta password',
                'token' => $_POST['token'] ?? '',
                'error' => 'Link non valido o scaduto.',
            ]);
            return;
        }
        putmio_redirect('login');
    }

    public function registerForm(): void
    {
        $token = $_GET['token'] ?? '';
        if ($token === '') {
            http_response_code(404);
            exit('Invito non valido.');
        }
        View::render('auth/register', ['title' => 'Registrazione', 'token' => $token]);
    }

    public function register(): void
    {
        Csrf::requireValid($_POST['_csrf'] ?? null);
        $token = (string) ($_POST['token'] ?? '');
        $hash = hash('sha256', $token);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `' . Config::table('invites') . '`
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$hash]);
        $invite = $stmt->fetch();
        if (!$invite) {
            http_response_code(400);
            exit('Invito non valido o scaduto.');
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $name = trim((string) ($_POST['display_name'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');

        if ($email !== strtolower($invite['email']) || strlen($pass) < 10 || $name === '') {
            View::render('auth/register', [
                'title' => 'Registrazione',
                'token' => $token,
                'error' => 'Dati non validi.',
            ]);
            return;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO `' . Config::table('users') . '`
                (email, password_hash, display_name, role, status, theme, locale) VALUES (?, ?, ?, \'user\', \'active\', \'dark\', ?)'
            )->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $name, putmio_locale()]);
            $pdo->prepare('UPDATE `' . Config::table('invites') . '` SET used_at = NOW() WHERE id = ?')
                ->execute([(int) $invite['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        putmio_redirect('login');
    }
}
