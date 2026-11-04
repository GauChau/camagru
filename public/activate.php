<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Repositories\UserRepository;
use Camagru\Services\ActivationService;

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Repositories/UserRepository.php';
require_once dirname(__DIR__) . '/src/Services/ActivationService.php';

session_start();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$errors = [];
$success = false;
$resendSuccess = false;
$email = '';
$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';

if (!isset($_SESSION['activation_resend_csrf'])) {
    $_SESSION['activation_resend_csrf'] = bin2hex(random_bytes(32));
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';

        if (!is_string($_POST['csrf_token'] ?? null)
            || !hash_equals($_SESSION['activation_resend_csrf'], $_POST['csrf_token'])) {
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
                $_SESSION['activation_resend_csrf'] = bin2hex(random_bytes(32));
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

$csrfToken = $_SESSION['activation_resend_csrf'];
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

require dirname(__DIR__) . '/templates/activate.php';
