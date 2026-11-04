<?php
declare(strict_types=1);

namespace Camagru\Services;

use Camagru\Repositories\UserRepository;
use Closure;

final class ActivationService
{
    private Closure $sendEmail;

    public function __construct(private UserRepository $users, ?callable $sendEmail = null)
    {
        $this->sendEmail = Closure::fromCallable($sendEmail ?? 'mail');
    }

    public function createPendingAccount(string $login, string $email, string $passwordHash): array
    {
        $token = bin2hex(random_bytes(32));
        $this->users->create($login, $email, $passwordHash, hash('sha256', $token));

        if ($this->sendActivationEmail($email, $token) !== []) {
            return ['activation_email' => 'Ton compte a été créé, mais l’email d’activation n’a pas pu être envoyé. Demande un nouvel envoi.'];
        }

        return [];
    }

    public function activate(string $token): bool
    {
        if (preg_match('/\A[0-9a-f]{64}\z/', $token) !== 1) {
            return false;
        }

        return $this->users->activateByTokenHash(hash('sha256', $token));
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

    private function createNewEmailToken(string $email): ?string
    {
        $token = bin2hex(random_bytes(32));
        $activationTokenHash = hash('sha256', $token);
        if (!$this->users->updateEmailToken($email, $activationTokenHash)) {
            return null;
        }

        return $token;
    }

    private function sendActivationEmail(string $email, string $token): array
    {
        $lien = rtrim((string) getenv('APP_URL'), '/')
            . '/activate.php?'
            . http_build_query(['token' => $token]);
        $ok = ($this->sendEmail)(
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
