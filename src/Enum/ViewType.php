<?php

declare(strict_types=1);

namespace RFM\Enum;

enum ViewType: int
{
    case Boxes = 0;
    case DetailedList = 1;
    case ColumnsList = 2;
}
