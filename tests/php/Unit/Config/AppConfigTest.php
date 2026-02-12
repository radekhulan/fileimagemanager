<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Config\AppConfig;

#[CoversClass(AppConfig::class)]
final class AppConfigTest extends TestCase
{
    // ---------------------------------------------------------------
    // fromArray() - defaults
    // ---------------------------------------------------------------

    #[Test]
    public function fromArrayCreatesConfigWithDefaults(): void
    {
        $config = AppConfig::fromArray([]);

        // Path defaults
        self::assertSame('/source/', $config->uploadDir);
        self::assertSame('/thumbs/', $config->thumbsUploadDir);
        self::assertSame('../source/', $config->currentPath);
        self::assertSame('../thumbs/', $config->thumbsBasePath);

        // Upload defaults
        self::assertTrue($config->mimeExtensionRename);
        self::assertFalse($config->maxSizeTotal);
        self::assertSame(10, $config->maxSizeUpload);
        self::assertSame(0644, $config->filePermission);
        self::assertSame(0755, $config->folderPermission);

        // Selection defaults
        self::assertTrue($config->multipleSelection);
        self::assertTrue($config->multipleSelectionActionButton);

        // Language & UI defaults
        self::assertSame('en_EN', $config->defaultLanguage);
        self::assertSame('ico', $config->iconTheme);
        self::assertFalse($config->showTotalSize);
        self::assertFalse($config->showFolderSize);
        self::assertTrue($config->showSortingBar);
        self::assertTrue($config->showFilterButtons);
        self::assertTrue($config->showLanguageSelection);
        self::assertSame(0, $config->defaultView);
        self::assertTrue($config->ellipsisTitleAfterFirstRow);

        // Filename handling defaults
        self::assertTrue($config->transliteration);
        self::assertTrue($config->convertSpaces);
        self::assertSame('_', $config->replaceWith);
        self::assertFalse($config->lowerCase);
        self::assertFalse($config->emptyFilename);
        self::assertFalse($config->filesWithoutExtension);
        self::assertFalse($config->addTimeToImg);

        // Image defaults
        self::assertSame(0, $config->imageMaxWidth);
        self::assertSame(0, $config->imageMaxHeight);
        self::assertFalse($config->imageResizing);

        // Watermark defaults
        self::assertFalse($config->imageWatermark);
        self::assertSame('br', $config->imageWatermarkPosition);
        self::assertSame(10, $config->imageWatermarkPadding);

        // Permission defaults
        self::assertTrue($config->deleteFiles);
        self::assertTrue($config->createFolders);
        self::assertTrue($config->deleteFolders);
        self::assertTrue($config->uploadFiles);
        self::assertTrue($config->renameFiles);
        self::assertTrue($config->renameFolders);
        self::assertTrue($config->duplicateFiles);
        self::assertFalse($config->extractFiles);
        self::assertTrue($config->copyCutFiles);
        self::assertTrue($config->copyCutDirs);
        self::assertFalse($config->chmodFiles);
        self::assertFalse($config->chmodDirs);
        self::assertFalse($config->previewTextFiles);
        self::assertFalse($config->editTextFiles);
        self::assertFalse($config->createTextFiles);
        self::assertTrue($config->downloadFiles);
        self::assertFalse($config->urlUpload);

        // Extension defaults
        self::assertContains('jpg', $config->extImg);
        self::assertContains('png', $config->extImg);
        self::assertContains('webp', $config->extImg);
        self::assertContains('pdf', $config->extFile);
        self::assertContains('mp4', $config->extVideo);
        self::assertContains('mp3', $config->extMusic);
        self::assertContains('zip', $config->extMisc);

        // Hidden defaults
        self::assertSame([], $config->hiddenFolders);
        self::assertSame(['config.php'], $config->hiddenFiles);

        // Performance defaults
        self::assertSame(500, $config->fileNumberLimitJs);
        self::assertFalse($config->rememberTextFilter);

        // Image editor defaults
        self::assertTrue($config->imageEditorActive);
        self::assertSame('bottom', $config->imageEditorPosition);

        // Dark mode / header defaults
        self::assertTrue($config->darkMode);
        self::assertTrue($config->removeHeader);

        // Access key defaults
        self::assertFalse($config->useAccessKeys);
        self::assertSame([], $config->accessKeys);

        // Debug default
        self::assertFalse($config->debugErrorMessage);
    }

