<?php

declare(strict_types=1);

namespace PutMio\PutIO;

use PutMio\Config;

final class SyncOptions
{
    public function __construct(
        public readonly bool $includeSubtitles = true,
        public readonly bool $deferOnActiveStreams = false,
    ) {
    }

    public static function admin(): self
    {
        return new self(includeSubtitles: true, deferOnActiveStreams: false);
    }

    public static function cronHttp(): self
    {
        return new self(
            includeSubtitles: (bool) Config::get('app.sync_subtitles_with_catalog', false),
            deferOnActiveStreams: (bool) Config::get('app.sync_defer_when_streaming', true),
        );
    }

    public static function cronCli(): self
    {
        return self::cronHttp();
    }

    public static function subtitlesCron(): self
    {
        return new self(
            includeSubtitles: true,
            deferOnActiveStreams: (bool) Config::get('app.sync_defer_when_streaming', true),
        );
    }
}
