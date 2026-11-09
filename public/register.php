<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Http\Csrf;
use Camagru\Http\Response;
use Camagru\Http\Session;
use Camagru\Repositories\UserRepository;
use Camagru\Services\ActivationService;
use Camagru\Services\RegistrationService;
use Camagru\View\Page;

require_once dirname(__DIR__) . '/src/bootstrap.php';

Session::start();

if (Session::userId() !== null) {
    Response::redirect('/profile.php');
}

$errors = [];
$values = ['login' => '', 'email' => ''];
$success = Session::pullFlash('registration_success', false);

// Le jeton lie l'envoi du formulaire à la session qui l'a affiché (protection CSRF).
$csrfToken = Csrf::token('registration');

Response::allowMethods('GET', 'POST');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    // Ne pas accepter des tableaux à la place de champs texte, ni réafficher les mots de passe.
    foreach ($values as $field => $value) {
        $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }

    if (!Csrf::isValid('registration', $_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errors['csrf'] = 'Le formulaire a expiré. Réessaie avec cette page.';
    } else {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirmation = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

        try {
            $users = new UserRepository(Database::connect());
            $activation = new ActivationService($users);
            $registration = new RegistrationService($users, $activation);
            $errors = $registration->register($values['login'], $values['email'], $password, $confirmation);

            if ($errors === []) {
                Session::flash('registration_success', true);
                Csrf::renew('registration');
                // Recharger la page de succès ne doit pas renvoyer une inscription.
                Response::redirect('/register.php');
            }

            http_response_code(422);
        } catch (Throwable $exception) {
            http_response_code(503);
            $errors['registration'] = 'L’inscription est momentanément indisponible. Réessaie plus tard.';
            // Ne jamais exposer les détails de connexion, les requêtes ou les mots de passe.
            error_log('Échec de l’inscription : ' . get_class($exception) . ' (' . $exception->getCode() . ').');
        }
    }
}

Page::render(
    'register',
    [
        'success' => $success,
        'errors' => $errors,
        'values' => $values,
        'csrfToken' => $csrfToken,
    ],
    title: 'Register — Camagru',
    styles: ['/assets/css/auth.css'],
    scripts: ['/assets/js/auth.js']
);
