<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Enum\ViewType;

#[CoversClass(ViewType::class)]
final class ViewTypeTest extends TestCase
{
    #[Test]
    public function allExpectedCasesExist(): void
    {
        $cases = ViewType::cases();
        self::assertCount(3, $cases);
    }

    #[Test]
    public function backingValuesAreCorrect(): void
    {
        self::assertSame(0, ViewType::Boxes->value);
        self::assertSame(1, ViewType::DetailedList->value);
        self::assertSame(2, ViewType::ColumnsList->value);
    }

    #[Test]
    public function canBeCreatedFromIntValue(): void
    {
        self::assertSame(ViewType::Boxes, ViewType::from(0));
        self::assertSame(ViewType::DetailedList, ViewType::from(1));
        self::assertSame(ViewType::ColumnsList, ViewType::from(2));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        self::assertNull(ViewType::tryFrom(99));
        self::assertNull(ViewType::tryFrom(-1));
    }
}
