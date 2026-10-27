<?php
declare(strict_types=1);

namespace Camagru\Services;

use Camagru\Repositories\UserRepository;
use PDOException;

final class LoginService
{
    public function __construct(private UserRepository $users)
    {
    }

    /** Retourne les erreurs du formulaire, ou un tableau vide si le compte est créé. */
    public function log(string $login, string $password): array
    {
        $login = trim($login);
        $errors = [];

        if ($login === '' || preg_match('//u', $login) !== 1 || str_contains($login, "\0")) {
            $errors['login'] = 'Renseigne un login valide.';
        }

        if ($errors !== []) {
            return $errors;
        }

        // Seul le hash est transmis au repository puis stocké dans users.password.
        // $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

        if (!$this->users->isPwdMatch($login, $password)) {
            return ['credentials' => 'Login ou mot de passe incorrect.'];
        }

        if (!$this->users->isAccountActivated($login)) {
            return ['credentials' => 'Activate your email.'];
        }
        return [];
    }
}
