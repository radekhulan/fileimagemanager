<?php

declare(strict_types=1);

namespace RFM\Service;

use RFM\Config\AppConfig;
use RFM\DTO\{FileItem, BreadcrumbItem};
use RFM\Enum\{FileCategory, SortField};
use RFM\Exception\{FileNotFoundException, PathTraversalException};

final class FileSystemService
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly SecurityService $security,
        private readonly ThumbnailService $thumbnails,
    ) {}

    /**
     * List the contents of a directory with optional pagination.
     *
     * @return array{items: FileItem[], breadcrumb: BreadcrumbItem[], counts: array{files: int, folders: int}, totalSize: int, total: int}
     */
    public function listDirectory(
        string $subdir = '',
        SortField $sortBy = SortField::Name,
        bool $descending = false,
        string $filter = '',
        ?string $typeFilter = null,
        int $limit = 0,
        int $offset = 0,
    ): array {
        $subdir = $this->normalizeSubdir($subdir);
        $fullPath = $this->config->currentPath . $subdir;

        $this->security->validatePath($fullPath);

        if (!is_dir($fullPath)) {
            throw new FileNotFoundException("Directory not found: {$subdir}");
        }

        // Pass 1: cheap scan — collect lightweight metadata for filtering/sorting
        $lightweight = $this->scanEntries($fullPath, $filter, $typeFilter);

        // Aggregate counts from all entries (before pagination)
        $fileCount = 0;
        $folderCount = 0;
        $totalSize = 0;
        foreach ($lightweight as $e) {
            if ($e['isDir']) {
                $folderCount++;
            } else {
                $fileCount++;
                $totalSize += $e['size'];
            }
        }

        // Sort lightweight entries (folders first, then files)
        $lightweight = $this->sortLightweight($lightweight, $sortBy, $descending);

        $total = count($lightweight);

        // Apply pagination
        if ($limit > 0) {
            $lightweight = array_slice($lightweight, $offset, $limit);
        }

        // Pass 2: build full FileItem DTOs only for the paginated slice
        $items = [];
        foreach ($lightweight as $e) {
            $items[] = $this->buildFileItem($fullPath, $subdir, $e['name']);
        }

        $breadcrumb = $this->buildBreadcrumb($subdir);

        return [
            'items' => $items,
            'breadcrumb' => $breadcrumb,
            'counts' => [
                'files' => $fileCount,
                'folders' => $folderCount,
            ],
            'totalSize' => $totalSize,
            'total' => $total,
        ];
    }

    /**
     * Cheap first-pass scan: collect only lightweight metadata needed for filtering and sorting.
     *
     * @return list<array{name: string, isDir: bool, size: int, mtime: int, ext: string, category: string}>
     */
    private function scanEntries(string $fullPath, string $filter, ?string $typeFilter): array
    {
        $entries = @scandir($fullPath);
        if ($entries === false) {
            throw new FileNotFoundException("Cannot read directory");
        }

        $result = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $fullPath . $entry;
            $isDir = is_dir($entryPath);

            // Skip hidden items
            if ($isDir) {
                if (in_array($entry, $this->config->hiddenFolders, true)) {
                    continue;
                }
            } else {
                if (in_array($entry, $this->config->hiddenFiles, true)) {
                    continue;
                }
            }

            // Text filter
            if ($filter !== '' && mb_stripos($entry, $filter) === false) {
                continue;
            }

            $ext = $isDir ? '' : mb_strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            $category = $isDir ? 'directory' : FileCategory::fromExtension($ext, $this->config->getExtConfig())->value;

            // Type filter
            if ($typeFilter !== null && !$isDir) {
                $matches = match ($typeFilter) {
                    'image' => $category === 'image',
                    'video' => $category === 'video',
                    'audio' => $category === 'audio',
                    'file' => $category === 'document',
                    'archive' => $category === 'archive',
                    default => true,
                };
                if (!$matches) {
                    continue;
                }
            }

            $size = $isDir ? 0 : (int) @filesize($entryPath);
            $mtime = (int) @filemtime($entryPath);

            $result[] = [
                'name' => $entry,
                'isDir' => $isDir,
                'size' => $size,
                'mtime' => $mtime,
                'ext' => $ext,
                'category' => $category,
            ];
        }

        return $result;
    }

    /**
     * Sort lightweight entry arrays (folders first, then files).
     *
     * @param list<array{name: string, isDir: bool, size: int, mtime: int, ext: string}> $entries
     * @return list<array{name: string, isDir: bool, size: int, mtime: int, ext: string}>
     */
    private function sortLightweight(array $entries, SortField $sortBy, bool $descending): array
    {
        $folders = array_filter($entries, fn(array $e) => $e['isDir']);
        $files = array_filter($entries, fn(array $e) => !$e['isDir']);

        $multiplier = $descending ? -1 : 1;
        $comparator = match ($sortBy) {
            SortField::Name => fn(array $a, array $b) => $multiplier * strnatcasecmp($a['name'], $b['name']),
            SortField::Date => fn(array $a, array $b) => $multiplier * ($a['mtime'] <=> $b['mtime']),
            SortField::Size => fn(array $a, array $b) => $multiplier * ($a['size'] <=> $b['size']),
            SortField::Extension => fn(array $a, array $b) => $multiplier * strcmp($a['ext'], $b['ext']),
        };

        usort($folders, $comparator);
        usort($files, $comparator);

        return [...$folders, ...$files];
    }

    /**
     * Get info about a single file or folder.
     *
     * @return array<string, mixed>
     */
    public function getFileInfo(string $relativePath): array
    {
        $fullPath = $this->config->currentPath . $relativePath;
        $this->security->validatePath($fullPath);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("File not found: {$relativePath}");
        }

        $dir = dirname($relativePath) . '/';
        $name = basename($relativePath);
        $item = $this->buildFileItem($this->config->currentPath . dirname($relativePath) . '/', $dir, $name);

        return $item->toArray();
    }

    /**
     * Delete a file and its thumbnail.
     */
    public function deleteFile(string $relativePath): void
    {
        $fullPath = $this->config->currentPath . $relativePath;
        $this->security->validatePath($fullPath);

        if (!is_file($fullPath)) {
            throw new FileNotFoundException("File not found: {$relativePath}");
        }

        @unlink($fullPath);
        $this->thumbnails->deleteThumbnail($relativePath);
    }

    /**
     * Delete a directory and its thumbnails recursively.
     */
    public function deleteDirectory(string $relativeDir): void
    {
        $fullPath = $this->config->currentPath . $relativeDir;
        $this->security->validatePath($fullPath);

        if (!is_dir($fullPath)) {
            throw new FileNotFoundException("Directory not found: {$relativeDir}");
        }

        if ($this->security->isUploadDir($fullPath)) {
            throw new PathTraversalException('Cannot delete the upload root directory');
        }

        $this->deleteDirectoryRecursive($fullPath);
        $this->thumbnails->deleteDirectoryThumbnails($relativeDir);
    }

    /**
     * Create a new folder.
     */
    public function createFolder(string $parentDir, string $name): string
    {
        $name = $this->security->sanitizeFilename($name, isFolder: true);
        $fullPath = $this->config->currentPath . $parentDir . $name;

        $this->security->validatePath($fullPath);

        if (is_dir($fullPath)) {
            return $parentDir . $name . '/';
        }

        if (!@mkdir($fullPath, $this->config->folderPermission)) {
            throw new \RuntimeException('Failed to create folder');
        }

        // Create thumbnail directory
        $thumbDir = $this->config->thumbsBasePath . $parentDir . $name;
        if (!is_dir($thumbDir)) {
            @mkdir($thumbDir, $this->config->folderPermission, true);
        }

        return $parentDir . $name . '/';
    }

    /**
     * Rename a file.
     */
    public function renameFile(string $relativePath, string $newName): string
    {
        $fullPath = $this->config->currentPath . $relativePath;
        $this->security->validatePath($fullPath);

        if (!is_file($fullPath)) {
            throw new FileNotFoundException("File not found: {$relativePath}");
        }

        $ext = pathinfo($relativePath, PATHINFO_EXTENSION);
        $newName = $this->security->sanitizeFilename($newName);

        // Preserve original extension
        if ($ext !== '') {
            $newNameExt = pathinfo($newName, PATHINFO_EXTENSION);
            if (mb_strtolower($newNameExt) !== mb_strtolower($ext)) {
                $newName .= '.' . $ext;
            }
        }

        // Check all dot-separated parts against blacklist (double-extension attack)
        $this->security->validateFilenameExtensions($newName);

        $dir = dirname($relativePath);
        $newRelativePath = ($dir !== '.' ? $dir . '/' : '') . $newName;
        $newFullPath = $this->config->currentPath . $newRelativePath;

        $this->security->validatePath($newFullPath);

        if (!@rename($fullPath, $newFullPath)) {
            throw new \RuntimeException('Failed to rename file');
        }

        $this->thumbnails->renameThumbnail($relativePath, $newRelativePath);

        return $newRelativePath;
    }

    /**
     * Rename a folder.
     */
    public function renameFolder(string $relativeDir, string $newName): string
    {
        $relativeDir = rtrim($relativeDir, '/');
        $fullPath = $this->config->currentPath . $relativeDir;
        $this->security->validatePath($fullPath);

        if (!is_dir($fullPath)) {
            throw new FileNotFoundException("Folder not found: {$relativeDir}");
        }

        $newName = $this->security->sanitizeFilename($newName, isFolder: true);
        $parent = dirname($relativeDir);
        $newRelativeDir = ($parent !== '.' ? $parent . '/' : '') . $newName;
        $newFullPath = $this->config->currentPath . $newRelativeDir;

        $this->security->validatePath($newFullPath);

        if (!@rename($fullPath, $newFullPath)) {
            throw new \RuntimeException('Failed to rename folder');
        }

        // Rename thumbnail directory
        $oldThumbDir = $this->config->thumbsBasePath . $relativeDir;
        $newThumbDir = $this->config->thumbsBasePath . $newRelativeDir;
        if (is_dir($oldThumbDir)) {
            @rename($oldThumbDir, $newThumbDir);
        }

        return $newRelativeDir . '/';
    }

    /**
     * Duplicate a file.
     */
    public function duplicateFile(string $relativePath, ?string $newName = null): string
    {
        $fullPath = $this->config->currentPath . $relativePath;
        $this->security->validatePath($fullPath);

        if (!is_file($fullPath)) {
            throw new FileNotFoundException("File not found: {$relativePath}");
        }

        $dir = dirname($relativePath);
        $dirPrefix = $dir !== '.' ? $dir . '/' : '';
        $filename = pathinfo($relativePath, PATHINFO_FILENAME);
        $ext = pathinfo($relativePath, PATHINFO_EXTENSION);

        if ($newName !== null) {
            $newName = $this->security->sanitizeFilename($newName);
        } else {
            // Auto-generate copy name
            $counter = 1;
            do {
                $newName = $filename . '_copy' . ($counter > 1 ? $counter : '') . ($ext ? '.' . $ext : '');
                $counter++;
            } while (is_file($this->config->currentPath . $dirPrefix . $newName));
        }

        $newRelativePath = $dirPrefix . $newName;
        $newFullPath = $this->config->currentPath . $newRelativePath;

        $this->security->validatePath($newFullPath);

        if (!@copy($fullPath, $newFullPath)) {
            throw new \RuntimeException('Failed to duplicate file');
        }

        @chmod($newFullPath, $this->config->filePermission);

        $this->thumbnails->copyThumbnail($relativePath, $newRelativePath);

        return $newRelativePath;
    }

    /**
     * Copy a directory recursively.
     */
    public function copyDirectory(string $sourceDir, string $destDir): void
    {
        $sourceFull = $this->config->currentPath . $sourceDir;
        $destFull = $this->config->currentPath . $destDir;

        $this->security->validatePath($sourceFull);
        $this->security->validatePath($destFull);

        $this->copyDirectoryRecursive($sourceFull, $destFull);

        // Copy thumbnails too
        $sourceThumb = $this->config->thumbsBasePath . $sourceDir;
        $destThumb = $this->config->thumbsBasePath . $destDir;
        if (is_dir($sourceThumb)) {
            $this->copyDirectoryRecursive($sourceThumb, $destThumb);
        }
    }

    /**
     * Move a file.
     */
    public function moveFile(string $sourcePath, string $destPath): void
    {
        $sourceFull = $this->config->currentPath . $sourcePath;
        $destFull = $this->config->currentPath . $destPath;

        $this->security->validatePath($sourceFull);
        $this->security->validatePath($destFull);

        if (!is_file($sourceFull)) {
            throw new FileNotFoundException("Source file not found: {$sourcePath}");
        }

        $destDir = dirname($destFull);
        if (!is_dir($destDir)) {
            @mkdir($destDir, $this->config->folderPermission, true);
        }

        if (!@rename($sourceFull, $destFull)) {
            throw new \RuntimeException('Failed to move file');
        }

        // Move thumbnail
        $sourceThumb = $this->config->thumbsBasePath . $sourcePath;
        $destThumb = $this->config->thumbsBasePath . $destPath;
        if (is_file($sourceThumb)) {
            $destThumbDir = dirname($destThumb);
            if (!is_dir($destThumbDir)) {
                @mkdir($destThumbDir, $this->config->folderPermission, true);
            }
            @rename($sourceThumb, $destThumb);
        }
    }

    /**
     * Move a directory.
     */
    public function moveDirectory(string $sourceDir, string $destDir): void
    {
        $sourceFull = $this->config->currentPath . rtrim($sourceDir, '/');
        $destFull = $this->config->currentPath . rtrim($destDir, '/');

        $this->security->validatePath($sourceFull);
        $this->security->validatePath($destFull);

        // Check not moving into itself
        if (str_starts_with($this->normalizePath($destFull), $this->normalizePath($sourceFull))) {
            throw new PathTraversalException('Cannot move folder into itself');
        }

        $destParent = dirname($destFull);
        if (!is_dir($destParent)) {
            @mkdir($destParent, $this->config->folderPermission, true);
        }

        if (!@rename($sourceFull, $destFull)) {
            throw new \RuntimeException('Failed to move directory');
        }

        // Move thumbnails
        $sourceThumb = $this->config->thumbsBasePath . rtrim($sourceDir, '/');
        $destThumb = $this->config->thumbsBasePath . rtrim($destDir, '/');
        if (is_dir($sourceThumb)) {
            @rename($sourceThumb, $destThumb);
        }
    }

    /**
     * Change file/folder permissions (chmod).
     */
    public function changePermissions(string $relativePath, int $mode, string $recursive = 'none'): void
    {
        $fullPath = $this->config->currentPath . $relativePath;
        $this->security->validatePath($fullPath);

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException("Path not found: {$relativePath}");
        }

        if ($recursive === 'none' || !is_dir($fullPath)) {
            @chmod($fullPath, $mode);
            return;
        }

        $this->chmodRecursive($fullPath, $mode, $recursive);
    }

    /**
     * Get the full filesystem path for a relative path.
     */
    public function getFullPath(string $relativePath): string
    {
        return $this->config->currentPath . $relativePath;
    }

    /**
     * Validate that a full file path is within allowed directories.
     */
    public function validateFilePath(string $fullPath): void
    {
        $this->security->validatePath($fullPath);
    }

    /**
     * Calculate total size of a folder.
     */
    public function calculateFolderSize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $entries = @scandir($path);
        if ($entries === false) {
            return 0;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($entryPath)) {
                $size += $this->calculateFolderSize($entryPath);
            } else {
                $size += (int) @filesize($entryPath);
            }
        }

        return $size;
    }

    /**
     * Count files in a directory recursively.
     */
    public function countFiles(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $count = 0;
        $entries = @scandir($path);
        if ($entries === false) {
            return 0;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($entryPath)) {
                $count += $this->countFiles($entryPath);
            } else {
                $count++;
            }
        }

        return $count;
    }

    private function buildFileItem(string $basePath, string $subdir, string $entry): FileItem
    {
        $fullPath = $basePath . $entry;
        $isDir = is_dir($fullPath);

        if ($isDir) {
            $size = $this->config->showFolderSize ? $this->calculateFolderSize($fullPath) : 0;

            return new FileItem(
                name: $entry,
                path: $subdir . $entry,
                isDir: true,
                size: $size,
                modifiedAt: (int) @filemtime($fullPath),
                extension: '',
                category: FileCategory::Directory,
            );
        }

        $ext = mb_strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        $category = FileCategory::fromExtension($ext, $this->config->getExtConfig());

        $thumbUrl = null;
        $width = null;
        $height = null;

        if ($category === FileCategory::Image) {
            $thumbUrl = $this->thumbnails->getThumbnailUrl($subdir . $entry);
            $dims = $this->getImageDimensionsFast($fullPath);
            if ($dims !== null) {
                [$width, $height] = $dims;
            }
        }

        $permissions = null;
        if ($this->config->chmodFiles) {
            $perms = @fileperms($fullPath);
            if ($perms !== false) {
                $permissions = substr(decoct($perms), -3);
            }
        }

        return new FileItem(
            name: $entry,
            path: $subdir . $entry,
            isDir: false,
            size: (int) @filesize($fullPath),
            modifiedAt: (int) @filemtime($fullPath),
            extension: $ext,
            category: $category,
            thumbnailUrl: $thumbUrl,
            width: $width,
            height: $height,
            permissions: $permissions,
        );
    }

    /**
     * @return BreadcrumbItem[]
     */
    private function buildBreadcrumb(string $subdir): array
    {
        $breadcrumb = [new BreadcrumbItem(name: 'Home', path: '')];

        if ($subdir === '') {
            return $breadcrumb;
        }

        $parts = array_filter(explode('/', $subdir));
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath .= $part . '/';
            $breadcrumb[] = new BreadcrumbItem(name: $part, path: $currentPath);
        }

        return $breadcrumb;
    }

    /**
     * @param FileItem[] $items
     * @return FileItem[]
     */
    private function sortItems(array $items, SortField $sortBy, bool $descending): array
    {
        // Separate folders and files
        $folders = array_filter($items, fn(FileItem $i) => $i->isDir);
        $files = array_filter($items, fn(FileItem $i) => !$i->isDir);

        // Sort each group
        $comparator = $this->getComparator($sortBy, $descending);
        usort($folders, $comparator);
        usort($files, $comparator);

        return [...$folders, ...$files];
    }

    /**
     * @return callable(FileItem, FileItem): int
     */
    private function getComparator(SortField $sortBy, bool $descending): callable
    {
        $multiplier = $descending ? -1 : 1;

        return match ($sortBy) {
            SortField::Name => fn(FileItem $a, FileItem $b) => $multiplier * strnatcasecmp($a->name, $b->name),
            SortField::Date => fn(FileItem $a, FileItem $b) => $multiplier * ($a->modifiedAt <=> $b->modifiedAt),
            SortField::Size => fn(FileItem $a, FileItem $b) => $multiplier * ($a->size <=> $b->size),
            SortField::Extension => fn(FileItem $a, FileItem $b) => $multiplier * strcmp($a->extension, $b->extension),
        };
    }

    private function matchesTypeFilter(FileItem $item, string $typeFilter): bool
    {
        if ($item->isDir) {
            return true; // Always show folders
        }

        return match ($typeFilter) {
            'image' => $item->category === FileCategory::Image,
            'video' => $item->category === FileCategory::Video,
            'audio' => $item->category === FileCategory::Audio,
            'file' => $item->category === FileCategory::Document,
            'archive' => $item->category === FileCategory::Archive,
            default => true,
        };
    }

    private function normalizeSubdir(string $subdir): string
    {
        $subdir = trim($subdir, '/');
        if ($subdir !== '') {
            $this->security->checkRelativePath($subdir);
            $subdir .= '/';
        }
        return $subdir;
    }

    private function deleteDirectoryRecursive(string $dir): void
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private function copyDirectoryRecursive(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            @mkdir($dest, $this->config->folderPermission, true);
        }

        $entries = @scandir($source);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $srcPath = $source . DIRECTORY_SEPARATOR . $entry;
            $dstPath = $dest . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($srcPath)) {
                $this->copyDirectoryRecursive($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
                @chmod($dstPath, $this->config->filePermission);
            }
        }
    }

    private function chmodRecursive(string $path, int $mode, string $recursive): void
    {
        @chmod($path, $mode);

        $entries = @scandir($path);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($entryPath)) {
                if ($recursive === 'folders' || $recursive === 'both') {
                    @chmod($entryPath, $mode);
                }
                $this->chmodRecursive($entryPath, $mode, $recursive);
            } else {
                if ($recursive === 'files' || $recursive === 'both') {
                    @chmod($entryPath, $mode);
                }
            }
        }
    }

    /**
     * Read image dimensions from file headers without loading the entire image.
     *
     * @return ?array{0: int, 1: int} [width, height] or null on failure
     */
    private function getImageDimensionsFast(string $path): ?array
    {
        $ext = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $fp = @fopen($path, 'rb');
        if ($fp === false) {
            return null;
        }

        try {
            return match ($ext) {
                'png' => $this->readPngDimensions($fp),
                'gif' => $this->readGifDimensions($fp),
                'bmp' => $this->readBmpDimensions($fp),
                'webp' => $this->readWebpDimensions($fp),
                'jpg', 'jpeg' => $this->readJpegDimensions($fp),
                default => null,
            };
        } finally {
            fclose($fp);
        }
    }

    /** @param resource $fp */
    private function readPngDimensions($fp): ?array
    {
        $data = fread($fp, 24);
        if ($data === false || strlen($data) < 24) {
            return null;
        }
        // PNG signature (8 bytes) + IHDR length (4) + "IHDR" (4) + width (4) + height (4)
        $width = unpack('N', $data, 16);
        $height = unpack('N', $data, 20);
        if ($width === false || $height === false) {
            return null;
        }
        return [$width[1], $height[1]];
    }

    /** @param resource $fp */
    private function readGifDimensions($fp): ?array
    {
        $data = fread($fp, 10);
        if ($data === false || strlen($data) < 10) {
            return null;
        }
        // GIF87a/GIF89a header: bytes 6-7 = width, 8-9 = height (little-endian)
        $dims = unpack('vwidth/vheight', $data, 6);
        return $dims !== false ? [$dims['width'], $dims['height']] : null;
    }

    /** @param resource $fp */
    private function readBmpDimensions($fp): ?array
    {
        $data = fread($fp, 26);
        if ($data === false || strlen($data) < 26) {
            return null;
        }
        // BMP header: bytes 18-21 = width, 22-25 = height (little-endian signed int32)
        $dims = unpack('lwidth/lheight', $data, 18);
        if ($dims === false) {
            return null;
        }
        return [abs($dims['width']), abs($dims['height'])];
    }

    /** @param resource $fp */
    private function readWebpDimensions($fp): ?array
    {
        $data = fread($fp, 30);
        if ($data === false || strlen($data) < 30) {
            return null;
        }
        // RIFF....WEBP
        if (substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WEBP') {
            return null;
        }

        $chunk = substr($data, 12, 4);
        if ($chunk === 'VP8 ') {
            // Lossy: skip 4 bytes chunk size + 3 bytes frame tag + 3 bytes start code
            if (strlen($data) < 30) {
                return null;
            }
            // Bytes 26-27 = width (14 bits), 28-29 = height (14 bits)
            $w = unpack('v', $data, 26);
            $h = unpack('v', $data, 28);
            if ($w === false || $h === false) {
                return null;
            }
            return [$w[1] & 0x3FFF, $h[1] & 0x3FFF];
        }

        if ($chunk === 'VP8L') {
            // Lossless: signature byte at offset 21, then 4 bytes with packed width/height
            if (strlen($data) < 25) {
                $data .= fread($fp, 25 - strlen($data)) ?: '';
            }
            if (strlen($data) < 25) {
                return null;
            }
            $bits = unpack('V', $data, 21);
            if ($bits === false) {
                return null;
            }
            $val = $bits[1];
            $width = ($val & 0x3FFF) + 1;
            $height = (($val >> 14) & 0x3FFF) + 1;
            return [$width, $height];
        }

        if ($chunk === 'VP8X') {
            // Extended: bytes 24-26 = width-1 (24-bit LE), 27-29 = height-1 (24-bit LE)
            $w = ord($data[24]) | (ord($data[25]) << 8) | (ord($data[26]) << 16);
            $h = ord($data[27]) | (ord($data[28]) << 8) | (ord($data[29]) << 16);
            return [$w + 1, $h + 1];
        }

        return null;
    }

    /** @param resource $fp */
    private function readJpegDimensions($fp): ?array
    {
        // Read up to 64 KB looking for SOF marker
        $maxRead = 65536;
        $data = fread($fp, $maxRead);
        if ($data === false || strlen($data) < 4) {
            return null;
        }

        $len = strlen($data);
        $i = 2; // skip SOI marker (FF D8)

        while ($i < $len - 1) {
            if (ord($data[$i]) !== 0xFF) {
                $i++;
                continue;
            }

            $marker = ord($data[$i + 1]);

            // SOF markers: C0-C3, C5-C7, C9-CB, CD-CF
            if (($marker >= 0xC0 && $marker <= 0xC3) ||
                ($marker >= 0xC5 && $marker <= 0xC7) ||
                ($marker >= 0xC9 && $marker <= 0xCB) ||
                ($marker >= 0xCD && $marker <= 0xCF)) {
                if ($i + 9 >= $len) {
                    return null;
                }
                $height = (ord($data[$i + 5]) << 8) | ord($data[$i + 6]);
                $width = (ord($data[$i + 7]) << 8) | ord($data[$i + 8]);
                return [$width, $height];
            }

            // Skip non-SOF segments
            if ($i + 3 >= $len) {
                return null;
            }
            $segLen = (ord($data[$i + 2]) << 8) | ord($data[$i + 3]);
            $i += 2 + $segLen;
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        $path = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
        }
        return $path;
    }
}
