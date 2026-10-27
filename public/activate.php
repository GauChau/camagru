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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}

$errors = [];
$success = false;
$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';

// Les jetons envoyés par RegistrationService contiennent 64 caractères hexadécimaux.
if (preg_match('/\A[0-9a-f]{64}\z/', $token) !== 1) {
    http_response_code(400);
    $errors['activation'] = 'Le lien d’activation est absent ou invalide.';
} else {
    try {
        $users = new UserRepository(Database::connect());
        $activation = new ActivationService($users);

        // TODO : appeler le service pour vérifier le jeton et activer le compte.
        // Mettre $success à true uniquement après confirmation de l’activation.
    } catch (Throwable $exception) {
        http_response_code(503);
        $errors['activation'] = 'L’activation est momentanément indisponible. Réessaie plus tard.';
        // Ne pas journaliser le jeton ni les détails de connexion.
        error_log('Échec de l’activation : ' . get_class($exception) . ' (' . $exception->getCode() . ').');
    }
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

require dirname(__DIR__) . '/templates/activate.php';
