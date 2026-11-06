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

    public function findById(int $id): ?array
    {
        $query = $this->database->prepare('SELECT id, login, email, password, comment_notifications FROM users WHERE id = :id');
        $query->execute(['id' => $id]);
        $user = $query->fetch();

        return $user === false ? null : $user;
    }

    public function isLoginUsedByOther(string $login, int $userId): bool
    {
        $query = $this->database->prepare(
            'SELECT 1 FROM users WHERE lower(btrim(login)) = lower(btrim(:login)) AND id <> :id LIMIT 1'
        );
        $query->execute(['login' => $login, 'id' => $userId]);

        return $query->fetchColumn() !== false;
    }

    public function isEmailUsedByOther(string $email, int $userId): bool
    {
        $query = $this->database->prepare(
            'SELECT 1 FROM users WHERE lower(btrim(email)) = lower(btrim(:email)) AND id <> :id LIMIT 1'
        );
        $query->execute(['email' => $email, 'id' => $userId]);

        return $query->fetchColumn() !== false;
    }

    public function updateSettings(
        int $userId,
        string $login,
        string $email,
        ?string $passwordHash,
        string $currentPasswordHash,
        bool $commentNotifications = true
    ): bool {
        $query = $this->database->prepare(
            'UPDATE users SET login = :login, email = :email, password = COALESCE(:password, password),
                comment_notifications = :notifications
            WHERE id = :id AND password = :current_password'
        );
        $query->execute([
            'id' => $userId,
            'login' => $login,
            'email' => $email,
            'password' => $passwordHash,
            'current_password' => $currentPasswordHash,
            'notifications' => $commentNotifications ? 'true' : 'false',
        ]);

        return $query->rowCount() === 1;
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

    public function activateByTokenHash(string $tokenHash): bool
    {
        $query = $this->database->prepare(
            'UPDATE users
            SET activation = TRUE,
                activation_token_hash = NULL,
                activation_token_expires_at = NULL
            WHERE activation_token_hash = :token_hash AND activation = FALSE'
        );
        $query->execute(['token_hash' => $tokenHash]);

        return $query->rowCount() === 1;
    }

    public function updateEmailToken(string $email, string $tokenHash): bool
    {
        $query = $this->database->prepare(
            'UPDATE users
            SET activation_token_hash = :tokenhash
            WHERE lower(btrim(email)) = lower(btrim(:email))
                AND activation = FALSE'
        );
        $query->execute([
            'email' => $email,
            'tokenhash' => $tokenHash,
        ]);

        return $query->rowCount() === 1;
    }


    public function updateRecoveryTokenHash(string $tokenHash, string $email, string $expiresAt): bool
    {
        $query = $this->database->prepare(
            'UPDATE users
            SET pwdrecovery_token_hash = :token_hash,
                pwdrecovery_token_expires_at = :expires_at
            WHERE lower(btrim(email)) = lower(btrim(:email))'
        );
        $query->execute([
            'email' => $email,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
        return $query->rowCount() === 1;
    }

    public function getIdbyRecoveryHash(string $tokenHash): ?int
    {
        $query = $this->database->prepare(
            'SELECT id
                FROM users
                WHERE pwdrecovery_token_hash = :token_hash
                AND pwdrecovery_token_expires_at > CURRENT_TIMESTAMP'
        );
        $query->execute(['token_hash' => $tokenHash]);
        $id = $query->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function resetPasswordByRecoveryHash(string $tokenHash, string $passwordHash): bool
    {
        // Vérifier et consommer le jeton dans le même UPDATE empêche sa réutilisation.
        $query = $this->database->prepare(
            'UPDATE users
             SET password = :password,
                 pwdrecovery_token_hash = NULL,
                 pwdrecovery_token_expires_at = NULL
             WHERE pwdrecovery_token_hash = :token_hash
               AND pwdrecovery_token_expires_at > CURRENT_TIMESTAMP'
        );
        $query->execute(['password' => $passwordHash, 'token_hash' => $tokenHash]);

        return $query->rowCount() === 1;
    }
}
