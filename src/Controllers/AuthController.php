<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\AuthService;
use PutMio\Auth\Csrf;
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
        putmio_redirect('');
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
                (email, password_hash, display_name, role, status, theme) VALUES (?, ?, ?, \'user\', \'active\', \'dark\')'
            )->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $name]);
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
