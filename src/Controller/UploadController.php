<?php

declare(strict_types=1);

namespace RFM\Controller;

use RFM\Config\AppConfig;
use RFM\Http\{Request, JsonResponse};
use RFM\Service\UploadService;

final class UploadController
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly UploadService $uploadService,
    ) {}

    public function upload(Request $request): JsonResponse
    {
        if (!$this->config->uploadFiles) {
            return JsonResponse::error('Uploads disabled', 403);
        }

        $targetDir = $request->post('path', '');
        if (!is_string($targetDir)) {
            $targetDir = '';
        }

        $format = $request->post('format', '');
        $maxSize = $request->post('max_size', '');

        $files = $request->files();
        if (empty($files)) {
            return JsonResponse::error('No files uploaded');
        }

        $uploadOptions = [];
        if (is_string($format) && in_array($format, ['jpeg', 'webp'], true)) {
            $uploadOptions['format'] = $format;
        }
        if (is_string($maxSize) && preg_match('/^(\d+)x(\d+)$/', $maxSize)) {
            $uploadOptions['max_size'] = $maxSize;
        }

        $results = $this->uploadService->handleUpload($targetDir, $files, $uploadOptions);

        return JsonResponse::success(['files' => $results]);
    }

    /**
     * Drag & drop upload from the TinyMCE plugin: stores dropped images in the
     * configured (date-based) folder and returns full + thumbnail URLs.
     */
    public function uploadDragDrop(Request $request): JsonResponse
    {
        if (!$this->config->uploadFiles) {
            return JsonResponse::error('Uploads disabled', 403);
        }
        if (!$this->config->dragDropUpload) {
            return JsonResponse::error('Drag & drop upload disabled', 403);
        }

        $files = $request->files();
        if (empty($files)) {
            return JsonResponse::error('No files uploaded');
        }

        $results = $this->uploadService->handleDragDropUpload($files);

        return JsonResponse::success(['files' => $results]);
    }

    /**
     * Delete a file that was just stored via drag & drop (the plugin's "remove"
     * button). Removes both the full file and its thumbnail. Gated by the same
     * flags as the upload itself; the service additionally restricts deletion to
     * the configured drag & drop directory.
     */
    public function uploadDragDropDelete(Request $request): JsonResponse
    {
        if (!$this->config->uploadFiles) {
            return JsonResponse::error('Uploads disabled', 403);
        }
        if (!$this->config->dragDropUpload) {
            return JsonResponse::error('Drag & drop upload disabled', 403);
        }

        $path = $request->post('path', '');
        if (!is_string($path) || $path === '') {
            return JsonResponse::error('File path required');
        }

        $this->uploadService->handleDragDropDelete($path);

        return JsonResponse::success();
    }

    public function uploadFromUrl(Request $request): JsonResponse
    {
        if (!$this->config->uploadFiles || !$this->config->urlUpload) {
            return JsonResponse::error('URL uploads disabled', 403);
        }

        $url = $request->post('url', '');
        $targetDir = $request->post('path', '');

        if (!is_string($url) || $url === '') {
            return JsonResponse::error('URL required');
        }
        if (!is_string($targetDir)) {
            $targetDir = '';
        }

        $result = $this->uploadService->uploadFromUrl($url, $targetDir);

        return JsonResponse::success(['file' => $result]);
    }
}
