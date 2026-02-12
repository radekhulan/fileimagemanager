<?php

declare(strict_types=1);

namespace RFM\Enum;

enum FileCategory: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Archive = 'archive';
    case Misc = 'misc';
    case Directory = 'directory';

    /**
     * @param array{ext_img: string[], ext_video: string[], ext_music: string[], ext_file: string[], ext_misc: string[]} $extConfig
     */
    public static function fromExtension(string $ext, array $extConfig): self
    {
        $ext = mb_strtolower($ext);

        return match (true) {
            in_array($ext, $extConfig['ext_img'], true) => self::Image,
            in_array($ext, $extConfig['ext_video'], true) => self::Video,
            in_array($ext, $extConfig['ext_music'], true) => self::Audio,
            in_array($ext, $extConfig['ext_file'], true) => self::Document,
            in_array($ext, $extConfig['ext_misc'], true) => self::Archive,
            default => self::Misc,
        };
    }
}
