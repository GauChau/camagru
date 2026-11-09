<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Http\Response;
use Camagru\Repositories\ImageRepository;

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('X-Content-Type-Options: nosniff');
Response::allowMethods('GET');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
if (!$id) {
    http_response_code(404);
    exit;
}
try {
    $image = (new ImageRepository(Database::connect()))->findImage($id);
    if ($image === null || preg_match('/\A[0-9a-f]{64}\z/', $image['file_key']) !== 1) {
        http_response_code(404);
        exit;
    }
    $path = dirname(__DIR__) . '/storage/cam_images/' . $image['file_key'] . '.png';
    if (!is_file($path) || is_link($path)) {
        http_response_code(404);
        exit;
    }
    $contents = @file_get_contents($path);
    if ($contents === false) {
        http_response_code(503);
        exit;
    }
    header('Content-Type: image/png');
    echo $contents;
} catch (Throwable $exception) {
    http_response_code(503);
    error_log('Échec de lecture du montage : ' . get_class($exception) . '.');
}
