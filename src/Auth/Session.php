<?php

declare(strict_types=1);

namespace PutMio\Auth;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        self::ensureSessionSavePath();

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => self::cookiePath(),
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('putmio_session');

        if (@session_start()) {
            if (!self::userId()) {
                $user = RememberMe::attempt() ?? TrustedDevice::attempt();
                if ($user) {
                    self::login($user);
                }
            }
            return;
        }

        $error = error_get_last();
        $message = is_array($error) ? ($error['message'] ?? 'session_start fallito') : 'session_start fallito';
        putmio_log('Sessione PHP: ' . $message);

        throw new \RuntimeException(
            'Impossibile avviare la sessione PHP. Verifica che storage/sessions sia scrivibile.'
        );
    }

    private static function ensureSessionSavePath(): void
    {
        $path = putmio_base_path() . '/storage/sessions';
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        if (is_dir($path) && is_writable($path)) {
            session_save_path($path);
        }
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['display_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_locale'] = $user['locale'] ?? $_COOKIE['putmio_locale'] ?? 'it';
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    private static function cookiePath(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '/putmio/index.php';
        $dir = str_replace('\\', '/', dirname($script));
        if ($dir === '/' || $dir === '.' || $dir === '') {
            return '/';
        }

        return rtrim($dir, '/') . '/';
    }

    public static function requireAuth(): void
    {
        if (!self::userId()) {
            putmio_redirect('login');
        }
    }

    public static function requireTvAuth(): void
    {
        if (!self::userId()) {
            putmio_redirect('tv/login');
        }
    }

    public static function release(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();
        if (!self::isAdmin()) {
            http_response_code(403);
            exit('Accesso negato.');
        }
    }
}
