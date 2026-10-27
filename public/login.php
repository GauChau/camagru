<?php
declare(strict_types=1);

use Camagru\Database;
// use Camagru\Repositories\UserRepository;
// use Camagru\Services\RegistrationService;
use Camagru\Repositories\UserRepository;
use Camagru\Services\LoginService;

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Repositories/UserRepository.php';
require_once dirname(__DIR__) . '/src/Services/LoginService.php';

session_start();
header('Cache-Control: no-store');

$errors = [];

if (!isset($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['login_csrf'];

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $login = is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '';

    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        http_response_code(403);
        $errors['csrf'] = 'Le formulaire a expiré. Réessaie avec cette page.';
    }else{
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        try{
            $users = new UserRepository(Database::connect());
            $loginserv = new LoginService($users);
            $errors = $loginserv->log($login, $password);
            if ($errors === []) {
                $_SESSION['login_success'] = true;
                $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
                $_SESSION['user_id'] = $users->getUserId($login);
                // Recharger la page de succès ne doit pas renvoyer une inscription.
                header('Location: /profile.php', true, 303);
                exit;
            }
            error_log(print_r($errors, true));
            http_response_code(422);
        }catch (Throwable $exception) {
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
elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

require dirname(__DIR__) . '/templates/login.php';
