<?php

declare(strict_types=1);

namespace RFM\Service;

use RFM\Config\AppConfig;
use RFM\Enum\ClipboardAction;
use RFM\Exception\ForbiddenException;

final class ClipboardService
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly SecurityService $security,
    ) {}

    /**
     * Copy paths to clipboard.
     *
     * @param string[] $paths
     */
    public function copy(array $paths): void
    {
        $this->validatePaths($paths);
        $this->checkLimits($paths);

        $_SESSION['RFM']['clipboard'] = [
            'action' => ClipboardAction::Copy->value,
            'paths' => $paths,
        ];
    }

    /**
     * Cut paths to clipboard.
     *
     * @param string[] $paths
     */
    public function cut(array $paths): void
    {
        $this->validatePaths($paths);
        $this->checkLimits($paths);

        $_SESSION['RFM']['clipboard'] = [
            'action' => ClipboardAction::Cut->value,
            'paths' => $paths,
        ];
    }

    /**
     * Get current clipboard contents.
     *
     * @return array{hasItems: bool, action: string|null, paths: string[], count: int}
     */
    public function getState(): array
    {
        $clipboard = $_SESSION['RFM']['clipboard'] ?? null;

        if ($clipboard === null || empty($clipboard['paths'])) {
            return [
                'hasItems' => false,
                'action' => null,
                'paths' => [],
                'count' => 0,
            ];
        }

        return [
            'hasItems' => true,
            'action' => $clipboard['action'],
            'paths' => $clipboard['paths'],
            'count' => count($clipboard['paths']),
        ];
    }

    /**
     * Get clipboard action enum.
     */
    public function getAction(): ?ClipboardAction
    {
        $clipboard = $_SESSION['RFM']['clipboard'] ?? null;
        if ($clipboard === null) {
            return null;
        }
        return ClipboardAction::tryFrom($clipboard['action'] ?? '');
    }

    /**
     * Get clipboard paths.
     *
     * @return string[]
     */
    public function getPaths(): array
    {
        return $_SESSION['RFM']['clipboard']['paths'] ?? [];
    }

    /**
     * Clear the clipboard.
     */
    public function clear(): void
    {
        unset($_SESSION['RFM']['clipboard']);
    }

    /**
     * @param string[] $paths
     */
    private function validatePaths(array $paths): void
    {
        foreach ($paths as $path) {
            $this->security->checkRelativePath($path);
            $fullPath = $this->config->currentPath . $path;
            $this->security->validatePath($fullPath);
        }
    }

    /**
     * @param string[] $paths
     */
    private function checkLimits(array $paths): void
    {
        // Count limit
        if ($this->config->copyCutMaxCount !== false) {
            $totalCount = 0;
            foreach ($paths as $path) {
                $fullPath = $this->config->currentPath . $path;
                if (is_dir($fullPath)) {
                    $totalCount += $this->countFilesRecursive($fullPath);
                } else {
                    $totalCount++;
                }
            }

            if ($totalCount > $this->config->copyCutMaxCount) {
                throw new ForbiddenException(
                    "File count limit exceeded ({$totalCount} > {$this->config->copyCutMaxCount})"
                );
            }
        }

        // Size limit
        if ($this->config->copyCutMaxSize !== false) {
            $totalSize = 0;
            foreach ($paths as $path) {
                $fullPath = $this->config->currentPath . $path;
                if (is_dir($fullPath)) {
                    $totalSize += $this->calculateDirSize($fullPath);
                } else {
                    $totalSize += (int) @filesize($fullPath);
                }
            }

            $maxBytes = $this->config->copyCutMaxSize * 1024 * 1024;
            if ($totalSize > $maxBytes) {
                $sizeMB = round($totalSize / 1024 / 1024, 1);
                throw new ForbiddenException(
                    "Size limit exceeded ({$sizeMB}MB > {$this->config->copyCutMaxSize}MB)"
                );
            }
        }
    }

    private function countFilesRecursive(string $dir): int
    {
        $count = 0;
        $entries = @scandir($dir);
        if ($entries === false) {
            return 0;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $count += $this->countFilesRecursive($path);
            } else {
                $count++;
            }
        }

        return $count;
    }

    private function calculateDirSize(string $dir): int
    {
        $size = 0;
        $entries = @scandir($dir);
        if ($entries === false) {
            return 0;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $size += $this->calculateDirSize($path);
            } else {
                $size += (int) @filesize($path);
            }
        }

        return $size;
    }
}
