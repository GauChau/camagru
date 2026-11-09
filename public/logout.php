<?php
declare(strict_types=1);

use Camagru\Http\Csrf;
use Camagru\Http\Response;
use Camagru\Http\Session;

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Cache-Control: no-store');

// Une déconnexion doit venir du formulaire envoyé en POST.
Response::allowMethods('POST');

Session::start();

if (!Csrf::isValid('logout', $_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Le formulaire a expiré. Recharge la page puis réessaie.');
}

Session::logout();
Response::redirect('/');
