<?php

declare(strict_types=1);

namespace RFM\Http;

final class JsonResponse
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly array $data = [],
        private readonly int $statusCode = 200,
        private readonly array $headers = [],
    ) {}

    public function send(): never
    {
        http_response_code($this->statusCode);

        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(array $data = [], string $message = 'OK'): self
    {
        return new self(['success' => true, 'message' => $message, ...$data]);
    }

    public static function error(string $message, int $statusCode = 400, array $extra = []): self
    {
        return new self(['success' => false, 'error' => $message, ...$extra], $statusCode);
    }
}
