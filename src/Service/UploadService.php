<?php

declare(strict_types=1);

namespace RFM\Service;

use RFM\Config\AppConfig;
use RFM\Enum\ImageResizeMode;
use RFM\Exception\{UploadException, InvalidExtensionException};

final class UploadService
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly SecurityService $security,
        private readonly ThumbnailService $thumbnails,
        private readonly ImageProcessingService $imageProcessor,
        private readonly MimeTypeService $mimeService,
    ) {}

    /**
     * Handle uploaded files.
     *
     * @return array{name: string, path: string, size: int, type: string}[]
     */
    public function handleUpload(string $targetDir, array $files): array
    {
        $results = [];
        $targetPath = $this->config->currentPath . $targetDir;

        $this->security->validatePath($targetPath);

        if (!is_dir($targetPath)) {
            throw new UploadException("Target directory does not exist: {$targetDir}");
        }

        // Check total folder size limit
        if ($this->config->maxSizeTotal !== false) {
            $currentSize = $this->calculateDirSize($targetPath);
            $maxBytes = $this->config->maxSizeTotal * 1024 * 1024;
            if ($currentSize >= $maxBytes) {
                throw new UploadException('Total folder size limit reached');
            }
        }

        // Normalize file array for multiple uploads
        $normalizedFiles = $this->normalizeFiles($files);

        foreach ($normalizedFiles as $file) {
            $results[] = $this->processUploadedFile($file, $targetPath, $targetDir);
        }

        return $results;
    }

    /**
     * Upload a file from a URL.
     *
     * @return array{name: string, path: string, size: int, type: string}
     */
    public function uploadFromUrl(string $url, string $targetDir): array
    {
        // Validate URL
        if (strlen($url) > 2000) {
            throw new UploadException('URL too long');
        }

        if (!preg_match('/^https?:\/\/[^\s<>"{}|\\\\^`\[\]]+$/i', $url)) {
            throw new UploadException('Invalid URL format');
        }

        // Block private/reserved IP ranges (SSRF prevention)
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === false || $host === '') {
            throw new UploadException('Invalid URL host');
        }
        $resolvedIp = $this->validateUrlHost($host);

        $targetPath = $this->config->currentPath . $targetDir;
        $this->security->validatePath($targetPath);

        if (!is_dir($targetPath)) {
            throw new UploadException("Target directory does not exist: {$targetDir}");
        }

        // Download file via cURL
        $ch = curl_init($url);
        if ($ch === false) {
            throw new UploadException('Failed to initialize download');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'rfm_');
        if ($tempFile === false) {
            throw new UploadException('Failed to create temporary file');
        }

        $fp = fopen($tempFile, 'wb');
        if ($fp === false) {
            @unlink($tempFile);
            throw new UploadException('Failed to open temporary file');
        }

        $maxBytes = $this->config->maxSizeUpload * 1024 * 1024;

        // Pin DNS resolution to the validated IP to prevent DNS rebinding attacks
        $port = parse_url($url, PHP_URL_PORT);
        $resolveEntries = [
            "{$host}:80:{$resolvedIp}",
            "{$host}:443:{$resolvedIp}",
        ];
        if ($port !== null) {
            $resolveEntries[] = "{$host}:{$port}:{$resolvedIp}";
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'FileImageManager/1.0.0',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => $resolveEntries,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($ch, $dlTotal, $dlNow) use ($maxBytes, $tempFile) {
                // Abort download if it exceeds max upload size
                if ($dlNow > $maxBytes) {
                    return 1; // non-zero aborts the transfer
                }
                return 0;
            },
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            @unlink($tempFile);
            throw new UploadException('Failed to download file from URL');
        }

        // Extract filename from URL
        $urlPath = parse_url($url, PHP_URL_PATH);
        $fileName = $urlPath ? basename($urlPath) : 'downloaded_file';
        $fileName = $this->security->sanitizeFilename($fileName);

        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        if ($ext === '') {
            // Try to get extension from MIME type
            $mime = $this->mimeService->detect($tempFile);
            $ext = $this->mimeService->getExtensionForMime($mime) ?? '';
            if ($ext !== '') {
                $fileName .= '.' . $ext;
            }
        }

        // Validate extension (reject files without extension)
        if ($ext === '') {
            @unlink($tempFile);
            throw new UploadException('Cannot determine file type — file has no extension');
        }
        $this->security->validateExtension($ext);
        $this->security->validateFilenameExtensions($fileName);

        // Check file size
        $fileSize = filesize($tempFile);
        $maxBytes = $this->config->maxSizeUpload * 1024 * 1024;
        if ($fileSize > $maxBytes) {
            @unlink($tempFile);
            throw new UploadException("File exceeds maximum upload size of {$this->config->maxSizeUpload}MB");
        }

        // Move to target
        $destPath = $targetPath . $this->ensureUniqueName($targetPath, $fileName);

        if (!@rename($tempFile, $destPath)) {
            if (!@copy($tempFile, $destPath)) {
                @unlink($tempFile);
                throw new UploadException('Failed to save downloaded file');
            }
            @unlink($tempFile);
        }

        @chmod($destPath, $this->config->filePermission);

        $finalName = basename($destPath);
        $relativePath = $targetDir . $finalName;

        // Post-process image
        $this->postProcessImage($destPath, $relativePath, $targetDir, $targetPath);

        return [
            'name' => $finalName,
            'path' => $relativePath,
            'size' => (int) filesize($destPath),
            'type' => $this->mimeService->detect($destPath),
        ];
    }

    /**
     * @return array{name: string, path: string, size: int, type: string}
     */
    private function processUploadedFile(array $file, string $targetPath, string $targetDir): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new UploadException($this->getUploadErrorMessage($file['error']));
        }

        $originalName = basename($file['name']);
        $fileName = $this->security->sanitizeFilename($originalName);
        $ext = mb_strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate extension (single + all dot-separated parts for double-extension attacks)
        if ($ext !== '' || !$this->config->filesWithoutExtension) {
            $this->security->validateExtension($ext);
        }
        $this->security->validateFilenameExtensions($fileName);

        // Check MIME type and optionally rename extension
        if ($this->config->mimeExtensionRename && $ext !== '') {
            $newExt = $this->mimeService->shouldRenameExtension($file['tmp_name'], $ext);
            if ($newExt !== null) {
                $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
                $fileName = $nameWithoutExt . '.' . $newExt;
                $ext = $newExt;
            }
        }

        // Check file size
        $maxBytes = $this->config->maxSizeUpload * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            throw new UploadException("File exceeds maximum upload size of {$this->config->maxSizeUpload}MB");
        }

        // Ensure unique filename
        $fileName = $this->ensureUniqueName($targetPath, $fileName);
        $destPath = $targetPath . $fileName;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new UploadException('Failed to move uploaded file');
        }

        @chmod($destPath, $this->config->filePermission);

        $relativePath = $targetDir . $fileName;

        // Post-process image
        $this->postProcessImage($destPath, $relativePath, $targetDir, $targetPath);

        return [
            'name' => $fileName,
            'path' => $relativePath,
            'size' => (int) filesize($destPath),
            'type' => $this->mimeService->detect($destPath),
        ];
    }

    private function postProcessImage(string $destPath, string $relativePath, string $targetDir, string $targetPath): void
    {
        $ext = mb_strtolower(pathinfo($destPath, PATHINFO_EXTENSION));

        if (!$this->security->isImageExtension($ext)) {
            return;
        }

        // Auto-orient from EXIF
        $this->imageProcessor->autoOrient($destPath);

        // Apply max dimensions
        if ($this->config->imageMaxWidth > 0 || $this->config->imageMaxHeight > 0) {
            $imgSize = @getimagesize($destPath);
            if ($imgSize !== false) {
                [$w, $h] = $imgSize;
                $maxW = $this->config->imageMaxWidth ?: PHP_INT_MAX;
                $maxH = $this->config->imageMaxHeight ?: PHP_INT_MAX;

                if ($w > $maxW || $h > $maxH) {
                    $mode = ImageResizeMode::fromLegacy($this->config->imageMaxMode);
                    $this->imageProcessor->resize($destPath, $destPath, $maxW, $maxH, $mode);
                }
            }
        }

        // Apply auto-resizing
        if ($this->config->imageResizing) {
            $resW = $this->config->imageResizingWidth;
            $resH = $this->config->imageResizingHeight;

            if ($resW > 0 || $resH > 0) {
                if (!$this->config->imageResizingOverride) {
                    $maxW = $this->config->imageMaxWidth ?: PHP_INT_MAX;
                    $maxH = $this->config->imageMaxHeight ?: PHP_INT_MAX;
                    $resW = min($resW ?: PHP_INT_MAX, $maxW);
                    $resH = min($resH ?: PHP_INT_MAX, $maxH);
                }

                $mode = ImageResizeMode::fromLegacy($this->config->imageResizingMode);
                $this->imageProcessor->resize($destPath, $destPath, $resW, $resH ?: null, $mode);
            }
        }

        // Apply watermark
        if ($this->config->imageWatermark !== false && is_file($this->config->imageWatermark)) {
            $this->imageProcessor->applyWatermark(
                $destPath,
                $this->config->imageWatermark,
                $this->config->imageWatermarkPosition,
                $this->config->imageWatermarkPadding,
            );
        }

        // Generate standard thumbnail
        $this->thumbnails->createThumbnail(
            $destPath,
            $this->config->thumbsBasePath . $relativePath,
        );

        // Generate fixed thumbnails
        $this->thumbnails->generateFixedThumbnails($destPath, $targetDir);

        // Generate relative thumbnails
        $this->thumbnails->generateRelativeThumbnails($destPath, $targetPath);
    }

    private function ensureUniqueName(string $dir, string $fileName): string
    {
        if (!is_file($dir . $fileName)) {
            return $fileName;
        }

        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $counter = 1;

        do {
            $newName = $name . '_' . $counter . ($ext !== '' ? '.' . $ext : '');
            $counter++;
        } while (is_file($dir . $newName));

        return $newName;
    }

    /**
     * @return array<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    private function normalizeFiles(array $files): array
    {
        // If it's a single file with 'files' key
        if (isset($files['files'])) {
            $files = $files['files'];
        }

        // Check if this is a multi-file upload array
        if (isset($files['name']) && is_array($files['name'])) {
            $normalized = [];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $normalized[] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
            }
            return $normalized;
        }

        // Single file
        if (isset($files['name']) && is_string($files['name'])) {
            return [$files];
        }

        return $files;
    }

    private function calculateDirSize(string $dir): int
    {
        $size = 0;
        $entries = @scandir($dir);
        if ($entries === false) {
            return 0;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $size += $this->calculateDirSize($path);
            } else {
                $size += (int) @filesize($path);
            }
        }

        return $size;
    }

    /**
     * Block requests to private/reserved IP ranges (SSRF prevention).
     * Returns the resolved IP so it can be pinned via CURLOPT_RESOLVE
     * to prevent DNS rebinding attacks.
     */
    private function validateUrlHost(string $host): string
    {
        // Resolve hostname to IP
        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            throw new UploadException('Cannot resolve hostname');
        }

        // Block private and reserved IP ranges
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new UploadException('URLs pointing to private or reserved IP ranges are not allowed');
        }

        return $ip;
    }

    private function getUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload_max_filesize limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            default => 'Unknown upload error',
        };
    }
}
