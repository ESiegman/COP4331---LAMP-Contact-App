<?php

namespace App\Support;

final class Response
{
    public static function json(array $body, int $status = 200): array
    {
        return ['status' => $status, 'body' => $body];
    }

    public static function success($data = [], int $status = 200): array
    {
        return self::json(['success' => true, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400): array
    {
        return self::json(['success' => false, 'error' => $message], $status);
    }

    public static function send(array $response): void
    {
        http_response_code($response['status']);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
    }
}
