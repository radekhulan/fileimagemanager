<?php

declare(strict_types=1);

namespace RFM\Controller;

use RFM\Config\AppConfig;
use RFM\Http\{Request, JsonResponse};
use RFM\Service\{FileSystemService, SecurityService};

final class FolderController
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly FileSystemService $fileSystem,
        private readonly SecurityService $security,
    ) {}

    public function create(Request $request): JsonResponse
    {
        if (!$this->config->createFolders) {
            return JsonResponse::error('Folder creation disabled', 403);
        }

        $path = $request->post('path', '');
        $name = $request->post('name', '');

        if (!is_string($name) || $name === '') {
            return JsonResponse::error('Folder name required');
        }

        $newPath = $this->fileSystem->createFolder($path, $name);

        return JsonResponse::success(['path' => $newPath]);
    }

    public function rename(Request $request): JsonResponse
    {
        if (!$this->config->renameFolders) {
            return JsonResponse::error('Folder renaming disabled', 403);
        }

        $path = $request->post('path', '');
        $newName = $request->post('name', '');

        if (!is_string($path) || $path === '') {
            return JsonResponse::error('Folder path required');
        }
        if (!is_string($newName) || $newName === '') {
            return JsonResponse::error('New folder name required');
        }

        $newPath = $this->fileSystem->renameFolder($path, $newName);

        return JsonResponse::success(['path' => $newPath]);
    }

    public function delete(Request $request): JsonResponse
    {
        if (!$this->config->deleteFolders) {
            return JsonResponse::error('Folder deletion disabled', 403);
        }

        $path = $request->post('path', '');

        if (!is_string($path) || $path === '') {
            return JsonResponse::error('Folder path required');
        }

        $this->fileSystem->deleteDirectory($path);

        return JsonResponse::success();
    }
}
