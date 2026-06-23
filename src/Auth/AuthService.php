<?php

declare(strict_types=1);

namespace PutMio\Auth;

use PutMio\Config;
use PutMio\Database;

final class AuthService
{
    public function attempt(string $email, string $password): ?array
    {
        $email = strtolower(trim($email));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($this->isRateLimited($email, $ip)) {
            return null;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `' . Config::table('users') . '` WHERE email = ? AND status = \'active\' LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->recordAttempt($email, $ip);
            return null;
        }

        $pdo->prepare('UPDATE `' . Config::table('users') . '` SET last_login_at = NOW() WHERE id = ?')
            ->execute([(int) $user['id']]);

        return $user;
    }

    private function isRateLimited(string $email, string $ip): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM `' . Config::table('login_attempts') . '`
             WHERE email = ? AND ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $stmt->execute([$email, $ip]);
        return (int) $stmt->fetchColumn() >= 5;
    }

    private function recordAttempt(string $email, string $ip): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO `' . Config::table('login_attempts') . '` (email, ip_address) VALUES (?, ?)'
        );
        $stmt->execute([$email, $ip]);
    }

    public function findUserById(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM `' . Config::table('users') . '` WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function updateTheme(int $userId, string $theme): void
    {
        if (!in_array($theme, ['light', 'dark'], true)) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE `' . Config::table('users') . '` SET theme = ? WHERE id = ?')
            ->execute([$theme, $userId]);
    }

    public function updateLocale(int $userId, string $locale): void
    {
        if (!isset(putmio_available_locales()[$locale])) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE `' . Config::table('users') . '` SET locale = ? WHERE id = ?')
            ->execute([$locale, $userId]);
    }

    public function createPasswordReset(string $email): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM `' . Config::table('users') . '` WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $userId = $stmt->fetchColumn();
        if (!$userId) {
            return true;
        }

        $token = putmio_random_token(32);
        $hash = hash('sha256', $token);
        $pdo->prepare(
            'INSERT INTO `' . Config::table('password_resets') . '` (user_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
        )->execute([(int) $userId, $hash]);

        $resetUrl = rtrim(Config::get('app.url'), '/') . '/reset-password?token=' . urlencode($token);
        // Invio email se SMTP configurato — implementazione base log
        if (Config::get('smtp.enabled')) {
            putmio_log('Reset password richiesto per ' . $email . ' — URL: ' . $resetUrl);
        } else {
            putmio_log('Reset password (SMTP disabilitato) per ' . $email);
        }

        return true;
    }

    public function resetPassword(string $token, string $password): bool
    {
        if (strlen($password) < 10) {
            return false;
        }
        $hash = hash('sha256', $token);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `' . Config::table('password_resets') . '`
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE `' . Config::table('users') . '` SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['user_id']]);
            $pdo->prepare('UPDATE `' . Config::table('password_resets') . '` SET used_at = NOW() WHERE id = ?')
                ->execute([(int) $row['id']]);
            RememberMe::revokeAllForUser((int) $row['user_id']);
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
