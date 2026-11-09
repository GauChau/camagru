<?php
declare(strict_types=1);

namespace Camagru\Http;

final class Csrf
{
    public static function token(string $action): string
    {
        $token = $_SESSION[$action . '_csrf'] ?? null;

        return is_string($token) && $token !== '' ? $token : self::renew($action);
    }

    // Vérifier un POST ne doit jamais créer de jeton dans une nouvelle session.
    public static function isValid(string $action, mixed $submittedToken): bool
    {
        $token = $_SESSION[$action . '_csrf'] ?? null;

        return is_string($token)
            && $token !== ''
            && is_string($submittedToken)
            && hash_equals($token, $submittedToken);
    }

    public static function renew(string $action): string
    {
        return $_SESSION[$action . '_csrf'] = bin2hex(random_bytes(32));
    }
}
