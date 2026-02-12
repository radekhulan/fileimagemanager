<?php

declare(strict_types=1);

namespace RFM;

use RFM\Config\{AppConfig, ConfigLoader};
use RFM\Http\{Request, JsonResponse};
use RFM\Middleware\{AuthMiddleware, CsrfMiddleware};
use RFM\Service\{
    FileSystemService,
    ThumbnailService,
    ImageProcessingService,
    SecurityService,
    UploadService,
    ClipboardService,
    MimeTypeService,
};

final class App
{
    private AppConfig $config;
    private ConfigLoader $configLoader;
    private Router $router;

    /** @var array<string, object> */
    private array $services = [];

    public function __construct(
        private readonly string $configPath,
    ) {
        $this->configLoader = new ConfigLoader($configPath);
        $this->config = $this->configLoader->load($configPath);
        $this->router = new Router();
    }

    public function run(): void
    {
        // Start session with explicit cookie params
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            // Ensure session save path is writable (common issue on IIS)
            $savePath = session_save_path();
            if ($savePath === '' || !is_writable($savePath)) {
                $fallback = sys_get_temp_dir();
                if (is_writable($fallback)) {
                    session_save_path($fallback);
                }
            }

            session_start();
        }

        // Set encoding
        mb_internal_encoding('UTF-8');
        mb_http_output('UTF-8');

        // Ensure media directories exist
        foreach ([$this->config->currentPath, $this->config->thumbsBasePath] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, $this->config->folderPermission, true);
            }
        }

        $request = new Request();

        // CORS headers for development
        $this->setCorsHeaders();

        // Handle preflight
        if ($request->method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Run middleware chain
        try {
            // Auth check for API routes
            if (str_starts_with($request->path, '/api/') && $request->path !== '/api/session/init') {
                $auth = new AuthMiddleware();
                $auth->handle($request);
            }

            // CSRF check for state-changing requests
            if ($request->method === 'POST') {
                $csrf = new CsrfMiddleware($this->security());
                $csrf->handle($request);
            }

            // Dispatch to router
            $this->router->dispatch($request, $this);

        } catch (\RFM\Exception\ForbiddenException $e) {
            JsonResponse::error($e->getMessage(), 403)->send();
        } catch (\RFM\Exception\PathTraversalException $e) {
            JsonResponse::error($e->getMessage(), 403)->send();
        } catch (\RFM\Exception\FileNotFoundException $e) {
            JsonResponse::error($e->getMessage(), 404)->send();
        } catch (\RFM\Exception\InvalidExtensionException $e) {
            JsonResponse::error($e->getMessage(), 400)->send();
        } catch (\RFM\Exception\UploadException $e) {
            JsonResponse::error($e->getMessage(), 400)->send();
        } catch (\Throwable $e) {
            $message = $this->config->debugErrorMessage ? $e->getMessage() : 'Internal server error';
            JsonResponse::error($message, 500)->send();
        }
    }

    /**
     * Simple service container - creates and caches service instances.
     */
    public function make(string $class): object
    {
        if (isset($this->services[$class])) {
            return $this->services[$class];
        }

        $instance = match ($class) {
            SecurityService::class => new SecurityService($this->config),
            MimeTypeService::class => new MimeTypeService(),
            ImageProcessingService::class => new ImageProcessingService(),
            ThumbnailService::class => new ThumbnailService($this->config, $this->make(ImageProcessingService::class)),
            FileSystemService::class => new FileSystemService(
                $this->config,
                $this->security(),
                $this->make(ThumbnailService::class),
            ),
            UploadService::class => new UploadService(
                $this->config,
                $this->security(),
                $this->make(ThumbnailService::class),
                $this->make(ImageProcessingService::class),
                $this->make(MimeTypeService::class),
            ),
            ClipboardService::class => new ClipboardService($this->config, $this->security()),

            Controller\FileController::class => new Controller\FileController(
                $this->config,
                $this->make(FileSystemService::class),
            ),
            Controller\FolderController::class => new Controller\FolderController(
                $this->config,
                $this->make(FileSystemService::class),
                $this->security(),
            ),
            Controller\UploadController::class => new Controller\UploadController(
                $this->config,
                $this->make(UploadService::class),
            ),
            Controller\OperationController::class => new Controller\OperationController(
                $this->config,
                $this->make(FileSystemService::class),
                $this->make(ClipboardService::class),
                $this->security(),
            ),
            Controller\ImageController::class => new Controller\ImageController(
                $this->config,
                $this->make(ThumbnailService::class),
                $this->security(),
            ),
            Controller\ConfigController::class => new Controller\ConfigController(
                $this->config,
                $this->security(),
            ),

            default => throw new \RuntimeException("Unknown service: {$class}"),
        };

        $this->services[$class] = $instance;
        return $instance;
    }

    public function config(): AppConfig
    {
        return $this->config;
    }

    public function security(): SecurityService
    {
        /** @var SecurityService */
        return $this->make(SecurityService::class);
    }

    /**
     * Reload config with per-folder overrides for a specific path.
     */
    public function configForFolder(string $folderPath): AppConfig
    {
        return $this->configLoader->loadHierarchicalConfig(
            $this->config,
            $folderPath,
            $this->config->currentPath,
        );
    }

    private function setCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
            header('Access-Control-Max-Age: 86400');
        }
    }
}
