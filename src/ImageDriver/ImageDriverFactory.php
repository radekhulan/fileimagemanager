<?php

declare(strict_types=1);

namespace RFM\ImageDriver;

final class ImageDriverFactory
{
    public static function create(): ImageDriverInterface
    {
        if (extension_loaded('imagick')) {
            return new ImagickDriver();
        }

        return new GdDriver();
    }
}
