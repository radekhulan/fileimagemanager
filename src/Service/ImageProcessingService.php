<?php

declare(strict_types=1);

namespace RFM\Service;

use RFM\Enum\ImageResizeMode;

/**
 * GD-based image processing service.
 * Replaces php_image_magician.php with a modern, typed implementation.
 */
final class ImageProcessingService
{
    /**
     * Resize an image and save to destination.
     */
    public function resize(
        string $sourcePath,
        string $destPath,
        int $newWidth,
        ?int $newHeight = null,
        ImageResizeMode $mode = ImageResizeMode::Auto,
        int $quality = 80,
    ): bool {
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        [$origWidth, $origHeight, $type] = $imageInfo;
        $source = $this->createFromFile($sourcePath, $type);
        if ($source === null) {
            return false;
        }

        // Calculate target dimensions
        if ($newHeight === null || $newHeight === 0) {
            $newHeight = (int) round($origHeight * ($newWidth / $origWidth));
        }

        [$targetWidth, $targetHeight, $srcX, $srcY, $srcW, $srcH] = $this->calculateDimensions(
            $origWidth, $origHeight, $newWidth, $newHeight, $mode,
        );

        // Create target image
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($target === false) {
            return false;
        }

        // Preserve transparency for PNG/GIF/WebP
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF || $type === IMAGETYPE_WEBP) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefill($target, 0, 0, $transparent);
        }

        // Resample
        imagecopyresampled($target, $source, 0, 0, $srcX, $srcY, $targetWidth, $targetHeight, $srcW, $srcH);

        // Save
        $result = $this->saveImage($target, $destPath, $type, $quality);

        return $result;
    }

    /**
     * Apply watermark to an image.
     */
    public function applyWatermark(
        string $imagePath,
        string $watermarkPath,
        string $position = 'br',
        int $padding = 10,
    ): bool {
        $imageInfo = @getimagesize($imagePath);
        $wmInfo = @getimagesize($watermarkPath);

        if ($imageInfo === false || $wmInfo === false) {
            return false;
        }

        [$imgW, $imgH, $imgType] = $imageInfo;
        [$wmW, $wmH, $wmType] = $wmInfo;

        $image = $this->createFromFile($imagePath, $imgType);
        $watermark = $this->createFromFile($watermarkPath, $wmType);

        if ($image === null || $watermark === null) {
            return false;
        }

        // Calculate watermark position
        [$destX, $destY] = $this->calculateWatermarkPosition(
            $imgW, $imgH, $wmW, $wmH, $position, $padding,
        );

        // Merge watermark
        imagecopy($image, $watermark, $destX, $destY, 0, 0, $wmW, $wmH);

        // Save back
        $result = $this->saveImage($image, $imagePath, $imgType, 90);

        return $result;
    }

    /**
     * Auto-orient image based on EXIF data.
     */
    public function autoOrient(string $imagePath): bool
    {
        if (!function_exists('exif_read_data')) {
            return false;
        }

        $exif = @exif_read_data($imagePath);
        if ($exif === false || !isset($exif['Orientation'])) {
            return false;
        }

        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            return false;
        }

        $image = $this->createFromFile($imagePath, $imageInfo[2]);
        if ($image === null) {
            return false;
        }

        $rotated = match ((int) $exif['Orientation']) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return false;
        }

        $result = $this->saveImage($rotated, $imagePath, $imageInfo[2], 90);

        return $result;
    }

    /**
     * Check if enough memory is available for image processing.
     */
    public function checkMemory(string $imagePath, int $targetWidth, int $targetHeight): bool
    {
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            return false;
        }

        [$width, $height] = $imageInfo;
        $channels = $imageInfo['channels'] ?? 4;
        $bits = $imageInfo['bits'] ?? 8;

        // Memory needed for source image
        $sourceMemory = $width * $height * $channels * ($bits / 8);
        // Memory needed for target image
        $targetMemory = $targetWidth * $targetHeight * 4 * ($bits / 8);

        $totalNeeded = ($sourceMemory + $targetMemory) * 1.8; // Safety margin

        $memoryLimit = $this->getMemoryLimit();
        $currentUsage = memory_get_usage(true);

        return ($currentUsage + $totalNeeded) < $memoryLimit;
    }

    private function createFromFile(string $path, int $type): ?\GdImage
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path) ?: null,
            IMAGETYPE_PNG => @imagecreatefrompng($path) ?: null,
            IMAGETYPE_GIF => @imagecreatefromgif($path) ?: null,
            IMAGETYPE_WEBP => @imagecreatefromwebp($path) ?: null,
            IMAGETYPE_BMP => @imagecreatefrombmp($path) ?: null,
            default => null,
        };
    }

    private function saveImage(\GdImage $image, string $path, int $type, int $quality): bool
    {
        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, $quality),
            IMAGETYPE_PNG => imagepng($image, $path, (int) round(9 - ($quality / 100 * 9))),
            IMAGETYPE_GIF => imagegif($image, $path),
            IMAGETYPE_WEBP => imagewebp($image, $path, $quality),
            IMAGETYPE_BMP => imagebmp($image, $path),
            default => false,
        };
    }

    /**
     * @return array{int, int, int, int, int, int} [targetW, targetH, srcX, srcY, srcW, srcH]
     */
    private function calculateDimensions(
        int $origW, int $origH, int $newW, int $newH, ImageResizeMode $mode,
    ): array {
        return match ($mode) {
            ImageResizeMode::Exact => [$newW, $newH, 0, 0, $origW, $origH],

            ImageResizeMode::Portrait => [
                (int) round($origW * ($newH / $origH)),
                $newH,
                0, 0, $origW, $origH,
            ],

            ImageResizeMode::Landscape => [
                $newW,
                (int) round($origH * ($newW / $origW)),
                0, 0, $origW, $origH,
            ],

            ImageResizeMode::Auto => $this->calculateAuto($origW, $origH, $newW, $newH),

            ImageResizeMode::Crop => $this->calculateCrop($origW, $origH, $newW, $newH),
        };
    }

    /**
     * @return array{int, int, int, int, int, int}
     */
    private function calculateAuto(int $origW, int $origH, int $newW, int $newH): array
    {
        if ($origH < $origW) {
            // Landscape
            $targetW = $newW;
            $targetH = (int) round($origH * ($newW / $origW));
        } elseif ($origH > $origW) {
            // Portrait
            $targetW = (int) round($origW * ($newH / $origH));
            $targetH = $newH;
        } else {
            // Square - use smaller dimension
            $size = min($newW, $newH);
            $targetW = $size;
            $targetH = $size;
        }

        return [$targetW, $targetH, 0, 0, $origW, $origH];
    }

    /**
     * @return array{int, int, int, int, int, int}
     */
    private function calculateCrop(int $origW, int $origH, int $newW, int $newH): array
    {
        $origRatio = $origW / $origH;
        $newRatio = $newW / $newH;

        if ($newRatio > $origRatio) {
            $srcW = $origW;
            $srcH = (int) round($origW / $newRatio);
            $srcX = 0;
            $srcY = (int) round(($origH - $srcH) / 2);
        } else {
            $srcW = (int) round($origH * $newRatio);
            $srcH = $origH;
            $srcX = (int) round(($origW - $srcW) / 2);
            $srcY = 0;
        }

        return [$newW, $newH, $srcX, $srcY, $srcW, $srcH];
    }

    /**
     * @return array{int, int}
     */
    private function calculateWatermarkPosition(
        int $imgW, int $imgH, int $wmW, int $wmH, string $position, int $padding,
    ): array {
        // Check for coordinate format (e.g., "50x100")
        if (preg_match('/^(\d+)x(\d+)$/', $position, $matches)) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return match ($position) {
            'tl' => [$padding, $padding],
            't' => [(int) round(($imgW - $wmW) / 2), $padding],
            'tr' => [$imgW - $wmW - $padding, $padding],
            'l' => [$padding, (int) round(($imgH - $wmH) / 2)],
            'm' => [(int) round(($imgW - $wmW) / 2), (int) round(($imgH - $wmH) / 2)],
            'r' => [$imgW - $wmW - $padding, (int) round(($imgH - $wmH) / 2)],
            'bl' => [$padding, $imgH - $wmH - $padding],
            'b' => [(int) round(($imgW - $wmW) / 2), $imgH - $wmH - $padding],
            'br' => [$imgW - $wmW - $padding, $imgH - $wmH - $padding],
            default => [$imgW - $wmW - $padding, $imgH - $wmH - $padding],
        };
    }

    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int) $limit;
        $unit = strtolower(substr($limit, -1));

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
