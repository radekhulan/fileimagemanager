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
        if (!in_array($format, ['webp', 'jpg'], true)) {
            return JsonResponse::error('Invalid format. Use "webp" or "jpg"');
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

        $targetType = $format === 'webp' ? IMAGETYPE_WEBP : IMAGETYPE_JPEG;
        $targetExt = $format === 'webp' ? 'webp' : 'jpg';

        // Build new path with changed extension
        $dir = dirname($path);
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $newRelPath = ($dir === '.' || $dir === '' ? '' : $dir . '/') . $baseName . '.' . $targetExt;
        $newFullPath = $this->config->currentPath . $newRelPath;

        // Convert
        $quality = $format === 'webp' ? $this->config->imageQualityWebp : $this->config->imageQualityJpeg;
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
        $name = $request->post('name', '');

        if (!is_string($path) || $path === '') {
            return JsonResponse::error('Image path required');
        }
        if (!is_string($imageData) || $imageData === '') {
            return JsonResponse::error('Image data required');
        }

        // Limit base64 input size (20 MB base64 ≈ ~15 MB decoded image)
        $maxBase64Size = 20 * 1024 * 1024;
        if (strlen($imageData) > $maxBase64Size) {
            return JsonResponse::error('Image data too large (max 15 MB)');
        }

        $fullPath = $this->config->currentPath . $path;
        $this->security->validatePath($fullPath);

        // Validate extension
        $ext = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return JsonResponse::error('Only JPG, PNG, and WebP images can be edited');
        }

        // Decode base64 image data
        $dataPrefix = 'data:image/';
        if (str_starts_with($imageData, $dataPrefix)) {
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
        }

        $decodedData = base64_decode($imageData, true);
        if ($decodedData === false) {
            return JsonResponse::error('Invalid image data');
        }

        // Validate that the decoded data is actually a valid image
        $imageInfo = @getimagesizefromstring($decodedData);
        if ($imageInfo === false) {
            return JsonResponse::error('Decoded data is not a valid image');
        }

        // Save the image
        if (file_put_contents($fullPath, $decodedData) === false) {
            return JsonResponse::error('Failed to save image');
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
}
