<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Repositories\ImageRepository;
use Camagru\Services\CamEditService;

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Repositories/ImageRepository.php';
require_once dirname(__DIR__) . '/src/Services/CamEditService.php';

session_start();
header('Cache-Control: no-store');

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Connecte-toi pour enregistrer une capture.']);
        exit;
    }

    if (!is_string($_SESSION['camedit_csrf'] ?? null)
        || !is_string($_POST['csrf_token'] ?? null)
        || !hash_equals($_SESSION['camedit_csrf'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'La session a expiré. Recharge la page.']);
        exit;
    }

    try {
        if (!is_string($_POST['image'] ?? null)) {
            throw new InvalidArgumentException('La capture est manquante.');
        }

        $images = new ImageRepository(Database::connect());
        $service = new CamEditService($images);

        $fileKey = $service->uploadImage((int) $_SESSION['user_id'], $_POST['image']);

        http_response_code(201);
        echo json_encode(['file_key' => $fileKey]);
    } catch (InvalidArgumentException $exception) {
        http_response_code(422);
        echo json_encode(['error' => $exception->getMessage()]);
    } catch (Throwable $exception) {
        error_log('Échec de la capture : ' . $exception->getMessage());
        http_response_code(503);
        echo json_encode(['error' => 'Impossible d’enregistrer la capture. Réessaie plus tard.']);
    }
    exit;
}

if (!is_string($_SESSION['camedit_csrf'] ?? null)) {
    $_SESSION['camedit_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['camedit_csrf'];

require dirname(__DIR__) . '/templates/camedit.php';
