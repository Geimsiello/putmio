<?php

declare(strict_types=1);

namespace PutMio\Media;

/**
 * Estrae un titolo leggibile da nomi file tipici delle release (scene/PVR).
 * Copre i pattern più comuni; su nomi atipici restituisce null.
 */
final class ReleaseNameParser
{
    public static function guessTitle(string $filename, ?string $folderName = null): ?string
    {
        $info = self::parseEpisode($filename, $folderName);
        if ($info !== null) {
            return $info['show_title'];
        }

        if ($folderName !== null && trim($folderName) !== '') {
            $fromFolder = self::guessShowTitleFromFolder($folderName);
            if ($fromFolder !== null) {
                return $fromFolder;
            }
        }

        return self::guessTitleFromBase(pathinfo($filename, PATHINFO_FILENAME));
    }

    /**
     * @return array{show_title: string, season: int, episode: int, episode_title: ?string}|null
     */
    public static function parseEpisode(string $filename, ?string $folderName = null): ?array
    {
        $fromFile = self::parseEpisodeFromFilename($filename);
        if ($fromFile !== null) {
            return $fromFile;
        }

        if ($folderName === null || trim($folderName) === '') {
            return null;
        }

        return self::parseEpisodeWithFolder($filename, trim($folderName));
    }

    public static function guessShowTitleFromFolder(string $folderName): ?string
    {
        $base = trim($folderName);
        if ($base === '') {
            return null;
        }

        $base = preg_replace('/\s*\([^)]*\)\s*$/u', '', $base) ?? $base;
        $base = trim($base);
        if ($base === '') {
            return null;
        }

        if (preg_match('/^(.+?)(?:\s*-\s*)?(?:Stagione|Season)\s+\d+/iu', $base, $m)) {
            $title = trim($m[1], " -_\t");

            return self::isReasonableTitle($title) ? $title : null;
        }

        if (preg_match('/^(.+?)[.\s_-]+S(\d{1,2})(?:[.\s_.-]|$)/iu', $base, $m)) {
            $title = trim($m[1], "._- \t");

            return self::isReasonableTitle($title) ? $title : null;
        }

        $tokens = self::tokenize($base);
        $titleTokens = [];
        foreach ($tokens as $token) {
            if (self::isMetadataToken($token)) {
                break;
            }
            if (preg_match('/^(?:Stagione|Season)$/iu', $token)) {
                break;
            }
            if (preg_match('/^S\d{1,2}$/i', $token)) {
                break;
            }
            $titleTokens[] = $token;
        }

        $title = self::formatTitle($titleTokens);

        return self::isReasonableTitle($title) ? $title : null;
    }

