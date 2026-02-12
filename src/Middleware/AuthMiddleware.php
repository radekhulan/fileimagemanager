<?php

declare(strict_types=1);

namespace RFM\Middleware;

use RFM\Exception\ForbiddenException;
use RFM\Http\Request;

final class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (($_SESSION['RFM']['verify'] ?? '') !== 'FILEimagemanager') {
            throw new ForbiddenException('Session not verified. Initialize session first.');
        }
    }
}
