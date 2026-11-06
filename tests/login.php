<?php
declare(strict_types=1);

// docker compose exec -T web php tests/login.php
// Les comptes sont isolés puis supprimés ; aucun email n’est envoyé.
require_once dirname(__DIR__) . '/src/Database.php';

use Camagru\Database;

$database = Database::connect();
$login = 'login_test_' . bin2hex(random_bytes(8));
$password = 'Login-password-2026!';
$ids = [];
$cookie = '';
$checks = 0;
$baseUrl = rtrim(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1', '/');

$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};

$request = static function (string $method, string $path, ?array $fields = null) use ($baseUrl, &$cookie): array {
    $headers = [];
    $handle = curl_init($baseUrl . $path);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIE => $cookie,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$cookie, &$headers): int {
            $headers[] = trim($header);
            if (preg_match('/^Set-Cookie:\s*(PHPSESSID=[^;]+)/i', $header, $match)) {
                $cookie = $match[1];
            }
            return strlen($header);
        },
    ]);
    if ($fields !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = curl_exec($handle);
    if ($body === false) {
        throw new RuntimeException('Requête HTTP impossible : ' . curl_error($handle));
    }
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers];
};

try {
    $insert = $database->prepare(
        'INSERT INTO users (login, email, password, activation) VALUES (:login, :email, :password, :activation) RETURNING id'
    );
    foreach ([$login => 'true', $login . '_inactive' => 'false'] as $name => $activated) {
        $insert->execute([
            'login' => $name, 'email' => $name . '@example.test',
            'password' => password_hash($password, PASSWORD_ARGON2ID), 'activation' => $activated,
        ]);
        $ids[] = (int) $insert->fetchColumn();
        $insert->closeCursor();
    }

    $page = $request('GET', '/login.php');
    $check($page['status'] === 200 && $cookie !== '', 'Le formulaire doit créer une session anonyme.');
    $check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $page['body'], $token) === 1,
        'Le formulaire doit contenir son jeton CSRF.');
    $anonymousCookie = $cookie;
    $fields = ['login' => $login, 'password' => $password, 'csrf_token' => $token[1]];

    $invalid = $request('POST', '/login.php', array_replace($fields, ['csrf_token' => 'incorrect']));
    $check($invalid['status'] === 403 && $cookie === $anonymousCookie,
        'Un CSRF incorrect ne doit ni authentifier ni renouveler la session.');
    $invalid = $request('POST', '/login.php', array_replace($fields, ['password' => 'incorrect']));
    $check($invalid['status'] === 422 && $cookie === $anonymousCookie,
        'Un mauvais mot de passe ne doit pas renouveler la session.');
    $invalid = $request('POST', '/login.php', array_replace($fields, ['login' => $login . '_inactive']));
    $check($invalid['status'] === 422 && $cookie === $anonymousCookie,
        'Un compte non activé ne doit pas renouveler la session.');
    $check($request('GET', '/camedit.php')['status'] === 303,
        'Ces tentatives refusées doivent laisser la session anonyme.');

    $success = $request('POST', '/login.php', $fields);
    $authenticatedCookie = $cookie;
    $check($success['status'] === 303 && in_array('Location: /camedit.php', $success['headers'], true),
        'Une authentification valide doit rediriger vers l’éditeur.');
    $check($authenticatedCookie !== '' && $authenticatedCookie !== $anonymousCookie,
        'Une authentification réussie doit envoyer un nouvel identifiant de session.');
    $check($request('GET', '/camedit.php')['status'] === 200,
        'Le nouveau cookie doit donner accès à l’éditeur.');
    $settings = $request('GET', '/settings.php');
    $check($settings['status'] === 200 && str_contains($settings['body'], 'value="' . $login . '"'),
        'La nouvelle session doit être associée au bon compte.');

    $cookie = $anonymousCookie;
    $oldSession = $request('GET', '/camedit.php');
    $check($oldSession['status'] === 303 && in_array('Location: /login.php', $oldSession['headers'], true),
        'Réutiliser le cookie anonyme ne doit jamais donner accès au compte connecté.');
    $replay = $request('POST', '/login.php', $fields);
    $check($replay['status'] === 403,
        'Le jeton CSRF de l’ancienne session détruite ne doit plus être accepté.');

    $cookie = $authenticatedCookie;
    $check($request('GET', '/camedit.php')['status'] === 200,
        'La réutilisation de l’ancien cookie ne doit pas déconnecter la nouvelle session.');

    echo $checks . " vérifications réussies (connexion, renouvellement et isolation des sessions).\n";
} finally {
    $delete = $database->prepare('DELETE FROM users WHERE id = :id');
    foreach ($ids as $id) {
        $delete->execute(['id' => $id]);
    }
}
