<?php

declare(strict_types=1);

namespace RFM\Controller;

use RFM\Config\AppConfig;
use RFM\Http\{Request, JsonResponse};
use RFM\Service\SecurityService;

final class ConfigController
{
    public function __construct(
        private readonly AppConfig $config,
        private readonly SecurityService $security,
    ) {}

    /**
     * Initialize session and return CSRF token + config + translations.
     */
    public function initSession(Request $request): JsonResponse
    {
        // Validate access key if enabled
        $this->security->validateAccessKey($request->get('akey'));

        // Regenerate session ID to prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Set session verification
        $_SESSION['RFM']['verify'] = 'FILEimagemanager';

        // Generate CSRF token
        $csrfToken = $this->security->generateCsrfToken();

        // Load language
        $lang = $request->cookie('rfm_lang', $this->config->defaultLanguage);
        if (!is_string($lang)) {
            $lang = $this->config->defaultLanguage;
        }
        $_SESSION['RFM']['language'] = basename($lang);

        // Load translations
        $translations = $this->loadTranslations($lang);

        // Flush session to disk before sending response
        // This ensures the verify flag is persisted for subsequent requests
        session_write_close();

        return new JsonResponse([
            'csrfToken' => $csrfToken,
            'config' => $this->config->toClientConfig(),
            'language' => $lang,
            'translations' => $translations,
        ]);
    }

    /**
     * Get current client configuration.
     */
    public function getConfig(Request $request): JsonResponse
    {
        return new JsonResponse([
            'config' => $this->config->toClientConfig(),
        ]);
    }

    /**
     * Get available languages list.
     */
    public function getLanguages(Request $request): JsonResponse
    {
        $langDir = dirname(__DIR__, 2) . '/lang/';
        $languages = [];

        foreach (glob($langDir . '*.json') as $file) {
            $code = pathinfo($file, PATHINFO_FILENAME);
            $data = json_decode(file_get_contents($file), true);
            $languages[] = [
                'code' => $code,
                'name' => $data['_language_name'] ?? $code,
            ];
        }

        return new JsonResponse(['languages' => $languages]);
    }

    /**
     * Get translations for current or specified language.
     */
    public function getTranslations(Request $request): JsonResponse
    {
        $lang = $request->get('lang', $_SESSION['RFM']['language'] ?? $this->config->defaultLanguage);
        $translations = $this->loadTranslations($lang);

        return new JsonResponse(['translations' => $translations]);
    }

    /**
     * Change the current language.
     */
    public function changeLanguage(Request $request): JsonResponse
    {
        $lang = $request->post('lang');

        if (!is_string($lang) || $lang === '') {
            return JsonResponse::error('Language code required');
        }

        // Validate language file exists
        $langFile = dirname(__DIR__, 2) . '/lang/' . basename($lang) . '.json';
        if (!is_file($langFile)) {
            return JsonResponse::error('Unknown language');
        }

        $_SESSION['RFM']['language'] = basename($lang);

        // Set cookie for 1 year
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie('rfm_lang', basename($lang), [
            'expires' => time() + 365 * 86400,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $isHttps,
        ]);

        $translations = $this->loadTranslations($lang);

        return JsonResponse::success(['translations' => $translations]);
    }

    /**
     * Change view mode.
     */
    public function changeView(Request $request): JsonResponse
    {
        $type = (int) $request->post('type', 0);
        if ($type < 0 || $type > 2) {
            $type = 0;
        }

        $_SESSION['RFM']['view_type'] = $type;

        return JsonResponse::success(['viewType' => $type]);
    }

    /**
     * Change sort settings.
     */
    public function changeSort(Request $request): JsonResponse
    {
        $sortBy = $request->post('sort_by', 'name');
        $descending = (bool) $request->post('descending', false);

        $_SESSION['RFM']['sort_by'] = $sortBy;
        $_SESSION['RFM']['descending'] = $descending;

        return JsonResponse::success([
            'sortBy' => $sortBy,
            'descending' => $descending,
        ]);
    }

    /**
     * Change filter settings.
     */
    public function changeFilter(Request $request): JsonResponse
    {
        $filter = $request->post('filter', '');

        if ($this->config->rememberTextFilter) {
            $_SESSION['RFM']['filter'] = $filter;
        }

        return JsonResponse::success(['filter' => $filter]);
    }

    /**
     * @return array<string, string>
     */
    private function loadTranslations(string $lang): array
    {
        $langFile = dirname(__DIR__, 2) . '/lang/' . basename($lang) . '.json';

        if (!is_file($langFile)) {
            // Fallback to default
            $langFile = dirname(__DIR__, 2) . '/lang/' . basename($this->config->defaultLanguage) . '.json';
        }

        if (!is_file($langFile)) {
            // Fallback to English
            $langFile = dirname(__DIR__, 2) . '/lang/en_EN.json';
        }

        if (!is_file($langFile)) {
            return [];
        }

        $data = json_decode(file_get_contents($langFile), true);
        return is_array($data) ? $data : [];
    }
}
