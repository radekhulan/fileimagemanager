<?php

declare(strict_types=1);

namespace RFM\Exception;

class FileNotFoundException extends \RuntimeException
{
    public function __construct(string $message = 'File not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
