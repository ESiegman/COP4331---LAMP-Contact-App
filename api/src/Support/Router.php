<?php

namespace App\Support;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $action, callable $handler): void
    {
        $this->routes[strtoupper($method) . ' ' . $action] = $handler;
    }

    public function dispatch(Request $request): array
    {
        $key = strtoupper($request->method) . ' ' . $request->action;

        if (!isset($this->routes[$key])) {
            return Response::error('Not found', 404);
        }

        return ($this->routes[$key])($request);
    }
}
