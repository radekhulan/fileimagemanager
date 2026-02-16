<?php

declare(strict_types=1);

namespace RFM\Middleware;

use RFM\Exception\ForbiddenException;
use RFM\Http\Request;

final class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (empty($_SESSION['RFM']['verify']) || !is_string($_SESSION['RFM']['verify'])) {
            throw new ForbiddenException('Session not verified. Initialize session first.');
        }
    }
}
