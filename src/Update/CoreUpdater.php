<?php

declare(strict_types=1);

namespace PutMio\Update;

use RuntimeException;

/**
 * Coordina il controllo versioni e l'applicazione degli aggiornamenti core.
 * Lo scheletro espone lo stato; apply() sarà completato in una fase successiva.
 */
final class CoreUpdater
{
    private GithubReleaseClient $releases;

    public function __construct(?GithubReleaseClient $releases = null)
    {
        $this->releases = $releases ?? new GithubReleaseClient();
    }

    public function installedVersion(): string
    {
        return putmio_version();
    }

    /**
     * @return array{
     *   installed_version: string,
     *   configured: bool,
     *   repository: string,
     *   check_error: string|null,
     *   latest: array<string, mixed>|null,
     *   update_available: bool,
     *   can_apply: bool,
     *   apply_blockers: list<string>
     * }
     */
    public function status(): array
    {
        $installed = $this->installedVersion();
        $configured = $this->releases->isConfigured();
        $latest = null;
        $checkError = null;
        $updateAvailable = false;

        if (!$configured) {
            $checkError = 'repository_not_configured';
        } else {
            $latest = $this->releases->fetchLatest();
            if ($latest === null) {
                $checkError = $this->releases->lastError() ?? 'release_fetch_failed';
            } else {
                $updateAvailable = version_compare($latest['version'], $installed, '>');
            }
        }

        $blockers = $this->applyBlockers();

        return [
            'installed_version' => $installed,
            'configured' => $configured,
            'repository' => $this->releases->repository(),
            'check_error' => $checkError,
            'check_error_detail' => $this->releases->lastError(),
            'check_http_status' => $this->releases->lastHttpStatus(),
            'latest' => $latest,
            'update_available' => $updateAvailable,
            'can_apply' => $updateAvailable && $blockers === [],
            'apply_blockers' => $blockers,
        ];
    }

    /**
     * @return list<string> Codici interni per messaggi i18n
     */
    public function applyBlockers(): array
    {
        $blockers = [];

        if (!is_writable(putmio_base_path())) {
            $blockers[] = 'root_not_writable';
        }

        if (!extension_loaded('curl')) {
            $blockers[] = 'curl_missing';
        }

        if (!class_exists('ZipArchive')) {
            $blockers[] = 'zip_missing';
        }

        $workDir = CoreManifest::updatesWorkDir();
        if (!is_dir($workDir) && !@mkdir($workDir, 0755, true)) {
            $blockers[] = 'updates_dir_not_writable';
        } elseif (!is_writable($workDir)) {
            $blockers[] = 'updates_dir_not_writable';
        }

        return $blockers;
    }

    /**
     * Scarica ed applica l'ultima release. Da implementare: download ZIP, backup, copia file.
     *
     * @return array{version: string, message: string}
     */
    public function applyLatest(): array
    {
        $status = $this->status();
        if (!$status['update_available'] || $status['latest'] === null) {
            throw new RuntimeException('no_update_available');
        }

        if (!$status['can_apply']) {
            throw new RuntimeException('apply_blocked');
        }

        if (empty($status['latest']['zip_url'])) {
            throw new RuntimeException('release_zip_missing');
        }

        throw new RuntimeException('apply_not_implemented');
    }
}
