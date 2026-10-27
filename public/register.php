<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Repositories\UserRepository;
use Camagru\Services\RegistrationService;

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Repositories/UserRepository.php';
require_once dirname(__DIR__) . '/src/Services/RegistrationService.php';

session_start();
header('Cache-Control: no-store');

$errors = [];
$values = ['login' => '', 'email' => ''];
$success = $_SESSION['registration_success'] ?? false;
unset($_SESSION['registration_success']);

// Le jeton lie l'envoi du formulaire à la session qui l'a affiché (protection CSRF).
if (!isset($_SESSION['registration_csrf'])) {
    $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['registration_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    // Ne pas accepter des tableaux à la place de champs texte, ni réafficher les mots de passe.
    foreach ($values as $field => $value) {
        $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }

    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        http_response_code(403);
        $errors['csrf'] = 'Le formulaire a expiré. Réessaie avec cette page.';
    } else {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirmation = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

        try {
            $users = new UserRepository(Database::connect());
            $registration = new RegistrationService($users);
            $errors = $registration->register($values['login'], $values['email'], $password, $confirmation);

            if ($errors === []) {
                $_SESSION['registration_success'] = true;
                $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
                // Recharger la page de succès ne doit pas renvoyer une inscription.
                header('Location: /register.php', true, 303);
                exit;
            }

            http_response_code(422);
        } catch (Throwable $exception) {
            http_response_code(503);
            $errors['registration'] = 'L’inscription est momentanément indisponible. Réessaie plus tard.';
            // Ne jamais exposer les détails de connexion, les requêtes ou les mots de passe.
            error_log('Échec de l’inscription : ' . get_class($exception) . ' (' . $exception->getCode() . ').');
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

require dirname(__DIR__) . '/templates/register.php';