    // ---------------------------------------------------------------
    // fromArray() - custom values
    // ---------------------------------------------------------------

    #[Test]
    public function fromArrayAcceptsCustomValues(): void
    {
        $config = AppConfig::fromArray([
            'base_url' => 'https://example.com',
            'upload_dir' => '/uploads/',
            'thumbs_upload_dir' => '/cache/thumbs/',
            'current_path' => '/var/www/uploads/',
            'thumbs_base_path' => '/var/www/thumbs/',
            'MaxSizeUpload' => 50,
            'MaxSizeTotal' => 1000,
            'default_language' => 'cs_CZ',
            'convert_spaces' => false,
            'lower_case' => true,
            'delete_files' => false,
            'extract_files' => true,
            'ext_blacklist' => ['exe', 'bat'],
            'hidden_folders' => ['.git', '.svn'],
            'hidden_files' => ['.htaccess', 'web.config'],
            'dark_mode' => false,
            'file_number_limit_js' => 1000,
            'use_access_keys' => true,
            'access_keys' => ['key1', 'key2'],
            'debug_error_message' => true,
        ]);

        self::assertSame('https://example.com', $config->baseUrl);
        self::assertSame('/uploads/', $config->uploadDir);
        self::assertSame('/cache/thumbs/', $config->thumbsUploadDir);
        self::assertSame('/var/www/uploads/', $config->currentPath);
        self::assertSame('/var/www/thumbs/', $config->thumbsBasePath);
        self::assertSame(50, $config->maxSizeUpload);
        self::assertSame(1000, $config->maxSizeTotal);
        self::assertSame('cs_CZ', $config->defaultLanguage);
        self::assertFalse($config->convertSpaces);
        self::assertTrue($config->lowerCase);
        self::assertFalse($config->deleteFiles);
        self::assertTrue($config->extractFiles);
        self::assertSame(['exe', 'bat'], $config->extBlacklist);
        self::assertSame(['.git', '.svn'], $config->hiddenFolders);
        self::assertSame(['.htaccess', 'web.config'], $config->hiddenFiles);
        self::assertFalse($config->darkMode);
        self::assertSame(1000, $config->fileNumberLimitJs);
        self::assertTrue($config->useAccessKeys);
        self::assertSame(['key1', 'key2'], $config->accessKeys);
        self::assertTrue($config->debugErrorMessage);
    }

    #[Test]
    public function fromArrayMaxSizeTotalFalseWhenNotSet(): void
    {
        $config = AppConfig::fromArray([]);
        self::assertFalse($config->maxSizeTotal);
    }

    #[Test]
    public function fromArrayMaxSizeTotalFalseWhenExplicitlyFalse(): void
    {
        $config = AppConfig::fromArray(['MaxSizeTotal' => false]);
        self::assertFalse($config->maxSizeTotal);
    }

    #[Test]
    public function fromArrayMaxSizeTotalIntWhenSet(): void
    {
        $config = AppConfig::fromArray(['MaxSizeTotal' => 500]);
        self::assertSame(500, $config->maxSizeTotal);
    }

    #[Test]
    public function fromArrayImageWatermarkFalseByDefault(): void
    {
        $config = AppConfig::fromArray([]);
        self::assertFalse($config->imageWatermark);
    }

    #[Test]
    public function fromArrayImageWatermarkStringWhenSet(): void
    {
        $config = AppConfig::fromArray(['image_watermark' => '/path/to/watermark.png']);
        self::assertSame('/path/to/watermark.png', $config->imageWatermark);
    }

    #[Test]
    public function fromArrayFixedImageCreationDimensionsAreCastToInt(): void
    {
        $config = AppConfig::fromArray([
            'fixed_image_creation_width' => ['200', '400'],
            'fixed_image_creation_height' => ['150', '300'],
        ]);
        self::assertSame([200, 400], $config->fixedImageCreationWidth);
        self::assertSame([150, 300], $config->fixedImageCreationHeight);
    }

    #[Test]
    public function fromArrayImageEditorFallsBackFromTuiKeys(): void
    {
        $config = AppConfig::fromArray([
            'tui_active' => false,
            'tui_position' => 'top',
        ]);
        self::assertFalse($config->imageEditorActive);
        self::assertSame('top', $config->imageEditorPosition);
    }

