<?php

declare(strict_types=1);

namespace PutMio\Update;

use RuntimeException;

/**
 * Rimuove file obsoleti dopo un aggiornamento core:
 * - manifest esplicito REMOVED_FILES.json (per versione)
 * - sync mirror su directory solo-core (file presenti in installazione ma assenti nella release)
 */
final class CoreFileCleanup
{
    /**
     * @return list<string> Path relativi rimossi (unici, ordinati)
     */
    public function apply(string $sourceRoot, string $fromVersion, string $toVersion): array
    {
        $sourceRoot = rtrim(str_replace('\\', '/', $sourceRoot), '/');
        $removed = [];

        foreach ($this->pathsFromManifest($sourceRoot, $fromVersion, $toVersion) as $relative) {
            if ($this->removePath($relative)) {
                $removed[] = $relative;
            }
        }

        foreach ($this->pathsFromMirrorSync($sourceRoot) as $relative) {
            if ($this->removePath($relative)) {
                $removed[] = $relative;
            }
        }

        $removed = array_values(array_unique($removed));
        sort($removed, SORT_STRING);

        if ($removed !== []) {
            putmio_log('Core update cleanup: removed ' . count($removed) . ' file(s): ' . implode(', ', $removed));
        }

        return $removed;
    }

    /**
     * @return list<string>
     */
    private function pathsFromManifest(string $sourceRoot, string $fromVersion, string $toVersion): array
    {
        $manifestPath = $sourceRoot . '/REMOVED_FILES.json';
        if (!is_file($manifestPath)) {
            return [];
        }

        $raw = file_get_contents($manifestPath);
        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $paths = [];
        foreach ($data as $version => $files) {
            if (!is_string($version) || !is_array($files) || !preg_match('/^\d+\.\d+/', $version)) {
                continue;
            }
            if (version_compare($version, $fromVersion, '<=') || version_compare($version, $toVersion, '>')) {
                continue;
            }
            foreach ($files as $file) {
                if (!is_string($file) || trim($file) === '') {
                    continue;
                }
                $normalized = CoreManifest::normalize($file);
                if ($normalized !== '' && $this->isRemovable($normalized)) {
                    $paths[] = $normalized;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * File presenti in installazione ma non nella release, dentro le directory mirror.
     *
     * @return list<string>
     */
    private function pathsFromMirrorSync(string $sourceRoot): array
    {
        $base = putmio_base_path();
        $sourceFiles = $this->collectSourceFiles($sourceRoot);
        $orphans = [];

        foreach (CoreManifest::MIRROR_SYNC_PATHS as $mirrorRoot) {
            $mirrorRoot = CoreManifest::normalize($mirrorRoot);
            $installedDir = $base . '/' . $mirrorRoot;
            if (!is_dir($installedDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($installedDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                $relative = CoreManifest::normalize(
                    $mirrorRoot . '/' . substr(str_replace('\\', '/', $item->getPathname()), strlen($installedDir) + 1)
                );
                if ($relative === '' || !$this->isRemovable($relative)) {
                    continue;
                }
                if (!isset($sourceFiles[$relative])) {
                    $orphans[] = $relative;
                }
            }
        }

        return $orphans;
    }

    /**
     * @return array<string, true> path relativi presenti nella release
     */
    private function collectSourceFiles(string $sourceRoot): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relative = CoreManifest::normalize(substr(str_replace('\\', '/', $item->getPathname()), strlen($sourceRoot) + 1));
            if ($relative === '' || CoreManifest::isProtected($relative) || !CoreManifest::isUpdatable($relative)) {
                continue;
            }
            $files[$relative] = true;
        }

        return $files;
    }

    private function isRemovable(string $relative): bool
    {
        if ($relative === '' || str_contains($relative, '..')) {
            return false;
        }
        if (CoreManifest::isProtected($relative) || !CoreManifest::isUpdatable($relative)) {
            return false;
        }

        return true;
    }

    private function removePath(string $relative): bool
    {
        if (!$this->isRemovable($relative)) {
            return false;
        }

        $full = putmio_base_path() . '/' . $relative;
        if (!is_file($full)) {
            return false;
        }

        if (!@unlink($full)) {
            throw new RuntimeException('cleanup_failed');
        }

        $this->pruneEmptyParents(dirname($relative));

        return true;
    }

    private function pruneEmptyParents(string $relativeDir): void
    {
        $relativeDir = CoreManifest::normalize($relativeDir);
        if ($relativeDir === '' || $relativeDir === '.') {
            return;
        }

        $isUnderMirror = false;
        foreach (CoreManifest::MIRROR_SYNC_PATHS as $mirrorRoot) {
            $mirrorRoot = CoreManifest::normalize($mirrorRoot);
            if ($relativeDir === $mirrorRoot || str_starts_with($relativeDir, $mirrorRoot . '/')) {
                $isUnderMirror = true;
                break;
            }
        }
        if (!$isUnderMirror) {
            return;
        }

        $full = putmio_base_path() . '/' . $relativeDir;
        if (!is_dir($full)) {
            return;
        }

        $entries = @scandir($full);
        if ($entries === false) {
            return;
        }
        $entries = array_diff($entries, ['.', '..']);
        if ($entries !== []) {
            return;
        }

        @rmdir($full);
        $this->pruneEmptyParents(dirname($relativeDir));
    }
}
