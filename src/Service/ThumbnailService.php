<?php

declare(strict_types=1);

namespace RFM\Service;

use RFM\Config\AppConfig;
use RFM\Enum\ImageResizeMode;

final class ThumbnailService
{
    private const THUMB_WIDTH = 213;
    private const THUMB_HEIGHT = 160;

    public function __construct(
        private readonly AppConfig $config,
        private readonly ImageProcessingService $imageProcessor,
    ) {}

    /**
     * Get the URL for a thumbnail of the given file.
     */
    public function getThumbnailUrl(string $relativePath): string
    {
        $thumbPath = $this->getThumbPath($relativePath);

        // Generate thumbnail if it doesn't exist
        if (!is_file($thumbPath)) {
            $sourcePath = $this->config->currentPath . $relativePath;
            if (is_file($sourcePath)) {
                try {
                    $this->createThumbnail($sourcePath, $thumbPath);
                } catch (\Throwable) {
                    // Thumbnail generation failed - continue without it
                }
            }
        }

        $url = $this->config->thumbsUploadDir . $relativePath;

        if ($this->config->addTimeToImg && is_file($thumbPath)) {
            $url .= '?' . filemtime($thumbPath);
        }

        return $url;
    }

    /**
     * Create a thumbnail for an image.
     */
    public function createThumbnail(
        string $sourcePath,
        string $destPath,
        int $width = self::THUMB_WIDTH,
        int $height = self::THUMB_HEIGHT,
        ImageResizeMode $mode = ImageResizeMode::Crop,
    ): bool {
        // Ensure target directory exists
        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            @mkdir($dir, $this->config->folderPermission, true);
        }

        // Check memory before processing
        if (!$this->imageProcessor->checkMemory($sourcePath, $width, $height)) {
            return false;
        }

        return $this->imageProcessor->resize($sourcePath, $destPath, $width, $height, $mode, 80);
    }

    /**
     * Generate all configured fixed-path thumbnails for an uploaded image.
     */
    public function generateFixedThumbnails(string $sourcePath, string $relativeDir): void
    {
        if (!$this->config->fixedImageCreation) {
            return;
        }

        $count = count($this->config->fixedPathFromFilemanager);

        for ($i = 0; $i < $count; $i++) {
            $fixedPath = $this->config->fixedPathFromFilemanager[$i] ?? '';
            $prepend = $this->config->fixedImageCreationNameToPrepend[$i] ?? '';
            $append = $this->config->fixedImageCreationToAppend[$i] ?? '';
            $width = $this->config->fixedImageCreationWidth[$i] ?? 0;
            $height = $this->config->fixedImageCreationHeight[$i] ?? 0;
            $option = $this->config->fixedImageCreationOption[$i] ?? 'auto';

            if ($width === 0 && $height === 0) {
                continue;
            }

            $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
            $ext = pathinfo($sourcePath, PATHINFO_EXTENSION);
            $newName = $prepend . $filename . $append . '.' . $ext;

            // fixedPath is relative to upload root + current browsing directory
            $baseDir = rtrim($this->config->currentPath, '/\\') . '/';
            $destDir = $baseDir . ltrim($relativeDir, '/') . ltrim($fixedPath, '/');
            if (!is_dir($destDir)) {
                @mkdir($destDir, $this->config->folderPermission, true);
            }

            $destPath = rtrim($destDir, '/\\') . '/' . $newName;
            $mode = ImageResizeMode::fromLegacy($option);

            $this->imageProcessor->resize($sourcePath, $destPath, $width, $height ?: null, $mode, 80);
        }
    }

    /**
     * Generate all configured relative-path thumbnails for an uploaded image.
     */
    public function generateRelativeThumbnails(string $sourcePath, string $sourceDir): void
    {
        if (!$this->config->relativeImageCreation) {
            return;
        }

        $count = count($this->config->relativePathFromCurrentPos);

        for ($i = 0; $i < $count; $i++) {
            $relPath = $this->config->relativePathFromCurrentPos[$i] ?? './';
            $prepend = $this->config->relativeImageCreationNameToPrepend[$i] ?? '';
            $append = $this->config->relativeImageCreationNameToAppend[$i] ?? '';
            $width = $this->config->relativeImageCreationWidth[$i] ?? 0;
            $height = $this->config->relativeImageCreationHeight[$i] ?? 0;
            $option = $this->config->relativeImageCreationOption[$i] ?? 'auto';

            if ($width === 0 && $height === 0) {
                continue;
            }

            $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
            $ext = pathinfo($sourcePath, PATHINFO_EXTENSION);
            $newName = $prepend . $filename . $append . '.' . $ext;

            $destDir = rtrim($sourceDir, '/') . '/' . ltrim($relPath, '/');
            if (!is_dir($destDir)) {
                @mkdir($destDir, $this->config->folderPermission, true);
            }

            $destPath = rtrim($destDir, '/') . '/' . $newName;
            $mode = ImageResizeMode::fromLegacy($option);

            $this->imageProcessor->resize($sourcePath, $destPath, $width, $height ?: null, $mode, 80);
        }
    }

    /**
     * Delete thumbnail for a file.
     */
    public function deleteThumbnail(string $relativePath): void
    {
        $thumbPath = $this->getThumbPath($relativePath);
        if (is_file($thumbPath)) {
            @unlink($thumbPath);
        }
    }

    /**
     * Delete all thumbnails in a directory (recursively).
     */
    public function deleteDirectoryThumbnails(string $relativeDir): void
    {
        $thumbDir = $this->config->thumbsBasePath . $relativeDir;
        if (is_dir($thumbDir)) {
            $this->deleteDirectoryRecursive($thumbDir);
        }
    }

    /**
     * Rename a thumbnail to match a renamed file.
     */
    public function renameThumbnail(string $oldRelativePath, string $newRelativePath): void
    {
        $oldPath = $this->getThumbPath($oldRelativePath);
        $newPath = $this->getThumbPath($newRelativePath);

        if (is_file($oldPath)) {
            $newDir = dirname($newPath);
            if (!is_dir($newDir)) {
                @mkdir($newDir, $this->config->folderPermission, true);
            }
            @rename($oldPath, $newPath);
        }
    }

    /**
     * Copy thumbnails for a duplicated/copied file.
     */
    public function copyThumbnail(string $sourceRelativePath, string $destRelativePath): void
    {
        $sourcePath = $this->getThumbPath($sourceRelativePath);
        $destPath = $this->getThumbPath($destRelativePath);

        if (is_file($sourcePath)) {
            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                @mkdir($destDir, $this->config->folderPermission, true);
            }
            @copy($sourcePath, $destPath);
        }
    }

    private function getThumbPath(string $relativePath): string
    {
        return $this->config->thumbsBasePath . $relativePath;
    }

    private function deleteDirectoryRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
