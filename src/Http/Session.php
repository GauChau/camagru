<?php
declare(strict_types=1);

namespace Camagru\Http;

use RuntimeException;

final class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
            throw new RuntimeException('Impossible de démarrer la session.');
        }

        header('Cache-Control: no-store');
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function regenerate(): void
    {
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Impossible de renouveler la session.');
        }
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);

        return $value;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();

        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }
}
