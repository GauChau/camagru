<?php
declare(strict_types=1);

namespace Camagru\Services;

use Camagru\Repositories\UserRepository;
use Closure;
use DateTimeImmutable;
use DateTimeZone;

final class PasswordRecoveryService
{
    private Closure $sendEmail;

    public function __construct(private UserRepository $users, ?callable $sendEmail = null)
    {
        $this->sendEmail = Closure::fromCallable($sendEmail ?? 'mail');
    }

    public function requestRecovery(string $email): array
    {
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['email' => 'Renseigne une adresse email valide.'];
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+30 minutes');
        if (!$this->users->updateRecoveryTokenHash(hash('sha256', $token), $email, $expiresAt->format('Y-m-d H:i:sP'))) {
            // Même résultat public pour une adresse connue ou inconnue.
            return [];
        }

        $errors = $this->sendRecoveryEmail($email, $token);
        if ($errors !== []) {
            // Ne pas révéler l'existence du compte par une erreur d'envoi.
            error_log('Password recovery: email could not be sent.');
        }

        return [];
    }

    public function findUserIdByToken(string $token): ?int
    {
        if (preg_match('/\A[0-9a-f]{64}\z/', $token) !== 1) {
            return null;
        }

        return $this->users->getIdbyRecoveryHash(hash('sha256', $token));
    }

    public function resetPassword(string $token, string $password, string $confirmation): array
    {
        if ($this->findUserIdByToken($token) === null) {
            return ['token' => 'Ce lien est invalide ou a expiré. Demande un nouveau lien.'];
        }

        $errors = [];
        if (str_contains($password, "\0") || preg_match('/\A.{10,}\z/us', $password) !== 1 || preg_match('/[0-9]/', $password) !== 1) {
            $errors['password'] = 'Le mot de passe doit contenir au moins 10 caractères et au moins un chiffre.';
        }
        if ($password !== $confirmation) {
            $errors['confirm_password'] = 'Les mots de passe doivent être identiques.';
        }
        if ($errors !== []) {
            return $errors;
        }

        $updated = $this->users->resetPasswordByRecoveryHash(
            hash('sha256', $token),
            password_hash($password, PASSWORD_ARGON2ID)
        );

        return $updated ? [] : ['token' => 'Ce lien est invalide ou a expiré. Demande un nouveau lien.'];
    }

    private function sendRecoveryEmail(string $email, string $token): array
    {
        $link = rtrim((string) getenv('APP_URL'), '/')
            . '/recovery.php?' . http_build_query(['token' => $token]);
        $ok = ($this->sendEmail)(
            $email,
            'Password Recovery',
            "Click on this link to change your password (valid for 30 minutes):\r\n" . $link,
            [
                'From' => 'no-reply@camagru.test',
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]
        );

        return $ok ? [] : ['email' => 'The recovery email could not be sent. Please try again.'];
    }
}
