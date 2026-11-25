<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal router. Routes look like: $router->get('/api/products/{slug}', [ProductController::class, 'show']);
 * Optional 'auth' => true makes the route require a valid Bearer token.
 */
final class Router
{
    /** @var array<int, array{method:string, regex:string, names:string[], handler:array, auth:bool}> */
    private array $routes = [];

    public function get(string $path, array $handler, bool $auth = false): void    { $this->add('GET', $path, $handler, $auth); }
    public function post(string $path, array $handler, bool $auth = false): void   { $this->add('POST', $path, $handler, $auth); }
    public function patch(string $path, array $handler, bool $auth = false): void  { $this->add('PATCH', $path, $handler, $auth); }
    public function delete(string $path, array $handler, bool $auth = false): void { $this->add('DELETE', $path, $handler, $auth); }

    private function add(string $method, string $path, array $handler, bool $auth): void
    {
        preg_match_all('/\{(\w+)\}/', $path, $m);
        $regex = '#^' . preg_replace('/\{\w+\}/', '([^/]+)', $path) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'names' => $m[1], 'handler' => $handler, 'auth' => $auth];
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }

            $request->params = array_combine($route['names'], array_map('urldecode', array_slice($matches, 1))) ?: [];

            // Optional auth: resolve the user if a token is present; require it if the route says so.
            $request->user = Auth::userFromRequest($request);
            if ($route['auth'] && $request->user === null) {
                throw HttpException::unauthorized();
            }

            [$class, $method] = $route['handler'];
            return (new $class())->$method($request);
        }

        throw $pathMatched
            ? new HttpException(405, 'Method not allowed.')
            : HttpException::notFound('Endpoint not found.');
    }
}
