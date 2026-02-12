<?php

declare(strict_types=1);

namespace RFM\DTO;

final readonly class BreadcrumbItem
{
    public function __construct(
        public string $name,
        public string $path,
    ) {}

    /**
     * @return array{name: string, path: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
        ];
    }
}