    // ---------------------------------------------------------------
    // toClientConfig()
    // ---------------------------------------------------------------

    #[Test]
    public function toClientConfigReturnsExpectedKeys(): void
    {
        $config = AppConfig::fromArray([]);
        $client = $config->toClientConfig();

        $expectedKeys = [
            'uploadFiles', 'createFolders', 'deleteFolders', 'deleteFiles',
            'renameFiles', 'renameFolders', 'duplicateFiles', 'copyCutFiles',
            'copyCutDirs', 'chmodFiles', 'chmodDirs', 'extractFiles',
            'previewTextFiles', 'editTextFiles', 'createTextFiles', 'downloadFiles',
            'urlUpload', 'multipleSelection', 'multipleSelectionActionButton',
            'showTotalSize', 'showFolderSize', 'showSortingBar', 'showFilterButtons',
            'showLanguageSelection', 'imageEditorActive', 'darkMode', 'removeHeader',
            'maxSizeUpload', 'fileNumberLimitJs', 'extImg', 'extVideo', 'extMusic',
            'extFile', 'extMisc', 'baseUrl', 'uploadDir', 'editableTextFileExts',
            'previewableTextFileExts', 'addTimeToImg', 'copyCutMaxSize',
            'copyCutMaxCount', 'googledocEnabled', 'googledocFileExts', 'defaultView',
        ];

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $client, "Missing key: {$key}");
        }
    }

    #[Test]
    public function toClientConfigDoesNotExposeSensitiveFields(): void
    {
        $config = AppConfig::fromArray([
            'use_access_keys' => true,
            'access_keys' => ['secret-key-123'],
        ]);
        $client = $config->toClientConfig();

        // Server-only fields must not leak to the client
        self::assertArrayNotHasKey('accessKeys', $client);
        self::assertArrayNotHasKey('useAccessKeys', $client);
        self::assertArrayNotHasKey('currentPath', $client);
        self::assertArrayNotHasKey('thumbsBasePath', $client);
        self::assertArrayNotHasKey('filePermission', $client);
        self::assertArrayNotHasKey('folderPermission', $client);
        self::assertArrayNotHasKey('hiddenFolders', $client);
        self::assertArrayNotHasKey('hiddenFiles', $client);
        self::assertArrayNotHasKey('extBlacklist', $client);
        self::assertArrayNotHasKey('debugErrorMessage', $client);
    }

    #[Test]
    public function toClientConfigValuesMatchSourceConfig(): void
    {
        $config = AppConfig::fromArray([
            'delete_files' => false,
            'dark_mode' => false,
            'MaxSizeUpload' => 25,
            'file_number_limit_js' => 200,
        ]);
        $client = $config->toClientConfig();

        self::assertFalse($client['deleteFiles']);
        self::assertFalse($client['darkMode']);
        self::assertSame(25, $client['maxSizeUpload']);
        self::assertSame(200, $client['fileNumberLimitJs']);
    }

    // ---------------------------------------------------------------
    // getExtConfig()
    // ---------------------------------------------------------------

    #[Test]
    public function getExtConfigReturnsAllExtensionArrays(): void
    {
        $config = AppConfig::fromArray([]);
        $extConfig = $config->getExtConfig();

        self::assertArrayHasKey('ext_img', $extConfig);
        self::assertArrayHasKey('ext_video', $extConfig);
        self::assertArrayHasKey('ext_music', $extConfig);
        self::assertArrayHasKey('ext_file', $extConfig);
        self::assertArrayHasKey('ext_misc', $extConfig);
        self::assertCount(5, $extConfig);
    }

    #[Test]
    public function getExtConfigReturnsCorrectValues(): void
    {
        $config = AppConfig::fromArray([
            'ext_img' => ['png', 'jpg'],
            'ext_video' => ['mp4'],
            'ext_music' => ['mp3'],
            'ext_file' => ['pdf'],
            'ext_misc' => ['zip'],
        ]);
        $extConfig = $config->getExtConfig();

        self::assertSame(['png', 'jpg'], $extConfig['ext_img']);
        self::assertSame(['mp4'], $extConfig['ext_video']);
        self::assertSame(['mp3'], $extConfig['ext_music']);
        self::assertSame(['pdf'], $extConfig['ext_file']);
        self::assertSame(['zip'], $extConfig['ext_misc']);
    }
}
