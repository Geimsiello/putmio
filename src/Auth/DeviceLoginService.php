<?php

declare(strict_types=1);

namespace PutMio\Auth;

use PutMio\Config;
use PutMio\Database;

final class DeviceLoginService
{
    private const CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 8;
    private const EXPIRY_MINUTES = 10;
    private const MAX_START_PER_IP = 5;

    public function startRequest(string $clientIp, ?string $userAgent): ?array
    {
        if ($this->isRateLimited($clientIp)) {
            return null;
        }

        $code = $this->generateCode();
        $codeHash = hash('sha256', $this->normalizeCode($code));
        $deviceToken = putmio_random_token(32);
        $expiresAt = date('Y-m-d H:i:s', time() + self::EXPIRY_MINUTES * 60);

        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO `' . Config::table('device_login_requests') . '`
            (code_hash, device_token, status, client_ip, user_agent, expires_at)
            VALUES (?, ?, \'pending\', ?, ?, ?)'
        )->execute([
            $codeHash,
            $deviceToken,
            $clientIp,
            $userAgent !== null ? mb_substr($userAgent, 0, 512) : null,
            $expiresAt,
        ]);

        $formattedCode = $this->formatCode($code);
        $appUrl = rtrim(Config::get('app.url', putmio_detect_base_url()), '/');

        return [
            'code' => $formattedCode,
            'device_token' => $deviceToken,
            'expires_at' => $expiresAt,
            'authorize_url' => $appUrl . '/authorize-device?code=' . rawurlencode($formattedCode),
        ];
    }

    public function findPendingByCode(string $code): ?array
    {
        $hash = hash('sha256', $this->normalizeCode($code));
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `' . Config::table('device_login_requests') . '`
             WHERE code_hash = ? AND status = \'pending\' AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function approve(string $code, int $userId): bool
    {
        $request = $this->findPendingByCode($code);
        if (!$request) {
            return false;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE `' . Config::table('device_login_requests') . '`
             SET status = \'approved\', user_id = ?, approved_by = ?, approved_at = NOW()
             WHERE id = ? AND status = \'pending\' AND expires_at > NOW()'
        );

        return $stmt->execute([(int) $userId, $userId, (int) $request['id']]) && $stmt->rowCount() > 0;
    }

    public function deny(string $code): bool
    {
        $hash = hash('sha256', $this->normalizeCode($code));
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE `' . Config::table('device_login_requests') . '`
             SET status = \'denied\'
             WHERE code_hash = ? AND status = \'pending\' AND expires_at > NOW()'
        );
        $stmt->execute([$hash]);

        return $stmt->rowCount() > 0;
    }

    public function pollStatus(string $deviceToken): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT status, expires_at FROM `' . Config::table('device_login_requests') . '`
             WHERE device_token = ? LIMIT 1'
        );
        $stmt->execute([$deviceToken]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['status' => 'not_found'];
        }

        if ($row['status'] === 'pending' && strtotime((string) $row['expires_at']) < time()) {
            $this->markExpired($deviceToken);

            return ['status' => 'expired'];
        }

        return ['status' => (string) $row['status']];
    }

    public function complete(string $deviceToken): ?array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $table = Config::table('device_login_requests');
            $usersTable = Config::table('users');
            $stmt = $pdo->prepare(
                'SELECT dlr.id, dlr.user_id, u.email, u.display_name, u.role, u.theme, u.locale
                 FROM `' . $table . '` dlr
                 INNER JOIN `' . $usersTable . '` u ON u.id = dlr.user_id
                 WHERE dlr.device_token = ? AND dlr.status = \'approved\'
                   AND dlr.expires_at > NOW() AND u.status = \'active\'
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([$deviceToken]);
            $row = $stmt->fetch();

            if (!$row) {
                $pdo->rollBack();

                return null;
            }

            $pdo->prepare(
                'UPDATE `' . $table . '` SET status = \'consumed\' WHERE id = ? AND status = \'approved\''
            )->execute([(int) $row['id']]);

            $pdo->prepare('UPDATE `' . $usersTable . '` SET last_login_at = NOW() WHERE id = ?')
                ->execute([(int) $row['user_id']]);

            $pdo->commit();

            return [
                'id' => (int) $row['user_id'],
                'email' => $row['email'],
                'display_name' => $row['display_name'],
                'role' => $row['role'],
                'theme' => $row['theme'] ?? 'dark',
                'locale' => $row['locale'] ?? 'it',
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function deviceLabel(?string $userAgent): string
    {
        if ($userAgent === null || $userAgent === '') {
            return putmio_lang('device_unknown');
        }

        $ua = $userAgent;
        if (stripos($ua, 'Tizen') !== false || stripos($ua, 'SmartTV') !== false || stripos($ua, 'Smart-TV') !== false) {
            return putmio_lang('device_smart_tv');
        }
        if (stripos($ua, 'Android TV') !== false || stripos($ua, 'GoogleTV') !== false) {
            return putmio_lang('device_android_tv');
        }
        if (stripos($ua, 'Apple TV') !== false || stripos($ua, 'tvOS') !== false) {
            return putmio_lang('device_apple_tv');
        }
        if (stripos($ua, 'Xbox') !== false || stripos($ua, 'PlayStation') !== false) {
            return putmio_lang('device_console');
        }
        if (stripos($ua, 'Mobile') !== false || stripos($ua, 'iPhone') !== false || stripos($ua, 'Android') !== false) {
            return putmio_lang('device_mobile');
        }

        return putmio_lang('device_browser');
    }

    private function markExpired(string $deviceToken): void
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE `' . Config::table('device_login_requests') . '`
             SET status = \'expired\'
             WHERE device_token = ? AND status = \'pending\''
        )->execute([$deviceToken]);
    }

    private function isRateLimited(string $clientIp): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM `' . Config::table('device_login_requests') . '`
             WHERE client_ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $stmt->execute([$clientIp]);

        return (int) $stmt->fetchColumn() >= self::MAX_START_PER_IP;
    }

    private function generateCode(): string
    {
        $chars = self::CODE_CHARS;
        $len = strlen($chars);
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, $len - 1)];
        }

        return $code;
    }

    private function formatCode(string $code): string
    {
        return substr($code, 0, 4) . '-' . substr($code, 4, 4);
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(str_replace(['-', ' ', "\t"], '', $code));
    }
}
