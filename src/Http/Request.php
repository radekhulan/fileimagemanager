<?php

declare(strict_types=1);

namespace RFM\Http;

final class Request
{
    public readonly string $method;
    public readonly string $path;
    public readonly string $queryString;

    /** @var array<string, string> */
    private array $headers;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Strip the base path so the router always sees app-relative paths
        // e.g. /public/api/files → /api/files, /filemanager/api/files → /api/files
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($basePath !== '' && $basePath !== '/' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        $this->path = $uri;
        $this->queryString = $_SERVER['QUERY_STRING'] ?? '';
        $this->headers = $this->parseHeaders();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $this->jsonBody()[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function files(): array
    {
        return $_FILES;
    }

    public function header(string $name): ?string
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? null;
    }

    public function cookie(string $name, mixed $default = null): mixed
    {
        return $_COOKIE[$name] ?? $default;
    }

    public function isAjax(): bool
    {
        return $this->header('x-requested-with') === 'XMLHttpRequest'
            || $this->header('accept') === 'application/json';
    }

    /**
     * @return array<string, mixed>
     */
    public function allPost(): array
    {
        return array_merge($_POST, $this->jsonBody());
    }

    /**
     * @return array<string, mixed>
     */
    private array $parsedJsonBody;

    private function jsonBody(): array
    {
        if (!isset($this->parsedJsonBody)) {
            $contentType = $this->header('content-type') ?? '';
            if (str_contains($contentType, 'application/json')) {
                $raw = file_get_contents('php://input');
                $this->parsedJsonBody = $raw ? (json_decode($raw, true) ?? []) : [];
            } else {
                $this->parsedJsonBody = [];
            }
        }
        return $this->parsedJsonBody;
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string) $_SERVER['CONTENT_LENGTH'];
        }
        return $headers;
    }
}
