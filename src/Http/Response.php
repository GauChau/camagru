<?php
declare(strict_types=1);

namespace Camagru\Http;

final class Response
{
    public static function allowMethods(string ...$methods): void
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
            header('Allow: ' . implode(', ', $methods));
            http_response_code(405);
            exit;
        }
    }

    public static function redirect(string $location, int $status = 303): never
    {
        header('Location: ' . $location, true, $status);
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo $body;
        exit;
    }
}
