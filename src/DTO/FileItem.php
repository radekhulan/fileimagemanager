<?php

declare(strict_types=1);

namespace RFM\DTO;

use RFM\Enum\FileCategory;

final readonly class FileItem
{
    public function __construct(
        public string $name,
        public string $path,
        public bool $isDir,
        public int $size,
        public int $modifiedAt,
        public string $extension,
        public FileCategory $category,
        public ?string $thumbnailUrl = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $permissions = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'isDir' => $this->isDir,
            'size' => $this->size,
            'modifiedAt' => $this->modifiedAt,
            'extension' => $this->extension,
            'category' => $this->category->value,
            'thumbnailUrl' => $this->thumbnailUrl,
            'width' => $this->width,
            'height' => $this->height,
            'permissions' => $this->permissions,
        ];
    }
}
