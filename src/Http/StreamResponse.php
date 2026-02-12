<?php

declare(strict_types=1);

namespace RFM\Http;

final class StreamResponse
{
    public function __construct(
        private readonly string $filePath,
        private readonly string $fileName,
        private readonly ?string $mimeType = null,
    ) {}

    public function send(): never
    {
        if (!is_file($this->filePath) || !is_readable($this->filePath)) {
            http_response_code(404);
            echo 'File not found';
            exit;
        }

        $size = filesize($this->filePath);
        $mime = $this->mimeType ?? $this->detectMimeType();

        // Handle range requests for resume support
        $start = 0;
        $end = $size - 1;
        $statusCode = 200;

        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = $_SERVER['HTTP_RANGE'];
            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = (int) $matches[1];
                if (!empty($matches[2])) {
                    $end = (int) $matches[2];
                }
                $statusCode = 206;
            }
        }

        $length = $end - $start + 1;

        http_response_code($statusCode);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $this->fileName . '"');
        header('Content-Length: ' . $length);
        header('Accept-Ranges: bytes');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        if ($statusCode === 206) {
            header("Content-Range: bytes {$start}-{$end}/{$size}");
        }

        $fp = fopen($this->filePath, 'rb');
        if ($fp === false) {
            http_response_code(500);
            echo 'Cannot read file';
            exit;
        }

        if ($start > 0) {
            fseek($fp, $start);
        }

        $chunkSize = 1024 * 1024; // 1MB chunks
        $remaining = $length;

        while (!feof($fp) && $remaining > 0) {
            $read = min($chunkSize, $remaining);
            $data = fread($fp, $read);
            if ($data === false) {
                break;
            }
            echo $data;
            $remaining -= strlen($data);
            flush();
        }

        fclose($fp);
        exit;
    }

    private function detectMimeType(): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return 'application/octet-stream';
        }
        $mime = finfo_file($finfo, $this->filePath);
        finfo_close($finfo);
        return $mime ?: 'application/octet-stream';
    }
}
