<?php

declare(strict_types=1);

namespace RFM\Exception;

class InvalidExtensionException extends \RuntimeException
{
    public function __construct(string $message = 'Invalid file extension', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
