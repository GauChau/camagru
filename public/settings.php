<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Http\Csrf;
use Camagru\Http\Response;
use Camagru\Http\Session;
use Camagru\Repositories\UserRepository;
use Camagru\Services\UserSettingsService;
use Camagru\View\Page;

require_once dirname(__DIR__) . '/src/bootstrap.php';

Session::start();

if (Session::userId() === null) {
    Response::redirect('/login.php');
}
Response::allowMethods('GET', 'POST');

$errors = [];
$values = ['login' => '', 'email' => '', 'comment_notifications' => true];
$success = Session::pullFlash('settings_success', false) === true;

$csrfToken = Csrf::token('settings');

try {
    $users = new UserRepository(Database::connect());
    // L'identifiant vient exclusivement de la session, jamais du formulaire.
    $userId = Session::userId();
    $user = $users->findById($userId);
    if ($user === null) {
        Session::logout();
        Response::redirect('/login.php');
    }
    $values = ['login' => $user['login'], 'email' => $user['email'], 'comment_notifications' => $user['comment_notifications']];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $success = false;
        $values['login'] = is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '';
        $values['email'] = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
        $values['comment_notifications'] = ($_POST['comment_notifications'] ?? null) === '1';

        if (!Csrf::isValid('settings', $_POST['csrf_token'] ?? null)) {
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
                Session::regenerate();
                Session::flash('settings_success', true);
                Csrf::renew('settings');
                Response::redirect('/settings.php');
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

Page::render(
    'settings',
    [
        'success' => $success,
        'errors' => $errors,
        'values' => $values,
        'csrfToken' => $csrfToken,
    ],
    title: 'User settings — Camagru',
    styles: ['/assets/css/auth.css', '/assets/css/settings.css'],
    scripts: ['/assets/js/auth.js']
);
