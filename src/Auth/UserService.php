<?php

declare(strict_types=1);

namespace PutMio\Auth;

use PutMio\Config;
use PutMio\Database;

final class UserService
{
    /** @return null|'self'|'not_found'|'last_admin' */
    public function delete(int $userId, int $performedByAdminId): ?string
    {
        if ($userId === $performedByAdminId) {
            return 'self';
        }

        $auth = new AuthService();
        $user = $auth->findUserById($userId);
        if (!$user) {
            return 'not_found';
        }

        $pdo = Database::pdo();

        if ((string) ($user['role'] ?? '') === 'admin') {
            $adminCount = (int) $pdo->query(
                'SELECT COUNT(*) FROM `' . Config::table('users') . '` WHERE role = \'admin\''
            )->fetchColumn();
            if ($adminCount <= 1) {
                return 'last_admin';
            }
        }

        $pdo->beginTransaction();

        try {
            $this->purgeUserData($pdo, $userId);
            $pdo->prepare('DELETE FROM `' . Config::table('users') . '` WHERE id = ?')
                ->execute([$userId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return null;
    }

    private function purgeUserData(\PDO $pdo, int $userId): void
    {
        $tablesByUserId = [
            'password_resets',
            'watch_progress',
            'user_subtitle_prefs',
            'user_catalog_hidden_sources',
            'user_watchlist',
            'stream_sessions',
        ];

        foreach ($tablesByUserId as $table) {
            $pdo->prepare('DELETE FROM `' . Config::table($table) . '` WHERE user_id = ?')
                ->execute([$userId]);
        }

        RememberMe::revokeAllForUser($userId);
        TrustedDevice::revokeAllForUser($userId);

        $pdo->prepare(
            'UPDATE `' . Config::table('device_login_requests') . '` SET approved_by = NULL WHERE approved_by = ?'
        )->execute([$userId]);
        $pdo->prepare(
            'DELETE FROM `' . Config::table('device_login_requests') . '` WHERE user_id = ?'
        )->execute([$userId]);

        $pdo->prepare(
            'UPDATE `' . Config::table('media_subtitles') . '` SET downloaded_by = NULL WHERE downloaded_by = ?'
        )->execute([$userId]);
    }
}
