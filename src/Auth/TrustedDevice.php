<?php

declare(strict_types=1);

namespace PutMio\Auth;

use PutMio\Config;
use PutMio\Database;

final class TrustedDevice
{
    private const COOKIE_NAME = 'putmio_device';
    private const LIFETIME_DAYS = 30;

    public static function issue(int $userId, ?string $userAgent, ?string $clientIp, ?string $label = null): void
    {
        $selector = putmio_random_token(16);
        $validator = putmio_random_token(32);
        $deviceLabel = $label ?? DeviceLoginService::deviceLabel($userAgent);

        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO `' . Config::table('user_devices') . '`
             (user_id, selector, token_hash, label, user_agent, client_ip, expires_at, last_used_at)
             VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . self::LIFETIME_DAYS . ' DAY), NOW())'
        )->execute([
            $userId,
            $selector,
            hash('sha256', $validator),
            mb_substr($deviceLabel, 0, 64),
            $userAgent !== null ? mb_substr($userAgent, 0, 512) : null,
            $clientIp !== null ? mb_substr($clientIp, 0, 45) : null,
        ]);

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
            'SELECT ud.*, u.id, u.email, u.display_name, u.role, u.theme, u.locale, u.status
             FROM `' . Config::table('user_devices') . '` ud
             INNER JOIN `' . Config::table('users') . '` u ON u.id = ud.user_id
             WHERE ud.selector = ? AND ud.expires_at > NOW() AND u.status = \'active\'
             LIMIT 1'
        );
        $stmt->execute([$selector]);
        $row = $stmt->fetch();

        if (!$row || !hash_equals((string) $row['token_hash'], hash('sha256', $validator))) {
            self::clearCookie();
            return null;
        }

        $userId = (int) $row['user_id'];
        $label = (string) $row['label'];
        $userAgent = isset($row['user_agent']) ? (string) $row['user_agent'] : null;
        $clientIp = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : (
            isset($row['client_ip']) ? (string) $row['client_ip'] : null
        );

        $pdo->prepare('DELETE FROM `' . Config::table('user_devices') . '` WHERE id = ?')
            ->execute([(int) $row['id']]);

        $pdo->prepare('UPDATE `' . Config::table('users') . '` SET last_login_at = NOW() WHERE id = ?')
            ->execute([$userId]);

        self::issue($userId, $userAgent, $clientIp, $label);

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
            $pdo->prepare('DELETE FROM `' . Config::table('user_devices') . '` WHERE selector = ?')
                ->execute([$matches[1]]);
        }

        self::clearCookie();
    }

    public static function revokeAllForUser(int $userId): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM `' . Config::table('user_devices') . '` WHERE user_id = ?')
            ->execute([$userId]);
    }

    /** @return list<array<string, mixed>> */
    public static function listForUser(int $userId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, label, user_agent, client_ip, expires_at, last_used_at, created_at
             FROM `' . Config::table('user_devices') . '`
             WHERE user_id = ? AND expires_at > NOW()
             ORDER BY COALESCE(last_used_at, created_at) DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll() ?: [];
    }

    public static function revokeById(int $userId, int $deviceId): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'DELETE FROM `' . Config::table('user_devices') . '` WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$deviceId, $userId]);

        return $stmt->rowCount() > 0;
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
