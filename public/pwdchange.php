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
$success = Session::pullFlash('pwdchange_success', false);

if (Session::userId() !== null) {
    Response::redirect('/gallery.php');
}
$csrfToken = Csrf::token('pwdchange');
Response::allowMethods('GET', 'POST');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::isValid('pwdchange', $_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errors['csrf'] = 'Le formulaire a expiré. Réessaie avec cette page.';
    } else {
        $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
        try {
            $users = new UserRepository(Database::connect());
            $recovery = new PasswordRecoveryService($users);
            $errors = $recovery->requestRecovery($email);
            if ($errors === []) {
                Session::flash('pwdchange_success', true);
                Csrf::renew('pwdchange');
                Response::redirect('/pwdchange.php');
            }
            http_response_code(422);
        } catch (Throwable $exception) {
            http_response_code(503);
            $errors['recovery'] = 'Password Recovery est momentanément indisponible. Réessaie plus tard.';
            // Ne jamais exposer les détails de connexion, les requêtes ou les mots de passe.
            error_log('Échec de récupération : ' . get_class($exception) . ' (' . $exception->getCode() . ').');
        }
    }
}

Page::render(
    'pwdchange',
    [
        'success' => $success,
        'errors' => $errors,
        'csrfToken' => $csrfToken,
    ],
    title: 'Password Change - Camagru',
    styles: ['/assets/css/auth.css']
);
