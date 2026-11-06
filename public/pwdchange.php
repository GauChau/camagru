<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Repositories\UserRepository;
use Camagru\Services\PasswordRecoveryService;

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Repositories/UserRepository.php';
require_once dirname(__DIR__) . '/src/Services/PasswordRecoveryService.php';

session_start();
$errors = [];
$success = $_SESSION['pwdchange_success'] ?? false;
unset($_SESSION['pwdchange_success']);
header('Cache-Control: no-store');

if (isset($_SESSION['user_id'])) {
    header('Location: /gallery.php', true, 303);
    exit;
}
if (!isset($_SESSION['pwdchange_csrf'])) {
    $_SESSION['pwdchange_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['pwdchange_csrf'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        http_response_code(403);
        $errors['csrf'] = 'Le formulaire a expiré. Réessaie avec cette page.';
    } else {
        $email = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
        try {
            $users = new UserRepository(Database::connect());
            $recovery = new PasswordRecoveryService($users);
            $errors = $recovery->requestRecovery($email);
            if ($errors === []) {
                $_SESSION['pwdchange_success'] = true;
                $_SESSION['pwdchange_csrf'] = bin2hex(random_bytes(32));
                header('Location: /pwdchange.php', true, 303);
                exit;
            }
            http_response_code(422);
        } catch (Throwable $exception) {
            http_response_code(503);
            $errors['recovery'] = 'Password Recovery est momentanément indisponible. Réessaie plus tard.';
            // Ne jamais exposer les détails de connexion, les requêtes ou les mots de passe.
            error_log('Échec de récupération : ' . get_class($exception) . ' (' . $exception->getCode() . ').');
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
require dirname(__DIR__) . '/templates/pwdchange.php';
