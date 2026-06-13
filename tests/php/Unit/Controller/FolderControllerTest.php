<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Controller\FolderController;
use RFM\Service\{FileSystemService, SecurityService};
use RFM\Tests\Unit\TestConfigTrait;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(FolderController::class)]
final class FolderControllerTest extends TestCase
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
    // tree
    // ---------------------------------------------------------------

    #[Test]
    public function treeDelegatesToFileSystemService(): void
    {
        $config = self::createConfig();
        $fileSystem = $this->createMock(FileSystemService::class);
        $security = new SecurityService($config);

        $fileSystem->expects(self::once())
            ->method('getDirectoryTree')
            ->with('')
            ->willReturn([]);

        $controller = new FolderController($config, $fileSystem, $security);
        $request = self::createRequest('GET', '/api/folders/tree');
        $response = $controller->tree($request);
        $data = $response->getData();

        self::assertTrue($data['success']);
        self::assertSame([], $data['tree']);
    }

    #[Test]
    public function treePassesPathToService(): void
    {
        $config = self::createConfig(currentPath: sys_get_temp_dir() . '/');
        $fileSystem = $this->createMock(FileSystemService::class);
        $security = new SecurityService($config);

        // Create a real temp subdir so validatePath passes
        $subdir = sys_get_temp_dir() . '/rfm_tree_test_' . bin2hex(random_bytes(4));
        @mkdir($subdir);

        try {
            $relativePath = basename($subdir) . '/';
            $fileSystem->expects(self::once())
                ->method('getDirectoryTree')
                ->with($relativePath)
                ->willReturn([]);

            $controller = new FolderController($config, $fileSystem, $security);
            $request = self::createRequest('GET', '/api/folders/tree', get: ['path' => $relativePath]);
            $controller->tree($request);
        } finally {
            @rmdir($subdir);
        }
    }

    // ---------------------------------------------------------------
    // create
    // ---------------------------------------------------------------

    #[Test]
    public function createReturnsErrorWhenDisabled(): void
    {
        $config = self::createConfig(createFolders: false);
        $fileSystem = $this->createMock(FileSystemService::class);
        $security = new SecurityService($config);
        $controller = new FolderController($config, $fileSystem, $security);

        $request = self::createRequest('POST', '/api/folders/create', post: ['path' => '', 'name' => 'test']);
        $response = $controller->create($request);

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function createReturnsErrorWhenNameEmpty(): void
    {
        $config = self::createConfig(createFolders: true);
        $fileSystem = $this->createMock(FileSystemService::class);
        $security = new SecurityService($config);
        $controller = new FolderController($config, $fileSystem, $security);

        $request = self::createRequest('POST', '/api/folders/create', post: ['path' => '', 'name' => '']);
        $response = $controller->create($request);

        self::assertFalse($response->getData()['success']);
        self::assertStringContainsString('name required', mb_strtolower($response->getData()['error']));
    }

    #[Test]
    public function createDelegatesToService(): void
    {
        $config = self::createConfig(createFolders: true);
        $fileSystem = $this->createMock(FileSystemService::class);
        $security = new SecurityService($config);

        $fileSystem->expects(self::once())
            ->method('createFolder')
            ->with('photos/', 'vacation')
            ->willReturn('photos/vacation/');

        $controller = new FolderController($config, $fileSystem, $security);
        $request = self::createRequest('POST', '/api/folders/create', post: ['path' => 'photos/', 'name' => 'vacation']);
        $response = $controller->create($request);
        $data = $response->getData();

        self::assertTrue($data['success']);
        self::assertSame('photos/vacation/', $data['path']);
    }

    // ---------------------------------------------------------------
    // rename
    // ---------------------------------------------------------------

    #[Test]
    public function renameReturnsErrorWhenDisabled(): void
    {
        $config = self::createConfig(renameFolders: false);
        $fileSystem = $this->createMock(FileSystemService::class);
        $security = new SecurityService($config);
        $controller = new FolderController($config, $fileSystem, $security);

        $request = self::createRequest('POST', '/api/folders/rename', post: ['path' => 'old/', 'name' => 'new']);
        $response = $controller->rename($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function renameReturnsErrorWhenPathEmpty(): void
    {
        $config = self::createConfig(renameFolders: true);
        $fileSystem = $this->createMock(FileSystemService::class);
        $security = new SecurityService($config);
        $controller = new FolderController($config, $fileSystem, $security);

        $request = self::createRequest('POST', '/api/folders/rename', post: ['path' => '', 'name' => 'new']);
        $response = $controller->rename($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function renameReturnsErrorWhenNameEmpty(): void
    {
        $config = self::createConfig(renameFolders: true);
        $fileSystem = $this->createMock(FileSystemService::class);
        $security = new SecurityService($config);
        $controller = new FolderController($config, $fileSystem, $security);

        $request = self::createRequest('POST', '/api/folders/rename', post: ['path' => 'old/', 'name' => '']);
        $response = $controller->rename($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function renameDelegatesToService(): void
    {
        $config = self::createConfig(renameFolders: true);
        $fileSystem = $this->createMock(FileSystemService::class);
        $security = new SecurityService($config);

        $fileSystem->expects(self::once())
            ->method('renameFolder')
            ->with('old/', 'newname')
            ->willReturn('newname/');

        $controller = new FolderController($config, $fileSystem, $security);
        $request = self::createRequest('POST', '/api/folders/rename', post: ['path' => 'old/', 'name' => 'newname']);
        $response = $controller->rename($request);

        self::assertTrue($response->getData()['success']);
    }

    // ---------------------------------------------------------------
    // delete
    // ---------------------------------------------------------------

    #[Test]
    public function deleteReturnsErrorWhenDisabled(): void
    {
        $config = self::createConfig(deleteFolders: false);
        $fileSystem = $this->createMock(FileSystemService::class);
        $security = new SecurityService($config);
        $controller = new FolderController($config, $fileSystem, $security);

        $request = self::createRequest('POST', '/api/folders/delete', post: ['path' => 'folder/']);
        $response = $controller->delete($request);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function deleteReturnsErrorWhenPathEmpty(): void
    {
        $config = self::createConfig(deleteFolders: true);
        $fileSystem = $this->createMock(FileSystemService::class);
        $security = new SecurityService($config);
        $controller = new FolderController($config, $fileSystem, $security);

        $request = self::createRequest('POST', '/api/folders/delete', post: ['path' => '']);
        $response = $controller->delete($request);

        self::assertFalse($response->getData()['success']);
    }

    #[Test]
    public function deleteDelegatesToService(): void
    {
        $config = self::createConfig(deleteFolders: true);
        $fileSystem = $this->createMock(FileSystemService::class);
        $security = new SecurityService($config);

        $fileSystem->expects(self::once())
            ->method('deleteDirectory')
            ->with('folder/');

        $controller = new FolderController($config, $fileSystem, $security);
        $request = self::createRequest('POST', '/api/folders/delete', post: ['path' => 'folder/']);
        $response = $controller->delete($request);

        self::assertTrue($response->getData()['success']);
    }
}
