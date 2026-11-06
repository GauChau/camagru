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
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

if (isset($_SESSION['user_id'])) {
    header('Location: /gallery.php', true, 303);
    exit;
}
if (!isset($_SESSION['recovery_csrf'])) {
    $_SESSION['recovery_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['recovery_csrf'];
$tokenInput = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['token'] ?? null) : ($_GET['token'] ?? null);
$token = is_string($tokenInput) ? $tokenInput : '';
$canReset = false;

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($csrfToken, $_POST['csrf_token']))) {
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
                $_SESSION['recovery_success'] = true;
                $_SESSION['recovery_csrf'] = bin2hex(random_bytes(32));
                header('Location: /login.php', true, 303);
                exit;
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

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
require dirname(__DIR__) . '/templates/recovery.php';
