<?php

declare(strict_types=1);

namespace PutMio\Update;

use PutMio\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Coordina il controllo versioni e l'applicazione degli aggiornamenti core.
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
     *   check_error_detail: string|null,
     *   check_http_status: int,
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

        $backupDir = CoreManifest::backupsDir();
        if (!is_dir($backupDir) && !@mkdir($backupDir, 0755, true)) {
            $blockers[] = 'backup_dir_not_writable';
        } elseif (!is_writable($backupDir)) {
            $blockers[] = 'backup_dir_not_writable';
        }

        return $blockers;
    }

    /**
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

        $zipUrl = (string) ($status['latest']['zip_url'] ?? '');
        if ($zipUrl === '') {
            throw new RuntimeException('release_zip_missing');
        }

        $targetVersion = (string) $status['latest']['version'];
        $installedVersion = (string) $status['installed_version'];
        $workDir = CoreManifest::updatesWorkDir();
        $zipPath = $workDir . '/download-' . $this->safeFilename($targetVersion) . '.zip';
        $extractBase = $workDir . '/extract-' . uniqid('', true);

        try {
            $this->backupCurrentCore($installedVersion);
            $this->downloadFile($zipUrl, $zipPath);
            $sourceRoot = $this->extractArchive($zipPath, $extractBase);
            $this->installFromSource($sourceRoot);
            $this->clearReleaseCache();
        } finally {
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            if (is_dir($extractBase)) {
                $this->removeDirectory($extractBase);
            }
        }

        return [
            'version' => $targetVersion,
            'message' => putmio_lang('admin_update_success', ['version' => $targetVersion]),
        ];
    }

    private function backupCurrentCore(string $version): void
    {
        $backupDir = CoreManifest::backupsDir();
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $zipPath = $backupDir . '/core-' . date('Ymd-His') . '-v' . $this->safeFilename($version) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('backup_failed');
        }

        $base = putmio_base_path();
        foreach (CoreManifest::UPDATABLE_PATHS as $path) {
            $full = $base . '/' . $path;
            if (is_file($full)) {
                $zip->addFile($full, $path);
            } elseif (is_dir($full)) {
                $this->addDirectoryToZip($zip, $full, $path);
            }
        }

        if (!$zip->close()) {
            throw new RuntimeException('backup_failed');
        }
    }

    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $zipPrefix): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relative = CoreManifest::normalize($zipPrefix . '/' . substr($item->getPathname(), strlen($directory) + 1));
            if ($relative === '' || CoreManifest::isProtected($relative)) {
                continue;
            }
            $zip->addFile($item->getPathname(), $relative);
        }
    }

    private function downloadFile(string $url, string $destPath): void
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: PutMio-Updater/' . putmio_version(),
        ];
        $token = trim((string) Config::get('updates.github_token', ''));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('zip_download_failed');
        }

        $fp = fopen($destPath, 'wb');
        if ($fp === false) {
            curl_close($ch);
            throw new RuntimeException('zip_download_failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $caFile = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
        if (is_string($caFile) && $caFile !== '' && is_file($caFile)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caFile);
        }

        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $status < 200 || $status >= 300 || !is_file($destPath) || filesize($destPath) < 1024) {
            @unlink($destPath);
            throw new RuntimeException('zip_download_failed');
        }
    }

    private function extractArchive(string $zipPath, string $extractTo): string
    {
        if (!@mkdir($extractTo, 0755, true) && !is_dir($extractTo)) {
            throw new RuntimeException('zip_extract_failed');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('zip_extract_failed');
        }

        if (!$zip->extractTo($extractTo)) {
            $zip->close();
            throw new RuntimeException('zip_extract_failed');
        }
        $zip->close();

        $root = $this->detectSourceRoot($extractTo);
        if ($root === null) {
            throw new RuntimeException('zip_invalid');
        }

        return $root;
    }

    private function detectSourceRoot(string $extractTo): ?string
    {
        $entries = array_values(array_diff(scandir($extractTo) ?: [], ['.', '..']));
        if (count($entries) === 1) {
            $candidate = $extractTo . '/' . $entries[0];
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        if (is_file($extractTo . '/VERSION') || is_dir($extractTo . '/src')) {
            return $extractTo;
        }

        return null;
    }

    private function installFromSource(string $sourceRoot): void
    {
        $base = putmio_base_path();
        $sourceRoot = rtrim(str_replace('\\', '/', $sourceRoot), '/');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = CoreManifest::normalize(substr(str_replace('\\', '/', $item->getPathname()), strlen($sourceRoot) + 1));
            if ($relative === '' || CoreManifest::isProtected($relative) || !CoreManifest::isUpdatable($relative)) {
                continue;
            }

            if (str_contains($relative, '..')) {
                continue;
            }

            $dest = $base . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($dest) && !@mkdir($dest, 0755, true) && !is_dir($dest)) {
                    throw new RuntimeException('copy_failed');
                }
                continue;
            }

            $destDir = dirname($dest);
            if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                throw new RuntimeException('copy_failed');
            }

            if (!@copy($item->getPathname(), $dest)) {
                throw new RuntimeException('copy_failed');
            }
        }
    }

    private function clearReleaseCache(): void
    {
        $pattern = CoreManifest::updatesWorkDir() . '/github-release-*.json';
        foreach (glob($pattern) ?: [] as $file) {
            @unlink($file);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }

    private function safeFilename(string $value): string
    {
        $safe = preg_replace('/[^0-9a-zA-Z._-]+/', '_', $value);
        return $safe !== '' ? $safe : 'unknown';
    }
}
