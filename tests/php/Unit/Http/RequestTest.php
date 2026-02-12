<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Http\Request;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    // ---------------------------------------------------------------
    // Path stripping logic
    // ---------------------------------------------------------------

    #[Test]
    #[DataProvider('pathStrippingProvider')]
    public function pathIsCorrectlyStrippedFromBasePath(
        string $requestUri,
        string $scriptName,
        string $expectedPath,
    ): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $requestUri;
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $_SERVER['QUERY_STRING'] = '';

        $request = new Request();
        self::assertSame($expectedPath, $request->path);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function pathStrippingProvider(): iterable
    {
        // When app is in a subdirectory: /public/api/files -> /api/files
        yield 'subdirectory public' => [
            '/public/api/files',
            '/public/index.php',
            '/api/files',
        ];

        // When app is in /filemanager: /filemanager/api/files -> /api/files
        yield 'subdirectory filemanager' => [
            '/filemanager/api/files',
            '/filemanager/index.php',
            '/api/files',
        ];

        // When app is at document root: /api/files stays /api/files
        yield 'root base path' => [
            '/api/files',
            '/index.php',
            '/api/files',
        ];

        // Deeply nested base path
        yield 'deep subdirectory' => [
            '/apps/tools/rfm/api/upload',
            '/apps/tools/rfm/index.php',
            '/api/upload',
        ];

        // Root URI with root script
        yield 'root URI root script' => [
            '/',
            '/index.php',
            '/',
        ];

        // Subdirectory root
        yield 'subdirectory root' => [
            '/public/',
            '/public/index.php',
            '/',
        ];

        // Subdirectory without trailing slash -> empty substr becomes /
        yield 'subdirectory exact match' => [
            '/public',
            '/public/index.php',
            '/',
        ];

        // URI with query string (parse_url strips it)
        yield 'uri with query string' => [
            '/public/api/files?type=image',
            '/public/index.php',
            '/api/files',
        ];
    }

    // ---------------------------------------------------------------
    // HTTP method
    // ---------------------------------------------------------------

    #[Test]
    public function methodIsUppercased(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'post';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';

        $request = new Request();
        self::assertSame('POST', $request->method);
    }

    #[Test]
    public function methodDefaultsToGetWhenMissing(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';

        $request = new Request();
        self::assertSame('GET', $request->method);
    }

    // ---------------------------------------------------------------
    // Query string
    // ---------------------------------------------------------------

    #[Test]
    public function queryStringIsCapturedFromServer(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/files?type=image&sort=name';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = 'type=image&sort=name';

        $request = new Request();
        self::assertSame('type=image&sort=name', $request->queryString);
    }

    #[Test]
    public function queryStringDefaultsToEmptyString(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/files';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        unset($_SERVER['QUERY_STRING']);

        $request = new Request();
        self::assertSame('', $request->queryString);
    }

    // ---------------------------------------------------------------
    // Headers
    // ---------------------------------------------------------------

    #[Test]
    public function headerReturnsNormalizedServerHeaders(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = new Request();
        self::assertSame('XMLHttpRequest', $request->header('x-requested-with'));
        self::assertSame('application/json', $request->header('accept'));
    }

    #[Test]
    public function headerReturnsNullForMissingHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';

        $request = new Request();
        self::assertNull($request->header('x-custom-header'));
    }

    #[Test]
    public function contentTypeHeaderIsParsedFromServerVar(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $request = new Request();
        self::assertSame('application/json', $request->header('content-type'));
    }

    #[Test]
    public function contentLengthHeaderIsParsedFromServerVar(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';
        $_SERVER['CONTENT_LENGTH'] = '1024';

        $request = new Request();
        self::assertSame('1024', $request->header('content-length'));
    }

    // ---------------------------------------------------------------
    // isAjax()
    // ---------------------------------------------------------------

    #[Test]
    public function isAjaxReturnsTrueForXmlHttpRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $request = new Request();
        self::assertTrue($request->isAjax());
    }

    #[Test]
    public function isAjaxReturnsTrueForJsonAcceptHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = new Request();
        self::assertTrue($request->isAjax());
    }

    #[Test]
    public function isAjaxReturnsFalseForNormalRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $request = new Request();
        self::assertFalse($request->isAjax());
    }

    // ---------------------------------------------------------------
    // get() / post() / cookie()
    // ---------------------------------------------------------------

    #[Test]
    public function getReturnsValueFromGetSuperglobal(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';
        $_GET['type'] = 'image';

        $request = new Request();
        self::assertSame('image', $request->get('type'));

        unset($_GET['type']);
    }

    #[Test]
    public function getReturnsDefaultWhenKeyMissing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';

        $request = new Request();
        self::assertSame('fallback', $request->get('nonexistent', 'fallback'));
    }

    #[Test]
    public function cookieReturnsValueFromCookieSuperglobal(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';
        $_COOKIE['session_id'] = 'abc123';

        $request = new Request();
        self::assertSame('abc123', $request->cookie('session_id'));

        unset($_COOKIE['session_id']);
    }

    #[Test]
    public function cookieReturnsDefaultWhenMissing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';

        $request = new Request();
        self::assertNull($request->cookie('nonexistent'));
        self::assertSame('default', $request->cookie('nonexistent', 'default'));
    }

    // ---------------------------------------------------------------
    // Edge cases for path resolution
    // ---------------------------------------------------------------

    #[Test]
    public function pathDefaultsToSlashWhenRequestUriMissing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['QUERY_STRING'] = '';
        unset($_SERVER['REQUEST_URI']);

        $request = new Request();
        self::assertSame('/', $request->path);
    }
}
