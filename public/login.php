<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Http\Csrf;
use Camagru\Http\Response;
use Camagru\Http\Session;
use Camagru\Repositories\UserRepository;
use Camagru\Services\LoginService;
use Camagru\View\Page;

require_once dirname(__DIR__) . '/src/bootstrap.php';

Session::start();

if (Session::userId() !== null) {
    Response::redirect('/profile.php');
}

$errors = [];
$recoverySuccess = Session::pullFlash('recovery_success', false);

$csrfToken = Csrf::token('login');

Response::allowMethods('GET', 'POST');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '';

    if (!Csrf::isValid('login', $_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errors['csrf'] = 'Le formulaire a expiré. Réessaie avec cette page.';
    } else {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        try {
            $users = new UserRepository(Database::connect());
            $loginService = new LoginService($users);
            $errors = $loginService->log($login, $password);
            $userId = $errors === [] ? $users->getUserId($login) : null;
            if ($userId !== null) {
                // Invalide la session anonyme avant de lui associer une identité.
                Session::login($userId);
                Session::flash('login_success', true);
                Csrf::renew('login');
                Response::redirect('/camedit.php');
            }
            http_response_code(422);
        } catch (Throwable $exception) {
            http_response_code(503);
            $errors['login'] = 'Connexion momentanément indisponible. Réessaie plus tard.';
            error_log(sprintf(
                '%s : %s dans %s à la ligne %d',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
        }
    }
}

Page::render(
    'login',
    [
        'recoverySuccess' => $recoverySuccess,
        'errors' => $errors,
        'csrfToken' => $csrfToken,
    ],
    title: 'Login — Camagru',
    styles: ['/assets/css/auth.css'],
    scripts: ['/assets/js/auth.js']
);
