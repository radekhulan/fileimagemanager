<?php

declare(strict_types=1);

namespace RFM\Service;

final class MimeTypeService
{
    /** @var array<string, string> Common MIME type to extension mapping */
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/bmp' => 'bmp',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'video/mp4' => 'mp4',
        'video/mpeg' => 'mpg',
        'video/x-msvideo' => 'avi',
        'video/x-flv' => 'flv',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-m4v' => 'm4v',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/ogg' => 'ogg',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/midi' => 'mid',
        'audio/x-aiff' => 'aiff',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/zip' => 'zip',
        'application/x-rar-compressed' => 'rar',
        'application/gzip' => 'gz',
        'application/x-tar' => 'tar',
        'text/plain' => 'txt',
        'text/html' => 'html',
        'text/css' => 'css',
        'text/xml' => 'xml',
        'application/json' => 'json',
    ];

    /**
     * Detect MIME type of a file using finfo.
     */
    public function detect(string $filePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return $mime ?: 'application/octet-stream';
    }

    /**
     * Get the expected extension for a MIME type.
     */
    public function getExtensionForMime(string $mimeType): ?string
    {
        return self::MIME_TO_EXT[$mimeType] ?? null;
    }

    /**
     * Get MIME type for an extension.
     */
    public function getMimeForExtension(string $extension): string
    {
        $ext = strtolower($extension);
        $flipped = array_flip(self::MIME_TO_EXT);
        return $flipped[$ext] ?? 'application/octet-stream';
    }

    /**
     * Check if MIME type matches the file extension.
     * Used to rename files with wrong extensions.
     */
    public function shouldRenameExtension(string $filePath, string $currentExt): ?string
    {
        $mime = $this->detect($filePath);
        $expectedExt = $this->getExtensionForMime($mime);

        if ($expectedExt === null) {
            return null;
        }

        $currentExt = strtolower($currentExt);

        // Handle JPEG variants
        if ($expectedExt === 'jpg' && $currentExt === 'jpeg') {
            return null;
        }
        if ($expectedExt === 'jpeg' && $currentExt === 'jpg') {
            return null;
        }

        if ($expectedExt !== $currentExt) {
            return $expectedExt;
        }

        return null;
    }
}
