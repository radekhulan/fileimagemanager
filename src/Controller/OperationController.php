<?php

declare(strict_types=1);

namespace RFM\Controller;

use RFM\Config\AppConfig;
use RFM\Enum\ClipboardAction;
use RFM\Http\{Request, JsonResponse};
use RFM\Service\{FileSystemService, ClipboardService, SecurityService};

final class OperationController
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly FileSystemService $fileSystem,
        private readonly ClipboardService $clipboard,
        private readonly SecurityService $security,
    ) {}

    public function rename(Request $request): JsonResponse
    {
        if (!$this->config->renameFiles) {
            return JsonResponse::error('File renaming disabled', 403);
        }

        $path = $request->post('path', '');
        $newName = $request->post('name', '');

        if (!is_string($path) || $path === '') {
            return JsonResponse::error('File path required');
        }
        if (!is_string($newName) || $newName === '') {
            return JsonResponse::error('New name required');
        }

        $newPath = $this->fileSystem->renameFile($path, $newName);

        return JsonResponse::success(['path' => $newPath]);
    }

    public function delete(Request $request): JsonResponse
    {
        if (!$this->config->deleteFiles) {
            return JsonResponse::error('File deletion disabled', 403);
        }

        $path = $request->post('path', '');

        if (!is_string($path) || $path === '') {
            return JsonResponse::error('File path required');
        }

        $this->fileSystem->deleteFile($path);

        return JsonResponse::success();
    }

    public function deleteBulk(Request $request): JsonResponse
    {
        if (!$this->config->deleteFiles) {
            return JsonResponse::error('File deletion disabled', 403);
        }

        $paths = $request->post('paths', []);

        if (!is_array($paths) || empty($paths)) {
            return JsonResponse::error('File paths required');
        }

        $deleted = [];
        $errors = [];

        foreach ($paths as $path) {
            try {
                $fullPath = $this->config->currentPath . $path;
                if (is_dir($fullPath)) {
                    if ($this->config->deleteFolders) {
                        $this->fileSystem->deleteDirectory($path);
                        $deleted[] = $path;
                    } else {
                        $errors[] = ['path' => $path, 'error' => 'Folder deletion disabled'];
                    }
                } else {
                    $this->fileSystem->deleteFile($path);
                    $deleted[] = $path;
                }
            } catch (\Throwable $e) {
                $errors[] = ['path' => $path, 'error' => $e->getMessage()];
            }
        }

        return JsonResponse::success([
            'deleted' => $deleted,
            'errors' => $errors,
        ]);
    }

    public function duplicate(Request $request): JsonResponse
    {
        if (!$this->config->duplicateFiles) {
            return JsonResponse::error('File duplication disabled', 403);
        }

        $path = $request->post('path', '');
        $newName = $request->post('name');

        if (!is_string($path) || $path === '') {
            return JsonResponse::error('File path required');
        }

        $newPath = $this->fileSystem->duplicateFile($path, is_string($newName) ? $newName : null);

        return JsonResponse::success(['path' => $newPath]);
    }

    public function copy(Request $request): JsonResponse
    {
        if (!$this->config->copyCutFiles) {
            return JsonResponse::error('Copy disabled', 403);
        }

        $paths = $request->post('paths', []);
        if (!is_array($paths)) {
            $path = $request->post('path', '');
            $paths = is_string($path) && $path !== '' ? [$path] : [];
        }

        if (empty($paths)) {
            return JsonResponse::error('Paths required');
        }

        $this->clipboard->copy($paths);

        return JsonResponse::success(['clipboard' => $this->clipboard->getState()]);
    }

    public function cut(Request $request): JsonResponse
    {
        if (!$this->config->copyCutFiles) {
            return JsonResponse::error('Cut disabled', 403);
        }

        $paths = $request->post('paths', []);
        if (!is_array($paths)) {
            $path = $request->post('path', '');
            $paths = is_string($path) && $path !== '' ? [$path] : [];
        }

        if (empty($paths)) {
            return JsonResponse::error('Paths required');
        }

        $this->clipboard->cut($paths);

        return JsonResponse::success(['clipboard' => $this->clipboard->getState()]);
    }

    public function paste(Request $request): JsonResponse
    {
        $targetDir = $request->post('path', '');
        if (!is_string($targetDir)) {
            $targetDir = '';
        }

        $action = $this->clipboard->getAction();
        $paths = $this->clipboard->getPaths();

        if ($action === null || empty($paths)) {
            return JsonResponse::error('Clipboard is empty');
        }

        $results = [];

        foreach ($paths as $path) {
            $name = basename($path);
            $destPath = rtrim($targetDir, '/') . '/' . $name;
            $sourceFull = $this->config->currentPath . $path;

            if (is_dir($sourceFull)) {
                if ($action === ClipboardAction::Copy) {
                    $this->fileSystem->copyDirectory($path, $destPath);
                } else {
                    $this->fileSystem->moveDirectory($path, $destPath);
                }
            } else {
                if ($action === ClipboardAction::Copy) {
                    $this->fileSystem->duplicateFile($path, $name);
                } else {
                    $this->fileSystem->moveFile($path, $destPath);
                }
            }

            $results[] = $destPath;
        }

        // Clear clipboard after cut
        if ($action === ClipboardAction::Cut) {
            $this->clipboard->clear();
        }

        return JsonResponse::success(['pasted' => $results]);
    }

    public function clearClipboard(Request $request): JsonResponse
    {
        $this->clipboard->clear();

        return JsonResponse::success();
    }

    public function chmod(Request $request): JsonResponse
    {
        if (!$this->config->chmodFiles && !$this->config->chmodDirs) {
            return JsonResponse::error('Permission changes disabled', 403);
        }

        $path = $request->post('path', '');
        $mode = $request->post('mode', '');
        $recursive = $request->post('recursive', 'none');

        if (!is_string($path) || $path === '') {
            return JsonResponse::error('Path required');
        }
        if (!is_string($mode) || !preg_match('/^[0-7]{3}$/', $mode)) {
            return JsonResponse::error('Invalid permission mode (must be 3 octal digits)');
        }
        if (!is_string($recursive)) {
            $recursive = 'none';
        }

        $octalMode = intval($mode, 8);
        $this->fileSystem->changePermissions($path, $octalMode, $recursive);

        return JsonResponse::success();
    }

    public function extract(Request $request): JsonResponse
    {
        if (!$this->config->extractFiles) {
            return JsonResponse::error('Extraction disabled', 403);
        }

        $path = $request->post('path', '');

        if (!is_string($path) || $path === '') {
            return JsonResponse::error('File path required');
        }

        $fullPath = $this->fileSystem->getFullPath($path);
        $this->security->validatePath($fullPath);

        if (!is_file($fullPath)) {
            return JsonResponse::error('File not found', 404);
        }

        $ext = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $extractDir = dirname($fullPath) . '/' . pathinfo($path, PATHINFO_FILENAME);

        if (!is_dir($extractDir)) {
            @mkdir($extractDir, $this->config->folderPermission, true);
        }

        $success = match ($ext) {
            'zip' => $this->extractZip($fullPath, $extractDir),
            'gz', 'tar' => $this->extractTar($fullPath, $extractDir),
            default => false,
        };

        if (!$success) {
            return JsonResponse::error('Extraction failed');
        }

        return JsonResponse::success();
    }

    public function saveTextFile(Request $request): JsonResponse
    {
        if (!$this->config->editTextFiles) {
            return JsonResponse::error('File editing disabled', 403);
        }

        $path = $request->post('path', '');
        $content = $request->post('content', '');

        if (!is_string($path) || $path === '') {
            return JsonResponse::error('File path required');
        }

        $ext = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->config->editableTextFileExts, true)) {
            return JsonResponse::error('This file type cannot be edited');
        }

        $fullPath = $this->fileSystem->getFullPath($path);
        $this->security->validatePath($fullPath);

        if (file_put_contents($fullPath, $content) === false) {
            return JsonResponse::error('Failed to save file');
        }

        return JsonResponse::success();
    }

    public function createFile(Request $request): JsonResponse
    {
        if (!$this->config->createTextFiles) {
            return JsonResponse::error('File creation disabled', 403);
        }

        $path = $request->post('path', '');
        $name = $request->post('name', '');
        $content = $request->post('content', '');

        if (!is_string($name) || $name === '') {
            return JsonResponse::error('File name required');
        }
        if (!is_string($path)) {
            $path = '';
        }

        $ext = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->config->editableTextFileExts, true)) {
            return JsonResponse::error('Only these extensions allowed: ' . implode(', ', $this->config->editableTextFileExts));
        }

        $name = $this->security->sanitizeFilename($name);
        $fullPath = $this->config->currentPath . $path . $name;
        $this->security->validatePath($fullPath);

        if (is_file($fullPath)) {
            return JsonResponse::error('File already exists');
        }

        if (file_put_contents($fullPath, $content) === false) {
            return JsonResponse::error('Failed to create file');
        }

        @chmod($fullPath, $this->config->filePermission);

        return JsonResponse::success(['path' => $path . $name]);
    }

    private function extractZip(string $file, string $destDir): bool
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            return false;
        }

        $realDest = realpath($destDir);
        if ($realDest === false) {
            $zip->close();
            return false;
        }

        // Validate all entry paths before extracting (Zip Slip prevention)
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false) {
                continue;
            }
            if (str_contains($entryName, '..')) {
                $zip->close();
                return false;
            }
            $targetPath = $realDest . DIRECTORY_SEPARATOR . $entryName;
            $normalizedTarget = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetPath);
            if (!str_starts_with($normalizedTarget, $realDest . DIRECTORY_SEPARATOR) && $normalizedTarget !== $realDest) {
                $zip->close();
                return false;
            }
        }

        $result = $zip->extractTo($destDir);
        $zip->close();

        if ($result) {
            $this->removeBlacklistedFiles($destDir);
        }

        return $result;
    }

    private function extractTar(string $file, string $destDir): bool
    {
        if (!class_exists('PharData')) {
            return false;
        }

        try {
            $phar = new \PharData($file);

            $realDest = realpath($destDir);
            if ($realDest === false) {
                return false;
            }

            // Validate all entry paths before extracting (Zip Slip prevention)
            foreach (new \RecursiveIteratorIterator($phar) as $entry) {
                /** @var \PharFileInfo $entry */
                $entryPath = $entry->getPathname();
                // PharData entries use phar:// prefix — extract relative path
                $relativePath = preg_replace('#^phar://.+\.(?:tar|gz|bz2)/(.+)$#', '$1', $entryPath);
                if ($relativePath !== null && str_contains($relativePath, '..')) {
                    return false;
                }
            }

            $phar->extractTo($destDir, null, true);

            $this->removeBlacklistedFiles($destDir);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Remove files with blacklisted extensions from an extracted directory.
     */
    private function removeBlacklistedFiles(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = mb_strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                try {
                    $this->security->validateExtension($ext);
                } catch (\RFM\Exception\InvalidExtensionException) {
                    @unlink($file->getPathname());
                }
            }
        }
    }
}
