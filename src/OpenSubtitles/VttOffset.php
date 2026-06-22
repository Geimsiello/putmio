<?php

declare(strict_types=1);

namespace PutMio\OpenSubtitles;

final class VttOffset
{
    public static function apply(string $vtt, int $offsetMs): string
    {
        if ($offsetMs === 0) {
            return $vtt;
        }

        $offsetSec = $offsetMs / 1000;

        $shifted = preg_replace_callback(
            '/(\d{1,2}:\d{2}:\d{2}\.\d{3}|\d{1,2}:\d{2}\.\d{3})\s*-->\s*(\d{1,2}:\d{2}:\d{2}\.\d{3}|\d{1,2}:\d{2}\.\d{3})/',
            static function (array $matches) use ($offsetSec): string {
                $start = max(0.0, self::toSeconds($matches[1]) + $offsetSec);
                $end = max($start, self::toSeconds($matches[2]) + $offsetSec);

                return self::fromSeconds($start) . ' --> ' . self::fromSeconds($end);
            },
            $vtt
        );

        return $shifted ?? $vtt;
    }

    private static function toSeconds(string $timestamp): float
    {
        if (preg_match('/^(\d{2}):(\d{2}):(\d{2})\.(\d{3})$/', $timestamp, $m)) {
            return ((int) $m[1] * 3600) + ((int) $m[2] * 60) + (int) $m[3] + ((int) $m[4] / 1000);
        }

        if (preg_match('/^(\d{2}):(\d{2})\.(\d{3})$/', $timestamp, $m)) {
            return ((int) $m[1] * 60) + (int) $m[2] + ((int) $m[3] / 1000);
        }

        return 0.0;
    }

    private static function fromSeconds(float $seconds): string
    {
        if ($seconds < 0) {
            $seconds = 0.0;
        }

        $hours = (int) floor($seconds / 3600);
        $seconds -= $hours * 3600;
        $minutes = (int) floor($seconds / 60);
        $seconds -= $minutes * 60;
        $secs = (int) floor($seconds);
        $millis = (int) round(($seconds - $secs) * 1000);

        if ($millis >= 1000) {
            $millis = 0;
            $secs++;
        }
        if ($secs >= 60) {
            $secs = 0;
            $minutes++;
        }
        if ($minutes >= 60) {
            $minutes = 0;
            $hours++;
        }

        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $secs, $millis);
    }
}
