<?php

declare(strict_types=1);

namespace RFM\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RFM\Controller\ConfigController;
use RFM\Service\SecurityService;
use RFM\Tests\Unit\TestConfigTrait;

#[CoversClass(ConfigController::class)]
final class ConfigControllerTest extends TestCase
{
    use TestConfigTrait;

    private ConfigController $controller;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $config = self::createConfig();
        $security = new SecurityService($config);
        $this->controller = new ConfigController($config, $security);
    }

    // ---------------------------------------------------------------
    // initSession
    // ---------------------------------------------------------------

    #[Test]
    public function initSessionSetsVerifyFlag(): void
    {
        $request = self::createRequest('GET', '/api/session/init');
        $response = $this->controller->initSession($request);

        // Verify flag is now a random hex token (32 hex chars = 16 bytes)
        self::assertNotEmpty($_SESSION['RFM']['verify']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $_SESSION['RFM']['verify']);
    }

    #[Test]
    public function initSessionReturnsCsrfToken(): void
    {
        $request = self::createRequest('GET', '/api/session/init');
        $response = $this->controller->initSession($request);
        $data = $response->getData();

        self::assertArrayHasKey('csrfToken', $data);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $data['csrfToken']);
    }

    #[Test]
    public function initSessionReturnsConfigAndTranslations(): void
    {
        $request = self::createRequest('GET', '/api/session/init');
        $response = $this->controller->initSession($request);
        $data = $response->getData();

        self::assertArrayHasKey('config', $data);
        self::assertArrayHasKey('translations', $data);
        self::assertArrayHasKey('language', $data);
        self::assertIsArray($data['config']);
        self::assertIsArray($data['translations']);
    }

    #[Test]
    public function initSessionUsesCookieLangWhenSet(): void
    {
        $request = self::createRequest('GET', '/api/session/init', cookies: ['rfm_lang' => 'cs']);
        $response = $this->controller->initSession($request);
        $data = $response->getData();

        self::assertSame('cs', $data['language']);
    }

    #[Test]
    public function initSessionDetectsBrowserAcceptLanguage(): void
    {
        $request = self::createRequest('GET', '/api/session/init', server: [
            'HTTP_ACCEPT_LANGUAGE' => 'cs-CZ,cs;q=0.9,en;q=0.8',
        ]);
        $response = $this->controller->initSession($request);
        $data = $response->getData();

        // Should detect "cs" from Accept-Language header
        self::assertSame('cs', $data['language']);
    }

    #[Test]
    public function initSessionFallsBackToDefaultLanguage(): void
    {
        $request = self::createRequest('GET', '/api/session/init');
        $response = $this->controller->initSession($request);
        $data = $response->getData();

        // No cookie, no Accept-Language => falls back to default (en_EN)
        self::assertSame('en_EN', $data['language']);
    }

    #[Test]
    public function initSessionResponseStatusIs200(): void
    {
        $request = self::createRequest('GET', '/api/session/init');
        $response = $this->controller->initSession($request);

        self::assertSame(200, $response->getStatusCode());
    }

    // ---------------------------------------------------------------
    // getConfig
    // ---------------------------------------------------------------

    #[Test]
    public function getConfigReturnsConfigArray(): void
    {
        $request = self::createRequest('GET', '/api/config');
        $response = $this->controller->getConfig($request);
        $data = $response->getData();

        self::assertArrayHasKey('config', $data);
        self::assertIsArray($data['config']);
    }

    // ---------------------------------------------------------------
    // getLanguages
    // ---------------------------------------------------------------

    #[Test]
    public function getLanguagesReturnsArray(): void
    {
        $request = self::createRequest('GET', '/api/languages');
        $response = $this->controller->getLanguages($request);
        $data = $response->getData();

        self::assertArrayHasKey('languages', $data);
        self::assertIsArray($data['languages']);
        // Should find at least en_EN
        self::assertNotEmpty($data['languages']);
    }

    #[Test]
    public function getLanguagesEntryHasCodeAndName(): void
    {
        $request = self::createRequest('GET', '/api/languages');
        $response = $this->controller->getLanguages($request);
        $data = $response->getData();

        $first = $data['languages'][0];
        self::assertArrayHasKey('code', $first);
        self::assertArrayHasKey('name', $first);
    }

    // ---------------------------------------------------------------
    // getTranslations
    // ---------------------------------------------------------------

    #[Test]
    public function getTranslationsReturnsTranslationsArray(): void
    {
        $_SESSION['RFM']['language'] = 'en_EN';
        $request = self::createRequest('GET', '/api/translations');
        $response = $this->controller->getTranslations($request);
        $data = $response->getData();

        self::assertArrayHasKey('translations', $data);
        self::assertIsArray($data['translations']);
    }

    // ---------------------------------------------------------------
    // changeLanguage
    // ---------------------------------------------------------------

    #[Test]
    public function changeLanguageReturnsErrorForEmptyLang(): void
    {
        $request = self::createRequest('POST', '/api/config/language', post: ['lang' => '']);
        $response = $this->controller->changeLanguage($request);
        $data = $response->getData();

        self::assertFalse($data['success']);
        self::assertStringContainsString('required', $data['error']);
    }

    #[Test]
    public function changeLanguageReturnsErrorForUnknownLang(): void
    {
        $request = self::createRequest('POST', '/api/config/language', post: ['lang' => 'xx_XX']);
        $response = $this->controller->changeLanguage($request);
        $data = $response->getData();

        self::assertFalse($data['success']);
        self::assertStringContainsString('Unknown', $data['error']);
    }

    #[Test]
    public function changeLanguageSuccessSetsSessionAndReturnsTranslations(): void
    {
        $request = self::createRequest('POST', '/api/config/language', post: ['lang' => 'en_EN']);
        $response = $this->controller->changeLanguage($request);
        $data = $response->getData();

        self::assertTrue($data['success']);
        self::assertArrayHasKey('translations', $data);
        self::assertSame('en_EN', $_SESSION['RFM']['language']);
    }

    // ---------------------------------------------------------------
    // changeView
    // ---------------------------------------------------------------

    #[Test]
    public function changeViewSetsSession(): void
    {
        $request = self::createRequest('POST', '/api/config/view', post: ['type' => '1']);
        $response = $this->controller->changeView($request);
        $data = $response->getData();

        self::assertTrue($data['success']);
        self::assertSame(1, $data['viewType']);
        self::assertSame(1, $_SESSION['RFM']['view_type']);
    }

    #[Test]
    public function changeViewClampsInvalidValues(): void
    {
        $request = self::createRequest('POST', '/api/config/view', post: ['type' => '5']);
        $response = $this->controller->changeView($request);
        $data = $response->getData();

        self::assertSame(0, $data['viewType']);
    }

    #[Test]
    public function changeViewClampsNegativeValues(): void
    {
        $request = self::createRequest('POST', '/api/config/view', post: ['type' => '-1']);
        $response = $this->controller->changeView($request);
        $data = $response->getData();

        self::assertSame(0, $data['viewType']);
    }

    // ---------------------------------------------------------------
    // changeSort
    // ---------------------------------------------------------------

    #[Test]
    public function changeSortSetsSession(): void
    {
        $request = self::createRequest('POST', '/api/config/sort', post: ['sort_by' => 'date', 'descending' => '1']);
        $response = $this->controller->changeSort($request);
        $data = $response->getData();

        self::assertTrue($data['success']);
        self::assertSame('date', $_SESSION['RFM']['sort_by']);
        self::assertTrue($_SESSION['RFM']['descending']);
    }

    // ---------------------------------------------------------------
    // changeFilter
    // ---------------------------------------------------------------

    #[Test]
    public function changeFilterStoresWhenRememberEnabled(): void
    {
        $config = self::createConfig(rememberTextFilter: true);
        $security = new SecurityService($config);
        $controller = new ConfigController($config, $security);

        $request = self::createRequest('POST', '/api/config/filter', post: ['filter' => 'test']);
        $response = $controller->changeFilter($request);
        $data = $response->getData();

        self::assertTrue($data['success']);
        self::assertSame('test', $_SESSION['RFM']['filter']);
    }

    #[Test]
    public function changeFilterDoesNotStoreWhenRememberDisabled(): void
    {
        $request = self::createRequest('POST', '/api/config/filter', post: ['filter' => 'test']);
        $response = $this->controller->changeFilter($request);

        self::assertArrayNotHasKey('filter', $_SESSION['RFM'] ?? []);
    }
}
