<?php

header('Cache-Control: no-store');

// Une déconnexion doit venir du formulaire envoyé en POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

session_start();

// Comparer le jeton reçu à celui créé lors de l'affichage du bouton.
$csrfToken = $_SESSION['logout_csrf'] ?? null;
$submittedToken = $_POST['csrf_token'] ?? null;

if (!is_string($csrfToken) || !is_string($submittedToken)
    || !hash_equals($csrfToken, $submittedToken)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Le formulaire a expiré. Recharge la page puis réessaie.');
}

// Vider les données et détruire la session côté serveur.
$_SESSION = [];
session_destroy();

// Faire aussi expirer le cookie de session dans le navigateur.
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => $params['path'],
    'domain' => $params['domain'],
    'secure' => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite'],
]);

header('Location: /', true, 303);
exit;
