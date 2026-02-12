<?php

declare(strict_types=1);

namespace RFM\Service;

use RFM\Config\AppConfig;
use RFM\Exception\{PathTraversalException, ForbiddenException, InvalidExtensionException};

final class SecurityService
{
    public function __construct(
        private readonly AppConfig $config,
    ) {}

    /**
     * Validate that a path is within allowed directories.
     * Prevents path traversal attacks using realpath comparison.
     */
    public function validatePath(string $path): void
    {
        $allowedMedia = realpath($this->config->currentPath);
        $allowedThumbs = realpath($this->config->thumbsBasePath);

        if ($allowedMedia === false && $allowedThumbs === false) {
            throw new PathTraversalException('Invalid base paths configuration');
        }

        $realPath = realpath($path);

        // If path doesn't exist yet (new file/folder), check parent directory
        if ($realPath === false) {
            $parentDir = dirname($path);
            $realParent = realpath($parentDir);
            if ($realParent === false) {
                throw new PathTraversalException('Path does not exist');
            }
            $realPath = $realParent . DIRECTORY_SEPARATOR . basename($path);
        }

        $normalizedPath = $this->normalizePath($realPath);

        $isAllowed = false;

        if ($allowedMedia !== false) {
            $normalizedMedia = $this->normalizePath($allowedMedia);
            if (str_starts_with($normalizedPath, $normalizedMedia)) {
                $isAllowed = true;
            }
        }

        if (!$isAllowed && $allowedThumbs !== false) {
            $normalizedThumbs = $this->normalizePath($allowedThumbs);
            if (str_starts_with($normalizedPath, $normalizedThumbs)) {
                $isAllowed = true;
            }
        }

        if (!$isAllowed) {
            throw new PathTraversalException('Path outside allowed directories');
        }
    }

    /**
     * Check that a relative path doesn't contain traversal sequences.
     */
    public function checkRelativePath(string $path): void
    {
        $this->checkRelativePathPartial($path);

        // Also check URL-decoded version
        $decoded = rawurldecode($path);
        if ($decoded !== $path) {
            $this->checkRelativePathPartial($decoded);
        }
    }

    /**
     * Validate file extension against blacklist/whitelist.
     */
    public function validateExtension(string $extension): void
    {
        $ext = mb_strtolower($extension);

        if (!empty($this->config->extBlacklist)) {
            if (in_array($ext, $this->config->extBlacklist, true)) {
                throw new InvalidExtensionException("Extension '{$ext}' is blacklisted");
            }
        } else {
            if (!in_array($ext, $this->config->ext, true)) {
                throw new InvalidExtensionException("Extension '{$ext}' is not allowed");
            }
        }
    }

    /**
     * Check if an extension is an image type.
     */
    public function isImageExtension(string $extension): bool
    {
        return in_array(mb_strtolower($extension), $this->config->extImg, true);
    }

    /**
     * Generate a CSRF token for the current session.
     */
    public function generateCsrfToken(): string
    {
        if (empty($_SESSION['RFM']['csrf_token'])) {
            $_SESSION['RFM']['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['RFM']['csrf_token'];
    }

    /**
     * Verify a CSRF token against the session token.
     * Uses timing-safe comparison.
     */
    public function verifyCsrfToken(string $token): bool
    {
        $sessionToken = $_SESSION['RFM']['csrf_token'] ?? '';
        return !empty($token) && !empty($sessionToken) && hash_equals($sessionToken, $token);
    }

    /**
     * Sanitize a filename: strip tags, transliterate, replace spaces, etc.
     */
    public function sanitizeFilename(string $name, bool $isFolder = false): string
    {
        $name = strip_tags(htmlspecialchars($name, ENT_QUOTES, 'UTF-8'));

        if ($this->config->convertSpaces) {
            $name = str_replace(' ', $this->config->replaceWith, $name);
        }

        if ($this->config->transliteration) {
            $name = $this->transliterate($name);
        }

        if ($this->config->lowerCase) {
            $name = mb_strtolower($name);
        }

        // Remove dangerous characters
        $name = str_replace(['"', "'", '/', '\\'], '', $name);
        $name = strip_tags($name);

        // Prevent dot-only filenames (e.g. ".jpg")
        if (!$this->config->emptyFilename && str_starts_with($name, '.')) {
            $name = 'file' . $name;
        }

        return trim($name);
    }

    /**
     * Validate access key if access keys are enabled.
     */
    public function validateAccessKey(?string $key): void
    {
        if (!$this->config->useAccessKeys) {
            return;
        }

        if ($key === null || $key === '') {
            throw new ForbiddenException('Access key required');
        }

        // Validate key format
        if (preg_match('/[^a-zA-Z0-9._-]/', $key)) {
            throw new ForbiddenException('Invalid access key format');
        }

        if (!in_array($key, $this->config->accessKeys, true)) {
            throw new ForbiddenException('Invalid access key');
        }
    }

    /**
     * Check if a directory is the upload root (should not be deleted).
     */
    public function isUploadDir(string $path): bool
    {
        $realPath = realpath($path);
        $uploadDir = realpath($this->config->currentPath);

        if ($realPath === false || $uploadDir === false) {
            return false;
        }

        return $this->normalizePath($realPath) === $this->normalizePath($uploadDir);
    }

    private function checkRelativePathPartial(string $path): void
    {
        if (
            str_contains($path, '../')
            || str_contains($path, './')
            || str_contains($path, '/..')
            || str_contains($path, '..\\')
            || str_contains($path, '.\\')
            || str_contains($path, '\\..')
            || $path === '..'
        ) {
            throw new PathTraversalException('Relative path traversal detected');
        }
    }

    private function normalizePath(string $path): string
    {
        $path = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;

        // Case-insensitive on Windows
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
        }

        return $path;
    }

    private function transliterate(string $str): string
    {
        if (!mb_detect_encoding($str, 'UTF-8', true)) {
            $str = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
        }

        if (function_exists('transliterator_transliterate')) {
            $result = transliterator_transliterate('Any-Latin; Latin-ASCII', $str);
            if ($result !== false) {
                $str = $result;
            }
        } else {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
            if ($converted !== false) {
                $str = $converted;
            }
        }

        // Remove any remaining non-ASCII characters
        $str = preg_replace('/[^a-zA-Z0-9.\[\]_| -]/', '', $str) ?? $str;

        return $str;
    }
}
