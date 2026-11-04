<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Repositories\GalleryRepository;
use Camagru\Repositories\UserRepository;
use Camagru\Services\GalleryService;

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Repositories/GalleryRepository.php';
require_once dirname(__DIR__) . '/src/Repositories/UserRepository.php';
require_once dirname(__DIR__) . '/src/Services/GalleryService.php';

session_start();
header('Cache-Control: no-store');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$images = [];
$total = 0;
$pageCount = 1;
$error = null;
$commentValue = '';
$commentImageId = null;
$notice = $_SESSION['gallery_notice'] ?? null;
unset($_SESSION['gallery_notice']);
if (!is_string($_SESSION['gallery_csrf'] ?? null)) {
    $_SESSION['gallery_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['gallery_csrf'];

try {
    $database = Database::connect();
    $gallery = new GalleryRepository($database);
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    if ($userId !== null && (new UserRepository($database))->findById($userId) === null) {
        unset($_SESSION['user_id']);
        $userId = null;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($userId === null) {
            http_response_code(401);
            $error = 'Connecte-toi pour aimer ou commenter un montage.';
        } elseif (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
            http_response_code(403);
            $error = 'Le formulaire a expiré. Réessaie avec cette page.';
        } else {
            $imageId = filter_var($_POST['image_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
            $commentValue = is_string($_POST['comment'] ?? null) ? $_POST['comment'] : '';
            $commentImageId = $imageId;
            try {
                if (!$imageId || ($action === 'comment' && !is_string($_POST['comment'] ?? null))) {
                    throw new InvalidArgumentException('Le montage ou le commentaire est invalide.');
                }
                if (!(new GalleryService($gallery))->react($imageId, $userId, $action, $commentValue)) {
                    http_response_code(404);
                    $error = 'Ce montage n’est plus disponible.';
                } else {
                    $_SESSION['gallery_notice'] = $action === 'comment' ? 'Ton commentaire a été publié.' : 'Ton choix a été enregistré.';
                    header('Location: /gallery.php?page=' . $page . '#image-' . $imageId, true, 303);
                    exit;
                }
            } catch (InvalidArgumentException $exception) {
                http_response_code(422);
                $error = $exception->getMessage();
            }
        }
    }
    $total = $gallery->countImages();
    $pageCount = max(1, (int) ceil($total / 6));
    $page = min($page, $pageCount);
    $images = $gallery->getPage($page, 6, $userId);
} catch (Throwable $exception) {
    http_response_code(503);
    $error = 'La galerie est momentanément indisponible. Réessaie plus tard.';
    error_log('Échec de la galerie : ' . get_class($exception) . ' (' . $exception->getCode() . ').');
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
require dirname(__DIR__) . '/templates/gallery.php';
