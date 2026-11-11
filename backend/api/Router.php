<?php

declare(strict_types=1);

/**
 * A small route table: each route is an HTTP method, a URL pattern, the roles
 * allowed to call it, and the code that handles it.
 *
 *   $router->add('GET', 'students/{id}', ['admin', 'registrar'], $handler);
 *
 * dispatch() finds the matching route, checks who is calling (ApiAuth), then
 * runs the handler. No match → 404. Path matches but method doesn't → 405
 * with an "Allow" header listing the methods that do work.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, roles: ?array, handler: callable}> */
    private array $routes = [];

    /**
     * @param string[]|null $roles roles allowed to call this route; null = public (no sign-in)
     * @param callable(array<string,string> $params, ?array $user): never $handler
     */
    public function add(string $method, string $pattern, ?array $roles, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => self::toRegex($pattern),
            'roles'   => $roles,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): never
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $match) !== 1) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $params = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
            $user = $route['roles'] === null ? null : ApiAuth::requireRole($route['roles']);

            ($route['handler'])($params, $user);

            // Every handler ends by sending a response; reaching here is a bug.
            Response::error('Internal server error.', 500);
        }

        if ($allowedMethods !== []) {
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            Response::error("The {$method} method is not allowed for this URL.", 405);
        }

        Response::error('Route not found.', 404);
    }

    /** 'students/{id}' → '#^students/(?P<id>[A-Za-z0-9-]+)$#' */
    private static function toRegex(string $pattern): string
    {
        $segments = [];
        foreach (explode('/', trim($pattern, '/')) as $segment) {
            $segments[] = preg_match('/^\{(\w+)\}$/', $segment, $name) === 1
                ? '(?P<' . $name[1] . '>[A-Za-z0-9-]+)'
                : preg_quote($segment, '#');
        }

        return '#^' . implode('/', $segments) . '$#';
    }
}
