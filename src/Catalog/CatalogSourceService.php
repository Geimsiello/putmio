<?php

declare(strict_types=1);

namespace PutMio\Catalog;

use PutMio\Auth\Session;
use PutMio\Config;
use PutMio\Database;

final class CatalogSourceService
{
    public const OWN_KEY = '__own__';

    /** @return list<string> */
    public function hiddenKeysForUser(int $userId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT source_key FROM `' . Config::table('user_catalog_hidden_sources') . '`
             WHERE user_id = ? ORDER BY source_key ASC'
        );
        $stmt->execute([$userId]);

        return array_map(
            static fn (array $row): string => (string) $row['source_key'],
            $stmt->fetchAll() ?: []
        );
    }

    public function isSourceEnabledForUser(int $userId, ?string $sharedByUsername): bool
    {
        if (Session::isAdmin()) {
            return true;
        }

        $key = self::sourceKeyFromUsername($sharedByUsername);
        return !in_array($key, $this->hiddenKeysForUser($userId), true);
    }

    /** @param list<string> $availableKeys */
    /** @param list<string> $enabledKeys */
    public function saveForUser(int $userId, array $enabledKeys): void
    {
        $catalog = new \PutMio\CatalogService();
        $sharers = $catalog->listSharedByUsernames();
        $available = array_merge([self::OWN_KEY], $sharers);
        $this->saveEnabledSources($userId, $available, $enabledKeys);
    }

    /** @param list<string> $availableKeys */
    public function saveEnabledSources(int $userId, array $availableKeys, array $enabledKeys): void
    {
        $availableKeys = array_values(array_unique(array_map('strval', $availableKeys)));
        $enabledKeys = array_values(array_intersect(
            array_unique(array_map('strval', $enabledKeys)),
            $availableKeys
        ));
        $hidden = array_values(array_diff($availableKeys, $enabledKeys));

        $pdo = Database::pdo();
        $table = Config::table('user_catalog_hidden_sources');
        $pdo->prepare('DELETE FROM `' . $table . '` WHERE user_id = ?')->execute([$userId]);

        if ($hidden === []) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO `' . $table . '` (user_id, source_key) VALUES (?, ?)'
        );
        foreach ($hidden as $key) {
            $stmt->execute([$userId, $key]);
        }
    }

    public static function sourceKeyFromUsername(?string $sharedByUsername): string
    {
        $username = trim((string) $sharedByUsername);
        return $username === '' ? self::OWN_KEY : $username;
    }

    public static function usernameFromSourceKey(string $sourceKey): ?string
    {
        return $sourceKey === self::OWN_KEY ? null : $sourceKey;
    }

    /**
     * @param list<string> $sharers
     * @return list<array{key: string, label: string, is_own: bool}>
     */
    public function buildSourceOptions(array $sharers): array
    {
        $options = [
            [
                'key' => self::OWN_KEY,
                'label' => putmio_lang('account_content_own'),
                'is_own' => true,
            ],
        ];

        foreach ($sharers as $username) {
            $username = trim($username);
            if ($username === '') {
                continue;
            }
            $options[] = [
                'key' => $username,
                'label' => $username,
                'is_own' => false,
            ];
        }

        return $options;
    }
}
