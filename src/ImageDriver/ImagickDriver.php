<?php

declare(strict_types=1);

namespace RFM\ImageDriver;

final class ImagickDriver implements ImageDriverInterface
{
    /** Only allow safe raster formats — blocks SVG, MVG, MSL, and other delegate-based formats. */
    private const ALLOWED_FORMATS = ['jpeg', 'jpg', 'png', 'gif', 'webp', 'bmp'];

    /** Max memory for Imagick operations (256 MB). */
    private const MAX_MEMORY = 256 * 1024 * 1024;

    public function name(): string
    {
        return 'imagick';
    }

    public function loadFile(string $path): \Imagick
    {
        try {
            $im = new \Imagick();
            $this->applyResourceLimits($im);
            $im->readImage($path);
        } catch (\ImagickException $e) {
            throw new \RuntimeException('Failed to load image', 0, $e);
        }
        if ($im->getNumberImages() === 0) {
            throw new \RuntimeException('Imagick loaded 0 images');
        }
        $this->assertSafeFormat($im);
        return $im;
    }

    public function loadString(string $data): \Imagick
    {
        try {
            $im = new \Imagick();
            $this->applyResourceLimits($im);
            $im->readImageBlob($data);
        } catch (\ImagickException $e) {
            throw new \RuntimeException('Failed to create image from string data', 0, $e);
        }
        $this->assertSafeFormat($im);
        return $im;
    }

    /**
     * Reject non-raster formats that could trigger delegate processing (SVG, MVG, MSL, etc.).
     */
    private function assertSafeFormat(\Imagick $im): void
    {
        $format = strtolower($im->getImageFormat());
        if (!in_array($format, self::ALLOWED_FORMATS, true)) {
            $im->clear();
            $im->destroy();
            throw new \RuntimeException("Blocked unsafe image format: {$format}");
        }
    }

    /**
     * Apply resource limits to prevent DoS via crafted images.
     */
    private function applyResourceLimits(\Imagick $im): void
    {
        $im->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, self::MAX_MEMORY);
        $im->setResourceLimit(\Imagick::RESOURCETYPE_MAP, self::MAX_MEMORY * 2);
        $im->setResourceLimit(\Imagick::RESOURCETYPE_DISK, self::MAX_MEMORY * 4);
        $im->setResourceLimit(\Imagick::RESOURCETYPE_TIME, 30);
    }

    public function getWidth(object $image): int
    {
        /** @var \Imagick $image */
        return $image->getImageWidth();
    }

    public function getHeight(object $image): int
    {
        /** @var \Imagick $image */
        return $image->getImageHeight();
    }

    public function getImageInfo(string $path): array|false
    {
        try {
            $im = new \Imagick();
            $this->applyResourceLimits($im);
            $im->readImage($path);
            $w = $im->getImageWidth();
            $h = $im->getImageHeight();
            $format = strtolower($im->getImageFormat());
            $im->clear();
            $im->destroy();

            $type = match ($format) {
                'jpeg', 'jpg' => IMAGETYPE_JPEG,
                'png'         => IMAGETYPE_PNG,
                'gif'         => IMAGETYPE_GIF,
                'webp'        => IMAGETYPE_WEBP,
                'bmp'         => IMAGETYPE_BMP,
                default       => 0,
            };

            return [$w, $h, $type];
        } catch (\ImagickException) {
            return false;
        }
    }

    public function resize(object $image, int $dstW, int $dstH, int $srcX, int $srcY, int $srcW, int $srcH): \Imagick
    {
        /** @var \Imagick $image */
        // Crop the source region first if needed
        if ($srcX !== 0 || $srcY !== 0 || $srcW !== $image->getImageWidth() || $srcH !== $image->getImageHeight()) {
            $image->cropImage($srcW, $srcH, $srcX, $srcY);
            $image->setImagePage(0, 0, 0, 0);
        }

        $image->resizeImage($dstW, $dstH, \Imagick::FILTER_LANCZOS, 1);

        return $image;
    }

    public function rotate(object $image, int $clockwiseDegrees): \Imagick
    {
        /** @var \Imagick $image */
        $bg = new \ImagickPixel('transparent');
        $image->rotateImage($bg, $clockwiseDegrees);
        return $image;
    }

    public function flipHorizontal(object $image): object
    {
        /** @var \Imagick $image */
        $image->flopImage();
        return $image;
    }

    public function flipVertical(object $image): object
    {
        /** @var \Imagick $image */
        $image->flipImage();
        return $image;
    }

    public function flattenAlpha(object $image, int $r = 255, int $g = 255, int $b = 255): \Imagick
    {
        /** @var \Imagick $image */
        $bg = new \ImagickPixel("rgb({$r},{$g},{$b})");
        $canvas = new \Imagick();
        $canvas->newImage($image->getImageWidth(), $image->getImageHeight(), $bg);
        $canvas->setImageFormat($image->getImageFormat());
        $canvas->compositeImage($image, \Imagick::COMPOSITE_OVER, 0, 0);
        return $canvas;
    }

    public function preserveAlpha(object $image): object
    {
        // Imagick preserves alpha natively — no action needed
        return $image;
    }

    public function composite(object $base, object $overlay, int $x, int $y): object
    {
        /** @var \Imagick $base */
        /** @var \Imagick $overlay */
        $base->compositeImage($overlay, \Imagick::COMPOSITE_OVER, $x, $y);
        return $base;
    }

    public function save(object $image, string $path, int $imageType, int $quality): bool
    {
        /** @var \Imagick $image */
        try {
            $format = match ($imageType) {
                IMAGETYPE_JPEG => 'jpeg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_GIF  => 'gif',
                IMAGETYPE_WEBP => 'webp',
                IMAGETYPE_BMP  => 'bmp',
                default        => throw new \RuntimeException("Unsupported image type: {$imageType}"),
            };

            $image->setImageFormat($format);

            if ($imageType === IMAGETYPE_PNG) {
                // PNG uses Zlib compression 0-9; map quality 0-100 to compression level
                $zlibLevel = (int) round(9 - ($quality / 100 * 9));
                $image->setImageCompressionQuality($zlibLevel * 10);
            } else {
                $image->setImageCompressionQuality($quality);
            }

            return $image->writeImage($path);
        } catch (\ImagickException) {
            return false;
        }
    }

    public function destroy(object $image): void
    {
        /** @var \Imagick $image */
        $image->clear();
        $image->destroy();
    }

    public function getOrientation(string $path): int
    {
        try {
            $im = new \Imagick();
            $this->applyResourceLimits($im);
            $im->readImage($path);
            $orientation = $im->getImageOrientation();
            $im->clear();
            $im->destroy();
            return $orientation ?: 1;
        } catch (\ImagickException) {
            return 1;
        }
    }

    public function stripMetadata(object $image): object
    {
        /** @var \Imagick $image */
        $image->stripImage();
        return $image;
    }
}
