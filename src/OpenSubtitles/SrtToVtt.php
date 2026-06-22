<?php

declare(strict_types=1);

namespace PutMio\OpenSubtitles;

final class SrtToVtt
{
    public static function convert(string $srt): string
    {
        $srt = str_replace(["\r\n", "\r"], "\n", $srt);
        $srt = preg_replace('/^\xEF\xBB\xBF/', '', $srt) ?? $srt;

        $blocks = preg_split("/\n\s*\n/", trim($srt)) ?: [];
        $lines = ['WEBVTT', ''];

        foreach ($blocks as $block) {
            $blockLines = array_values(array_filter(explode("\n", trim($block)), static fn (string $l): bool => $l !== ''));
            if ($blockLines === []) {
                continue;
            }

            $timeIdx = 0;
            if (isset($blockLines[0]) && ctype_digit($blockLines[0])) {
                $timeIdx = 1;
            }
            if (!isset($blockLines[$timeIdx])) {
                continue;
            }

            $timing = self::srtTimeToVtt($blockLines[$timeIdx]);
            if ($timing === null) {
                continue;
            }

            $textLines = array_slice($blockLines, $timeIdx + 1);
            if ($textLines === []) {
                continue;
            }

            $lines[] = $timing;
            foreach ($textLines as $textLine) {
                $lines[] = self::stripTags($textLine);
            }
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    private static function srtTimeToVtt(string $line): ?string
    {
        if (!preg_match(
            '/(\d{1,2}):(\d{2}):(\d{2})[,.](\d{3})\s*-->\s*(\d{1,2}):(\d{2}):(\d{2})[,.](\d{3})/',
            $line,
            $m
        )) {
            return null;
        }

        $start = sprintf('%02d:%02d:%02d.%03d', (int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]);
        $end = sprintf('%02d:%02d:%02d.%03d', (int) $m[5], (int) $m[6], (int) $m[7], (int) $m[8]);

        return $start . ' --> ' . $end;
    }

    private static function stripTags(string $line): string
    {
        $line = preg_replace('/\{[^}]*\}/', '', $line) ?? $line;
        $line = str_replace(['<i>', '</i>', '<b>', '</b>', '<u>', '</u>'], '', $line);

        return trim($line);
    }
}
