<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Controller\UploadController;
use RFM\Service\UploadService;
use RFM\Tests\Unit\TestConfigTrait;

#[CoversClass(UploadController::class)]
final class UploadControllerTest extends TestCase
{
    use TestConfigTrait;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
    }

    // ---------------------------------------------------------------
    // upload
    // ---------------------------------------------------------------

    #[Test]
    public function uploadReturnsErrorWhenUploadsDisabled(): void
    {
        $config = self::createConfig(uploadFiles: false);
        $uploadService = $this->createMock(UploadService::class);
        $controller = new UploadController($config, $uploadService);

        $request = self::createRequest('POST', '/api/upload');
        $response = $controller->upload($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function uploadReturnsErrorWhenNoFilesUploaded(): void
    {
        $config = self::createConfig(uploadFiles: true);
        $uploadService = $this->createMock(UploadService::class);
        $controller = new UploadController($config, $uploadService);

        $request = self::createRequest('POST', '/api/upload');
        $response = $controller->upload($request);

        self::assertFalse($response->getData()['success']);
        self::assertStringContainsString('No files', $response->getData()['error']);
    }

    #[Test]
    public function uploadDelegatesToUploadService(): void
    {
        $config = self::createConfig(uploadFiles: true);
        $uploadService = $this->createMock(UploadService::class);
        $uploadService->expects(self::once())
            ->method('handleUpload')
            ->with('photos/', self::isArray())
            ->willReturn([['name' => 'test.jpg', 'path' => 'photos/test.jpg', 'size' => 1024, 'type' => 'image/jpeg']]);

        $controller = new UploadController($config, $uploadService);

        $_FILES = ['files' => ['name' => 'test.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/tmp/phpXXX', 'error' => 0, 'size' => 1024]];
        $request = self::createRequest('POST', '/api/upload', post: ['path' => 'photos/']);
        $response = $controller->upload($request);
        $data = $response->getData();

        self::assertTrue($data['success']);
        self::assertArrayHasKey('files', $data);
    }

    // ---------------------------------------------------------------
    // uploadFromUrl
    // ---------------------------------------------------------------

    #[Test]
    public function uploadFromUrlReturnsErrorWhenUploadsDisabled(): void
    {
        $config = self::createConfig(uploadFiles: false);
        $uploadService = $this->createMock(UploadService::class);
        $controller = new UploadController($config, $uploadService);

        $request = self::createRequest('POST', '/api/upload/url', post: ['url' => 'http://example.com/file.jpg']);
        $response = $controller->uploadFromUrl($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function uploadFromUrlReturnsErrorWhenUrlUploadDisabled(): void
    {
        $config = self::createConfig(uploadFiles: true, urlUpload: false);
        $uploadService = $this->createMock(UploadService::class);
        $controller = new UploadController($config, $uploadService);

        $request = self::createRequest('POST', '/api/upload/url', post: ['url' => 'http://example.com/file.jpg']);
        $response = $controller->uploadFromUrl($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function uploadFromUrlReturnsErrorWhenUrlEmpty(): void
    {
        $config = self::createConfig(uploadFiles: true, urlUpload: true);
        $uploadService = $this->createMock(UploadService::class);
        $controller = new UploadController($config, $uploadService);

        $request = self::createRequest('POST', '/api/upload/url', post: ['url' => '']);
        $response = $controller->uploadFromUrl($request);

        self::assertFalse($response->getData()['success']);
        self::assertStringContainsString('URL required', $response->getData()['error']);
    }

    #[Test]
    public function uploadFromUrlDelegatesToUploadService(): void
    {
        $config = self::createConfig(uploadFiles: true, urlUpload: true);
        $uploadService = $this->createMock(UploadService::class);
        $uploadService->expects(self::once())
            ->method('uploadFromUrl')
            ->with('http://example.com/photo.jpg', 'photos/')
            ->willReturn(['name' => 'photo.jpg', 'path' => 'photos/photo.jpg', 'size' => 2048, 'type' => 'image/jpeg']);

        $controller = new UploadController($config, $uploadService);

        $request = self::createRequest('POST', '/api/upload/url', post: ['url' => 'http://example.com/photo.jpg', 'path' => 'photos/']);
        $response = $controller->uploadFromUrl($request);
        $data = $response->getData();

        self::assertTrue($data['success']);
        self::assertArrayHasKey('file', $data);
    }
}
