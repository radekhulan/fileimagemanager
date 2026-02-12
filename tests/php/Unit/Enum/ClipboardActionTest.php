<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Enum\ClipboardAction;

#[CoversClass(ClipboardAction::class)]
final class ClipboardActionTest extends TestCase
{
    #[Test]
    public function allExpectedCasesExist(): void
    {
        $cases = ClipboardAction::cases();
        self::assertCount(2, $cases);
    }

    #[Test]
    public function backingValuesAreCorrect(): void
    {
        self::assertSame('copy', ClipboardAction::Copy->value);
        self::assertSame('cut', ClipboardAction::Cut->value);
    }

    #[Test]
    public function canBeCreatedFromValue(): void
    {
        self::assertSame(ClipboardAction::Copy, ClipboardAction::from('copy'));
        self::assertSame(ClipboardAction::Cut, ClipboardAction::from('cut'));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        self::assertNull(ClipboardAction::tryFrom('paste'));
        self::assertNull(ClipboardAction::tryFrom(''));
    }
}
