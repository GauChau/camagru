<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Http\Csrf;
use Camagru\Http\Response;
use Camagru\Http\Session;
use Camagru\Repositories\ImageRepository;
use Camagru\Services\CamEditService;
use Camagru\Services\OverlayCatalog;
use Camagru\View\Page;

require_once dirname(__DIR__) . '/src/bootstrap.php';

Session::start();

Response::allowMethods('GET', 'POST');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && Session::userId() === null) {
    Response::redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Session::userId() === null) {
        Response::json(['error' => 'Connecte-toi pour enregistrer une capture.'], 401);
    }

    if (!Csrf::isValid('camedit', $_POST['csrf_token'] ?? null)) {
        Response::json(['error' => 'La session a expiré. Recharge la page.'], 403);
    }

    try {
        if (!isset($_FILES['photo']) && !is_string($_POST['image'] ?? null)) {
            throw new InvalidArgumentException('La capture est manquante.');
        }
        $overlayNames = $_POST['overlays'] ?? [];
        // Accepter aussi les requêtes de l’ancienne version de l’éditeur.
        if (array_key_exists('overlay', $_POST)) {
            if (array_key_exists('overlays', $_POST) || !is_string($_POST['overlay'])) {
                throw new InvalidArgumentException('La sélection de stickers est invalide.');
            }
            $overlayNames = $_POST['overlay'] === '' ? [] : [$_POST['overlay']];
        }
        if (!is_array($overlayNames)) {
            throw new InvalidArgumentException('La sélection de stickers est invalide.');
        }

        $images = new ImageRepository(Database::connect());
        $service = new CamEditService($images, new OverlayCatalog());

        if (isset($_FILES['photo'])) {
            $result = $service->uploadFile(Session::userId(), $_FILES['photo'], $overlayNames);
        } else {
            $result = $service->uploadImage(Session::userId(), $_POST['image'], $overlayNames);
        }

        Response::json($result, 201);
    } catch (InvalidArgumentException $exception) {
        Response::json(['error' => $exception->getMessage()], 422);
    } catch (Throwable $exception) {
        error_log('Échec de la capture : ' . $exception->getMessage());
        Response::json(['error' => 'Impossible d’enregistrer la capture. Réessaie plus tard.'], 503);
    }
}

$csrfToken = Csrf::token('camedit');
$overlayError = null;
try {
    $overlayNames = (new OverlayCatalog())->listImages();
} catch (Throwable $exception) {
    error_log('Échec du stock de calques : ' . $exception->getMessage());
    http_response_code(503);
    $overlayNames = [];
    $overlayError = 'Les calques sont momentanément indisponibles. Réessaie plus tard.';
}

$previousImages = [];
$photosError = null;
try {
    $previousImages = (new ImageRepository(Database::connect()))->getUserImageListByDate(Session::userId());
} catch (Throwable $exception) {
    http_response_code(503);
    $photosError = 'Tes créations sont momentanément indisponibles. Réessaie plus tard.';
    error_log('Échec de l’historique des montages : ' . get_class($exception) . '.');
}

Page::render(
    'camedit',
    [
        'csrfToken' => $csrfToken,
        'overlayError' => $overlayError,
        'overlayNames' => $overlayNames,
        'photosError' => $photosError,
        'previousImages' => $previousImages,
    ],
    title: 'Camedit — Camagru',
    styles: ['/assets/css/camedit.css'],
    scripts: ['/assets/js/camedit.js']
);
