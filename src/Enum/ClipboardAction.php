<?php

declare(strict_types=1);

namespace RFM\Enum;

enum ClipboardAction: string
{
    case Copy = 'copy';
    case Cut = 'cut';
}
