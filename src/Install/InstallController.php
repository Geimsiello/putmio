<?php

declare(strict_types=1);

namespace PutMio\Install;

use PutMio\Auth\Csrf;
use PutMio\Database;
use PutMio\View;

final class InstallController
{
    public function handle(): void
    {
        if (InstallGate::isInstalled()) {
            putmio_redirect('login');
        }

        $step = (int) ($_GET['step'] ?? ($_SESSION['install_step'] ?? 1));
        if ($step < 1 || $step > 6) {
            $step = 1;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::requireValid($_POST['_csrf'] ?? null);
            $this->handlePost($step);
            return;
        }

        $this->renderStep($step);
    }

    private function handlePost(int $step): void
    {
        switch ($step) {
            case 1:
                $_SESSION['install_step'] = 2;
                putmio_redirect('?step=2');
                break;
            case 2:
                $req = Installer::requirements();
                if (!$req['all_ok']) {
                    $_SESSION['install_error'] = 'Correggi i requisiti mancanti prima di proseguire.';
                    putmio_redirect('?step=2');
                }
                $_SESSION['install_step'] = 3;
                putmio_redirect('?step=3');
                break;
            case 3:
                $db = $this->dbFromPost();
                try {
                    Database::assertDatabaseConnection($db);
                } catch (\Throwable $e) {
                    $_SESSION['install_error'] = $e->getMessage();
                    putmio_redirect('?step=3');
                }
                $_SESSION['install_db'] = $db;
                $_SESSION['install_error'] = null;
                if (isset($_POST['action']) && $_POST['action'] === 'test') {
                    $_SESSION['install_success'] = putmio_lang('connection_ok');
                    putmio_redirect('?step=3');
                }
                $_SESSION['install_step'] = 4;
                putmio_redirect('?step=4');
                break;
            case 4:
                $db = $_SESSION['install_db'] ?? null;
                if (!is_array($db)) {
                    putmio_redirect('?step=3');
                }
                try {
                    Installer::runSchema($db);
                } catch (\Throwable $e) {
                    $_SESSION['install_error'] = 'Errore SQL: ' . $e->getMessage();
                    putmio_redirect('?step=4');
                }
                $_SESSION['install_step'] = 5;
                putmio_redirect('?step=5');
                break;
            case 5:
                $db = $_SESSION['install_db'] ?? null;
                if (!is_array($db)) {
                    putmio_redirect('?step=3');
                }
                $email = trim((string) ($_POST['email'] ?? ''));
                $name = trim((string) ($_POST['display_name'] ?? ''));
                $pass = (string) ($_POST['password'] ?? '');
                $pass2 = (string) ($_POST['password_confirm'] ?? '');

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['install_error'] = 'Email non valida.';
                    putmio_redirect('?step=5');
                }
                if (strlen($pass) < 10) {
                    $_SESSION['install_error'] = 'La password deve avere almeno 10 caratteri.';
                    putmio_redirect('?step=5');
                }
                if ($pass !== $pass2) {
                    $_SESSION['install_error'] = 'Le password non coincidono.';
                    putmio_redirect('?step=5');
                }
                if ($name === '') {
                    $_SESSION['install_error'] = 'Inserisci un nome visualizzato.';
                    putmio_redirect('?step=5');
                }

                $smtp = [
                    'enabled' => !empty($_POST['smtp_enable']),
                    'host' => trim((string) ($_POST['smtp_host'] ?? '')),
                    'port' => (int) ($_POST['smtp_port'] ?? 587),
                    'user' => trim((string) ($_POST['smtp_user'] ?? '')),
                    'pass' => (string) ($_POST['smtp_pass'] ?? ''),
                    'from_email' => trim((string) ($_POST['smtp_from'] ?? '')),
                    'from_name' => 'PutMio',
                ];

                $app = [
                    'url' => putmio_detect_base_url(),
                    'encryption_key' => putmio_random_token(32),
                    'cron_token' => putmio_random_token(24),
                ];

                try {
                    Installer::writeConfig($db, $app, $smtp);
                    \PutMio\Config::load();
                    Database::reset();
                    Installer::createAdmin($db, $email, $name, $pass);
                } catch (\Throwable $e) {
                    $_SESSION['install_error'] = $e->getMessage();
                    @unlink(putmio_config_path());
                    putmio_redirect('?step=5');
                }

                unset($_SESSION['install_db'], $_SESSION['install_error']);
                $_SESSION['install_step'] = 6;
                putmio_redirect('?step=6');
                break;
            default:
                putmio_redirect('?step=1');
        }
    }

    private function dbFromPost(): array
    {
        return [
            'host' => trim((string) ($_POST['db_host'] ?? 'localhost')),
            'name' => putmio_sanitize_db_name(trim((string) ($_POST['db_name'] ?? ''))),
            'user' => trim((string) ($_POST['db_user'] ?? '')),
            'pass' => (string) ($_POST['db_pass'] ?? ''),
            'prefix' => putmio_normalize_table_prefix((string) ($_POST['db_prefix'] ?? 'pm_')),
            'charset' => 'utf8mb4',
        ];
    }

    private function renderStep(int $step): void
    {
        $error = $_SESSION['install_error'] ?? null;
        $success = $_SESSION['install_success'] ?? null;
        unset($_SESSION['install_error'], $_SESSION['install_success']);

        $data = [
            'step' => $step,
            'error' => $error,
            'success' => $success,
            'csrf' => Csrf::field(),
        ];

        switch ($step) {
            case 1:
                View::renderInstall('install/welcome', $data);
                break;
            case 2:
                $data['requirements'] = Installer::requirements();
                View::renderInstall('install/requirements', $data);
                break;
            case 3:
                $data['db'] = $_SESSION['install_db'] ?? [
                    'host' => 'localhost',
                    'name' => '',
                    'user' => '',
                    'pass' => '',
                    'prefix' => 'pm_',
                ];
                View::renderInstall('install/database', $data);
                break;
            case 4:
                View::renderInstall('install/run', $data);
                break;
            case 5:
                View::renderInstall('install/admin', $data);
                break;
            case 6:
                if (!InstallGate::isInstalled()) {
                    Installer::finalizeInstall();
                }
                View::renderInstall('install/complete', $data);
                break;
            default:
                View::renderInstall('install/welcome', $data);
        }
    }
}