    public static function guessSeasonFromFolder(string $folderName): ?int
    {
        if (preg_match('/(?:Stagione|Season)\s+(\d{1,2})/iu', $folderName, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\bS(\d{1,2})\b/i', $folderName, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @return array{show_title: string, season: int, episode: int, episode_title: ?string}|null
     */
    private static function parseEpisodeFromFilename(string $filename): ?array
    {
        $originalBase = pathinfo($filename, PATHINFO_FILENAME);
        if ($originalBase === '') {
            return null;
        }

        $hadBracketGroup = (bool) preg_match('/^\[[^\]]+\]/u', $originalBase);
        $base = self::stripBracketReleaseGroup($originalBase);
        $base = self::stripReleaseGroup($base);

        if (preg_match('/^(.+?)[.\s_-]*[Ss](\d{1,2})[Ee](\d{1,3})(.*)$/u', $base, $m)) {
            $showTitle = self::formatTitle(self::tokenize(trim($m[1], "._- \t")));
            if (!self::isReasonableTitle($showTitle)) {
                return null;
            }

            return [
                'show_title' => $showTitle,
                'season' => (int) $m[2],
                'episode' => (int) $m[3],
                'episode_title' => self::formatEpisodeTitleSuffix($m[4] ?? ''),
            ];
        }

        $tokens = self::tokenize($base);
        foreach ($tokens as $i => $token) {
            if (!preg_match('/^[Ss](\d{1,2})[Ee](\d{1,3})$/', $token, $m)) {
                continue;
            }

            $titleTokens = array_slice($tokens, 0, $i);
            if ($titleTokens === []) {
                return null;
            }

            $showTitle = self::formatTitle($titleTokens);
            if (!self::isReasonableTitle($showTitle)) {
                return null;
            }

            $suffixTokens = self::trimMetadataTokens(array_slice($tokens, $i + 1));

            return [
                'show_title' => $showTitle,
                'season' => (int) $m[1],
                'episode' => (int) $m[2],
                'episode_title' => self::formatEpisodeTitleSuffix(implode(' ', $suffixTokens)),
            ];
        }

        if (preg_match('/^(.+?)[.\s_-]*(\d{1,2})[xX](\d{1,3})(.*)$/u', $base, $m)) {
            $showTitle = self::formatTitle(self::tokenize(trim($m[1], "._- \t")));
            if (!self::isReasonableTitle($showTitle)) {
                return null;
            }

            return [
                'show_title' => $showTitle,
                'season' => (int) $m[2],
                'episode' => (int) $m[3],
                'episode_title' => self::formatEpisodeTitleSuffix($m[4] ?? ''),
            ];
        }

        if (
            $hadBracketGroup
            && preg_match('/^(.+?)[.\s_-]*-\s*(?:E(?:P(?:ISODE)?)?)?(\d{1,3})(?:\s.*)?$/iu', $base, $m)
        ) {
            $showTitle = self::formatTitle(self::tokenize(trim($m[1], "._- \t")));
            if (!self::isReasonableTitle($showTitle)) {
                return null;
            }

            return [
                'show_title' => $showTitle,
                'season' => 1,
                'episode' => (int) $m[2],
                'episode_title' => null,
            ];
        }

        return null;
    }

    /**
     * Episodio con solo numero/titolo nel filename; nome serie e stagione dalla cartella padre.
     *
     * @return array{show_title: string, season: int, episode: int, episode_title: ?string}|null
     */
    private static function parseEpisodeWithFolder(string $filename, string $folderName): ?array
    {
        $showTitle = self::guessShowTitleFromFolder($folderName);
        if ($showTitle === null) {
            return null;
        }

        $originalBase = pathinfo($filename, PATHINFO_FILENAME);
        if ($originalBase === '') {
            return null;
        }

        $base = self::stripBracketReleaseGroup($originalBase);
        $base = self::stripReleaseGroup($base);

        if (preg_match('/^(\d{1,2})[xX](\d{1,3})\s*[-–—]?\s*(.*)$/u', $base, $m)) {
            return [
                'show_title' => $showTitle,
                'season' => (int) $m[1],
                'episode' => (int) $m[2],
                'episode_title' => self::formatEpisodeTitleSuffix(self::stripTrailingVideoExtension($m[3])),
            ];
        }

        if (preg_match('/^[Ss](\d{1,2})[Ee](\d{1,3})\s*[-–—]?\s*(.*)$/u', $base, $m)) {
            return [
                'show_title' => $showTitle,
                'season' => (int) $m[1],
                'episode' => (int) $m[2],
                'episode_title' => self::formatEpisodeTitleSuffix(self::stripTrailingVideoExtension($m[3])),
            ];
        }

        $folderSeason = self::guessSeasonFromFolder($folderName);
        if ($folderSeason === null) {
            return null;
        }

        if (preg_match('/^(?:E(?:P(?:ISODE)?)?)?(\d{1,3})\s*[-–—]?\s*(.*)$/iu', $base, $m)) {
            return [
                'show_title' => $showTitle,
                'season' => $folderSeason,
                'episode' => (int) $m[1],
                'episode_title' => self::formatEpisodeTitleSuffix(self::stripTrailingVideoExtension($m[2])),
            ];
        }

        return null;
    }

    public static function episodeDisplayTitle(int $season, int $episode, ?string $episodeTitle = null): string
    {
        $code = sprintf('S%02dE%02d', $season, $episode);
        if ($episodeTitle !== null && $episodeTitle !== '') {
            return $code . ' · ' . $episodeTitle;
        }

        return $code;
    }

    private static function guessTitleFromBase(string $base): ?string
    {
        if ($base === '') {
            return null;
        }

        $base = self::stripBracketReleaseGroup($base);
        $base = self::stripReleaseGroup($base);
        $tokens = self::tokenize($base);
        if ($tokens === []) {
            return null;
        }

        $titleTokens = [];
        foreach ($tokens as $token) {
            if (self::isSeasonEpisode($token)) {
                break;
            }
            if (self::isMetadataToken($token)) {
                break;
            }
            $titleTokens[] = $token;
        }

        if ($titleTokens === []) {
            foreach ($tokens as $token) {
                if (preg_match('/^(.+?)[.\s_-]*S\d{1,2}E\d{1,3}$/i', $token, $m)) {
                    $candidate = trim($m[1], "._- \t");
                    if ($candidate !== '') {
                        $titleTokens = [$candidate];
                    }
                    break;
                }
            }
        }

        $title = self::formatTitle($titleTokens);

        return self::isReasonableTitle($title) ? $title : null;
    }

    private static function stripBracketReleaseGroup(string $name): string
    {
        $stripped = preg_replace('/^\[[^\]]+\]\s*[.\s]*/u', '', $name);
        if ($stripped === null) {
            return $name;
        }

        return ltrim($stripped, "._ \t");
    }

    private static function stripReleaseGroup(string $name): string
    {
        if (preg_match('/^(.+)-([A-Za-z0-9]{2,15})$/', $name, $m)) {
            if (preg_match('/^\d+$/', $m[2])) {
                return $name;
            }

            return $m[1];
        }

        return $name;
    }

    /** @return list<string> */
    private static function tokenize(string $name): array
    {
        $parts = preg_split('/[._\s]+/', $name, -1, PREG_SPLIT_NO_EMPTY);

        return $parts ?: [];
    }

    private static function isSeasonEpisode(string $token): bool
    {
        return (bool) preg_match(
            '/^(?:S\d{1,2}E\d{1,3}|\d{1,2}x\d{1,3}|S\d{1,2}|E\d{1,3})$/i',
            $token
        );
    }

    private static function isMetadataToken(string $token): bool
    {
        $upper = strtoupper($token);

        if (preg_match('/^(?:19|20)\d{2}$/', $token)) {
            return true;
        }
        if (preg_match('/^\(?\d{4}\)?$/', $token)) {
            return true;
        }
        if (preg_match('/^\d{3,4}P$/', $upper)) {
            return true;
        }
        if (preg_match('/^\d+BIT$/', $upper)) {
            return true;
        }

        static $exact = [
            '4K', 'UHD', 'HDR', 'HDR10', 'HDR10PLUS', 'DV', 'SDR',
            'WEBDL', 'WEBRIP', 'BLURAY', 'BDRIP', 'BRRIP', 'HDTV', 'DVDRIP', 'DVD',
            'REMUX', 'HDTS', 'CAM', 'TS', 'TC', 'R5', 'SCR',
            'X264', 'X265', 'H264', 'H265', 'HEVC', 'AVC', 'XVID', 'DIVX', 'VP9', 'AV1',
            'AAC', 'AC3', 'DD', 'DTS', 'TRUEHD', 'ATMOS',
            'MULTI', 'MULTISUB', 'DUBBED', 'SUBBED', 'SUBS',
            'ITALIAN', 'ITALIANO', 'ENGLISH', 'FRENCH', 'GERMAN', 'SPANISH',
            'ITA', 'ENG', 'SUB', 'DUB', 'DL', 'MUX', 'WEB',
            'AMZN', 'NF', 'NETFLIX', 'HULU', 'DSNP', 'ATVP', 'PCOK', 'MAX',
            'PROPER', 'REPACK', 'EXTENDED', 'UNRATED', 'DIRECTORS', 'CUT',
            'INTERNAL', 'READNFO', 'LIMITED',
        ];

        if (in_array($upper, $exact, true)) {
            return true;
        }

        if (preg_match('/(?:WEB[- ]?DL|WEBRIP|BLURAY|BDRIP|HDTV|REMUX|DD\d(?:\.\d)?|H\.?264|H\.?265|X26[45]|WEB[- ]?DLMUX)/i', $token)) {
            return true;
        }

        if (preg_match('/^(?:MULTI|SUBBED|DUBBED|ITALIAN|ITALIANO|ENGLISH|FRENCH|GERMAN|SPANISH)$/i', $token)) {
            return true;
        }

        return false;
    }

    /** @param list<string> $tokens */
    private static function trimMetadataTokens(array $tokens): array
    {
        $kept = [];
        foreach ($tokens as $token) {
            if (self::isMetadataToken($token)) {
                break;
            }
            $kept[] = $token;
        }

        return $kept;
    }

    private static function formatEpisodeTitleSuffix(string $suffix): ?string
    {
        $suffix = self::stripTrailingVideoExtension(trim(str_replace(['.', '_'], ' ', $suffix)));
        if ($suffix === '' || !self::isReasonableTitle($suffix)) {
            return null;
        }

        return $suffix;
    }

    private static function stripTrailingVideoExtension(string $suffix): string
    {
        $trimmed = trim($suffix);
        if ($trimmed === '') {
            return '';
        }

        $stripped = preg_replace(
            '/\s+(?:mkv|mp4|avi|mov|webm|m4v|wmv|ts|mpeg|mpg)$/iu',
            '',
            $trimmed
        );

        return $stripped !== null ? trim($stripped) : $trimmed;
    }

    /** @param list<string> $tokens */
    private static function formatTitle(array $tokens): string
    {
        $words = array_map(static fn (string $t): string => str_replace(['-', '_'], ' ', $t), $tokens);

        return trim(implode(' ', $words));
    }

    private static function isReasonableTitle(string $title): bool
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) < 2) {
            return false;
        }

        return !preg_match('/^\d+$/', $title);
    }
}
