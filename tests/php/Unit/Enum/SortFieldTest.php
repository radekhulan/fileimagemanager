<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Enum\SortField;

#[CoversClass(SortField::class)]
final class SortFieldTest extends TestCase
{
    #[Test]
    public function allExpectedCasesExist(): void
    {
        $cases = SortField::cases();
        self::assertCount(4, $cases);
    }

    #[Test]
    public function backingValuesAreCorrect(): void
    {
        self::assertSame('name', SortField::Name->value);
        self::assertSame('date', SortField::Date->value);
        self::assertSame('size', SortField::Size->value);
        self::assertSame('extension', SortField::Extension->value);
    }

    #[Test]
    public function canBeCreatedFromValue(): void
    {
        self::assertSame(SortField::Name, SortField::from('name'));
        self::assertSame(SortField::Date, SortField::from('date'));
        self::assertSame(SortField::Size, SortField::from('size'));
        self::assertSame(SortField::Extension, SortField::from('extension'));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        self::assertNull(SortField::tryFrom('type'));
        self::assertNull(SortField::tryFrom(''));
        self::assertNull(SortField::tryFrom('Name'));
    }
}
