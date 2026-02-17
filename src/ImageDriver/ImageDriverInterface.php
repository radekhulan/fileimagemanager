<?php

declare(strict_types=1);

namespace RFM\ImageDriver;

interface ImageDriverInterface
{
    /** Driver name: 'gd' or 'imagick'. */
    public function name(): string;

    /** Load image from file path. */
    public function loadFile(string $path): object;

    /** Load image from raw binary data. */
    public function loadString(string $data): object;

    public function getWidth(object $image): int;

    public function getHeight(object $image): int;

    /** @return array{int, int, int}|false  [width, height, IMAGETYPE_*] */
    public function getImageInfo(string $path): array|false;

    /** Resample a region of the source image into a new image. */
    public function resize(object $image, int $dstW, int $dstH, int $srcX, int $srcY, int $srcW, int $srcH): object;

    /** Rotate by clockwise degrees (90, 180, 270). */
    public function rotate(object $image, int $clockwiseDegrees): object;

    public function flipHorizontal(object $image): object;

    public function flipVertical(object $image): object;

    /** Flatten alpha channel onto a solid RGB background (for JPEG output). */
    public function flattenAlpha(object $image, int $r = 255, int $g = 255, int $b = 255): object;

    /** Preserve alpha channel transparency (for PNG/WebP output). */
    public function preserveAlpha(object $image): object;

    /** Composite overlay onto base at (x, y). */
    public function composite(object $base, object $overlay, int $x, int $y): object;

    /** Save image to file with format dispatch. */
    public function save(object $image, string $path, int $imageType, int $quality): bool;

    public function destroy(object $image): void;

    /** Read EXIF orientation value (1-8). Returns 1 if unavailable. */
    public function getOrientation(string $path): int;

    /** Strip metadata (EXIF, etc.). GD: no-op. Imagick: stripImage(). */
    public function stripMetadata(object $image): object;
}
