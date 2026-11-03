<?php
declare(strict_types=1);

use Camagru\Database;
use Camagru\Repositories\UserRepository;
use Camagru\Services\CamEditService;

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Repositories/UserRepository.php';
require_once dirname(__DIR__) . '/src/Services/CamEditService.php';

session_start();
header('Cache-Control: no-store');

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

require dirname(__DIR__) . '/templates/camedit.php';
