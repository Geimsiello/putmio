<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\Csrf;
use PutMio\Auth\Session;
use PutMio\Auth\TrustedDevice;
use PutMio\Auth\AuthService;
use PutMio\Auth\UserService;
use PutMio\CatalogService;
use PutMio\Config;
use PutMio\Database;
use PutMio\Mail\Mailer;
use PutMio\PutIO\Client;
use PutMio\PutIO\FriendService;
use PutMio\PutIO\SyncService;
use PutMio\Stream\StreamProxy;
use PutMio\Update\CoreManifest;
use PutMio\Update\CoreUpdater;
use PutMio\Update\GithubReleaseClient;
use PutMio\View;

final class AdminController
{
    public function dashboard(): void
    {
        Session::requireAdmin();
        $pdo = Database::pdo();
        $unclassified = (int) $pdo->query(
            "SELECT COUNT(*) FROM `" . Config::table('media_items') . "` WHERE classification_status = 'unclassified'"
        )->fetchColumn();

        $activeStreams = $pdo->query(
            'SELECT ss.*, u.display_name, mi.title, mi.poster_url, mi.poster_local_path
             FROM `' . Config::table('stream_sessions') . '` ss
             LEFT JOIN `' . Config::table('users') . '` u ON u.id = ss.user_id
             LEFT JOIN `' . Config::table('media_items') . '` mi ON mi.id = ss.media_id
             WHERE ss.active = 1 ORDER BY ss.started_at DESC LIMIT 10'
        )->fetchAll();

        $todayBytes = (int) $pdo->query(
            'SELECT COALESCE(SUM(bytes_sent),0) FROM `' . Config::table('stream_sessions') . '`
             WHERE DATE(started_at) = CURDATE()'
        )->fetchColumn();

        View::render('admin/dashboard', [
            'title' => putmio_lang('admin'),
            'unclassified' => $unclassified,
            'activeStreams' => $activeStreams,
            'activeCount' => count($activeStreams),
            'todayBytes' => $todayBytes,
            'catalog' => new CatalogService(),
        ]);
    }

