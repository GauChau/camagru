<?php
declare(strict_types=1);

namespace Camagru\Services;

use Camagru\Repositories\UserRepository;
use PDOException;

final class UserSettingsService
{
    public function __construct(private UserRepository $users)
    {
    }

    /** Un tableau vide indique que les modifications ont été enregistrées. */
    public function update(
        int $userId,
        string $login,
        string $email,
        string $currentPassword,
        string $password,
        string $confirmation
    ): array {
        $login = trim($login);
        $email = strtolower(trim($email));
        $errors = [];

        if ($login === '' || preg_match('//u', $login) !== 1 || str_contains($login, "\0")) {
            $errors['login'] = 'Please enter a valid username.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return ['settings' => 'Your account could not be found. Please log in again.'];
        }
        if ($currentPassword === '' || !password_verify($currentPassword, $user['password'])) {
            $errors['current_password'] = 'Your current password is incorrect.';
        }

        if ($password !== '' || $confirmation !== '') {
            if (preg_match('/\A.{10,}\z/us', $password) !== 1 || preg_match('/[0-9]/', $password) !== 1) {
                $errors['password'] = 'The new password must contain at least 10 characters and one number.';
            }
            if ($password !== $confirmation) {
                $errors['confirm_password'] = 'The new passwords must match.';
            }
        }

        if ($errors !== []) {
            return $errors;
        }
        $errors = $this->findConflicts($userId, $login, $email);
        if ($errors !== []) {
            return $errors;
        }

        $passwordHash = $password === '' ? null : password_hash($password, PASSWORD_ARGON2ID);
        try {
            $updated = $this->users->updateSettings($userId, $login, $email, $passwordHash, $user['password']);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            // Les index uniques protègent aussi contre deux modifications simultanées.
            return $this->findConflicts($userId, $login, $email)
                ?: ['settings' => 'This username or email address is already in use.'];
        }

        return $updated ? [] : ['settings' => 'Your account changed during this request. Please try again.'];
    }

    private function findConflicts(int $userId, string $login, string $email): array
    {
        $errors = [];
        if ($this->users->isLoginUsedByOther($login, $userId)) {
            $errors['login'] = 'This username is already in use.';
        }
        if ($this->users->isEmailUsedByOther($email, $userId)) {
            $errors['email'] = 'This email address is already in use.';
        }

        return $errors;
    }
}
