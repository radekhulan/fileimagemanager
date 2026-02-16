<?php

declare(strict_types=1);

namespace RFM\Controller;

use RFM\Config\AppConfig;
use RFM\Http\{Request, JsonResponse};
use RFM\Service\{ImageProcessingService, ThumbnailService, SecurityService};

final class ImageController
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly ThumbnailService $thumbnails,
        private readonly SecurityService $security,
        private readonly ImageProcessingService $imageProcessor,
    ) {}

    /**
     * Convert an image to a different format (WebP or JPG).
     */
    public function convert(Request $request): JsonResponse
    {
        if (!$this->config->imageEditorActive) {
            return JsonResponse::error('Image editor disabled', 403);
        }

        $path = $request->post('path', '');
        $format = $request->post('format', '');
        $keepOriginal = (bool) $request->post('keep_original', false);

        if (!is_string($path) || $path === '') {
            return JsonResponse::error('Image path required');
        }
        if (!in_array($format, ['webp', 'jpg', 'png'], true)) {
            return JsonResponse::error('Invalid format. Use "webp", "jpg" or "png"');
        }

        $fullPath = $this->config->currentPath . $path;
        $this->security->validatePath($fullPath);

        if (!is_file($fullPath)) {
            return JsonResponse::error('File not found', 404);
        }

        // Validate source is a convertible image
        $ext = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];
        if (!in_array($ext, $allowedExts, true)) {
            return JsonResponse::error('File is not a supported image format');
        }

        $targetType = match ($format) {
            'webp' => IMAGETYPE_WEBP,
            'png' => IMAGETYPE_PNG,
            default => IMAGETYPE_JPEG,
        };
        $targetExt = match ($format) {
            'webp' => 'webp',
            'png' => 'png',
            default => 'jpg',
        };

        // Build new path with changed extension
        $dir = dirname($path);
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $newRelPath = ($dir === '.' || $dir === '' ? '' : $dir . '/') . $baseName . '.' . $targetExt;
        $newFullPath = $this->config->currentPath . $newRelPath;

        // Convert (PNG is always lossless; quality param controls compression level only)
        $quality = match ($format) {
            'webp' => $this->config->imageQualityWebp,
            'png' => 100,
            default => $this->config->imageQualityJpeg,
        };
        if (!$this->imageProcessor->convert($fullPath, $newFullPath, $targetType, $quality)) {
            return JsonResponse::error('Image conversion failed');
        }

        @chmod($newFullPath, $this->config->filePermission);

        // Delete old file if the path changed and user doesn't want to keep it
        if (!$keepOriginal && $fullPath !== $newFullPath && is_file($fullPath)) {
            @unlink($fullPath);
            $this->thumbnails->deleteThumbnail($path);
        }

        // Create new thumbnail
        $thumbPath = $this->config->thumbsBasePath . $newRelPath;
        $this->thumbnails->createThumbnail($newFullPath, $thumbPath);

        return JsonResponse::success([
            'path' => $newRelPath,
            'name' => $baseName . '.' . $targetExt,
        ]);
    }

    /**
     * Rotate an image by 90° left or right (lossless via GD).
     */
    public function rotate(Request $request): JsonResponse
    {
        if (!$this->config->imageEditorActive) {
            return JsonResponse::error('Image editor disabled', 403);
        }

        $path = $request->post('path', '');
        $direction = $request->post('direction', '');

        if (!is_string($path) || $path === '') {
            return JsonResponse::error('Image path required');
        }
        if (!in_array($direction, ['left', 'right'], true)) {
            return JsonResponse::error('Direction must be "left" or "right"');
        }

        $fullPath = $this->config->currentPath . $path;
        $this->security->validatePath($fullPath);

        if (!is_file($fullPath)) {
            return JsonResponse::error('File not found', 404);
        }

        $ext = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return JsonResponse::error('Only JPG, PNG, and WebP images can be rotated');
        }

        $source = $this->imageProcessor->loadImage($fullPath);
        if ($source === false) {
            return JsonResponse::error('Failed to load image');
        }

        // GD imagerotate() uses counter-clockwise degrees
        // "right" (clockwise 90°) = GD -90° = 270°
        // "left" (counter-clockwise 90°) = GD 90°
        $gdAngle = $direction === 'right' ? 270 : 90;
        $bgColor = imagecolorallocatealpha($source, 0, 0, 0, 127);
        $rotated = imagerotate($source, $gdAngle, $bgColor);
        imagedestroy($source);

        if ($rotated === false) {
            return JsonResponse::error('Rotation failed');
        }

        $imageType = match ($ext) {
            'webp' => IMAGETYPE_WEBP,
            'png' => IMAGETYPE_PNG,
            default => IMAGETYPE_JPEG,
        };

        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_WEBP) {
            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);
        }

        $quality = match ($imageType) {
            IMAGETYPE_WEBP => $this->config->imageQualityWebp,
            IMAGETYPE_PNG => 100,
            default => $this->config->imageQualityJpeg,
        };

        $saved = match ($imageType) {
            IMAGETYPE_WEBP => imagewebp($rotated, $fullPath, $quality),
            IMAGETYPE_PNG => imagepng($rotated, $fullPath, (int) round(9 - ($quality / 100 * 9))),
            default => imagejpeg($rotated, $fullPath, $quality),
        };
        imagedestroy($rotated);

        if (!$saved) {
            return JsonResponse::error('Failed to save rotated image');
        }

        @chmod($fullPath, $this->config->filePermission);

        // Regenerate thumbnail
        $this->thumbnails->deleteThumbnail($path);
        $thumbPath = $this->config->thumbsBasePath . $path;
        $this->thumbnails->createThumbnail($fullPath, $thumbPath);

        return JsonResponse::success([
            'path' => $path,
            'size' => filesize($fullPath),
        ]);
    }

    /**
     * Save an edited image from the Filerobot Image Editor.
     * Receives base64-encoded image data.
     */
    public function saveEdited(Request $request): JsonResponse
    {
        if (!$this->config->imageEditorActive) {
            return JsonResponse::error('Image editor disabled', 403);
        }

        $path = $request->post('path', '');
        $imageData = $request->post('image_data', '');
        $newName = $request->post('new_name', '');
        $quality = (int) $request->post('quality', 92);
        $quality = max(1, min(100, $quality));

        if (!is_string($path) || $path === '') {
            return JsonResponse::error('Image path required');
        }
        $fullPath = $this->config->currentPath . $path;
        $this->security->validatePath($fullPath);

        // Validate original extension
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            return JsonResponse::error('Only JPG, PNG, and WebP images can be edited');
        }

        // Determine target path — may differ if user changed name/extension in editor
        $dir = dirname($path);
        $dirPrefix = ($dir === '.' || $dir === '') ? '' : $dir . '/';

        if (is_string($newName) && $newName !== '') {
            $newName = $this->security->sanitizeFilename($newName);
            $newExt = mb_strtolower(pathinfo($newName, PATHINFO_EXTENSION));
            if (!in_array($newExt, $allowedExts, true)) {
                return JsonResponse::error('Only JPG, PNG, and WebP extensions are allowed');
            }
            $targetPath = $dirPrefix . $newName;
        } else {
            $targetPath = $path;
        }

        $targetFullPath = $this->config->currentPath . $targetPath;
        $this->security->validatePath($targetFullPath);

        $targetExt = mb_strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        $targetType = match ($targetExt) {
            'webp' => IMAGETYPE_WEBP,
            'png' => IMAGETYPE_PNG,
            default => IMAGETYPE_JPEG,
        };

        $rotation = (int) $request->post('rotation', 0);
        $flipX = filter_var($request->post('flip_x', false), FILTER_VALIDATE_BOOLEAN);
        $flipY = filter_var($request->post('flip_y', false), FILTER_VALIDATE_BOOLEAN);

        // Only allow cardinal rotations (lossless, no memory expansion)
        if ($rotation !== 0 && !in_array($rotation, [90, 180, 270, -90, -180, -270], true)) {
            $rotation = 0;
        }

        if (!is_string($imageData) || $imageData === '') {
            // No canvas data — handle server-side (lossless geometric transforms + format conversion).
            if (!is_file($fullPath)) {
                return JsonResponse::error('Original file not found', 404);
            }

            $hasGeometric = ($rotation !== 0) || $flipX || $flipY;
            $hasFormatChange = ($targetPath !== $path);

            if (!$hasGeometric && !$hasFormatChange) {
                // Nothing to do
                return JsonResponse::success([
                    'path' => $path,
                    'size' => filesize($fullPath),
                ]);
            }

            // Load original image with GD
            $source = $this->imageProcessor->loadImage($fullPath);
            if ($source === false) {
                return JsonResponse::error('Failed to load original image');
            }

            // Apply geometric transforms (lossless — no interpolation for 90° multiples)
            if ($rotation !== 0) {
                // GD rotates counter-clockwise, Filerobot uses clockwise
                $gdAngle = -$rotation;
                $bgColor = imagecolorallocatealpha($source, 0, 0, 0, 127);
                $rotated = imagerotate($source, $gdAngle, $bgColor);
                if ($rotated !== false) {
                    imagedestroy($source);
                    $source = $rotated;
                }
            }

            if ($flipX) {
                imageflip($source, IMG_FLIP_HORIZONTAL);
            }
            if ($flipY) {
                imageflip($source, IMG_FLIP_VERTICAL);
            }

            // Save to target format
            if ($targetType === IMAGETYPE_PNG || $targetType === IMAGETYPE_WEBP) {
                imagealphablending($source, false);
                imagesavealpha($source, true);
            }

            if ($targetType === IMAGETYPE_JPEG) {
                $w = imagesx($source);
                $h = imagesy($source);
                $flat = imagecreatetruecolor($w, $h);
                if ($flat !== false) {
                    $white = imagecolorallocate($flat, 255, 255, 255);
                    imagefill($flat, 0, 0, $white);
                    imagecopy($flat, $source, 0, 0, 0, 0, $w, $h);
                    imagedestroy($source);
                    $source = $flat;
                }
            }

            $saved = match ($targetType) {
                IMAGETYPE_WEBP => imagewebp($source, $targetFullPath, $quality),
                IMAGETYPE_PNG => imagepng($source, $targetFullPath, (int) round(9 - ($quality / 100 * 9))),
                default => imagejpeg($source, $targetFullPath, $quality),
            };
            imagedestroy($source);

            if (!$saved) {
                return JsonResponse::error('Failed to save image');
            }
        } else {
            // Canvas data provided — edits were made, re-encode via GD
            $maxBase64Size = 40 * 1024 * 1024;
            if (strlen($imageData) > $maxBase64Size) {
                return JsonResponse::error('Image data too large (max 40 MB)');
            }

            $dataPrefix = 'data:image/';
            if (str_starts_with($imageData, $dataPrefix)) {
                $imageData = preg_replace('/^data:image\/[\w+]+;base64,/', '', $imageData);
            }

            $decodedData = base64_decode($imageData, true);
            if ($decodedData === false) {
                return JsonResponse::error('Invalid image data');
            }

            $imageInfo = @getimagesizefromstring($decodedData);
            if ($imageInfo === false) {
                return JsonResponse::error('Decoded data is not a valid image');
            }

            // Prevent memory exhaustion from huge canvas dimensions
            [$canvasW, $canvasH] = $imageInfo;
            $maxDim = 16384; // 16K pixels — generous but bounded
            if ($canvasW > $maxDim || $canvasH > $maxDim || $canvasW <= 0 || $canvasH <= 0) {
                return JsonResponse::error('Image dimensions too large');
            }

            $gdImage = @imagecreatefromstring($decodedData);
            if ($gdImage === false) {
                return JsonResponse::error('Failed to process image data');
            }

            if ($targetType === IMAGETYPE_PNG || $targetType === IMAGETYPE_WEBP) {
                imagealphablending($gdImage, false);
                imagesavealpha($gdImage, true);
            }

            if ($targetType === IMAGETYPE_JPEG) {
                $width = imagesx($gdImage);
                $height = imagesy($gdImage);
                $flat = imagecreatetruecolor($width, $height);
                if ($flat !== false) {
                    $white = imagecolorallocate($flat, 255, 255, 255);
                    imagefill($flat, 0, 0, $white);
                    imagecopy($flat, $gdImage, 0, 0, 0, 0, $width, $height);
                    imagedestroy($gdImage);
                    $gdImage = $flat;
                }
            }

            $saved = match ($targetType) {
                IMAGETYPE_WEBP => imagewebp($gdImage, $targetFullPath, $quality),
                IMAGETYPE_PNG => imagepng($gdImage, $targetFullPath, (int) round(9 - ($quality / 100 * 9))),
                default => imagejpeg($gdImage, $targetFullPath, $quality),
            };
            imagedestroy($gdImage);

            if (!$saved) {
                return JsonResponse::error('Failed to save image');
            }
        }

        @chmod($targetFullPath, $this->config->filePermission);

        // If the path changed, delete the old file and its thumbnail
        if ($targetPath !== $path && is_file($fullPath)) {
            @unlink($fullPath);
            $this->thumbnails->deleteThumbnail($path);
        } else {
            $this->thumbnails->deleteThumbnail($path);
        }

        // Create new thumbnail
        $thumbPath = $this->config->thumbsBasePath . $targetPath;
        $this->thumbnails->createThumbnail($targetFullPath, $thumbPath);

        return JsonResponse::success([
            'path' => $targetPath,
            'size' => filesize($targetFullPath),
        ]);
    }
}
