<?php

declare(strict_types=1);

namespace RFM;

use RFM\Http\{Request, JsonResponse};

final class Router
{
    /** @var array<string, array<string, array{controller: string, action: string}>> */
    private array $routes = [];

    public function __construct()
    {
        // File listing & info
        $this->get('/api/files', Controller\FileController::class, 'list');
        $this->get('/api/files/info', Controller\FileController::class, 'info');
        $this->get('/api/files/download', Controller\FileController::class, 'download');
        $this->get('/api/files/preview', Controller\FileController::class, 'preview');
        $this->get('/api/files/content', Controller\FileController::class, 'getContent');

        // Folder operations
        $this->post('/api/folders/create', Controller\FolderController::class, 'create');
        $this->post('/api/folders/rename', Controller\FolderController::class, 'rename');
        $this->post('/api/folders/delete', Controller\FolderController::class, 'delete');

        // File upload
        $this->post('/api/upload', Controller\UploadController::class, 'upload');
        $this->post('/api/upload/url', Controller\UploadController::class, 'uploadFromUrl');

        // File operations
        $this->post('/api/operations/rename', Controller\OperationController::class, 'rename');
        $this->post('/api/operations/delete', Controller\OperationController::class, 'delete');
        $this->post('/api/operations/delete-bulk', Controller\OperationController::class, 'deleteBulk');
        $this->post('/api/operations/duplicate', Controller\OperationController::class, 'duplicate');
        $this->post('/api/operations/copy', Controller\OperationController::class, 'copy');
        $this->post('/api/operations/cut', Controller\OperationController::class, 'cut');
        $this->post('/api/operations/paste', Controller\OperationController::class, 'paste');
        $this->post('/api/operations/clear-clipboard', Controller\OperationController::class, 'clearClipboard');
        $this->post('/api/operations/chmod', Controller\OperationController::class, 'chmod');
        $this->post('/api/operations/extract', Controller\OperationController::class, 'extract');
        $this->post('/api/operations/save-text', Controller\OperationController::class, 'saveTextFile');
        $this->post('/api/operations/create-file', Controller\OperationController::class, 'createFile');

        // Image editor
        $this->post('/api/image/save', Controller\ImageController::class, 'saveEdited');

        // Config & session
        $this->get('/api/session/init', Controller\ConfigController::class, 'initSession');
        $this->get('/api/config', Controller\ConfigController::class, 'getConfig');
        $this->get('/api/languages', Controller\ConfigController::class, 'getLanguages');
        $this->get('/api/translations', Controller\ConfigController::class, 'getTranslations');
        $this->post('/api/config/language', Controller\ConfigController::class, 'changeLanguage');
        $this->post('/api/config/view', Controller\ConfigController::class, 'changeView');
        $this->post('/api/config/sort', Controller\ConfigController::class, 'changeSort');
        $this->post('/api/config/filter', Controller\ConfigController::class, 'changeFilter');
    }

    public function dispatch(Request $request, App $app): void
    {
        $path = $request->path;
        $method = $request->method;

        // Try exact match first
        if (isset($this->routes[$method][$path])) {
            $route = $this->routes[$method][$path];
            $this->callController($route['controller'], $route['action'], $request, $app);
            return;
        }

        // If it's not an API route, serve the SPA
        if (!str_starts_with($path, '/api/')) {
            $this->callController(Controller\FileController::class, 'spa', $request, $app);
            return;
        }

        // No route found
        JsonResponse::error('Not found', 404)->send();
    }

    private function get(string $path, string $controller, string $action): void
    {
        $this->routes['GET'][$path] = ['controller' => $controller, 'action' => $action];
    }

    private function post(string $path, string $controller, string $action): void
    {
        $this->routes['POST'][$path] = ['controller' => $controller, 'action' => $action];
    }

    private function callController(string $controllerClass, string $action, Request $request, App $app): void
    {
        $controller = $app->make($controllerClass);
        $response = $controller->$action($request);

        if ($response instanceof JsonResponse) {
            $response->send();
        }
    }
}
