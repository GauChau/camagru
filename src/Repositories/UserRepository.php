<?php
declare(strict_types=1);

namespace Camagru\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function isLoginUsed(string $login): bool
    {
        $query = $this->database->prepare(
            'SELECT 1 FROM users WHERE lower(btrim(login)) = lower(:login) LIMIT 1'
        );
        $query->execute(['login' => $login]);

        return $query->fetchColumn() !== false;
    }

    public function isEmailUsed(string $email): bool
    {
        $query = $this->database->prepare(
            'SELECT 1 FROM users WHERE lower(btrim(email)) = lower(:email) LIMIT 1'
        );
        $query->execute(['email' => $email]);

        return $query->fetchColumn() !== false;
    }

    public function create(string $login, string $email, string $passwordHash, string $activationTokenHash): void
    {
        // Les valeurs sont transmises séparément du SQL, jamais concaténées dedans.
        $query = $this->database->prepare(
            'INSERT INTO users (login, email, password, activation_token_hash) VALUES (:login, :email, :password, :activation_token_hash)'
        );
        $query->execute([
            'login' => $login,
            'email' => $email,
            'password' => $passwordHash,
            'activation_token_hash' => $activationTokenHash,
        ]);
    }

    public function isPwdMatch($login, $password):bool
    {
        $query = $this->database->prepare(
            'SELECT password FROM users WHERE lower(btrim(login)) = lower(:login)'
        );
        $query->execute(['login' => $login]);
        $pwdHash = $query->fetchColumn();
        
        if($pwdHash ===false){return false;}

        return (password_verify($password, $pwdHash));
    }

    public function isAccountActivated($login):bool
    {
        $query = $this->database->prepare(
            'SELECT activation FROM users WHERE lower(btrim(login)) = lower(:login)'
        );
        $query->execute(['login' => $login]);
        $activation = $query->fetchColumn();

        if($activation === false){return false;}
        return true;
    }
    public function getEmailAdress($login): ?string
    {
        $query = $this->database->prepare(
            'SELECT email FROM users WHERE lower(btrim(login)) = lower(:login)'
        );
        $query->execute(['login' => $login]);
        $email = $query->fetchColumn();

        return $email === false ? null : $email;
    }

    public function getUserId(string $login): ?int
    {
        $query = $this->database->prepare(
            'SELECT id FROM users WHERE lower(btrim(login)) = lower(btrim(:login))'
        );
        $query->execute(['login' => $login]);
        $id = $query->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function getActivationTokenHash($login): ?string
    {
        $query = $this->database->prepare(
            'SELECT activation_token_hash FROM users WHERE lower(btrim(login)) = lower(:login)'
        );
        $query->execute(['login' => $login]);
        $activation_token_hash = $query->fetchColumn();

        return $activation_token_hash === false ? null : $activation_token_hash;
    }


}
