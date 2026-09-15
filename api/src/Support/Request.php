<?php

namespace App\Support;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $action,
        public readonly array $body = [],
        public readonly array $query = []
    ) {
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }
}
