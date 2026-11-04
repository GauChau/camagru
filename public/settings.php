<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Repositories\UserRepository;
use Camagru\Services\UserSettingsService;

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Repositories/UserRepository.php';
require_once dirname(__DIR__) . '/src/Services/UserSettingsService.php';

session_start();
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php', true, 303);
    exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$errors = [];
$values = ['login' => '', 'email' => '', 'comment_notifications' => true];
$success = ($_SESSION['settings_success'] ?? false) === true;
unset($_SESSION['settings_success']);

if (!isset($_SESSION['settings_csrf'])) {
    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['settings_csrf'];

try {
    $users = new UserRepository(Database::connect());
    // L'identifiant vient exclusivement de la session, jamais du formulaire.
    $userId = (int) $_SESSION['user_id'];
    $user = $users->findById($userId);
    if ($user === null) {
        $_SESSION = [];
        session_destroy();
        header('Location: /login.php', true, 303);
        exit;
    }
    $values = ['login' => $user['login'], 'email' => $user['email'], 'comment_notifications' => $user['comment_notifications']];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $success = false;
        $values['login'] = is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '';
        $values['email'] = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
        $values['comment_notifications'] = ($_POST['comment_notifications'] ?? null) === '1';

        if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
            http_response_code(403);
            $errors['csrf'] = 'The form has expired. Please try again.';
        } else {
            $passwords = [];
            foreach (['current_password', 'password', 'confirm_password'] as $field) {
                $value = $_POST[$field] ?? '';
                if (!is_string($value)) {
                    $errors[$field] = 'Please enter a valid password.';
                } else {
                    $passwords[$field] = $value;
                }
            }

            if ($errors === []) {
                $settings = new UserSettingsService($users);
                $errors = $settings->update(
                    $userId,
                    $values['login'],
                    $values['email'],
                    $passwords['current_password'],
                    $passwords['password'],
                    $passwords['confirm_password'],
                    $values['comment_notifications']
                );
            }
            if ($errors === []) {
                session_regenerate_id(true);
                $_SESSION['settings_success'] = true;
                $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
                header('Location: /settings.php', true, 303);
                exit;
            }
            http_response_code(422);
        }
    }
} catch (Throwable $exception) {
    http_response_code(503);
    $success = false;
    $errors['settings'] = 'Your settings are temporarily unavailable. Please try again later.';
    error_log('Échec des paramètres : ' . get_class($exception) . ' (' . $exception->getCode() . ').');
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
require dirname(__DIR__) . '/templates/settings.php';
