<?php

declare(strict_types=1);

namespace RFM\Exception;

class UploadException extends \RuntimeException
{
    public function __construct(string $message = 'Upload failed', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
