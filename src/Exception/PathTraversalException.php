<?php

declare(strict_types=1);

namespace RFM\Exception;

class PathTraversalException extends \RuntimeException
{
    public function __construct(string $message = 'Path traversal detected', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
