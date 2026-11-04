<?php
declare(strict_types=1);

use Camagru\Services\OverlayCatalog;

require_once dirname(__DIR__) . '/src/Services/OverlayCatalog.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}

try {
    if (!is_string($_GET['name'] ?? null)) {
        throw new InvalidArgumentException('Calque manquant.');
    }
    $contents = (new OverlayCatalog())->readImage($_GET['name']);
    header('Content-Type: image/png');
    echo $contents;
} catch (InvalidArgumentException $exception) {
    http_response_code(404);
} catch (Throwable $exception) {
    error_log('Échec de lecture du calque : ' . $exception->getMessage());
    http_response_code(503);
}
