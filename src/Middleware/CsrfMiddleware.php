<?php

declare(strict_types=1);

namespace RFM\Middleware;

use RFM\Exception\ForbiddenException;
use RFM\Http\Request;
use RFM\Service\SecurityService;

final class CsrfMiddleware
{
    public function __construct(
        private readonly SecurityService $security,
    ) {}

    public function handle(Request $request): void
    {
        $token = $request->header('x-csrf-token')
            ?? $request->post('csrf_token')
            ?? '';

        if (!is_string($token) || !$this->security->verifyCsrfToken($token)) {
            throw new ForbiddenException('CSRF token invalid or missing');
        }
    }
}
