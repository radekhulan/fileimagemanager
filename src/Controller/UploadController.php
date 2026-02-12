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

        $files = $request->files();
        if (empty($files)) {
            return JsonResponse::error('No files uploaded');
        }

        $results = $this->uploadService->handleUpload($targetDir, $files);

        return JsonResponse::success(['files' => $results]);
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
