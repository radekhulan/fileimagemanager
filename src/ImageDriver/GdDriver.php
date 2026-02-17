<?php

declare(strict_types=1);

namespace RFM\ImageDriver;

final class GdDriver implements ImageDriverInterface
{
    public function name(): string
    {
        return 'gd';
    }

    public function loadFile(string $path): \GdImage
    {
        $info = @getimagesize($path);
        if ($info === false) {
            throw new \RuntimeException("Cannot read image: {$path}");
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_BMP  => @imagecreatefrombmp($path),
            default        => false,
        };

        if ($image === false) {
            throw new \RuntimeException("Failed to load image: {$path}");
        }

        return $image;
    }

    public function loadString(string $data): \GdImage
    {
        $image = @imagecreatefromstring($data);
        if ($image === false) {
            throw new \RuntimeException('Failed to create image from string data');
        }
        return $image;
    }

    public function getWidth(object $image): int
    {
        /** @var \GdImage $image */
        return imagesx($image);
    }

    public function getHeight(object $image): int
    {
        /** @var \GdImage $image */
        return imagesy($image);
    }

    public function getImageInfo(string $path): array|false
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return false;
        }
        return [$info[0], $info[1], $info[2]];
    }

    public function resize(object $image, int $dstW, int $dstH, int $srcX, int $srcY, int $srcW, int $srcH): \GdImage
    {
        /** @var \GdImage $image */
        $target = imagecreatetruecolor($dstW, $dstH);
        if ($target === false) {
            throw new \RuntimeException('Failed to create target image for resize');
        }

        // Preserve alpha for the intermediate canvas
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefill($target, 0, 0, $transparent);

        imagecopyresampled($target, $image, 0, 0, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);

        return $target;
    }

    public function rotate(object $image, int $clockwiseDegrees): \GdImage
    {
        /** @var \GdImage $image */
        // GD uses counter-clockwise, so negate
        $gdAngle = -$clockwiseDegrees;
        $bgColor = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $rotated = imagerotate($image, $gdAngle, $bgColor);
        if ($rotated === false) {
            throw new \RuntimeException('GD rotation failed');
        }
        return $rotated;
    }

    public function flipHorizontal(object $image): object
    {
        /** @var \GdImage $image */
        imageflip($image, IMG_FLIP_HORIZONTAL);
        return $image;
    }

    public function flipVertical(object $image): object
    {
        /** @var \GdImage $image */
        imageflip($image, IMG_FLIP_VERTICAL);
        return $image;
    }

    public function flattenAlpha(object $image, int $r = 255, int $g = 255, int $b = 255): \GdImage
    {
        /** @var \GdImage $image */
        $w = imagesx($image);
        $h = imagesy($image);
        $flat = imagecreatetruecolor($w, $h);
        if ($flat === false) {
            throw new \RuntimeException('Failed to create flat image');
        }
        $bg = imagecolorallocate($flat, $r, $g, $b);
        imagefill($flat, 0, 0, $bg);
        imagecopy($flat, $image, 0, 0, 0, 0, $w, $h);
        return $flat;
    }

    public function preserveAlpha(object $image): object
    {
        /** @var \GdImage $image */
        imagealphablending($image, false);
        imagesavealpha($image, true);
        return $image;
    }

    public function composite(object $base, object $overlay, int $x, int $y): object
    {
        /** @var \GdImage $base */
        /** @var \GdImage $overlay */
        $wmW = imagesx($overlay);
        $wmH = imagesy($overlay);
        imagecopy($base, $overlay, $x, $y, 0, 0, $wmW, $wmH);
        return $base;
    }

    public function save(object $image, string $path, int $imageType, int $quality): bool
    {
        /** @var \GdImage $image */
        return match ($imageType) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, $quality),
            IMAGETYPE_PNG  => imagepng($image, $path, (int) round(9 - ($quality / 100 * 9))),
            IMAGETYPE_GIF  => imagegif($image, $path),
            IMAGETYPE_WEBP => imagewebp($image, $path, $quality),
            IMAGETYPE_BMP  => imagebmp($image, $path),
            default        => false,
        };
    }

    public function destroy(object $image): void
    {
        // GdImage is freed automatically when unreferenced (PHP 8.0+).
        // imagedestroy() is deprecated since PHP 8.5.
    }

    public function getOrientation(string $path): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }
        $exif = @exif_read_data($path);
        if ($exif === false || !isset($exif['Orientation'])) {
            return 1;
        }
        return (int) $exif['Orientation'];
    }

    public function stripMetadata(object $image): object
    {
        // GD strips metadata implicitly on re-encode
        return $image;
    }
}
