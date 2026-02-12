<?php

declare(strict_types=1);

namespace RFM\Enum;

enum ImageResizeMode: string
{
    case Exact = 'exact';
    case Portrait = 'portrait';
    case Landscape = 'landscape';
    case Auto = 'auto';
    case Crop = 'crop';

    public static function fromLegacy(string|int $value): self
    {
        return match ($value) {
            0, '0', 'exact' => self::Exact,
            1, '1', 'portrait' => self::Portrait,
            2, '2', 'landscape' => self::Landscape,
            3, '3', 'auto' => self::Auto,
            4, '4', 'crop' => self::Crop,
            default => self::Auto,
        };
    }
}
