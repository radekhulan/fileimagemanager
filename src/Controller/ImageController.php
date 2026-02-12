<?php

declare(strict_types=1);

namespace RFM\Controller;

use RFM\Config\AppConfig;
use RFM\Http\{Request, JsonResponse};
use RFM\Service\{ThumbnailService, SecurityService};

final class ImageController
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly ThumbnailService $thumbnails,
        private readonly SecurityService $security,
    ) {}

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
