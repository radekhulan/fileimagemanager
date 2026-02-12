<?php

declare(strict_types=1);

namespace RFM\Exception;

class ForbiddenException extends \RuntimeException
{
    public function __construct(string $message = 'Forbidden', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
