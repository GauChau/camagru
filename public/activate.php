<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Http\Csrf;
use Camagru\Http\Response;
use Camagru\Http\Session;
use Camagru\Repositories\UserRepository;
use Camagru\Services\ActivationService;
use Camagru\View\Page;

require_once dirname(__DIR__) . '/src/bootstrap.php';

Session::start();
header('Referrer-Policy: no-referrer');

Response::allowMethods('GET', 'POST');

$errors = [];
$success = false;
$resendSuccess = false;
$email = '';
$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';

$csrfToken = Csrf::token('activation_resend');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';

        if (!Csrf::isValid('activation_resend', $_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            $errors['csrf'] = 'The form has expired. Please try again.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            http_response_code(422);
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            $users = new UserRepository(Database::connect());
            $activation = new ActivationService($users);
            $errors = $activation->resendActivationEmail($email);
            $resendSuccess = $errors === [];

            if ($resendSuccess) {
                $csrfToken = Csrf::renew('activation_resend');
            } else {
                http_response_code(502);
            }
        }
    } elseif ($token !== '') {
        $users = new UserRepository(Database::connect());
        $activation = new ActivationService($users);

        $success = $activation->activate($token);
        if (!$success) {
            http_response_code(400);
        }
    }
} catch (Throwable $exception) {
    http_response_code(503);
    // Ne pas journaliser le jeton ni les détails de connexion.
    error_log('Échec de l’activation : ' . get_class($exception) . ' (' . $exception->getCode() . ').');
}

Page::render(
    'activate',
    [
        'success' => $success,
        'resendSuccess' => $resendSuccess,
        'token' => $token,
        'errors' => $errors,
        'email' => $email,
        'csrfToken' => $csrfToken,
    ],
    title: 'Activation — Camagru',
    styles: ['/assets/css/auth.css']
);
