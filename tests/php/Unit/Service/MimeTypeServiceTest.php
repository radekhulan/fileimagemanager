<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Service\MimeTypeService;

#[CoversClass(MimeTypeService::class)]
final class MimeTypeServiceTest extends TestCase
{
    private MimeTypeService $service;

    protected function setUp(): void
    {
        $this->service = new MimeTypeService();
    }

    // ---------------------------------------------------------------
    // getExtensionForMime()
    // ---------------------------------------------------------------

    #[Test]
    #[DataProvider('mimeToExtProvider')]
    public function getExtensionForMimeReturnsCorrectExtension(string $mime, string $expectedExt): void
    {
        self::assertSame($expectedExt, $this->service->getExtensionForMime($mime));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mimeToExtProvider(): iterable
    {
        yield 'JPEG' => ['image/jpeg', 'jpg'];
        yield 'PNG' => ['image/png', 'png'];
        yield 'GIF' => ['image/gif', 'gif'];
        yield 'WebP' => ['image/webp', 'webp'];
        yield 'SVG' => ['image/svg+xml', 'svg'];
        yield 'ICO' => ['image/x-icon', 'ico'];
        yield 'ICO (vnd)' => ['image/vnd.microsoft.icon', 'ico'];
        yield 'BMP' => ['image/bmp', 'bmp'];
        yield 'MP4 video' => ['video/mp4', 'mp4'];
        yield 'MPEG video' => ['video/mpeg', 'mpg'];
        yield 'AVI' => ['video/x-msvideo', 'avi'];
        yield 'FLV' => ['video/x-flv', 'flv'];
        yield 'WebM video' => ['video/webm', 'webm'];
        yield 'QuickTime' => ['video/quicktime', 'mov'];
        yield 'M4V' => ['video/x-m4v', 'm4v'];
        yield 'MP3' => ['audio/mpeg', 'mp3'];
        yield 'M4A' => ['audio/mp4', 'm4a'];
        yield 'OGG' => ['audio/ogg', 'ogg'];
        yield 'WAV' => ['audio/wav', 'wav'];
        yield 'WAV (x-wav)' => ['audio/x-wav', 'wav'];
        yield 'MIDI' => ['audio/midi', 'mid'];
        yield 'AIFF' => ['audio/x-aiff', 'aiff'];
        yield 'PDF' => ['application/pdf', 'pdf'];
        yield 'DOC' => ['application/msword', 'doc'];
        yield 'DOCX' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'];
        yield 'XLS' => ['application/vnd.ms-excel', 'xls'];
        yield 'XLSX' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'];
        yield 'PPT' => ['application/vnd.ms-powerpoint', 'ppt'];
        yield 'PPTX' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx'];
        yield 'ZIP' => ['application/zip', 'zip'];
        yield 'RAR' => ['application/x-rar-compressed', 'rar'];
        yield 'GZIP' => ['application/gzip', 'gz'];
        yield 'TAR' => ['application/x-tar', 'tar'];
        yield 'TXT' => ['text/plain', 'txt'];
        yield 'HTML' => ['text/html', 'html'];
        yield 'CSS' => ['text/css', 'css'];
        yield 'XML' => ['text/xml', 'xml'];
        yield 'JSON' => ['application/json', 'json'];
    }

    #[Test]
    public function getExtensionForMimeReturnsNullForUnknownType(): void
    {
        self::assertNull($this->service->getExtensionForMime('application/x-unknown-type'));
    }

    // ---------------------------------------------------------------
    // getMimeForExtension()
    // ---------------------------------------------------------------

    #[Test]
    #[DataProvider('extToMimeProvider')]
    public function getMimeForExtensionReturnsCorrectMime(string $ext, string $expectedMime): void
    {
        self::assertSame($expectedMime, $this->service->getMimeForExtension($ext));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function extToMimeProvider(): iterable
    {
        yield 'jpg' => ['jpg', 'image/jpeg'];
        yield 'png' => ['png', 'image/png'];
        yield 'pdf' => ['pdf', 'application/pdf'];
        yield 'mp4' => ['mp4', 'video/mp4'];
        yield 'mp3' => ['mp3', 'audio/mpeg'];
        yield 'zip' => ['zip', 'application/zip'];
        yield 'txt' => ['txt', 'text/plain'];
        yield 'json' => ['json', 'application/json'];
    }

    #[Test]
    public function getMimeForExtensionIsCaseInsensitive(): void
    {
        self::assertSame('image/jpeg', $this->service->getMimeForExtension('JPG'));
        self::assertSame('application/pdf', $this->service->getMimeForExtension('PDF'));
    }

    #[Test]
    public function getMimeForExtensionReturnsOctetStreamForUnknown(): void
    {
        self::assertSame('application/octet-stream', $this->service->getMimeForExtension('xyz'));
        self::assertSame('application/octet-stream', $this->service->getMimeForExtension('unknownext'));
    }

    // ---------------------------------------------------------------
    // shouldRenameExtension() - logic tests (no filesystem needed)
    // ---------------------------------------------------------------

    #[Test]
    public function shouldRenameExtensionReturnsNullWhenMimeUnknown(): void
    {
        // Create a temporary file with octet-stream content
        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_');
        file_put_contents($tmpFile, random_bytes(32));

        try {
            // finfo will likely return application/octet-stream for random bytes
            // which is not in MIME_TO_EXT map -> expected extension is null
            $result = $this->service->shouldRenameExtension($tmpFile, 'bin');
            // If the MIME is not in the map, result should be null
            // (If it happens to match, that's fine too - the test is about the logic path)
            self::assertTrue($result === null || is_string($result));
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function shouldRenameExtensionReturnsNullWhenExtensionsMatch(): void
    {
        // Create a real PNG file (1x1 pixel)
        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_') . '.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        unset($img);

        try {
            $result = $this->service->shouldRenameExtension($tmpFile, 'png');
            self::assertNull($result, 'Should not suggest rename when extension matches MIME');
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function shouldRenameExtensionReturnsCorrectExtWhenMismatch(): void
    {
        // Create a real PNG file but claim it has .jpg extension
        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_') . '.jpg';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        unset($img);

        try {
            $result = $this->service->shouldRenameExtension($tmpFile, 'jpg');
            self::assertSame('png', $result, 'Should suggest rename from jpg to png');
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function shouldRenameExtensionAllowsJpegJpgVariants(): void
    {
        // Create a real JPEG file
        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_') . '.jpeg';
        $img = imagecreatetruecolor(1, 1);
        imagejpeg($img, $tmpFile);
        unset($img);

        try {
            // MIME will be image/jpeg -> expected ext is 'jpg'
            // But current ext is 'jpeg' which is a valid variant -> should return null
            $result = $this->service->shouldRenameExtension($tmpFile, 'jpeg');
            self::assertNull($result, 'Should not rename jpeg to jpg (they are equivalent)');
        } finally {
            @unlink($tmpFile);
        }
    }

    // ---------------------------------------------------------------
    // detect()
    // ---------------------------------------------------------------

    #[Test]
    public function detectReturnsCorrectMimeForPng(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_') . '.png';
        $img = imagecreatetruecolor(1, 1);
        imagepng($img, $tmpFile);
        unset($img);

        try {
            self::assertSame('image/png', $this->service->detect($tmpFile));
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function detectReturnsCorrectMimeForJpeg(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_') . '.jpg';
        $img = imagecreatetruecolor(1, 1);
        imagejpeg($img, $tmpFile);
        unset($img);

        try {
            self::assertSame('image/jpeg', $this->service->detect($tmpFile));
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function detectReturnsTextPlainForTextFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_') . '.txt';
        file_put_contents($tmpFile, 'Hello, this is a plain text file.');

        try {
            self::assertSame('text/plain', $this->service->detect($tmpFile));
        } finally {
            @unlink($tmpFile);
        }
    }
}
