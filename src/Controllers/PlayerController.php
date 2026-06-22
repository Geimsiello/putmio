<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\Session;
use PutMio\CatalogService;
use PutMio\Config;
use PutMio\Database;
use PutMio\PutIO\Client;
use PutMio\Stream\StreamProxy;
use PutMio\View;

final class PlayerController
{
    public function show(): void
    {
        Session::requireAuth();
        $id = (int) ($_GET['id'] ?? 0);
        $catalog = new CatalogService();
        $media = $catalog->findMedia($id);
        if (!$media) {
            http_response_code(404);
            exit('Media non trovato');
        }

        if ($catalog->isSeries($media)) {
            putmio_redirect('media?id=' . $id);
        }

        $userId = (int) Session::userId();
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `' . Config::table('watch_progress') . '` WHERE user_id = ? AND media_id = ?'
        );
        $stmt->execute([$userId, $id]);
        $progress = $stmt->fetch() ?: null;

        $appUrl = rtrim(Config::get('app.url'), '/');
        $startAt = 0;
        if (!empty($progress) && empty($progress['completed']) && ($progress['position_sec'] ?? 0) > 0) {
            $startAt = (int) $progress['position_sec'];
        }

        $series = null;
        $detailMediaId = $id;
        $displayTitle = (string) $media['title'];
        $subtitle = putmio_lang((string) ($media['media_type'] ?? 'altro'));

        if (!empty($media['series_id'])) {
            $series = $catalog->findMedia((int) $media['series_id']);
            $detailMediaId = (int) $media['series_id'];
            if ($series) {
                $displayTitle = (string) $series['title'];
            }
            $subtitle = putmio_lang('serie') . ' · ' . (string) $media['title'];
        }

        $runtimeSec = (int) ($media['duration_sec'] ?? 0);
        if ($runtimeSec <= 0 && !empty($progress['duration_sec'])) {
            $runtimeSec = (int) $progress['duration_sec'];
        }

        $streamMime = putmio_browser_playback_mime($media['file_name'] ?? null, $media['file_mime'] ?? null);
        $fileExt = strtolower(pathinfo((string) ($media['file_name'] ?? ''), PATHINFO_EXTENSION));
        $isOriginalNonMp4 = !in_array($fileExt, ['mp4', 'm4v'], true);

        $mp4Available = false;
        try {
            $putio = new Client();
            if ($putio->isConnected()) {
                $mp4Available = $putio->isMp4Available((int) $media['putio_id']);
            }
        } catch (\Throwable $e) {
            $mp4Available = false;
        }

        $playbackFormat = $mp4Available ? 'mp4' : 'original';
        $streamUrl = $appUrl . '/stream?id=' . (int) $media['putio_id'] . '&format=' . $playbackFormat;

        $audioWarning = !$mp4Available && (
            putmio_has_unsupported_browser_audio($media['file_name'] ?? null)
            || $fileExt === 'mkv'
        );

        $synopsis = (string) ($media['synopsis'] ?? '');
        if ($synopsis === '' && $series && !empty($series['synopsis'])) {
            $synopsis = (string) $series['synopsis'];
        }

        $year = $media['year'] ?? ($series['year'] ?? null);

        $adjacent = $catalog->adjacentEpisodes($id);
        $nextEpisode = $adjacent['next'] ?? null;
        $nextEpisodePayload = null;
        if ($nextEpisode) {
            $seasonNum = (int) ($nextEpisode['season_number'] ?? 0);
            $episodeNum = (int) ($nextEpisode['episode_number'] ?? 0);
            $nextEpisodePayload = [
                'id' => (int) $nextEpisode['id'],
                'title' => (string) $nextEpisode['title'],
                'code' => ($seasonNum > 0 && $episodeNum > 0)
                    ? sprintf('S%02dE%02d', $seasonNum, $episodeNum)
                    : null,
                'playUrl' => $appUrl . '/play?id=' . (int) $nextEpisode['id'],
            ];
        }

        View::render('player/show', [
            'title' => $displayTitle,
            'media' => $media,
            'progress' => $progress,
            'series' => $series,
            'displayTitle' => $displayTitle,
            'subtitle' => $subtitle,
            'synopsis' => $synopsis,
            'year' => $year,
            'runtimeLabel' => putmio_format_runtime_label($runtimeSec > 0 ? $runtimeSec : null),
            'detailMediaId' => $detailMediaId,
            'adjacent' => $adjacent,
            'techLabels' => putmio_file_technical_labels($media['file_name'] ?? null),
            'startAt' => $startAt,
            'streamUrl' => $streamUrl,
            'streamMime' => $streamMime,
            'durationSec' => $runtimeSec,
            'audioWarning' => $audioWarning,
            'mp4Available' => $mp4Available,
            'showSourcePicker' => $mp4Available && $isOriginalNonMp4,
            'playbackFormat' => $playbackFormat,
            'extraHead' => '<link href="https://vjs.zencdn.net/8.16.1/video-js.css" rel="stylesheet">',
            'extraScripts' => '<script src="https://vjs.zencdn.net/8.16.1/video.min.js"></script>'
                . '<script src="' . putmio_e($appUrl) . '/public/assets/player.js" defer></script>',
            'putmioExtra' => [
                'putioId' => (int) $media['putio_id'],
                'mediaId' => $id,
                'startAt' => $startAt,
                'streamUrl' => $streamUrl,
                'streamMime' => 'video/mp4',
                'durationSec' => $runtimeSec,
                'playbackFormat' => $playbackFormat,
                'mp4Available' => $mp4Available,
                'showSourcePicker' => $mp4Available && $isOriginalNonMp4,
                'nextEpisode' => $nextEpisodePayload,
                'playerLabels' => [
                    'nextUp' => putmio_lang('player_next_up'),
                    'playNow' => putmio_lang('player_play_next'),
                    'autoPlayIn' => putmio_lang('player_autoplay_in'),
                    'dismiss' => putmio_lang('player_next_dismiss'),
                ],
            ],
        ]);
    }

    public function stream(): void
    {
        Session::requireAuth();
        $putioId = (int) ($_GET['id'] ?? 0);
        if ($putioId <= 0) {
            http_response_code(400);
            exit('ID non valido');
        }
        $format = (string) ($_GET['format'] ?? 'mp4');
        if (!in_array($format, ['mp4', 'original'], true)) {
            $format = 'mp4';
        }
        $userId = (int) Session::userId();
        Session::release();
        (new StreamProxy())->stream($putioId, $userId, $format);
    }
}
