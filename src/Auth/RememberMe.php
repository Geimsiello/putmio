<?php

declare(strict_types=1);

namespace PutMio\Auth;

use PutMio\Config;
use PutMio\Database;

final class RememberMe
{
    private const COOKIE_NAME = 'putmio_remember';
    private const LIFETIME_DAYS = 30;

    public static function issue(int $userId): void
    {
        $selector = putmio_random_token(16);
        $validator = putmio_random_token(32);

        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO `' . Config::table('remember_tokens') . '`
             (user_id, selector, token_hash, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . self::LIFETIME_DAYS . ' DAY))'
        )->execute([$userId, $selector, hash('sha256', $validator)]);

        self::setCookie($selector . ':' . $validator, time() + self::LIFETIME_DAYS * 86400);
    }

    public static function attempt(): ?array
    {
        $raw = (string) ($_COOKIE[self::COOKIE_NAME] ?? '');
        if (!preg_match('/^([a-f0-9]{32}):([a-f0-9]{64})$/', $raw, $matches)) {
            return null;
        }

        $selector = $matches[1];
        $validator = $matches[2];

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT rt.*, u.id, u.email, u.display_name, u.role, u.theme, u.locale, u.status
             FROM `' . Config::table('remember_tokens') . '` rt
             INNER JOIN `' . Config::table('users') . '` u ON u.id = rt.user_id
             WHERE rt.selector = ? AND rt.expires_at > NOW() AND u.status = \'active\'
             LIMIT 1'
        );
        $stmt->execute([$selector]);
        $row = $stmt->fetch();

        if (!$row || !hash_equals((string) $row['token_hash'], hash('sha256', $validator))) {
            self::clearCookie();
            return null;
        }

        $pdo->prepare('DELETE FROM `' . Config::table('remember_tokens') . '` WHERE id = ?')
            ->execute([(int) $row['id']]);

        $userId = (int) $row['user_id'];
        self::issue($userId);

        return [
            'id' => $userId,
            'email' => $row['email'],
            'display_name' => $row['display_name'],
            'role' => $row['role'],
            'theme' => $row['theme'] ?? 'dark',
            'locale' => $row['locale'] ?? 'it',
        ];
    }

    public static function forget(): void
    {
        $raw = (string) ($_COOKIE[self::COOKIE_NAME] ?? '');
        if (preg_match('/^([a-f0-9]{32}):/', $raw, $matches)) {
            $pdo = Database::pdo();
            $pdo->prepare('DELETE FROM `' . Config::table('remember_tokens') . '` WHERE selector = ?')
                ->execute([$matches[1]]);
        }

        self::clearCookie();
    }

    public static function revokeAllForUser(int $userId): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM `' . Config::table('remember_tokens') . '` WHERE user_id = ?')
            ->execute([$userId]);
    }

    private static function setCookie(string $value, int $expires): void
    {
        $params = self::cookieParams($expires);
        setcookie(self::COOKIE_NAME, $value, $params);
    }

    private static function clearCookie(): void
    {
        $params = self::cookieParams(time() - 3600);
        setcookie(self::COOKIE_NAME, '', $params);
    }

    /** @return array{expires: int, path: string, secure: bool, httponly: bool, samesite: string} */
    private static function cookieParams(int $expires): array
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        return [
            'expires' => $expires,
            'path' => self::cookiePath(),
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
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
}
