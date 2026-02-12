<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Enum\FileCategory;

#[CoversClass(FileCategory::class)]
final class FileCategoryTest extends TestCase
{
    #[Test]
    public function allExpectedCasesExist(): void
    {
        $cases = FileCategory::cases();
        $values = array_map(static fn(FileCategory $c) => $c->value, $cases);

        self::assertContains('image', $values);
        self::assertContains('video', $values);
        self::assertContains('audio', $values);
        self::assertContains('document', $values);
        self::assertContains('archive', $values);
        self::assertContains('misc', $values);
        self::assertContains('directory', $values);
        self::assertCount(7, $cases);
    }

    #[Test]
    public function backingValuesAreCorrect(): void
    {
        self::assertSame('image', FileCategory::Image->value);
        self::assertSame('video', FileCategory::Video->value);
        self::assertSame('audio', FileCategory::Audio->value);
        self::assertSame('document', FileCategory::Document->value);
        self::assertSame('archive', FileCategory::Archive->value);
        self::assertSame('misc', FileCategory::Misc->value);
        self::assertSame('directory', FileCategory::Directory->value);
    }

    #[Test]
    #[DataProvider('extensionCategoryProvider')]
    public function fromExtensionResolvesCorrectCategory(
        string $extension,
        FileCategory $expected,
    ): void {
        $extConfig = self::defaultExtConfig();
        self::assertSame($expected, FileCategory::fromExtension($extension, $extConfig));
    }

    /**
     * @return iterable<string, array{string, FileCategory}>
     */
    public static function extensionCategoryProvider(): iterable
    {
        yield 'jpg -> Image' => ['jpg', FileCategory::Image];
        yield 'PNG -> Image (case insensitive)' => ['PNG', FileCategory::Image];
        yield 'webp -> Image' => ['webp', FileCategory::Image];
        yield 'mp4 -> Video' => ['mp4', FileCategory::Video];
        yield 'avi -> Video' => ['avi', FileCategory::Video];
        yield 'mp3 -> Audio' => ['mp3', FileCategory::Audio];
        yield 'wav -> Audio' => ['wav', FileCategory::Audio];
        yield 'pdf -> Document' => ['pdf', FileCategory::Document];
        yield 'docx -> Document' => ['docx', FileCategory::Document];
        yield 'zip -> Archive' => ['zip', FileCategory::Archive];
        yield 'tar -> Archive' => ['tar', FileCategory::Archive];
        yield 'exe -> Misc (unknown)' => ['exe', FileCategory::Misc];
        yield 'random -> Misc (unknown)' => ['random', FileCategory::Misc];
    }

    /**
     * @return array{ext_img: string[], ext_video: string[], ext_music: string[], ext_file: string[], ext_misc: string[]}
     */
    private static function defaultExtConfig(): array
    {
        return [
            'ext_img' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'ico', 'webp'],
            'ext_video' => ['mov', 'mpeg', 'm4v', 'mp4', 'avi', 'mpg', 'wma', 'flv', 'webm'],
            'ext_music' => ['mp3', 'mpga', 'm4a', 'ac3', 'aiff', 'mid', 'ogg', 'wav'],
            'ext_file' => ['doc', 'docx', 'xls', 'xlsx', 'pdf'],
            'ext_misc' => ['zip', 'rar', 'gz', 'tar'],
        ];
    }
}
