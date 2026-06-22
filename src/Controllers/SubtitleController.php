<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\Csrf;
use PutMio\Auth\Session;
use PutMio\Config;
use PutMio\OpenSubtitles\Client as OpenSubtitlesClient;
use PutMio\OpenSubtitles\SubtitleService;

final class SubtitleController
{
    public function list(): void
    {
        Session::requireAuth();
        $mediaId = (int) ($_GET['media_id'] ?? 0);
        if ($mediaId <= 0) {
            putmio_json(['error' => 'media_id richiesto'], 400);
        }

        $service = new SubtitleService();
        $appUrl = rtrim(Config::get('app.url'), '/');
        $userId = (int) Session::userId();
        $prefs = $service->userPrefs($userId, $mediaId);

        putmio_json([
            'ok' => true,
            'configured' => $service->isConfigured(),
            'subtitles' => putmio_subtitle_payload_list($service->listForMedia($mediaId), $appUrl),
            'activeSubtitleId' => $prefs['subtitle_id'],
            'offsetMs' => $prefs['offset_ms'],
        ]);
    }

    public function search(): void
    {
        Session::requireAuth();
        $mediaId = (int) ($_GET['media_id'] ?? 0);
        if ($mediaId <= 0) {
            putmio_json(['error' => 'media_id richiesto'], 400);
        }

        try {
            $service = new SubtitleService();
            if (!$service->isConfigured()) {
                putmio_json([
                    'error' => putmio_lang('subtitles_not_configured'),
                    'admin' => Session::isAdmin(),
                ], 400);
            }

            $results = $service->searchRemote($mediaId);
            putmio_json(['ok' => true, 'results' => $results]);
        } catch (\Throwable $e) {
            putmio_json(['error' => $e->getMessage()], 502);
        }
    }

    public function download(): void
    {
        Session::requireAuth();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        $mediaId = (int) ($_POST['media_id'] ?? 0);
        $fileId = trim((string) ($_POST['file_id'] ?? ''));
        $language = trim((string) ($_POST['language'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? ''));

        if ($mediaId <= 0 || $fileId === '') {
            putmio_json(['error' => 'Parametri non validi'], 400);
        }

        try {
            $service = new SubtitleService();
            if (!$service->isConfigured()) {
                putmio_json([
                    'error' => putmio_lang('subtitles_not_configured'),
                    'admin' => Session::isAdmin(),
                ], 400);
            }

            $row = $service->download(
                $mediaId,
                $fileId,
                (int) Session::userId(),
                $language !== '' ? $language : null,
                $label !== '' ? $label : null
            );

            $appUrl = rtrim(Config::get('app.url'), '/');
            putmio_json([
                'ok' => true,
                'subtitle' => [
                    'id' => (int) $row['id'],
                    'language' => (string) $row['language'],
                    'label' => (string) $row['label'],
                    'serveUrl' => $appUrl . '/subtitles/serve?id=' . (int) $row['id'],
                ],
            ]);
        } catch (\Throwable $e) {
            putmio_json(['error' => $e->getMessage()], 502);
        }
    }

    public function preference(): void
    {
        Session::requireAuth();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        $mediaId = (int) ($_POST['media_id'] ?? 0);
        $subtitleRaw = $_POST['subtitle_id'] ?? null;
        $subtitleId = ($subtitleRaw === '' || $subtitleRaw === null) ? null : (int) $subtitleRaw;
        $offsetMs = (int) ($_POST['offset_ms'] ?? 0);

        if ($mediaId <= 0) {
            putmio_json(['error' => 'media_id richiesto'], 400);
        }

        try {
            (new SubtitleService())->savePreference((int) Session::userId(), $mediaId, $subtitleId, $offsetMs);
            putmio_json(['ok' => true]);
        } catch (\Throwable $e) {
            putmio_json(['error' => $e->getMessage()], 400);
        }
    }

    public function serve(): void
    {
        Session::requireAuth();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            exit('ID non valido');
        }

        $service = new SubtitleService();
        $row = $service->findById($id);
        if ($row === null) {
            http_response_code(404);
            exit('Sottotitolo non trovato');
        }

        $path = $service->servePath($id);
        if ($path === null) {
            http_response_code(404);
            exit('Sottotitolo non trovato');
        }

        $userId = (int) Session::userId();
        $mediaId = (int) ($row['media_id'] ?? 0);
        $prefs = $service->userPrefs($userId, $mediaId);

        $offsetMs = 0;
        if (isset($_GET['offset_ms']) && $_GET['offset_ms'] !== '') {
            $offsetMs = max(-600_000, min(600_000, (int) $_GET['offset_ms']));
        } elseif ($prefs['subtitle_id'] === $id) {
            $offsetMs = max(-600_000, min(600_000, $prefs['offset_ms']));
        }

        $vtt = file_get_contents($path);
        if ($vtt === false) {
            http_response_code(500);
            exit('Errore lettura sottotitolo');
        }

        if ($offsetMs !== 0) {
            $vtt = \PutMio\OpenSubtitles\VttOffset::apply($vtt, $offsetMs);
        }

        header('Content-Type: text/vtt; charset=utf-8');
        header('Cache-Control: private, no-cache');
        echo $vtt;
        exit;
    }

    public function delete(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            putmio_json(['error' => 'ID non valido'], 400);
        }

        (new SubtitleService())->delete($id);
        putmio_json(['ok' => true]);
    }

    public function testOpenSubtitles(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        try {
            $client = new OpenSubtitlesClient();
            if (!$client->isConfigured()) {
                putmio_json(['error' => putmio_lang('subtitles_not_configured')], 400);
            }
            $client->testConnection();
            putmio_json(['ok' => true, 'message' => putmio_lang('subtitles_test_ok')]);
        } catch (\Throwable $e) {
            putmio_json(['error' => $e->getMessage()], 502);
        }
    }
}
