<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Controller\OperationController;
use RFM\Service\{ClipboardService, FileSystemService, SecurityService};
use RFM\Tests\Unit\TestConfigTrait;

#[CoversClass(OperationController::class)]
final class OperationControllerTest extends TestCase
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

    private function makeController(array $configOverrides = []): OperationController
    {
        $config = self::createConfig(...$configOverrides);
        $fileSystem = $this->createMock(FileSystemService::class);
        $clipboard = $this->createMock(ClipboardService::class);
        $security = new SecurityService($config);
        return new OperationController($config, $fileSystem, $clipboard, $security);
    }

    private function makeControllerWithMocks(array $configOverrides = []): array
    {
        $config = self::createConfig(...$configOverrides);
        $fileSystem = $this->createMock(FileSystemService::class);
        $clipboard = $this->createMock(ClipboardService::class);
        $security = new SecurityService($config);
        $controller = new OperationController($config, $fileSystem, $clipboard, $security);
        return [$controller, $fileSystem, $clipboard];
    }

    // ---------------------------------------------------------------
    // rename
    // ---------------------------------------------------------------

    #[Test]
    public function renameReturnsErrorWhenDisabled(): void
    {
        $controller = $this->makeController(['renameFiles' => false]);
        $request = self::createRequest('POST', '/api/operations/rename', post: ['path' => 'f.txt', 'name' => 'g.txt']);
        $response = $controller->rename($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function renameReturnsErrorWhenPathEmpty(): void
    {
        $controller = $this->makeController();
        $request = self::createRequest('POST', '/api/operations/rename', post: ['path' => '', 'name' => 'g.txt']);
        $response = $controller->rename($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function renameReturnsErrorWhenNameEmpty(): void
    {
        $controller = $this->makeController();
        $request = self::createRequest('POST', '/api/operations/rename', post: ['path' => 'f.txt', 'name' => '']);
        $response = $controller->rename($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function renameDelegatesToService(): void
    {
        [$controller, $fileSystem] = $this->makeControllerWithMocks();
        $fileSystem->expects(self::once())
            ->method('renameFile')
            ->with('old.txt', 'new.txt')
            ->willReturn('new.txt');

        $request = self::createRequest('POST', '/api/operations/rename', post: ['path' => 'old.txt', 'name' => 'new.txt']);
        $response = $controller->rename($request);

        self::assertTrue($response->getData()['success']);
    }

    // ---------------------------------------------------------------
    // delete
    // ---------------------------------------------------------------

    #[Test]
    public function deleteReturnsErrorWhenDisabled(): void
    {
        $controller = $this->makeController(['deleteFiles' => false]);
        $request = self::createRequest('POST', '/api/operations/delete', post: ['path' => 'f.txt']);
        $response = $controller->delete($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function deleteReturnsErrorWhenPathEmpty(): void
    {
        $controller = $this->makeController();
        $request = self::createRequest('POST', '/api/operations/delete', post: ['path' => '']);
        $response = $controller->delete($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function deleteDelegatesToService(): void
    {
        [$controller, $fileSystem] = $this->makeControllerWithMocks();
        $fileSystem->expects(self::once())
            ->method('deleteFile')
            ->with('file.txt');

        $request = self::createRequest('POST', '/api/operations/delete', post: ['path' => 'file.txt']);
        $response = $controller->delete($request);

        self::assertTrue($response->getData()['success']);
    }

    // ---------------------------------------------------------------
    // deleteBulk
    // ---------------------------------------------------------------

    #[Test]
    public function deleteBulkReturnsErrorWhenDisabled(): void
    {
        $controller = $this->makeController(['deleteFiles' => false]);
        $request = self::createRequest('POST', '/api/operations/delete-bulk', post: ['paths' => ['a.txt']]);
        $response = $controller->deleteBulk($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function deleteBulkReturnsErrorWhenPathsEmpty(): void
    {
        $controller = $this->makeController();
        $request = self::createRequest('POST', '/api/operations/delete-bulk', post: ['paths' => []]);
        $response = $controller->deleteBulk($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function deleteBulkReportsErrorForFolderWhenDeletionDisabled(): void
    {
        $config = self::createConfig(deleteFiles: true, deleteFolders: false, currentPath: sys_get_temp_dir() . '/');
        $fileSystem = $this->createMock(FileSystemService::class);
        $clipboard = $this->createMock(ClipboardService::class);
        $security = new SecurityService($config);
        $controller = new OperationController($config, $fileSystem, $clipboard, $security);

        // Create a real temp dir so is_dir() returns true
        $tmpDir = sys_get_temp_dir() . '/rfm_bulk_test_' . bin2hex(random_bytes(4));
        @mkdir($tmpDir);

        try {
            $request = self::createRequest('POST', '/api/operations/delete-bulk', post: ['paths' => [basename($tmpDir)]]);
            $response = $controller->deleteBulk($request);
            $data = $response->getData();

            self::assertNotEmpty($data['errors']);
            self::assertStringContainsString('Folder deletion disabled', $data['errors'][0]['error']);
        } finally {
            @rmdir($tmpDir);
        }
    }

    // ---------------------------------------------------------------
    // duplicate
    // ---------------------------------------------------------------

    #[Test]
    public function duplicateReturnsErrorWhenDisabled(): void
    {
        $controller = $this->makeController(['duplicateFiles' => false]);
        $request = self::createRequest('POST', '/api/operations/duplicate', post: ['path' => 'f.txt']);
        $response = $controller->duplicate($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function duplicateReturnsErrorWhenPathEmpty(): void
    {
        $controller = $this->makeController();
        $request = self::createRequest('POST', '/api/operations/duplicate', post: ['path' => '']);
        $response = $controller->duplicate($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function duplicateDelegatesToService(): void
    {
        [$controller, $fileSystem] = $this->makeControllerWithMocks();
        $fileSystem->expects(self::once())
            ->method('duplicateFile')
            ->with('file.txt', null)
            ->willReturn('file_copy.txt');

        $request = self::createRequest('POST', '/api/operations/duplicate', post: ['path' => 'file.txt']);
        $response = $controller->duplicate($request);

        self::assertTrue($response->getData()['success']);
    }

    // ---------------------------------------------------------------
    // copy
    // ---------------------------------------------------------------

    #[Test]
    public function copyReturnsErrorWhenDisabled(): void
    {
        $controller = $this->makeController(['copyCutFiles' => false]);
        $request = self::createRequest('POST', '/api/operations/copy', post: ['paths' => ['a.txt']]);
        $response = $controller->copy($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function copyReturnsErrorWhenPathsEmpty(): void
    {
        $controller = $this->makeController();
        $request = self::createRequest('POST', '/api/operations/copy', post: ['paths' => []]);
        $response = $controller->copy($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function copyDelegatesToClipboardService(): void
    {
        [$controller, , $clipboard] = $this->makeControllerWithMocks();
        $clipboard->expects(self::once())
            ->method('copy')
            ->with(['file.txt']);
        $clipboard->method('getState')->willReturn([
            'hasItems' => true, 'action' => 'copy', 'paths' => ['file.txt'], 'count' => 1,
        ]);

        $request = self::createRequest('POST', '/api/operations/copy', post: ['paths' => ['file.txt']]);
        $response = $controller->copy($request);

        self::assertTrue($response->getData()['success']);
    }

    // ---------------------------------------------------------------
    // cut
    // ---------------------------------------------------------------

    #[Test]
    public function cutReturnsErrorWhenDisabled(): void
    {
        $controller = $this->makeController(['copyCutFiles' => false]);
        $request = self::createRequest('POST', '/api/operations/cut', post: ['paths' => ['a.txt']]);
        $response = $controller->cut($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function cutDelegatesToClipboardService(): void
    {
        [$controller, , $clipboard] = $this->makeControllerWithMocks();
        $clipboard->expects(self::once())
            ->method('cut')
            ->with(['file.txt']);
        $clipboard->method('getState')->willReturn([
            'hasItems' => true, 'action' => 'cut', 'paths' => ['file.txt'], 'count' => 1,
        ]);

        $request = self::createRequest('POST', '/api/operations/cut', post: ['paths' => ['file.txt']]);
        $response = $controller->cut($request);

        self::assertTrue($response->getData()['success']);
    }

    // ---------------------------------------------------------------
    // paste
    // ---------------------------------------------------------------

    #[Test]
    public function pasteReturnsErrorWhenClipboardEmpty(): void
    {
        [$controller, , $clipboard] = $this->makeControllerWithMocks();
        $clipboard->method('getAction')->willReturn(null);
        $clipboard->method('getPaths')->willReturn([]);

        $request = self::createRequest('POST', '/api/operations/paste', post: ['path' => 'target/']);
        $response = $controller->paste($request);

        self::assertFalse($response->getData()['success']);
        self::assertStringContainsString('empty', mb_strtolower($response->getData()['error']));
    }

    #[Test]
    public function pasteClearsCutClipboard(): void
    {
        $config = self::createConfig(currentPath: sys_get_temp_dir() . '/');
        $fileSystem = $this->createMock(FileSystemService::class);
        $clipboard = $this->createMock(ClipboardService::class);
        $security = new SecurityService($config);
        $controller = new OperationController($config, $fileSystem, $clipboard, $security);

        $clipboard->method('getAction')->willReturn(\RFM\Enum\ClipboardAction::Cut);
        $clipboard->method('getPaths')->willReturn(['file.txt']);
        $fileSystem->method('moveFile');

        $clipboard->expects(self::once())->method('clear');

        $request = self::createRequest('POST', '/api/operations/paste', post: ['path' => 'target/']);
        $controller->paste($request);
    }

    #[Test]
    public function pasteDoesNotClearCopyClipboard(): void
    {
        $config = self::createConfig(currentPath: sys_get_temp_dir() . '/');
        $fileSystem = $this->createMock(FileSystemService::class);
        $clipboard = $this->createMock(ClipboardService::class);
        $security = new SecurityService($config);
        $controller = new OperationController($config, $fileSystem, $clipboard, $security);

        $clipboard->method('getAction')->willReturn(\RFM\Enum\ClipboardAction::Copy);
        $clipboard->method('getPaths')->willReturn(['file.txt']);
        $fileSystem->method('duplicateFile');

        $clipboard->expects(self::never())->method('clear');

        $request = self::createRequest('POST', '/api/operations/paste', post: ['path' => 'target/']);
        $controller->paste($request);
    }

    // ---------------------------------------------------------------
    // clearClipboard
    // ---------------------------------------------------------------

    #[Test]
    public function clearClipboardDelegatesToService(): void
    {
        [$controller, , $clipboard] = $this->makeControllerWithMocks();
        $clipboard->expects(self::once())->method('clear');

        $request = self::createRequest('POST', '/api/operations/clear-clipboard');
        $response = $controller->clearClipboard($request);

        self::assertTrue($response->getData()['success']);
    }

    // ---------------------------------------------------------------
    // chmod
    // ---------------------------------------------------------------

    #[Test]
    public function chmodReturnsErrorWhenDisabled(): void
    {
        $controller = $this->makeController(['chmodFiles' => false, 'chmodDirs' => false]);
        $request = self::createRequest('POST', '/api/operations/chmod', post: ['path' => 'f.txt', 'mode' => '755']);
        $response = $controller->chmod($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function chmodReturnsErrorForInvalidMode(): void
    {
        $controller = $this->makeController(['chmodFiles' => true]);
        $request = self::createRequest('POST', '/api/operations/chmod', post: ['path' => 'f.txt', 'mode' => 'abc']);
        $response = $controller->chmod($request);

        self::assertFalse($response->getData()['success']);
        self::assertStringContainsString('Invalid', $response->getData()['error']);
    }

    #[Test]
    public function chmodReturnsErrorWhenPathEmpty(): void
    {
        $controller = $this->makeController(['chmodFiles' => true]);
        $request = self::createRequest('POST', '/api/operations/chmod', post: ['path' => '', 'mode' => '755']);
        $response = $controller->chmod($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function chmodDelegatesToService(): void
    {
        $config = self::createConfig(chmodFiles: true);
        $fileSystem = $this->createMock(FileSystemService::class);
        $clipboard = $this->createMock(ClipboardService::class);
        $security = new SecurityService($config);
        $controller = new OperationController($config, $fileSystem, $clipboard, $security);

        $fileSystem->expects(self::once())
            ->method('changePermissions')
            ->with('file.txt', 0755, 'none');

        $request = self::createRequest('POST', '/api/operations/chmod', post: ['path' => 'file.txt', 'mode' => '755']);
        $response = $controller->chmod($request);

        self::assertTrue($response->getData()['success']);
    }

    // ---------------------------------------------------------------
    // extract
    // ---------------------------------------------------------------

    #[Test]
    public function extractReturnsErrorWhenDisabled(): void
    {
        $controller = $this->makeController(['extractFiles' => false]);
        $request = self::createRequest('POST', '/api/operations/extract', post: ['path' => 'archive.zip']);
        $response = $controller->extract($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function extractReturnsErrorWhenPathEmpty(): void
    {
        $controller = $this->makeController(['extractFiles' => true]);
        $request = self::createRequest('POST', '/api/operations/extract', post: ['path' => '']);
        $response = $controller->extract($request);

        self::assertFalse($response->getData()['success']);
    }

    // ---------------------------------------------------------------
    // saveTextFile
    // ---------------------------------------------------------------

    #[Test]
    public function saveTextFileReturnsErrorWhenDisabled(): void
    {
        $controller = $this->makeController(['editTextFiles' => false]);
        $request = self::createRequest('POST', '/api/operations/save-text', post: ['path' => 'f.txt', 'content' => 'x']);
        $response = $controller->saveTextFile($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function saveTextFileReturnsErrorWhenPathEmpty(): void
    {
        $controller = $this->makeController(['editTextFiles' => true]);
        $request = self::createRequest('POST', '/api/operations/save-text', post: ['path' => '', 'content' => 'x']);
        $response = $controller->saveTextFile($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function saveTextFileReturnsErrorForNonEditableExt(): void
    {
        $controller = $this->makeController(['editTextFiles' => true]);
        $request = self::createRequest('POST', '/api/operations/save-text', post: ['path' => 'photo.jpg', 'content' => 'x']);
        $response = $controller->saveTextFile($request);

        self::assertFalse($response->getData()['success']);
        self::assertStringContainsString('cannot be edited', $response->getData()['error']);
    }

    // ---------------------------------------------------------------
    // createFile
    // ---------------------------------------------------------------

    #[Test]
    public function createFileReturnsErrorWhenDisabled(): void
    {
        $controller = $this->makeController(['createTextFiles' => false]);
        $request = self::createRequest('POST', '/api/operations/create-file', post: ['path' => '', 'name' => 'f.txt']);
        $response = $controller->createFile($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function createFileReturnsErrorWhenNameEmpty(): void
    {
        $controller = $this->makeController(['createTextFiles' => true]);
        $request = self::createRequest('POST', '/api/operations/create-file', post: ['path' => '', 'name' => '']);
        $response = $controller->createFile($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function createFileReturnsErrorForNonEditableExt(): void
    {
        $controller = $this->makeController(['createTextFiles' => true]);
        $request = self::createRequest('POST', '/api/operations/create-file', post: ['path' => '', 'name' => 'photo.jpg']);
        $response = $controller->createFile($request);

        self::assertFalse($response->getData()['success']);
        self::assertStringContainsString('extensions', mb_strtolower($response->getData()['error']));
    }
}
