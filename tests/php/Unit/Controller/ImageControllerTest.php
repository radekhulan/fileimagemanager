<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Controller\ImageController;
use RFM\Service\{SecurityService, ThumbnailService};
use RFM\Tests\Unit\TestConfigTrait;

#[CoversClass(ImageController::class)]
final class ImageControllerTest extends TestCase
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

    #[Test]
    public function saveEditedReturnsErrorWhenImageEditorDisabled(): void
    {
        $config = self::createConfig(imageEditorActive: false);
        $thumbnails = $this->createMock(ThumbnailService::class);
        $security = new SecurityService($config);
        $controller = new ImageController($config, $thumbnails, $security);

        $request = self::createRequest('POST', '/api/image/save', post: [
            'path' => 'photo.jpg',
            'image_data' => 'data:image/jpeg;base64,/9j/4AAQ',
        ]);
        $response = $controller->saveEdited($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function saveEditedReturnsErrorWhenPathEmpty(): void
    {
        $config = self::createConfig(imageEditorActive: true);
        $thumbnails = $this->createMock(ThumbnailService::class);
        $security = new SecurityService($config);
        $controller = new ImageController($config, $thumbnails, $security);

        $request = self::createRequest('POST', '/api/image/save', post: [
            'path' => '',
            'image_data' => 'somedata',
        ]);
        $response = $controller->saveEdited($request);

        self::assertFalse($response->getData()['success']);
        self::assertStringContainsString('path required', mb_strtolower($response->getData()['error']));
    }

    #[Test]
    public function saveEditedReturnsErrorWhenImageDataEmpty(): void
    {
        $config = self::createConfig(imageEditorActive: true);
        $thumbnails = $this->createMock(ThumbnailService::class);
        $security = new SecurityService($config);
        $controller = new ImageController($config, $thumbnails, $security);

        $request = self::createRequest('POST', '/api/image/save', post: [
            'path' => 'photo.jpg',
            'image_data' => '',
        ]);
        $response = $controller->saveEdited($request);

        self::assertFalse($response->getData()['success']);
        self::assertStringContainsString('data required', mb_strtolower($response->getData()['error']));
    }

    #[Test]
    public function saveEditedReturnsErrorForInvalidExtension(): void
    {
        $config = self::createConfig(imageEditorActive: true, currentPath: sys_get_temp_dir() . '/');
        $thumbnails = $this->createMock(ThumbnailService::class);
        $security = new SecurityService($config);
        $controller = new ImageController($config, $thumbnails, $security);

        // Create a temporary file with .gif extension
        $tmpFile = sys_get_temp_dir() . '/test_img.gif';
        file_put_contents($tmpFile, 'fake');

        try {
            $request = self::createRequest('POST', '/api/image/save', post: [
                'path' => 'test_img.gif',
                'image_data' => base64_encode('fakedata'),
            ]);
            $response = $controller->saveEdited($request);

            self::assertFalse($response->getData()['success']);
            self::assertStringContainsString('JPG', $response->getData()['error']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function saveEditedReturnsErrorForInvalidBase64(): void
    {
        $config = self::createConfig(imageEditorActive: true, currentPath: sys_get_temp_dir() . '/');
        $thumbnails = $this->createMock(ThumbnailService::class);
        $security = new SecurityService($config);
        $controller = new ImageController($config, $thumbnails, $security);

        $tmpFile = sys_get_temp_dir() . '/test_img.png';
        file_put_contents($tmpFile, 'fake');

        try {
            $request = self::createRequest('POST', '/api/image/save', post: [
                'path' => 'test_img.png',
                'image_data' => '!!!not-valid-base64!!!',
            ]);
            $response = $controller->saveEdited($request);

            // Either "Invalid image data" or "not a valid image"
            self::assertFalse($response->getData()['success']);
        } finally {
            @unlink($tmpFile);
        }
    }
}
