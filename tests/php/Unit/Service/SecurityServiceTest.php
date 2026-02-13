<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Config\AppConfig;
use RFM\Exception\ForbiddenException;
use RFM\Exception\InvalidExtensionException;
use RFM\Exception\PathTraversalException;
use RFM\Service\SecurityService;

#[CoversClass(SecurityService::class)]
final class SecurityServiceTest extends TestCase
{
    private SecurityService $service;

    protected function setUp(): void
    {
        $this->service = new SecurityService(self::createConfig());
    }

    // ---------------------------------------------------------------
    // sanitizeFilename()
    // ---------------------------------------------------------------

    #[Test]
    public function sanitizeFilenameStripsHtmlTags(): void
    {
        $result = $this->service->sanitizeFilename('<script>alert("xss")</script>photo.jpg');
        self::assertStringNotContainsString('<script>', $result);
        self::assertStringNotContainsString('</script>', $result);
    }

    #[Test]
    public function sanitizeFilenameConvertsSpacesToUnderscores(): void
    {
        $result = $this->service->sanitizeFilename('my photo file.jpg');
        self::assertSame('my_photo_file.jpg', $result);
    }

    #[Test]
    public function sanitizeFilenamePreservesSpacesWhenConversionDisabled(): void
    {
        $config = self::createConfig(convertSpaces: false);
        $service = new SecurityService($config);
        $result = $service->sanitizeFilename('my photo.jpg');
        self::assertStringContainsString(' ', $result);
    }

    #[Test]
    public function sanitizeFilenameRemovesSlashesAndQuotes(): void
    {
        $result = $this->service->sanitizeFilename('file"name\'with/back\\slash.jpg');
        self::assertStringNotContainsString('"', $result);
        self::assertStringNotContainsString("'", $result);
        self::assertStringNotContainsString('/', $result);
        self::assertStringNotContainsString('\\', $result);
    }

    #[Test]
    public function sanitizeFilenamePrependsFileForDotOnlyNames(): void
    {
        // emptyFilename is false by default, so dot-only names get "file" prepended
        $result = $this->service->sanitizeFilename('.jpg');
        self::assertSame('file.jpg', $result);
    }

    #[Test]
    public function sanitizeFilenameAllowsDotStartWhenEmptyFilenameEnabled(): void
    {
        $config = self::createConfig(emptyFilename: true);
        $service = new SecurityService($config);
        $result = $service->sanitizeFilename('.gitignore');
        self::assertSame('.gitignore', $result);
    }

    #[Test]
    public function sanitizeFilenameTrimsWhitespace(): void
    {
        // With convertSpaces enabled, leading/trailing spaces become underscores
        // then trim() only strips actual whitespace. Disable convertSpaces to
        // test the raw trim behavior.
        $config = self::createConfig(convertSpaces: false);
        $service = new SecurityService($config);
        $result = $service->sanitizeFilename('  hello.jpg  ');
        self::assertSame('hello.jpg', $result);
    }

    #[Test]
    public function sanitizeFilenameConvertsLeadingTrailingSpacesToReplacement(): void
    {
        // With convertSpaces enabled (default), spaces become underscores
        $result = $this->service->sanitizeFilename('  hello.jpg  ');
        self::assertSame('__hello.jpg__', $result);
    }

    #[Test]
    public function sanitizeFilenameConvertsToLowerCaseWhenEnabled(): void
    {
        $config = self::createConfig(lowerCase: true);
        $service = new SecurityService($config);
        $result = $service->sanitizeFilename('MyFile.JPG');
        self::assertSame('myfile.jpg', $result);
    }

    #[Test]
    public function sanitizeFilenamePreservesCaseByDefault(): void
    {
        $result = $this->service->sanitizeFilename('MyFile.JPG');
        self::assertSame('MyFile.JPG', $result);
    }

    #[Test]
    public function sanitizeFilenameUsesCustomReplaceWith(): void
    {
        $config = self::createConfig(replaceWith: '-');
        $service = new SecurityService($config);
        $result = $service->sanitizeFilename('my photo.jpg');
        self::assertSame('my-photo.jpg', $result);
    }

    // ---------------------------------------------------------------
    // validateExtension()
    // ---------------------------------------------------------------

