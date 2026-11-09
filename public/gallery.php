<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Http\Csrf;
use Camagru\Http\Response;
use Camagru\Http\Session;
use Camagru\Repositories\ImageRepository;
use Camagru\Repositories\UserRepository;
use Camagru\Services\GalleryService;
use Camagru\View\Page;

require_once dirname(__DIR__) . '/src/bootstrap.php';

Session::start();
Response::allowMethods('GET', 'POST');

$page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$commentsFor = filter_var($_GET['comments_for'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) ?: null;
$commentPage = filter_var($_GET['comments_page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$images = [];
$total = 0;
$pageCount = 1;
$error = null;
$commentValue = '';
$commentImageId = null;
$userId = Session::userId();
$notice = Session::pullFlash('gallery_notice', null);
$csrfToken = Csrf::token('gallery');

try {
    $database = Database::connect();
    $imageRepository = new ImageRepository($database);
    if ($userId !== null && (new UserRepository($database))->findById($userId) === null) {
        unset($_SESSION['user_id']);
        $userId = null;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($userId === null) {
            http_response_code(401);
            $error = 'Connecte-toi pour aimer, commenter ou supprimer un montage.';
        } elseif (!Csrf::isValid('gallery', $_POST['csrf_token'] ?? null)) {
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
                if (!(new GalleryService($imageRepository))->react($imageId, $userId, $action, $commentValue)) {
                    http_response_code(404);
                    $error = 'Ce montage n’est plus disponible.';
                } else {
                    Session::flash('gallery_notice', match ($action) {
                        'comment' => 'Ton commentaire a été publié.',
                        'delete' => 'Ton montage a été supprimé.',
                        default => 'Ton choix a été enregistré.',
                    });
                    $location = '/gallery.php?page=' . $page;
                    if ($action !== 'delete') {
                        $location .= '&comments_for=' . $imageId . '#image-' . $imageId;
                    }
                    Response::redirect($location);
                }
            } catch (InvalidArgumentException $exception) {
                http_response_code(422);
                $error = $exception->getMessage();
            } catch (DomainException $exception) {
                http_response_code(403);
                $error = $exception->getMessage();
            }
        }
    }
    $total = $imageRepository->countImages();
    $pageCount = max(1, (int) ceil($total / 6));
    $page = min($page, $pageCount);
    $images = $imageRepository->getPage($page, 6, $userId, $commentsFor, $commentPage);
} catch (Throwable $exception) {
    http_response_code(503);
    $error = 'La galerie est momentanément indisponible. Réessaie plus tard.';
    error_log('Échec de la galerie : ' . get_class($exception) . ' (' . $exception->getCode() . ').');
}

Page::render(
    'gallery',
    [
        'notice' => $notice,
        'error' => $error,
        'images' => $images,
        'total' => $total,
        'page' => $page,
        'pageCount' => $pageCount,
        'userId' => $userId,
        'commentImageId' => $commentImageId,
        'commentValue' => $commentValue,
        'commentsFor' => $commentsFor,
        'csrfToken' => $csrfToken,
    ],
    title: 'Gallery — Camagru',
    styles: ['/assets/css/gallery.css']
);
