<?php

declare(strict_types=1);

namespace RFM\Controller;

use RFM\Config\AppConfig;
use RFM\Enum\SortField;
use RFM\Http\{Request, JsonResponse, StreamResponse};
use RFM\Service\FileSystemService;

final class FileController
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly FileSystemService $fileSystem,
    ) {}

    /**
     * List files and folders in a directory.
     */
    public function list(Request $request): JsonResponse
    {
        $path = $request->get('path', '');
        $sortBy = SortField::tryFrom($request->get('sort_by', $_SESSION['RFM']['sort_by'] ?? 'name')) ?? SortField::Name;
        $descending = filter_var($request->get('descending', $_SESSION['RFM']['descending'] ?? '0'), FILTER_VALIDATE_BOOLEAN);
        $filter = $request->get('filter', '');
        $typeFilter = $request->get('type_filter');
        $limit = max(0, (int) $request->get('limit', '0'));
        $offset = max(0, (int) $request->get('offset', '0'));

        $result = $this->fileSystem->listDirectory(
            subdir: $path,
            sortBy: $sortBy,
            descending: $descending,
            filter: $filter,
            typeFilter: $typeFilter,
            limit: $limit,
            offset: $offset,
        );

        // Convert DTOs to arrays
        $items = array_map(fn($item) => $item->toArray(), $result['items']);
        $breadcrumb = array_map(fn($bc) => $bc->toArray(), $result['breadcrumb']);

        // Get clipboard state
        $clipboard = $_SESSION['RFM']['clipboard'] ?? null;
        $clipboardState = [
            'hasItems' => !empty($clipboard['paths']),
            'action' => $clipboard['action'] ?? null,
        ];

        return new JsonResponse([
            'path' => $path,
            'items' => $items,
            'breadcrumb' => $breadcrumb,
            'counts' => $result['counts'],
            'totalSize' => $result['totalSize'],
            'total' => $result['total'],
            'clipboard' => $clipboardState,
        ]);
    }

    /**
     * Get info about a single file.
     */
    public function info(Request $request): JsonResponse
    {
        $path = $request->get('path', '');
        if ($path === '') {
            return JsonResponse::error('Path required');
        }

        $info = $this->fileSystem->getFileInfo($path);

        return new JsonResponse($info);
    }

    /**
     * Download a file.
     */
    public function download(Request $request): void
    {
        $path = $request->get('path', '');
        if ($path === '') {
            JsonResponse::error('Path required')->send();
        }

        if (!$this->config->downloadFiles) {
            JsonResponse::error('Downloads disabled', 403)->send();
        }

        $fullPath = $this->fileSystem->getFullPath($path);
        $this->fileSystem->validateFilePath($fullPath);

        $name = basename($request->get('name', basename($path)));
        // Sanitize filename for Content-Disposition header
        $name = str_replace(['"', "\r", "\n", "\0"], '', $name);

        $response = new StreamResponse($fullPath, $name);
        $response->send();
    }

    /**
     * Preview file content (for text files, returns content; for media, returns preview data).
     */
    public function preview(Request $request): JsonResponse
    {
        $path = $request->get('path', '');
        if ($path === '') {
            return JsonResponse::error('Path required');
        }

        $fullPath = $this->fileSystem->getFullPath($path);
        $this->fileSystem->validateFilePath($fullPath);
        $ext = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Text preview (limit to 1 MB to prevent OOM)
        if (in_array($ext, $this->config->previewableTextFileExts, true)) {
            if (!$this->config->previewTextFiles) {
                return JsonResponse::error('Text preview disabled', 403);
            }
            $maxPreviewBytes = 1024 * 1024;
            $fileSize = @filesize($fullPath);
            $truncated = $fileSize !== false && $fileSize > $maxPreviewBytes;
            $content = @file_get_contents($fullPath, false, null, 0, $maxPreviewBytes);
            return new JsonResponse([
                'type' => 'text',
                'content' => $content !== false ? $content : '',
                'extension' => $ext,
                'truncated' => $truncated,
            ]);
        }

        // Image preview - return URL
        if (in_array($ext, $this->config->extImg, true)) {
            $url = $this->config->baseUrl . $this->config->uploadDir . $path;
            return new JsonResponse([
                'type' => 'image',
                'url' => $url,
            ]);
        }

        // Media preview - return URL for HTML5 player
        if (in_array($ext, [...$this->config->extVideo, ...$this->config->extMusic], true)) {
            $url = $this->config->baseUrl . $this->config->uploadDir . $path;
            $isVideo = in_array($ext, $this->config->extVideo, true);
            return new JsonResponse([
                'type' => $isVideo ? 'video' : 'audio',
                'url' => $url,
                'extension' => $ext,
            ]);
        }

        // Google Docs preview
        if ($this->config->googledocEnabled && in_array($ext, $this->config->googledocFileExts, true)) {
            $fileUrl = $this->config->baseUrl . $this->config->uploadDir . $path;
            return new JsonResponse([
                'type' => 'googledoc',
                'url' => 'https://docs.google.com/gview?url=' . urlencode($fileUrl) . '&embedded=true',
            ]);
        }

        return new JsonResponse([
            'type' => 'unsupported',
            'message' => 'Preview not available for this file type',
        ]);
    }

    /**
     * Get file content for editing.
     */
    public function getContent(Request $request): JsonResponse
    {
        $path = $request->get('path', '');
        if ($path === '') {
            return JsonResponse::error('Path required');
        }

        if (!$this->config->editTextFiles) {
            return JsonResponse::error('File editing disabled', 403);
        }

        $ext = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->config->editableTextFileExts, true)) {
            return JsonResponse::error('This file type cannot be edited');
        }

        $fullPath = $this->fileSystem->getFullPath($path);
        $this->fileSystem->validateFilePath($fullPath);

        // Limit to 1 MB to prevent OOM on large files
        $maxEditBytes = 1024 * 1024;
        $fileSize = @filesize($fullPath);
        if ($fileSize !== false && $fileSize > $maxEditBytes) {
            return JsonResponse::error('File is too large to edit (max 1 MB)');
        }

        $content = @file_get_contents($fullPath);

        if ($content === false) {
            return JsonResponse::error('Cannot read file');
        }

        return new JsonResponse([
            'content' => $content,
            'name' => basename($path),
            'extension' => $ext,
        ]);
    }

    /**
     * Serve the Vue SPA for non-API routes.
     *
     * In production: reads the Vite manifest and generates HTML with correct asset paths.
     * In development: serves HTML that loads from the Vite dev server (port 5173).
     */
    public function spa(Request $request): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $manifestPath = $publicDir . '/assets/.vite/manifest.json';

        header('Content-Type: text/html; charset=utf-8');

        if (is_file($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            // Find the entry point: try known keys first, then fall back to
            // scanning for the chunk marked with "isEntry": true.  The manifest
            // key can be an absolute or relative path depending on the cwd used
            // during the build (e.g. Windows directory junctions).
            $entry = $manifest['main.ts'] ?? $manifest['index.html'] ?? null;
            if (!$entry) {
                foreach ($manifest as $chunk) {
                    if (!empty($chunk['isEntry'])) {
                        $entry = $chunk;
                        break;
                    }
                }
            }

            if ($entry) {
                // Build asset base URL relative to the front controller
                $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
                $assetsBase = $basePath . '/assets/';

                $jsFile = htmlspecialchars($assetsBase . $entry['file'], ENT_QUOTES);
                $cssLinks = '';
                foreach ($entry['css'] ?? [] as $cssFile) {
                    $cssLinks .= '    <link rel="stylesheet" href="'
                        . htmlspecialchars($assetsBase . $cssFile, ENT_QUOTES) . "\">\n";
                }

                echo <<<HTML
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>File Image Manager</title>
                {$cssLinks}</head>
                <body class="bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                    <div id="app"></div>
                    <script type="module" src="{$jsFile}"></script>
                </body>
                </html>
                HTML;
            } else {
                echo '<p style="font-family:sans-serif;padding:2rem">Build manifest found but entry point missing. Run <code>npm run build</code>.</p>';
            }
        } else {
            // Development fallback - load from Vite dev server
            echo <<<'HTML'
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>File Image Manager v1.0.0</title>
            </head>
            <body class="bg-white dark:bg-neutral-900 text-gray-900 dark:text-gray-100">
                <div id="app"></div>
                <script type="module" src="http://localhost:5173/@vite/client"></script>
                <script type="module" src="http://localhost:5173/main.ts"></script>
                <noscript>
                    <p style="font-family:sans-serif;padding:2rem">
                        Either start the Vite dev server (<code>npm run dev</code>)
                        or build for production (<code>npm run build</code>).
                    </p>
                </noscript>
            </body>
            </html>
            HTML;
        }
        exit;
    }
}
