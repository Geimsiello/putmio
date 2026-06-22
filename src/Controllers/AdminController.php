<?php

declare(strict_types=1);

namespace PutMio\Controllers;

use PutMio\Auth\Csrf;
use PutMio\Auth\Session;
use PutMio\CatalogService;
use PutMio\Config;
use PutMio\Database;
use PutMio\PutIO\Client;
use PutMio\PutIO\SyncService;
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

        View::render('admin/dashboard', [
            'title' => putmio_lang('admin'),
            'unclassified' => $unclassified,
        ]);
    }

    public function settings(): void
    {
        Session::requireAdmin();
        $putio = new Client();
        $conn = $putio->getConnection();

        View::render('admin/settings', [
            'title' => putmio_lang('settings'),
            'putioConnected' => $putio->isConnected(),
            'putioUser' => $conn['putio_username'] ?? null,
            'lastSync' => $conn['last_sync_at'] ?? null,
            'lastSyncCount' => $conn['last_sync_file_count'] ?? 0,
            'cronToken' => Config::get('app.cron_token'),
            'putioClientId' => Config::get('putio.client_id'),
            'tmdbKey' => Config::get('tmdb.api_key'),
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
        $config['putio']['client_secret'] = trim((string) ($_POST['putio_client_secret'] ?? ''));
        $config['putio']['redirect_uri'] = rtrim(Config::get('app.url'), '/') . '/admin/oauth/putio/callback';
        $config['tmdb']['api_key'] = trim((string) ($_POST['tmdb_api_key'] ?? ''));

        if (!empty($_POST['smtp_enable'])) {
            $config['smtp']['enabled'] = true;
            $config['smtp']['host'] = trim((string) ($_POST['smtp_host'] ?? ''));
            $config['smtp']['port'] = (int) ($_POST['smtp_port'] ?? 587);
            $config['smtp']['user'] = trim((string) ($_POST['smtp_user'] ?? ''));
            if (!empty($_POST['smtp_pass'])) {
                $config['smtp']['pass'] = (string) $_POST['smtp_pass'];
            }
            $config['smtp']['from_email'] = trim((string) ($_POST['smtp_from'] ?? ''));
        }

        $this->writeConfig($config);
        Config::load();
        $_SESSION['flash_success'] = 'Impostazioni salvate.';
        putmio_redirect('admin/impostazioni');
    }

    public function putioCallback(): void
    {
        Session::requireAdmin();
        $code = $_GET['code'] ?? null;
        if (!$code) {
            $_SESSION['flash_error'] = 'Autorizzazione put.io annullata.';
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
            $_SESSION['flash_success'] = 'put.io collegato con successo.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        putmio_redirect('admin/impostazioni');
    }

    public function disconnectPutio(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);
        (new Client())->disconnect();
        $_SESSION['flash_success'] = 'put.io disconnesso.';
        putmio_redirect('admin/impostazioni');
    }

    public function sync(): void
    {
        Session::requireAdmin();
        Csrf::requireValid($_POST['_csrf'] ?? null);
        try {
            $result = (new SyncService())->sync();
            $_SESSION['flash_success'] = 'Sync completata: ' . $result['imported'] . ' elementi.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        putmio_redirect('admin/impostazioni');
    }

    public function classify(): void
    {
        Session::requireAdmin();
        $pdo = Database::pdo();
        $items = $pdo->query(
            "SELECT mi.*, pf.name AS file_name FROM `" . Config::table('media_items') . "` mi
             JOIN `" . Config::table('putio_files') . "` pf ON pf.id = mi.putio_file_id
             WHERE mi.classification_status = 'unclassified'
             ORDER BY mi.created_at DESC LIMIT 100"
        )->fetchAll();

        View::render('admin/classify', [
            'title' => putmio_lang('classify'),
            'items' => $items,
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
        $pdo->prepare(
            'UPDATE `' . Config::table('media_items') . '`
             SET media_type = ?, title = ?, classification_status = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([$type, $title !== '' ? $title : 'Senza titolo', $status, $id]);

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
            'title' => 'Streaming',
            'active' => $active,
            'todayBytes' => $todayBytes,
        ]);
    }

    public function users(): void
    {
        Session::requireAdmin();
        $pdo = Database::pdo();
        $users = $pdo->query('SELECT id, email, display_name, role, status, last_login_at FROM `' . Config::table('users') . '` ORDER BY id')->fetchAll();

        View::render('admin/users', [
            'title' => 'Utenti',
            'users' => $users,
            'inviteLink' => $_SESSION['flash_invite'] ?? null,
        ]);
        unset($_SESSION['flash_invite']);
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

        $_SESSION['flash_invite'] = rtrim(Config::get('app.url'), '/') . '/registrati?token=' . urlencode($token);
        putmio_redirect('admin/utenti');
    }

    private function writeConfig(array $config): void
    {
        $export = var_export($config, true);
        file_put_contents(putmio_config_path(), "<?php\n\nreturn " . $export . ";\n");
    }
}
