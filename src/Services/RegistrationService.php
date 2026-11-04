<?php
declare(strict_types=1);

namespace Camagru\Services;

use Camagru\Repositories\UserRepository;
use Camagru\Services\ActivationService;
use PDOException;

final class RegistrationService
{
    public function __construct(
        private UserRepository $users,
        private ActivationService $activation
    ) {
    }

    /** Tableau vide après création et envoi ; activation_email indique un compte créé sans email envoyé. */
    public function register(string $login, string $email, string $password, string $confirmation): array
    {
        $login = trim($login);
        $email = strtolower(trim($email));
        $errors = [];

        if ($login === '' || preg_match('//u', $login) !== 1 || str_contains($login, "\0")) {
            $errors['login'] = 'Renseigne un login valide.';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Renseigne une adresse email valide.';
        }

        // Le mode Unicode compte les caractères, pas les octets des caractères accentués.
        // if (preg_match('/\A.{10,}\z/us', $password) !== 1 || preg_match('/[0-9]/', $password) !== 1) {
        //     $errors['password'] = 'Le mot de passe doit contenir au moins 10 caractères et au moins un chiffre.';
        // }

        if ($password !== $confirmation) {
            $errors['confirm_password'] = 'Les mots de passe doivent être identiques.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $errors = $this->findConflicts($login, $email);
        if ($errors !== []) {
            return $errors;
        }

        // Seul le hash est transmis au repository puis stocké dans users.password.
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        try {
            return $this->activation->createPendingAccount($login, $email, $passwordHash);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            // PostgreSQL protège aussi contre deux inscriptions simultanées identiques.
            return $this->findConflicts($login, $email)
                ?: ['registration' => 'Ce login ou cette adresse email est déjà utilisé.'];
        }
    }

    private function findConflicts(string $login, string $email): array
    {
        $errors = [];

        if ($this->users->isLoginUsed($login)) {
            $errors['login'] = 'Ce login est déjà utilisé.';
        }

        if ($this->users->isEmailUsed($email)) {
            $errors['email'] = 'Cette adresse email est déjà utilisée.';
        }

        return $errors;
    }


}