    #[Test]
    public function validateExtensionPassesWhenExtensionInWhitelist(): void
    {
        // ext whitelist: ['jpg', 'png', 'pdf']
        $config = self::createConfig(ext: ['jpg', 'png', 'pdf']);
        $service = new SecurityService($config);
        $service->validateExtension('jpg');
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validateExtensionThrowsWhenExtensionNotInWhitelist(): void
    {
        $config = self::createConfig(ext: ['jpg', 'png']);
        $service = new SecurityService($config);
        $this->expectException(InvalidExtensionException::class);
        $service->validateExtension('exe');
    }

    #[Test]
    public function validateExtensionIsCaseInsensitive(): void
    {
        $config = self::createConfig(ext: ['jpg', 'png']);
        $service = new SecurityService($config);
        // "JPG" should map to "jpg" via mb_strtolower
        $service->validateExtension('JPG');
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validateExtensionBlacklistBlocksMatchingExtension(): void
    {
        $config = self::createConfig(extBlacklist: ['exe', 'bat', 'php']);
        $service = new SecurityService($config);
        $this->expectException(InvalidExtensionException::class);
        $this->expectExceptionMessage("blacklisted");
        $service->validateExtension('exe');
    }

    #[Test]
    public function validateExtensionBlacklistAllowsNonMatchingExtension(): void
    {
        $config = self::createConfig(ext: ['jpg', 'png'], extBlacklist: ['exe', 'bat']);
        $service = new SecurityService($config);
        // Extension must pass both blacklist AND whitelist checks
        $service->validateExtension('jpg');
        $this->expectNotToPerformAssertions();
    }

    // ---------------------------------------------------------------
    // isImageExtension()
    // ---------------------------------------------------------------

    #[Test]
    public function isImageExtensionReturnsTrueForImageExt(): void
    {
        self::assertTrue($this->service->isImageExtension('jpg'));
        self::assertTrue($this->service->isImageExtension('PNG'));
        self::assertTrue($this->service->isImageExtension('webp'));
    }

    #[Test]
    public function isImageExtensionReturnsFalseForNonImage(): void
    {
        self::assertFalse($this->service->isImageExtension('pdf'));
        self::assertFalse($this->service->isImageExtension('exe'));
        self::assertFalse($this->service->isImageExtension('mp4'));
    }

    // ---------------------------------------------------------------
    // checkRelativePath() - path traversal detection
    // ---------------------------------------------------------------

    #[Test]
    #[DataProvider('traversalPathProvider')]
    public function checkRelativePathThrowsOnTraversalSequences(string $path): void
    {
        $this->expectException(PathTraversalException::class);
        $this->service->checkRelativePath($path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function traversalPathProvider(): iterable
    {
        yield 'dot-dot-slash' => ['../secret'];
        yield 'dot-slash' => ['./hidden'];
        yield 'slash-dot-dot' => ['foo/..'];
        yield 'backslash-dot-dot' => ['..\\windows'];
        yield 'dot-backslash' => ['.\\file'];
        yield 'backslash-dot-dot-end' => ['folder\\..'];
        yield 'bare-dot-dot' => ['..'];
        yield 'url-encoded-dot-dot-slash' => ['..%2Fsecret'];
    }

    #[Test]
    public function checkRelativePathAllowsNormalPaths(): void
    {
        $this->service->checkRelativePath('images/photos/vacation.jpg');
        $this->service->checkRelativePath('documents');
        $this->service->checkRelativePath('file.txt');
        $this->expectNotToPerformAssertions();
    }

    // ---------------------------------------------------------------
    // generateCsrfToken() / verifyCsrfToken()
    // ---------------------------------------------------------------

    #[Test]
    public function generateCsrfTokenCreatesHexToken(): void
    {
        $_SESSION = [];
        $token = $this->service->generateCsrfToken();
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    #[Test]
    public function generateCsrfTokenReturnsSameTokenOnRepeatedCalls(): void
    {
        $_SESSION = [];
        $first = $this->service->generateCsrfToken();
        $second = $this->service->generateCsrfToken();
        self::assertSame($first, $second);
    }

    #[Test]
    public function verifyCsrfTokenReturnsTrueForValidToken(): void
    {
        $_SESSION = [];
        $token = $this->service->generateCsrfToken();
        self::assertTrue($this->service->verifyCsrfToken($token));
    }

    #[Test]
    public function verifyCsrfTokenReturnsFalseForInvalidToken(): void
    {
        $_SESSION = [];
        $this->service->generateCsrfToken();
        self::assertFalse($this->service->verifyCsrfToken('wrong_token'));
    }

    #[Test]
    public function verifyCsrfTokenReturnsFalseForEmptyToken(): void
    {
        $_SESSION = [];
        $this->service->generateCsrfToken();
        self::assertFalse($this->service->verifyCsrfToken(''));
    }

    #[Test]
    public function verifyCsrfTokenReturnsFalseWhenNoSessionToken(): void
    {
        $_SESSION = [];
        self::assertFalse($this->service->verifyCsrfToken('anything'));
    }

    // ---------------------------------------------------------------
    // validateAccessKey()
    // ---------------------------------------------------------------

    #[Test]
    public function validateAccessKeyDoesNothingWhenAccessKeysDisabled(): void
    {
        // Default config has useAccessKeys=false
        $this->service->validateAccessKey(null);
        $this->service->validateAccessKey('');
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validateAccessKeyThrowsWhenKeyIsNull(): void
    {
        $config = self::createConfig(useAccessKeys: true, accessKeys: ['valid-key']);
        $service = new SecurityService($config);
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Access key required');
        $service->validateAccessKey(null);
    }

    #[Test]
    public function validateAccessKeyThrowsWhenKeyIsEmpty(): void
    {
        $config = self::createConfig(useAccessKeys: true, accessKeys: ['valid-key']);
        $service = new SecurityService($config);
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Access key required');
        $service->validateAccessKey('');
    }

    #[Test]
    public function validateAccessKeyThrowsForInvalidKeyFormat(): void
    {
        $config = self::createConfig(useAccessKeys: true, accessKeys: ['valid-key']);
        $service = new SecurityService($config);
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Invalid access key format');
        $service->validateAccessKey('key with spaces!');
    }

    #[Test]
    public function validateAccessKeyThrowsWhenKeyNotInList(): void
    {
        $config = self::createConfig(useAccessKeys: true, accessKeys: ['alpha', 'beta']);
        $service = new SecurityService($config);
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Invalid access key');
        $service->validateAccessKey('gamma');
    }

    #[Test]
    public function validateAccessKeyPassesForValidKey(): void
    {
        $config = self::createConfig(useAccessKeys: true, accessKeys: ['my-key.123']);
        $service = new SecurityService($config);
        $service->validateAccessKey('my-key.123');
        $this->expectNotToPerformAssertions();
    }

    // ---------------------------------------------------------------
    // Helper: build a minimal AppConfig for testing
    // ---------------------------------------------------------------

    private static function createConfig(
        bool $convertSpaces = true,
        string $replaceWith = '_',
        bool $transliteration = false,
        bool $lowerCase = false,
        bool $emptyFilename = false,
        array $ext = [],
        array $extBlacklist = [],
        array $extImg = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'ico', 'webp'],
        bool $useAccessKeys = false,
        array $accessKeys = [],
    ): AppConfig {
        return new AppConfig(
            baseUrl: 'http://localhost',
            uploadDir: '/source/',
            thumbsUploadDir: '/thumbs/',
            currentPath: __DIR__,
            thumbsBasePath: __DIR__,
            autoUpload: true,
            mimeExtensionRename: true,
            maxSizeTotal: false,
            maxSizeUpload: 10,
            filePermission: 0644,
            folderPermission: 0755,
            multipleSelection: true,
            multipleSelectionActionButton: true,
            defaultLanguage: 'en_EN',
            iconTheme: 'ico',
            showTotalSize: false,
            showFolderSize: false,
            showSortingBar: true,
            showFilterButtons: true,
            showLanguageSelection: true,
            defaultView: 0,
            ellipsisTitleAfterFirstRow: true,
            transliteration: $transliteration,
            convertSpaces: $convertSpaces,
            replaceWith: $replaceWith,
            lowerCase: $lowerCase,
            emptyFilename: $emptyFilename,
            filesWithoutExtension: false,
            addTimeToImg: false,
            imageMaxWidth: 0,
            imageMaxHeight: 0,
            imageMaxMode: 'auto',
            imageResizing: false,
            imageResizingWidth: 0,
            imageResizingHeight: 0,
            imageResizingMode: 'auto',
            imageResizingOverride: false,
            imageWatermark: false,
            imageWatermarkPosition: 'br',
            imageWatermarkPadding: 10,
            deleteFiles: true,
            createFolders: true,
            deleteFolders: true,
            uploadFiles: true,
            renameFiles: true,
            renameFolders: true,
            duplicateFiles: true,
            extractFiles: false,
            copyCutFiles: true,
            copyCutDirs: true,
            chmodFiles: false,
            chmodDirs: false,
            previewTextFiles: false,
            editTextFiles: false,
            createTextFiles: false,
            downloadFiles: true,
            urlUpload: false,
            extImg: $extImg,
            extFile: ['doc', 'docx', 'xls', 'xlsx', 'pdf'],
            extVideo: ['mov', 'mpeg', 'm4v', 'mp4', 'avi', 'mpg', 'wma', 'flv', 'webm'],
            extMusic: ['mp3', 'mpga', 'm4a', 'ac3', 'aiff', 'mid', 'ogg', 'wav'],
            extMisc: ['zip', 'rar', 'gz', 'tar'],
            ext: $ext,
            extBlacklist: $extBlacklist,
            editableTextFileExts: ['txt', 'log'],
            previewableTextFileExts: ['txt', 'log', 'xml', 'css'],
            jplayerExts: ['mp4', 'flv', 'webm', 'mp3', 'ogg', 'wav'],
            cadExts: ['dwg', 'dxf', 'svg'],
            googledocEnabled: true,
            googledocFileExts: ['doc', 'docx', 'pdf'],
            copyCutMaxSize: false,
            copyCutMaxCount: false,
            hiddenFolders: [],
            hiddenFiles: ['config.php'],
            fileNumberLimitJs: 500,
            rememberTextFilter: false,
            imageEditorActive: true,
            imageEditorPosition: 'bottom',
            darkMode: true,
            removeHeader: true,
            useAccessKeys: $useAccessKeys,
            accessKeys: $accessKeys,
            fixedImageCreation: false,
            fixedPathFromFilemanager: [],
            fixedImageCreationNameToPrepend: [],
            fixedImageCreationToAppend: [],
            fixedImageCreationWidth: [],
            fixedImageCreationHeight: [],
            fixedImageCreationOption: [],
            relativeImageCreation: false,
            relativePathFromCurrentPos: [],
            relativeImageCreationNameToPrepend: [],
            relativeImageCreationNameToAppend: [],
            relativeImageCreationWidth: [],
            relativeImageCreationHeight: [],
            relativeImageCreationOption: [],
        );
    }
}
