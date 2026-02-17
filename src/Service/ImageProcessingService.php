<?php

declare(strict_types=1);

namespace RFM\Service;

use RFM\Config\AppConfig;
use RFM\Enum\ImageResizeMode;
use RFM\ImageDriver\ImageDriverInterface;

/**
 * Image processing service using pluggable driver (Imagick or GD).
 */
final class ImageProcessingService
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly ImageDriverInterface $driver,
    ) {}

    /** Maximum allowed image dimension (width or height) in pixels. */
    private const MAX_IMAGE_DIMENSION = 10000;

    /** Maximum file size in bytes before attempting image operations (50 MB). */
    private const MAX_FILE_SIZE = 50 * 1024 * 1024;

    /**
     * Map file extension to IMAGETYPE_* constant.
     */
    public static function imageTypeFromExtension(string $ext): int
    {
        return match (mb_strtolower($ext)) {
            'jpg', 'jpeg' => IMAGETYPE_JPEG,
            'png'         => IMAGETYPE_PNG,
            'gif'         => IMAGETYPE_GIF,
            'webp'        => IMAGETYPE_WEBP,
            'bmp'         => IMAGETYPE_BMP,
            default       => 0,
        };
    }

    /**
     * Get image dimensions and type via the driver.
     *
     * @return array{int, int, int}|false  [width, height, IMAGETYPE_*]
     */
    public function getImageInfo(string $path): array|false
    {
        return $this->driver->getImageInfo($path);
    }

    /**
     * Validate image dimensions and file size before processing.
     * Prevents image bomb / memory exhaustion attacks.
     */
    private function validateImageSafety(string $imagePath): bool
    {
        $fileSize = @filesize($imagePath);
        if ($fileSize === false || $fileSize > self::MAX_FILE_SIZE) {
            return false;
        }

        $info = $this->driver->getImageInfo($imagePath);
        if ($info === false) {
            return false;
        }

        [$width, $height] = $info;
        if ($width > self::MAX_IMAGE_DIMENSION || $height > self::MAX_IMAGE_DIMENSION) {
            return false;
        }

        return true;
    }

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
        if (!$this->validateImageSafety($sourcePath)) {
            return false;
        }

        $info = $this->driver->getImageInfo($sourcePath);
        if ($info === false) {
            return false;
        }

        [$origWidth, $origHeight, $type] = $info;

        try {
            $source = $this->driver->loadFile($sourcePath);
        } catch (\RuntimeException) {
            return false;
        }

        // Calculate target dimensions
        if ($newHeight === null || $newHeight === 0) {
            $newHeight = (int) round($origHeight * ($newWidth / $origWidth));
        }

        [$targetWidth, $targetHeight, $srcX, $srcY, $srcW, $srcH] = $this->calculateDimensions(
            $origWidth, $origHeight, $newWidth, $newHeight, $mode,
        );

        $target = $this->driver->resize($source, $targetWidth, $targetHeight, $srcX, $srcY, $srcW, $srcH);
        if ($target !== $source) {
            $this->driver->destroy($source);
        }

        // Handle alpha for output format
        $prepared = $this->prepareForSave($target, $type);

        $result = $this->saveWithPermission($prepared, $destPath, $type, $quality);
        if ($prepared !== $target) {
            $this->driver->destroy($target);
        }
        $this->driver->destroy($prepared);

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
        if (!$this->validateImageSafety($imagePath) || !$this->validateImageSafety($watermarkPath)) {
            return false;
        }

        $imgInfo = $this->driver->getImageInfo($imagePath);
        $wmInfo = $this->driver->getImageInfo($watermarkPath);
        if ($imgInfo === false || $wmInfo === false) {
            return false;
        }

        [$imgW, $imgH, $imgType] = $imgInfo;
        [$wmW, $wmH] = $wmInfo;

        try {
            $image = $this->driver->loadFile($imagePath);
            $watermark = $this->driver->loadFile($watermarkPath);
        } catch (\RuntimeException) {
            return false;
        }

        [$destX, $destY] = $this->calculateWatermarkPosition(
            $imgW, $imgH, $wmW, $wmH, $position, $padding,
        );

        $image = $this->driver->composite($image, $watermark, $destX, $destY);
        $this->driver->destroy($watermark);

        $result = $this->saveWithPermission($image, $imagePath, $imgType, 90);
        $this->driver->destroy($image);

        return $result;
    }

    /**
     * Auto-orient image based on EXIF data.
     */
    public function autoOrient(string $imagePath): bool
    {
        if (!$this->validateImageSafety($imagePath)) {
            return false;
        }

        $orientation = $this->driver->getOrientation($imagePath);
        if ($orientation <= 1 || $orientation > 8) {
            return false;
        }

        $info = $this->driver->getImageInfo($imagePath);
        if ($info === false) {
            return false;
        }

        try {
            $image = $this->driver->loadFile($imagePath);
        } catch (\RuntimeException) {
            return false;
        }

        $rotated = match ($orientation) {
            3 => $this->driver->rotate($image, 180),
            6 => $this->driver->rotate($image, 90),
            8 => $this->driver->rotate($image, 270),
            default => null,
        };

        if ($rotated === null) {
            $this->driver->destroy($image);
            return false;
        }

        if ($rotated !== $image) {
            $this->driver->destroy($image);
        }

        $result = $this->saveWithPermission($rotated, $imagePath, $info[2], 90);
        $this->driver->destroy($rotated);

        return $result;
    }

    /**
     * Check if enough memory is available for image processing.
     */
    public function checkMemory(string $imagePath, int $targetWidth, int $targetHeight): bool
    {
        $info = $this->driver->getImageInfo($imagePath);
        if ($info === false) {
            return false;
        }

        [$width, $height] = $info;
        // Assume 4 channels, 8 bits
        $sourceMemory = $width * $height * 4;
        $targetMemory = $targetWidth * $targetHeight * 4;
        $totalNeeded = ($sourceMemory + $targetMemory) * 1.8;

        $memoryLimit = $this->getMemoryLimit();
        $currentUsage = memory_get_usage(true);

        return ($currentUsage + $totalNeeded) < $memoryLimit;
    }

    /**
     * Convert an image to a different format.
     */
    public function convert(string $sourcePath, string $destPath, int $targetType, int $quality = 80): bool
    {
        if (!$this->validateImageSafety($sourcePath)) {
            return false;
        }

        try {
            $source = $this->driver->loadFile($sourcePath);
        } catch (\RuntimeException) {
            return false;
        }

        $prepared = $this->prepareForSave($source, $targetType);
        $result = $this->saveWithPermission($prepared, $destPath, $targetType, $quality);
        if ($prepared !== $source) {
            $this->driver->destroy($source);
        }
        $this->driver->destroy($prepared);

        return $result;
    }

    /**
     * Load an image file via the driver. Returns the native image object or false on failure.
     */
    public function loadImage(string $path): object|false
    {
        if (!$this->validateImageSafety($path)) {
            return false;
        }

        try {
            return $this->driver->loadFile($path);
        } catch (\RuntimeException) {
            return false;
        }
    }

    /**
     * Rotate a file in-place by clockwise degrees (90, 180, 270).
     */
    public function rotateFile(string $path, int $clockwiseDegrees, int $quality): bool
    {
        if (!$this->validateImageSafety($path)) {
            return false;
        }

        $ext = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $imageType = self::imageTypeFromExtension($ext);
        if ($imageType === 0) {
            return false;
        }

        try {
            $image = $this->driver->loadFile($path);
        } catch (\RuntimeException) {
            return false;
        }

        $rotated = $this->driver->rotate($image, $clockwiseDegrees);
        if ($rotated !== $image) {
            $this->driver->destroy($image);
        }

        $prepared = $this->prepareForSave($rotated, $imageType);
        $result = $this->saveWithPermission($prepared, $path, $imageType, $quality);
        if ($prepared !== $rotated) {
            $this->driver->destroy($rotated);
        }
        $this->driver->destroy($prepared);

        return $result;
    }

    /**
     * Apply server-side geometric transforms (rotation + flip) and save.
     */
    public function applyGeometricTransforms(
        string $srcPath,
        string $destPath,
        int $targetType,
        int $quality,
        int $rotation,
        bool $flipX,
        bool $flipY,
    ): bool {
        if (!$this->validateImageSafety($srcPath)) {
            return false;
        }

        try {
            $image = $this->driver->loadFile($srcPath);
        } catch (\RuntimeException) {
            return false;
        }

        if ($rotation !== 0) {
            $rotated = $this->driver->rotate($image, $rotation);
            if ($rotated !== $image) {
                $this->driver->destroy($image);
            }
            $image = $rotated;
        }

        if ($flipX) {
            $image = $this->driver->flipHorizontal($image);
        }
        if ($flipY) {
            $image = $this->driver->flipVertical($image);
        }

        $prepared = $this->prepareForSave($image, $targetType);
        $result = $this->saveWithPermission($prepared, $destPath, $targetType, $quality);
        if ($prepared !== $image) {
            $this->driver->destroy($image);
        }
        $this->driver->destroy($prepared);

        return $result;
    }

    /**
     * Save an image from raw binary data (decoded base64) to a file.
     */
    public function saveFromString(
        string $imageData,
        string $destPath,
        int $targetType,
        int $quality,
    ): bool {
        try {
            $image = $this->driver->loadString($imageData);
        } catch (\RuntimeException) {
            return false;
        }

        $prepared = $this->prepareForSave($image, $targetType);
        $result = $this->saveWithPermission($prepared, $destPath, $targetType, $quality);
        if ($prepared !== $image) {
            $this->driver->destroy($image);
        }
        $this->driver->destroy($prepared);

        return $result;
    }

    /**
     * Prepare image for saving: flatten alpha for JPEG, preserve for PNG/WebP.
     */
    private function prepareForSave(object $image, int $targetType): object
    {
        if ($targetType === IMAGETYPE_JPEG) {
            return $this->driver->flattenAlpha($image);
        }

        if ($targetType === IMAGETYPE_PNG || $targetType === IMAGETYPE_WEBP) {
            return $this->driver->preserveAlpha($image);
        }

        return $image;
    }

    /**
     * Save via driver, ensuring directory exists with configured permission.
     */
    private function saveWithPermission(object $image, string $path, int $imageType, int $quality): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, $this->config->folderPermission, true);
        }

        return $this->driver->save($image, $path, $imageType, $quality);
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
            $targetW = $newW;
            $targetH = (int) round($origH * ($newW / $origW));
        } elseif ($origH > $origW) {
            $targetW = (int) round($origW * ($newH / $origH));
            $targetH = $newH;
        } else {
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
