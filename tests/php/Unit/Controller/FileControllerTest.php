<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Controller\FileController;
use RFM\Enum\SortField;
use RFM\Service\FileSystemService;
use RFM\Tests\Unit\TestConfigTrait;

#[CoversClass(FileController::class)]
final class FileControllerTest extends TestCase
{
    use TestConfigTrait;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
    }

    // ---------------------------------------------------------------
    // list
    // ---------------------------------------------------------------

    #[Test]
    public function listDelegatesToFileSystemService(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);

        $fileSystem->expects(self::once())
            ->method('listDirectory')
            ->willReturn([
                'items' => [],
                'breadcrumb' => [],
                'counts' => ['files' => 0, 'folders' => 0],
                'totalSize' => 0,
                'total' => 0,
            ]);

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files');
        $response = $controller->list($request);
        $data = $response->getData();

        self::assertArrayHasKey('items', $data);
        self::assertArrayHasKey('breadcrumb', $data);
        self::assertArrayHasKey('counts', $data);
        self::assertArrayHasKey('clipboard', $data);
    }

    #[Test]
    public function listPassesCorrectParamsToService(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);

        $fileSystem->expects(self::once())
            ->method('listDirectory')
            ->with(
                subdir: 'photos/',
                sortBy: SortField::Date,
                descending: true,
                filter: 'test',
                typeFilter: 'image',
                limit: 50,
                offset: 10,
            )
            ->willReturn([
                'items' => [],
                'breadcrumb' => [],
                'counts' => ['files' => 0, 'folders' => 0],
                'totalSize' => 0,
                'total' => 0,
            ]);

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files', get: [
            'path' => 'photos/',
            'sort_by' => 'date',
            'descending' => '1',
            'filter' => 'test',
            'type_filter' => 'image',
            'limit' => '50',
            'offset' => '10',
        ]);
        $controller->list($request);
    }

    #[Test]
    public function listIncludesClipboardState(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);
        $fileSystem->method('listDirectory')->willReturn([
            'items' => [],
            'breadcrumb' => [],
            'counts' => ['files' => 0, 'folders' => 0],
            'totalSize' => 0,
            'total' => 0,
        ]);

        $_SESSION['RFM']['clipboard'] = ['action' => 'copy', 'paths' => ['file.txt']];

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files');
        $response = $controller->list($request);
        $data = $response->getData();

        self::assertTrue($data['clipboard']['hasItems']);
        self::assertSame('copy', $data['clipboard']['action']);
    }

    #[Test]
    public function listClipboardEmptyWhenNoSession(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);
        $fileSystem->method('listDirectory')->willReturn([
            'items' => [],
            'breadcrumb' => [],
            'counts' => ['files' => 0, 'folders' => 0],
            'totalSize' => 0,
            'total' => 0,
        ]);

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files');
        $response = $controller->list($request);
        $data = $response->getData();

        self::assertFalse($data['clipboard']['hasItems']);
        self::assertNull($data['clipboard']['action']);
    }

    // ---------------------------------------------------------------
    // info
    // ---------------------------------------------------------------

    #[Test]
    public function infoReturnsErrorWhenPathEmpty(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);
        $controller = new FileController($config, $fileSystem);

        $request = self::createRequest('GET', '/api/files/info');
        $response = $controller->info($request);

        self::assertFalse($response->getData()['success']);
        self::assertStringContainsString('Path required', $response->getData()['error']);
    }

    #[Test]
    public function infoDelegatesToService(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);
        $fileSystem->expects(self::once())
            ->method('getFileInfo')
            ->with('photo.jpg')
            ->willReturn(['name' => 'photo.jpg', 'size' => 1024]);

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files/info', get: ['path' => 'photo.jpg']);
        $response = $controller->info($request);

        self::assertSame('photo.jpg', $response->getData()['name']);
    }

    // ---------------------------------------------------------------
    // preview
    // ---------------------------------------------------------------

    #[Test]
    public function previewReturnsErrorWhenPathEmpty(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);
        $controller = new FileController($config, $fileSystem);

        $request = self::createRequest('GET', '/api/files/preview');
        $response = $controller->preview($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function previewReturnsTextTypeForTextFile(): void
    {
        $config = self::createConfig(previewTextFiles: true, previewableTextFileExts: ['txt']);
        $fileSystem = $this->createMock(FileSystemService::class);

        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_');
        file_put_contents($tmpFile, 'Hello World');

        $fileSystem->method('getFullPath')->willReturn($tmpFile);
        // validateFilePath is void — mock auto-handles it

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files/preview', get: ['path' => 'file.txt']);

        try {
            $response = $controller->preview($request);
            $data = $response->getData();

            self::assertSame('text', $data['type']);
            self::assertSame('Hello World', $data['content']);
            self::assertSame('txt', $data['extension']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function previewReturnsErrorWhenTextPreviewDisabled(): void
    {
        $config = self::createConfig(previewTextFiles: false, previewableTextFileExts: ['txt']);
        $fileSystem = $this->createMock(FileSystemService::class);
        $fileSystem->method('getFullPath')->willReturn('/tmp/fake.txt');
        // validateFilePath is void — mock auto-handles it

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files/preview', get: ['path' => 'file.txt']);
        $response = $controller->preview($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function previewReturnsImageTypeForImageFile(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);
        $fileSystem->method('getFullPath')->willReturn('/tmp/fake.jpg');
        // validateFilePath is void — mock auto-handles it

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files/preview', get: ['path' => 'photo.jpg']);
        $response = $controller->preview($request);
        $data = $response->getData();

        self::assertSame('image', $data['type']);
        self::assertStringContainsString('/source/photo.jpg', $data['url']);
    }

    #[Test]
    public function previewReturnsVideoTypeForVideoFile(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);
        $fileSystem->method('getFullPath')->willReturn('/tmp/fake.mp4');
        // validateFilePath is void — mock auto-handles it

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files/preview', get: ['path' => 'clip.mp4']);
        $response = $controller->preview($request);

        self::assertSame('video', $response->getData()['type']);
    }

    #[Test]
    public function previewReturnsAudioTypeForMusicFile(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);
        $fileSystem->method('getFullPath')->willReturn('/tmp/fake.mp3');
        // validateFilePath is void — mock auto-handles it

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files/preview', get: ['path' => 'song.mp3']);
        $response = $controller->preview($request);

        self::assertSame('audio', $response->getData()['type']);
    }

    #[Test]
    public function previewReturnsPdfType(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);
        $fileSystem->method('getFullPath')->willReturn('/tmp/fake.pdf');
        // validateFilePath is void — mock auto-handles it

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files/preview', get: ['path' => 'doc.pdf']);
        $response = $controller->preview($request);

        self::assertSame('pdf', $response->getData()['type']);
    }

    #[Test]
    public function previewReturnsGoogledocType(): void
    {
        $config = self::createConfig(googledocEnabled: true, googledocFileExts: ['docx']);
        $fileSystem = $this->createMock(FileSystemService::class);
        $fileSystem->method('getFullPath')->willReturn('/tmp/fake.docx');
        // validateFilePath is void — mock auto-handles it

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files/preview', get: ['path' => 'report.docx']);
        $response = $controller->preview($request);
        $data = $response->getData();

        self::assertSame('googledoc', $data['type']);
        self::assertStringContainsString('docs.google.com/gview', $data['url']);
    }

    #[Test]
    public function previewReturnsUnsupportedForUnknownExt(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);
        $fileSystem->method('getFullPath')->willReturn('/tmp/fake.xyz');
        // validateFilePath is void — mock auto-handles it

        $controller = new FileController($config, $fileSystem);
        $request = self::createRequest('GET', '/api/files/preview', get: ['path' => 'data.xyz']);
        $response = $controller->preview($request);

        self::assertSame('unsupported', $response->getData()['type']);
    }

    // ---------------------------------------------------------------
    // getContent
    // ---------------------------------------------------------------

    #[Test]
    public function getContentReturnsErrorWhenPathEmpty(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);
        $controller = new FileController($config, $fileSystem);

        $request = self::createRequest('GET', '/api/files/content');
        $response = $controller->getContent($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function getContentReturnsErrorWhenEditingDisabled(): void
    {
        $config = self::createConfig(editTextFiles: false);
        $fileSystem = $this->createMock(FileSystemService::class);
        $controller = new FileController($config, $fileSystem);

        $request = self::createRequest('GET', '/api/files/content', get: ['path' => 'file.txt']);
        $response = $controller->getContent($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function getContentReturnsErrorForNonEditableExt(): void
    {
        $config = self::createConfig(editTextFiles: true, editableTextFileExts: ['txt']);
        $fileSystem = $this->createMock(FileSystemService::class);
        $controller = new FileController($config, $fileSystem);

        $request = self::createRequest('GET', '/api/files/content', get: ['path' => 'photo.jpg']);
        $response = $controller->getContent($request);

        self::assertFalse($response->getData()['success']);
        self::assertStringContainsString('cannot be edited', $response->getData()['error']);
    }
}
