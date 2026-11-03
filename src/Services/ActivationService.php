<?php
declare(strict_types=1);

namespace Camagru\Services;

use Camagru\Repositories\UserRepository;

final class ActivationService
{
    public function __construct(private UserRepository $users)
    {
    }

    public function activate(string $token): bool
    {
        $id = $this->users->getUserIdByActivationToken($token);

        if ($id === null) {
            return false;
        }

        return $this->users->updateActivationStatus($id);
    }

    public function resendActivationEmail(string $email): array
    {
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['email' => 'Please enter a valid email address.'];
        }

        $token = $this->createNewEmailToken($email);
        if ($token === null) {
            // Même réponse pour une adresse inconnue ou un compte déjà activé.
            return [];
        }

        return $this->sendActivationEmail($email, $token);
    }

    public function createNewEmailToken(string $email): ?string
    {
        $token = bin2hex(random_bytes(32));
        $activationTokenHash = hash('sha256', $token);
        if (!$this->users->updateEmailToken($email, $activationTokenHash)) {
            return null;
        }

        return $token;
    }

    public function sendActivationEmail(string $email, string $token): array
    {
        $lien = rtrim((string) getenv('APP_URL'), '/')
            . '/activate.php?'
            . http_build_query(['token' => $token]);
        $ok = mail(
            $email,
            'Confirme ton compte Camagru',
            "Clique sur ce lien pour activer ton compte :\r\n" . $lien,
            [
                'From' => 'no-reply@camagru.test',
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]
        );

        return $ok ? [] : ['email' => 'The activation email could not be sent. Please try again.'];
    }
}
