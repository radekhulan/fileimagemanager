<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Enum\ClipboardAction;
use RFM\Service\ClipboardService;
use RFM\Service\SecurityService;
use RFM\Tests\Unit\TestConfigTrait;

#[CoversClass(ClipboardService::class)]
final class ClipboardServiceTest extends TestCase
{
    use TestConfigTrait;

    private ClipboardService $service;

    protected function setUp(): void
    {
        $_SESSION = [];
        $config = self::createConfig(currentPath: sys_get_temp_dir() . '/');
        $security = new SecurityService($config);
        $this->service = new ClipboardService($config, $security);
    }

    #[Test]
    public function initialStateIsEmpty(): void
    {
        $state = $this->service->getState();
        self::assertFalse($state['hasItems']);
        self::assertNull($state['action']);
        self::assertSame([], $state['paths']);
        self::assertSame(0, $state['count']);
    }

    #[Test]
    public function getActionReturnsNullWhenEmpty(): void
    {
        self::assertNull($this->service->getAction());
    }

    #[Test]
    public function getPathsReturnsEmptyArrayWhenEmpty(): void
    {
        self::assertSame([], $this->service->getPaths());
    }

    #[Test]
    public function copySetsSessionCorrectly(): void
    {
        $_SESSION['RFM']['clipboard'] = null;

        // Use paths that pass validation — set currentPath to temp dir
        // and create a real temporary file
        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_');
        $relativePath = basename($tmpFile);

        try {
            $this->service->copy([$relativePath]);

            $state = $this->service->getState();
            self::assertTrue($state['hasItems']);
            self::assertSame('copy', $state['action']);
            self::assertSame([$relativePath], $state['paths']);
            self::assertSame(1, $state['count']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function cutSetsSessionCorrectly(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_');
        $relativePath = basename($tmpFile);

        try {
            $this->service->cut([$relativePath]);

            $state = $this->service->getState();
            self::assertTrue($state['hasItems']);
            self::assertSame('cut', $state['action']);
            self::assertSame([$relativePath], $state['paths']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function getActionReturnsCopyEnum(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_');
        $relativePath = basename($tmpFile);

        try {
            $this->service->copy([$relativePath]);
            self::assertSame(ClipboardAction::Copy, $this->service->getAction());
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function getActionReturnsCutEnum(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_');
        $relativePath = basename($tmpFile);

        try {
            $this->service->cut([$relativePath]);
            self::assertSame(ClipboardAction::Cut, $this->service->getAction());
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function getPathsReturnsStoredPaths(): void
    {
        $tmpFile1 = tempnam(sys_get_temp_dir(), 'rfm_test_');
        $tmpFile2 = tempnam(sys_get_temp_dir(), 'rfm_test_');
        $paths = [basename($tmpFile1), basename($tmpFile2)];

        try {
            $this->service->copy($paths);
            self::assertSame($paths, $this->service->getPaths());
        } finally {
            @unlink($tmpFile1);
            @unlink($tmpFile2);
        }
    }

    #[Test]
    public function clearRemovesSessionData(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'rfm_test_');
        $relativePath = basename($tmpFile);

        try {
            $this->service->copy([$relativePath]);
            self::assertTrue($this->service->getState()['hasItems']);

            $this->service->clear();

            self::assertFalse($this->service->getState()['hasItems']);
            self::assertNull($this->service->getAction());
            self::assertSame([], $this->service->getPaths());
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function copyMultiplePathsSetsCorrectCount(): void
    {
        $tmpFiles = [];
        $paths = [];
        for ($i = 0; $i < 3; $i++) {
            $tmpFiles[] = $tmp = tempnam(sys_get_temp_dir(), 'rfm_test_');
            $paths[] = basename($tmp);
        }

        try {
            $this->service->copy($paths);
            self::assertSame(3, $this->service->getState()['count']);
        } finally {
            array_map('unlink', $tmpFiles);
        }
    }

    #[Test]
    public function cutOverwritesPreviousCopy(): void
    {
        $tmpFile1 = tempnam(sys_get_temp_dir(), 'rfm_test_');
        $tmpFile2 = tempnam(sys_get_temp_dir(), 'rfm_test_');

        try {
            $this->service->copy([basename($tmpFile1)]);
            $this->service->cut([basename($tmpFile2)]);

            self::assertSame(ClipboardAction::Cut, $this->service->getAction());
            self::assertSame([basename($tmpFile2)], $this->service->getPaths());
        } finally {
            @unlink($tmpFile1);
            @unlink($tmpFile2);
        }
    }

    #[Test]
    public function pathValidationThrowsOnTraversal(): void
    {
        $this->expectException(\RFM\Exception\PathTraversalException::class);
        $this->service->copy(['../etc/passwd']);
    }
}