    public function settings(): void
    {
        Session::requireAdmin();
        $putio = new Client();
        $conn = $putio->getConnection();
        $friendService = new FriendService($putio);
        $putioFriends = [];
        $friendsError = null;

        if ($putio->isConnected()) {
            try {
                $friendService->refreshFromApi();
                $putioFriends = $friendService->listStored();
            } catch (\Throwable $e) {
                $friendsError = $e->getMessage();
                $putioFriends = $friendService->listStored();
            }
        }

        View::render('admin/settings', [
            'title' => putmio_lang('settings'),
            'putioConnected' => $putio->isConnected(),
            'putioUser' => $conn['putio_username'] ?? null,
            'lastSync' => $conn['last_sync_at'] ?? null,
            'lastSyncCount' => $conn['last_sync_file_count'] ?? 0,
            'cronToken' => Config::get('app.cron_token'),
            'putioClientId' => Config::get('putio.client_id'),
            'tmdbKey' => Config::get('tmdb.api_key'),
            'opensubtitlesConfigured' => (new \PutMio\OpenSubtitles\Client())->isConfigured(),
            'hasOpensubtitlesKey' => trim((string) Config::get('opensubtitles.api_key', '')) !== '',
            'hasOpensubtitlesPassword' => trim((string) Config::get('opensubtitles.password', '')) !== '',
            'opensubtitlesUsername' => (string) Config::get('opensubtitles.username', ''),
            'smtpEnabled' => (bool) Config::get('smtp.enabled'),
            'smtpHost' => (string) Config::get('smtp.host', ''),
            'smtpPort' => (int) Config::get('smtp.port', 587),
            'smtpUser' => (string) Config::get('smtp.user', ''),
            'smtpFromEmail' => (string) Config::get('smtp.from_email', ''),
            'smtpFromName' => (string) Config::get('smtp.from_name', 'PutMio'),
            'hasSmtpPass' => trim((string) Config::get('smtp.pass', '')) !== '',
            'putioFriends' => $putioFriends,
            'friendsError' => $friendsError,
            'putmioExtra' => [
                'initialToast' => !empty($_SESSION['flash_success'])
                    ? ['type' => 'success', 'message' => (string) $_SESSION['flash_success']]
                    : (!empty($_SESSION['flash_error'])
                        ? ['type' => 'error', 'message' => (string) $_SESSION['flash_error']]
                        : null),
                'settings' => [
                    'toastSaving' => putmio_lang('putio_friends_saving'),
                    'toastSaved' => putmio_lang('putio_friends_saved'),
                    'toastSaveError' => putmio_lang('putio_friends_save_error'),
                    'toastSyncRunning' => putmio_lang('putio_sync_running'),
                    'toastSyncError' => putmio_lang('admin_sync_error'),
                    'toastSubtitlesTesting' => putmio_lang('subtitles_test_running'),
                    'toastSubtitlesTestOk' => putmio_lang('subtitles_test_ok'),
                    'toastSubtitlesTestError' => putmio_lang('subtitles_test_error'),
                ],
            ],
            'extraScripts' => '<script src="' . htmlspecialchars(putmio_asset('public/assets/admin-settings.js'), ENT_QUOTES, 'UTF-8') . '" defer></script>',
            'success' => $_SESSION['flash_success'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    }

    public function saveSettings(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        $config = Config::all();
        $config['putio']['client_id'] = trim((string) ($_POST['putio_client_id'] ?? ''));
        $putioSecret = trim((string) ($_POST['putio_client_secret'] ?? ''));
        if ($putioSecret !== '') {
            $config['putio']['client_secret'] = $putioSecret;
        }
        $config['putio']['redirect_uri'] = rtrim(Config::get('app.url'), '/') . '/admin/oauth/putio/callback';
        $tmdbKey = trim((string) ($_POST['tmdb_api_key'] ?? ''));
        if ($tmdbKey !== '') {
            $config['tmdb']['api_key'] = $tmdbKey;
        }

        if (!isset($config['opensubtitles']) || !is_array($config['opensubtitles'])) {
            $config['opensubtitles'] = [
                'api_key' => '',
                'username' => '',
                'password' => '',
                'user_agent' => 'PutMio v1.0',
            ];
        }
        $osKey = trim((string) ($_POST['opensubtitles_api_key'] ?? ''));
        if ($osKey !== '') {
            $config['opensubtitles']['api_key'] = $osKey;
        }
        $osUser = trim((string) ($_POST['opensubtitles_username'] ?? ''));
        if ($osUser !== '') {
            $config['opensubtitles']['username'] = $osUser;
        }
        $osPass = trim((string) ($_POST['opensubtitles_password'] ?? ''));
        if ($osPass !== '') {
            $config['opensubtitles']['password'] = $osPass;
        }
        $osAgent = trim((string) ($_POST['opensubtitles_user_agent'] ?? ''));
        if ($osAgent !== '') {
            $config['opensubtitles']['user_agent'] = $osAgent;
        }

        if (!empty($_POST['smtp_enable'])) {
            $config['smtp']['enabled'] = true;
        } else {
            $config['smtp']['enabled'] = false;
        }
        $config['smtp']['host'] = trim((string) ($_POST['smtp_host'] ?? ''));
        $config['smtp']['port'] = max(1, (int) ($_POST['smtp_port'] ?? 587));
        $config['smtp']['user'] = trim((string) ($_POST['smtp_user'] ?? ''));
        if (!empty($_POST['smtp_pass'])) {
            $config['smtp']['pass'] = (string) $_POST['smtp_pass'];
        }
        $config['smtp']['from_email'] = trim((string) ($_POST['smtp_from'] ?? ''));
        $fromName = trim((string) ($_POST['smtp_from_name'] ?? ''));
        if ($fromName !== '') {
            $config['smtp']['from_name'] = $fromName;
        }

        $this->writeConfig($config);
        @unlink(putmio_base_path() . '/storage/.opensubtitles_token');
        Config::load();

        $_SESSION['flash_success'] = putmio_lang('admin_settings_saved');
        putmio_redirect('admin/impostazioni');
    }

    public function putioCallback(): void
    {
        Session::requireAdmin();
        $code = $_GET['code'] ?? null;
        if (!$code) {
            $_SESSION['flash_error'] = putmio_lang('admin_putio_auth_cancelled');
            putmio_redirect('admin/impostazioni');
        }
        try {
            $client = new Client();
            $tokens = $client->exchangeCode($code);
            $client->saveTokens($tokens);
            Config::load();
            Database::reset();
            $info = $client->accountInfo();
            $account = $info['account'] ?? $info;
            $client->saveTokens($tokens, [
                'user_id' => $account['user_id'] ?? $account['id'] ?? null,
                'username' => $account['username'] ?? null,
            ]);
            try {
                (new FriendService($client))->refreshFromApi();
            } catch (\Throwable $e) {
                // Lista amici opzionale al collegamento; l'admin può aggiornarla dalle impostazioni.
            }
            $_SESSION['flash_success'] = putmio_lang('admin_putio_connected');
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        putmio_redirect('admin/impostazioni');
    }

    public function disconnectPutio(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);
        (new FriendService())->clearAll();
        (new Client())->disconnect();
        $_SESSION['flash_success'] = putmio_lang('admin_putio_disconnected');
        putmio_redirect('admin/impostazioni');
    }

    public function refreshPutioFriends(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);
        try {
            $count = (new FriendService())->refreshFromApi();
            $_SESSION['flash_success'] = putmio_lang('admin_friends_refreshed', ['count' => (string) $count]);
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        putmio_redirect('admin/impostazioni');
    }

    public function sync(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);
        try {
            $result = (new SyncService(null, null, 'admin', (int) Session::userId()))->sync();
            $msg = putmio_lang('putio_sync_toast', [
                'imported' => (string) ($result['imported'] ?? 0),
                'removed' => (string) ($result['removed'] ?? 0),
            ]);
            $_SESSION['flash_success'] = $msg;
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        putmio_redirect('admin/impostazioni');
    }

    public function syncLog(): void
    {
        Session::requireAdmin();

        $pdo = Database::pdo();
        $runsTable = Config::table('putio_sync_runs');
        $itemsTable = Config::table('putio_sync_run_items');
        $usersTable = Config::table('users');

        $runs = $pdo->query(
            "SELECT r.*, u.display_name AS triggered_by_name, u.email AS triggered_by_email
             FROM `{$runsTable}` r
             LEFT JOIN `{$usersTable}` u ON u.id = r.triggered_by_user_id
             ORDER BY r.started_at DESC, r.id DESC
             LIMIT 30"
        )->fetchAll();

        $itemsByRun = [];
        $runIds = array_map(static fn (array $row): int => (int) $row['id'], $runs);
        if ($runIds !== []) {
            $placeholders = implode(',', array_fill(0, count($runIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT *
                 FROM `{$itemsTable}`
                 WHERE run_id IN ({$placeholders})
                 ORDER BY FIELD(action, 'added', 'removed', 'updated'), name ASC"
            );
            $stmt->execute($runIds);
            foreach ($stmt->fetchAll() as $item) {
                $runId = (int) ($item['run_id'] ?? 0);
                if (!isset($itemsByRun[$runId])) {
                    $itemsByRun[$runId] = ['added' => [], 'removed' => [], 'updated' => []];
                }
                $action = (string) ($item['action'] ?? '');
                if (!isset($itemsByRun[$runId][$action])) {
                    $itemsByRun[$runId][$action] = [];
                }
                $itemsByRun[$runId][$action][] = $item;
            }
        }

        View::render('admin/sync-log', [
            'title' => putmio_lang('admin_sync_log'),
            'runs' => $runs,
            'itemsByRun' => $itemsByRun,
        ]);
    }

    public function classify(): void
    {
        Session::requireAdmin();
        $pdo = Database::pdo();
        $mediaTable = Config::table('media_items');
        $filesTable = Config::table('putio_files');
        $items = $pdo->query(
            "SELECT mi.*, pf.name AS file_name, pf.shared_by_username,
                    (SELECT COUNT(*) FROM `{$mediaTable}` ep
                     WHERE ep.series_id = mi.id AND ep.classification_status = 'unclassified') AS episode_count
             FROM `{$mediaTable}` mi
             LEFT JOIN `{$filesTable}` pf ON pf.id = mi.putio_file_id
             WHERE mi.classification_status = 'unclassified'
               AND mi.series_id IS NULL
               AND (
                 mi.putio_file_id IS NOT NULL
                 OR EXISTS (
                   SELECT 1 FROM `{$mediaTable}` ep
                   WHERE ep.series_id = mi.id AND ep.classification_status = 'unclassified'
                 )
               )
             ORDER BY pf.shared_by_username IS NOT NULL DESC, mi.created_at DESC
             LIMIT 100"
        )->fetchAll();

        View::render('admin/classify', [
            'title' => putmio_lang('classify'),
            'items' => $items,
            'tmdbConfigured' => (new \PutMio\TMDB\Client())->isConfigured(),
            'extraScripts' => '<script src="' . htmlspecialchars(
                putmio_asset('public/assets/classify-tmdb.js'),
                ENT_QUOTES,
                'UTF-8'
            ) . '" defer></script>'
                . '<script src="' . htmlspecialchars(
                    putmio_asset('public/assets/series-merge.js'),
                    ENT_QUOTES,
                    'UTF-8'
                ) . '" defer></script>',
            'putmioExtra' => [
                'classifyTmdb' => [
                    'mediaIds' => array_map(static fn (array $row): int => (int) $row['id'], $items),
                ],
                'seriesMergeLabels' => [
                    'running' => putmio_lang('series_merge_running'),
                    'error' => putmio_lang('series_merge_error'),
                ],
                'classifyTmdbLabels' => [
                    'film' => putmio_lang('film'),
                    'serie' => putmio_lang('serie'),
                    'animazione' => putmio_lang('animazione'),
                    'altro' => putmio_lang('altro'),
                    'summary' => putmio_lang('classify_tmdb_summary'),
                    'empty' => putmio_lang('classify_tmdb_empty'),
                    'shared_from' => putmio_lang('classify_shared_from', ['user' => ':user']),
                    'confidence' => putmio_lang('classify_tmdb_confidence'),
                    'searched_as' => putmio_lang('classify_tmdb_searched_as'),
                    'no_match' => putmio_lang('classify_tmdb_no_match'),
                    'scan_error' => putmio_lang('classify_tmdb_scan_error'),
                    'scanning' => putmio_lang('classify_tmdb_scanning'),
                    'scan_done' => putmio_lang('classify_tmdb_scan_done'),
                    'nothing_selected' => putmio_lang('classify_tmdb_nothing_selected'),
                    'saving' => putmio_lang('classify_tmdb_saving'),
                    'save_error' => putmio_lang('classify_tmdb_save_error'),
                    'saved' => putmio_lang('classify_tmdb_saved'),
                    'pick_match' => putmio_lang('classify_tmdb_pick_match'),
                    'no_year' => putmio_lang('classify_tmdb_no_year'),
                    'year_hint' => putmio_lang('classify_tmdb_year_hint'),
                    'rating' => putmio_lang('classify_tmdb_rating'),
                ],
            ],
        ]);
    }

    public function saveClassification(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);
        $id = (int) ($_POST['media_id'] ?? 0);
        $type = (string) ($_POST['media_type'] ?? 'altro');
        $title = trim((string) ($_POST['title'] ?? ''));
        $status = (string) ($_POST['classification_status'] ?? 'classified');

        if (!in_array($type, ['film', 'serie', 'animazione', 'altro'], true)) {
            $type = 'altro';
        }
        if (!in_array($status, ['classified', 'ignored', 'unclassified'], true)) {
            $status = 'classified';
        }

        $pdo = Database::pdo();
        $titleFinal = $title !== '' ? $title : putmio_lang('admin_untitled');
        $pdo->prepare(
            'UPDATE `' . Config::table('media_items') . '`
             SET media_type = ?, title = ?, classification_status = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([$type, $titleFinal, $status, $id]);

        if ($status === 'classified') {
            $stmt = $pdo->prepare(
                'SELECT putio_file_id FROM `' . Config::table('media_items') . '` WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row && empty($row['putio_file_id'])) {
                $pdo->prepare(
                    'UPDATE `' . Config::table('media_items') . '`
                     SET media_type = ?, classification_status = \'classified\', updated_at = NOW()
                     WHERE series_id = ? AND classification_status = \'unclassified\''
                )->execute([$type, $id]);
                (new CatalogService())->syncSeriesMetadataToEpisodes($id);
            }
        }

        putmio_redirect('admin/classificazione');
    }

    public function streaming(): void
    {
        Session::requireAdmin();
        $pdo = Database::pdo();
        $active = $pdo->query(
            'SELECT ss.*, u.display_name, mi.title
             FROM `' . Config::table('stream_sessions') . '` ss
             LEFT JOIN `' . Config::table('users') . '` u ON u.id = ss.user_id
             LEFT JOIN `' . Config::table('media_items') . '` mi ON mi.id = ss.media_id
             WHERE ss.active = 1 ORDER BY ss.started_at DESC'
        )->fetchAll();

        $todayBytes = (int) $pdo->query(
            'SELECT COALESCE(SUM(bytes_sent),0) FROM `' . Config::table('stream_sessions') . '`
             WHERE DATE(started_at) = CURDATE()'
        )->fetchColumn();

        View::render('admin/streaming', [
            'title' => putmio_lang('admin_streaming'),
            'active' => $active,
            'todayBytes' => $todayBytes,
            'putmioExtra' => [
                'initialToast' => !empty($_SESSION['flash_success'])
                    ? ['type' => 'success', 'message' => (string) $_SESSION['flash_success']]
                    : (!empty($_SESSION['flash_error'])
                        ? ['type' => 'error', 'message' => (string) $_SESSION['flash_error']]
                        : null),
            ],
            'extraScripts' => '<script>document.addEventListener("DOMContentLoaded",function(){var t=window.PUTMIO&&window.PUTMIO.initialToast;if(t&&window.pmToast)window.pmToast(t.message,t.type||"success");});</script>',
        ]);
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    }

    public function stopAllStreams(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        $stopped = StreamProxy::terminateAllActive();
        if ($stopped > 0) {
            $_SESSION['flash_success'] = $stopped === 1
                ? putmio_lang('admin_streams_stopped_one')
                : putmio_lang('admin_streams_stopped_many', ['count' => (string) $stopped]);
        } else {
            $_SESSION['flash_success'] = putmio_lang('admin_no_streams_to_stop');
        }

        putmio_redirect('admin/streaming');
    }

    public function users(): void
    {
        Session::requireAdmin();
        $pdo = Database::pdo();
        $users = $pdo->query('SELECT id, email, display_name, role, status, last_login_at FROM `' . Config::table('users') . '` ORDER BY id')->fetchAll();

        View::render('admin/users', [
            'title' => putmio_lang('admin_users'),
            'users' => $users,
            'currentUserId' => (int) Session::userId(),
            'success' => $_SESSION['flash_success'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
            'inviteLink' => $_SESSION['flash_invite'] ?? null,
        ]);
        unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_invite']);
    }

    public function deleteUser(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $_SESSION['flash_error'] = putmio_lang('admin_user_delete_not_found');
            putmio_redirect('admin/utenti');
        }

        $auth = new AuthService();
        $target = $auth->findUserById($userId);
        $error = (new UserService())->delete($userId, (int) Session::userId());

        if ($error === 'self') {
            $_SESSION['flash_error'] = putmio_lang('admin_user_delete_self');
        } elseif ($error === 'last_admin') {
            $_SESSION['flash_error'] = putmio_lang('admin_user_delete_last_admin');
        } elseif ($error === 'not_found') {
            $_SESSION['flash_error'] = putmio_lang('admin_user_delete_not_found');
        } else {
            $_SESSION['flash_success'] = putmio_lang('admin_user_deleted', [
                'name' => (string) ($target['display_name'] ?? $target['email'] ?? ''),
            ]);
        }

        putmio_redirect('admin/utenti');
    }

    public function createInvite(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            putmio_redirect('admin/utenti');
        }

        $token = putmio_random_token(24);
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO `' . Config::table('invites') . '` (email, token_hash, expires_at, created_by)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 72 HOUR), ?)'
        )->execute([$email, hash('sha256', $token), Session::userId()]);

        $inviteUrl = rtrim(Config::get('app.url'), '/') . '/registrati?token=' . urlencode($token);

        if (!Mailer::isEnabled()) {
            $_SESSION['flash_error'] = putmio_lang('invite_smtp_required');
            $_SESSION['flash_invite'] = $inviteUrl;
            putmio_redirect('admin/utenti');
        }

        try {
            Mailer::sendInvite($email, $inviteUrl);
            $_SESSION['flash_success'] = putmio_lang('invite_email_sent', ['email' => $email]);
        } catch (\Throwable $e) {
            putmio_log('Invito email fallito per ' . $email . ': ' . $e->getMessage());
            $_SESSION['flash_error'] = putmio_lang('invite_email_failed');
            $_SESSION['flash_invite'] = $inviteUrl;
        }

        putmio_redirect('admin/utenti');
    }

    public function updates(): void
    {
        Session::requireAdmin();
        $updater = new CoreUpdater();

        View::render('admin/updates', [
            'title' => putmio_lang('admin_updates'),
            'status' => $updater->status(),
            'protectedPaths' => CoreManifest::PROTECTED_PATHS,
            'updatablePaths' => CoreManifest::UPDATABLE_PATHS,
            'success' => $_SESSION['flash_success'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
            'removedFiles' => $_SESSION['flash_update_removed'] ?? null,
        ]);
        unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_update_removed']);
    }

    public function applyUpdate(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        $updater = new CoreUpdater();

        try {
            $result = $updater->applyLatest();
            $_SESSION['flash_success'] = $result['message'];
            if (!empty($result['removed_files'])) {
                $_SESSION['flash_update_removed'] = $result['removed_files'];
            }
        } catch (\Throwable $e) {
            $code = $e->getMessage();
            $langKey = 'admin_update_error_' . $code;
            $fallback = putmio_lang('admin_update_error_generic');
            $message = putmio_lang($langKey);
            $_SESSION['flash_error'] = $message !== $langKey ? $message : $fallback;
        }

        putmio_redirect('admin/aggiornamenti');
    }

    public function refreshUpdates(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        (new GithubReleaseClient())->clearCache();
        $_SESSION['flash_success'] = putmio_lang('admin_updates_refreshed');

        putmio_redirect('admin/aggiornamenti');
    }

    public function devices(): void
    {
        Session::requireAdmin();

        $userId = (int) Session::userId();
        $ctx = putmio_user_devices_context($userId);
        $appUrl = rtrim(Config::get('app.url'), '/');

        View::render('admin/devices', [
            'title' => putmio_lang('account_devices'),
            'devices' => $ctx['devices'],
            'currentDeviceId' => $ctx['currentDeviceId'],
            'revokeAction' => $appUrl . '/admin/dispositivi/revoca',
            'success' => $_SESSION['flash_success'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    }

    public function revokeDevice(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);

        $userId = (int) Session::userId();
        $deviceId = (int) ($_POST['device_id'] ?? 0);
        if ($deviceId <= 0) {
            $_SESSION['flash_error'] = putmio_lang('account_device_revoke_error');
            putmio_redirect('admin/dispositivi');
        }

        $ctx = putmio_user_devices_context($userId);
        $isCurrent = $ctx['currentDeviceId'] !== null && $ctx['currentDeviceId'] === $deviceId;

        if (TrustedDevice::revokeById($userId, $deviceId)) {
            if ($isCurrent) {
                TrustedDevice::forget();
            }
            $_SESSION['flash_success'] = putmio_lang('account_device_revoked');
        } else {
            $_SESSION['flash_error'] = putmio_lang('account_device_revoke_error');
        }

        putmio_redirect('admin/dispositivi');
    }

    private function writeConfig(array $config): void
    {
        $export = var_export($config, true);
        file_put_contents(putmio_config_path(), "<?php\n\nreturn " . $export . ";\n");
    }
}
