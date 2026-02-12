<?php

declare(strict_types=1);

namespace RFM\Enum;

enum SortField: string
{
    case Name = 'name';
    case Date = 'date';
    case Size = 'size';
    case Extension = 'extension';
}
