<?php

declare(strict_types=1);

namespace Sulis;

class Router
{
    public bool $case_sensitive = false;

    protected array $routes = [];
    protected int $index = 0;

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function clear(): void
    {
        $this->routes = [];
    }

    public function map(string $pattern, callable $callback, bool $pass_route = false): void
    {
        $url = trim($pattern);
        $methods = ['*'];

        if (false !== strpos($url, ' ')) {
            [$method, $url] = explode(' ', $url, 2);
            $url = trim($url);
            $methods = explode('|', $method);
        }

        $this->routes[] = new Route($url, $callback, $methods, $pass_route);
    }

    public function route(Request $request)
    {
        $url_decoded = urldecode($request->url);

        while ($route = $this->current()) {
            if (false !== $route
            && $route->matchMethod($request->method)
            && $route->matchUrl($url_decoded, $this->case_sensitive)) {
                return $route;
            }

            $this->next();
        }

        return false;
    }

    public function current()
    {
        return $this->routes[$this->index] ?? false;
    }

    public function next(): void
    {
        $this->index++;
    }

    public function reset(): void
    {
        $this->index = 0;
    }
}
