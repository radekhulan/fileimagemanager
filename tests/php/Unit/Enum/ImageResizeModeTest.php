<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Enum\ImageResizeMode;

#[CoversClass(ImageResizeMode::class)]
final class ImageResizeModeTest extends TestCase
{
    #[Test]
    public function allExpectedCasesExist(): void
    {
        $cases = ImageResizeMode::cases();
        self::assertCount(5, $cases);
    }

    #[Test]
    public function backingValuesAreCorrect(): void
    {
        self::assertSame('exact', ImageResizeMode::Exact->value);
        self::assertSame('portrait', ImageResizeMode::Portrait->value);
        self::assertSame('landscape', ImageResizeMode::Landscape->value);
        self::assertSame('auto', ImageResizeMode::Auto->value);
        self::assertSame('crop', ImageResizeMode::Crop->value);
    }

    #[Test]
    #[DataProvider('legacyValueProvider')]
    public function fromLegacyResolvesCorrectMode(string|int $input, ImageResizeMode $expected): void
    {
        self::assertSame($expected, ImageResizeMode::fromLegacy($input));
    }

    /**
     * @return iterable<string, array{string|int, ImageResizeMode}>
     */
    public static function legacyValueProvider(): iterable
    {
        // Integer values (legacy numeric mode)
        yield 'int 0 -> Exact' => [0, ImageResizeMode::Exact];
        yield 'int 1 -> Portrait' => [1, ImageResizeMode::Portrait];
        yield 'int 2 -> Landscape' => [2, ImageResizeMode::Landscape];
        yield 'int 3 -> Auto' => [3, ImageResizeMode::Auto];
        yield 'int 4 -> Crop' => [4, ImageResizeMode::Crop];

        // String-numeric values
        yield 'string "0" -> Exact' => ['0', ImageResizeMode::Exact];
        yield 'string "1" -> Portrait' => ['1', ImageResizeMode::Portrait];
        yield 'string "2" -> Landscape' => ['2', ImageResizeMode::Landscape];
        yield 'string "3" -> Auto' => ['3', ImageResizeMode::Auto];
        yield 'string "4" -> Crop' => ['4', ImageResizeMode::Crop];

        // Named string values
        yield 'string "exact"' => ['exact', ImageResizeMode::Exact];
        yield 'string "portrait"' => ['portrait', ImageResizeMode::Portrait];
        yield 'string "landscape"' => ['landscape', ImageResizeMode::Landscape];
        yield 'string "auto"' => ['auto', ImageResizeMode::Auto];
        yield 'string "crop"' => ['crop', ImageResizeMode::Crop];
    }

    #[Test]
    public function fromLegacyDefaultsToAutoForUnknownValue(): void
    {
        self::assertSame(ImageResizeMode::Auto, ImageResizeMode::fromLegacy('invalid'));
        self::assertSame(ImageResizeMode::Auto, ImageResizeMode::fromLegacy(99));
    }
}
