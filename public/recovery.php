<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Http\Csrf;
use Camagru\Http\Response;
use Camagru\Http\Session;
use Camagru\Repositories\UserRepository;
use Camagru\Services\PasswordRecoveryService;
use Camagru\View\Page;

require_once dirname(__DIR__) . '/src/bootstrap.php';

Session::start();
$errors = [];
header('Referrer-Policy: no-referrer');

if (Session::userId() !== null) {
    Response::redirect('/gallery.php');
}
$csrfToken = Csrf::token('recovery');
$tokenInput = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['token'] ?? null) : ($_GET['token'] ?? null);
$token = is_string($tokenInput) ? $tokenInput : '';
$canReset = false;

Response::allowMethods('GET', 'POST');

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && !Csrf::isValid('recovery', $_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    $errors['csrf'] = 'Le formulaire a expiré. Rouvre le lien reçu par email.';
} else {
    try {
        $users = new UserRepository(Database::connect());
        $recovery = new PasswordRecoveryService($users);
        $canReset = $recovery->findUserIdByToken($token) !== null;
        if (!$canReset) {
            http_response_code(400);
            $errors['token'] = 'Ce lien est invalide ou a expiré. Demande un nouveau lien.';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $confirmation = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
            $errors = $recovery->resetPassword($token, $password, $confirmation);
            if ($errors === []) {
                Session::flash('recovery_success', true);
                Csrf::renew('recovery');
                Response::redirect('/login.php');
            }
            $canReset = !isset($errors['token']);
            http_response_code($canReset ? 422 : 400);
        }
    } catch (Throwable $exception) {
        http_response_code(503);
        $canReset = false;
        $errors['recovery'] = 'Récupération momentanément indisponible. Réessaie plus tard.';
        error_log('Échec de récupération : ' . get_class($exception) . ' (' . $exception->getCode() . ').');
    }
}

Page::render(
    'recovery',
    [
        'canReset' => $canReset,
        'errors' => $errors,
        'token' => $token,
        'csrfToken' => $csrfToken,
    ],
    title: 'Password Change - Camagru',
    styles: ['/assets/css/auth.css'],
    scripts: ['/assets/js/auth.js']
);
