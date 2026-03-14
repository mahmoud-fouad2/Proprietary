<?php
declare(strict_types=1);

namespace Zaco\Core;

use Zaco\Security\Auth;
use Zaco\Core\View;

final class Router
{
    /** @var array<int,array{method:string,path:string,handler:callable,middleware:list<string>}> */
    private array $routes = [];

    public function __construct(private readonly string $basePath = '')
    {
    }

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->map('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->map('POST', $path, $handler, $middleware);
    }

    private function map(string $method, string $path, callable $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
            $path = $path === '' ? '/' : $path;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if ($route['path'] !== $path) {
                continue;
            }

            $this->runMiddleware($route['middleware']);
            call_user_func($route['handler']);
            return;
        }

        http_response_code(404);
        View::render('errors/not_found', []);
    }

    /** @param list<string> $middleware */
    private function runMiddleware(array $middleware): void
    {
        if ($middleware === []) {
            return;
        }

        // Middleware uses globals from app/init.php (auth instance)
        /** @var Auth $auth */
        $auth = $GLOBALS['auth'] ?? null;

        foreach ($middleware as $mw) {
            if ($mw === 'auth') {
                if (!$auth || !$auth->check()) {
                    $this->redirect('/login');
                }
                continue;
            }

            if (str_starts_with($mw, 'role:')) {
                $role = substr($mw, strlen('role:'));
                if (!$auth || !$auth->hasRole($role)) {
                    $this->redirect('/forbidden');
                }
                continue;
            }

            if (str_starts_with($mw, 'perm:')) {
                $perm = substr($mw, strlen('perm:'));
                if (!$auth || !$auth->can($perm)) {
                    $this->redirect('/forbidden');
                }
            }
        }
    }

    public function url(string $path): string
    {
        $p = '/' . ltrim($path, '/');
        if ($this->basePath === '') {
            return $p;
        }
        return rtrim($this->basePath, '/') . $p;
    }

    public function redirect(string $path): never
    {
        $target = $this->url($path);
        header('Location: ' . $target);
        exit;
    }
}
