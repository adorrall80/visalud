<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];
    private array $middlewareAliases = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function get(string $path, array $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, array $handler): Route
    {
        return $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, array $handler): Route
    {
        return $this->add('DELETE', $path, $handler);
    }

    public function aliasMiddleware(string $name, string $class): void
    {
        $this->middlewareAliases[$name] = $class;
    }

    public function resource(string $base, string $controller): array
    {
        $base = '/' . trim($base, '/');
        return [
            $this->get($base, [$controller, 'index']),
            $this->get($base . '/create', [$controller, 'create']),
            $this->post($base, [$controller, 'store']),
            $this->get($base . '/{id}', [$controller, 'show']),
            $this->get($base . '/{id}/edit', [$controller, 'edit']),
            $this->put($base . '/{id}', [$controller, 'update']),
            $this->delete($base . '/{id}', [$controller, 'destroy']),
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes[$request->method()] ?? [] as $route) {
            $parameters = $this->match($route->path, $request->path());
            if ($parameters === null) {
                continue;
            }

            foreach ($parameters as $key => $value) {
                $request->setAttribute($key, $value);
            }

            $destination = function (Request $request) use ($route, $parameters): Response {
                [$controllerClass, $method] = $route->handler;
                $controller = $this->container->get($controllerClass);
                $result = $controller->{$method}($request, ...array_values($parameters));
                if ($result instanceof Response) {
                    return $result;
                }
                return Response::html((string) $result);
            };

            $pipeline = array_reduce(
                array_reverse($route->middlewareNames()),
                function (callable $next, string $name): callable {
                    return function (Request $request) use ($name, $next): Response {
                        $class = $this->middlewareAliases[$name] ?? $name;
                        $middleware = $this->container->get($class);
                        if (!$middleware instanceof Middleware) {
                            throw new \RuntimeException("Middleware inválido: {$class}");
                        }
                        return $middleware->handle($request, $next);
                    };
                },
                $destination,
            );

            return $pipeline($request);
        }

        return Response::html('<h1>404</h1><p>Ruta no encontrada.</p>', 404);
    }

    private function add(string $method, string $path, array $handler): Route
    {
        $normalized = rtrim($path, '/') ?: '/';
        $route = new Route($method, $normalized, $handler);
        $this->routes[$method][] = $route;
        return $route;
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        $names = [];
        $pattern = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', function (array $match) use (&$names): string {
            $names[] = $match[1];
            return '([^/]+)';
        }, $routePath);

        if ($pattern === null || !preg_match('#^' . $pattern . '$#', $requestPath, $matches)) {
            return null;
        }

        array_shift($matches);
        return array_combine($names, array_map('urldecode', $matches)) ?: [];
    }
}
