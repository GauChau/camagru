<?php
declare(strict_types=1);

// docker compose exec -T web php tests/settings.php
// Seuls les deux comptes temporaires créés ici sont modifiés puis supprimés.
require_once dirname(__DIR__) . '/src/Database.php';

use Camagru\Database;

$database = Database::connect();
$suffix = bin2hex(random_bytes(8));
$login = 'settings_test_' . $suffix;
$email = $login . '@example.test';
$otherLogin = $login . '_other';
$otherEmail = $otherLogin . '@example.test';
$password = 'Current-password-2026!';
$newPassword = 'New-password-2026!';
$cookie = '';
$ids = [];
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

$csrf = static function (array $response): string {
    $html = $response['body'];
    if (preg_match('~<form[^>]+action="/settings.php"[^>]*>(.*?)</form>~s', $html, $form)) {
        $html = $form[1];
    }
    if (!preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $html, $matches)) {
        throw new RuntimeException('Jeton CSRF absent.');
    }
    return $matches[1];
};

$findUser = static function (int $id) use ($database): array|false {
    $query = $database->prepare('SELECT id, login, email, password FROM users WHERE id = :id');
    $query->execute(['id' => $id]);
    return $query->fetch();
};

try {
    foreach (['GET', 'POST'] as $method) {
        $response = $request($method, '/settings.php', $method === 'POST' ? ['email' => $email] : null);
        $check($response['status'] === 303 && in_array('Location: /login.php', $response['headers'], true), 'Les visiteurs doivent être redirigés vers la connexion.');
    }
    $response = $request('GET', '/');
    $check(!str_contains($response['body'], 'class="settings-link"'), 'L’engrenage doit être masqué aux visiteurs.');

    $insert = $database->prepare('INSERT INTO users (login, email, password, activation) VALUES (:login, :email, :password, TRUE) RETURNING id');
    foreach ([[$login, $email], [$otherLogin, $otherEmail]] as [$name, $address]) {
        $insert->execute(['login' => $name, 'email' => $address, 'password' => password_hash($password, PASSWORD_ARGON2ID)]);
        $ids[] = (int) $insert->fetchColumn();
        $insert->closeCursor();
    }
    $original = $findUser($ids[0]);
    $otherOriginal = $findUser($ids[1]);
    $response = $request('GET', '/login.php');
    $response = $request('POST', '/login.php', ['login' => $login, 'password' => $password, 'csrf_token' => $csrf($response)]);
    $check($response['status'] === 303, 'Le compte de test doit pouvoir se connecter.');

    $response = $request('GET', '/settings.php');
    $check($response['status'] === 200, 'Les paramètres doivent être accessibles après connexion.');
    $check(in_array('Cache-Control: no-store', $response['headers'], true), 'La page ne doit pas être mise en cache.');
    $check(str_contains($response['body'], 'aria-label="User settings"'), 'Le header doit contenir un engrenage accessible.');
    $check(str_contains($response['body'], 'value="' . $login . '"') && str_contains($response['body'], 'value="' . $email . '"'), 'Le formulaire doit afficher les données du compte connecté.');
    $check(!str_contains($response['body'], $original['password']), 'Le hash ne doit jamais être envoyé au navigateur.');
    $fields = ['csrf_token' => $csrf($response), 'login' => $login, 'email' => $email, 'current_password' => $password, 'password' => '', 'confirm_password' => ''];
    $response = $request('POST', '/settings.php', array_replace($fields, ['csrf_token' => 'incorrect']));
    $check($response['status'] === 403 && $findUser($ids[0]) === $original, 'Un CSRF incorrect ne doit rien modifier.');

    $invalidCases = [
        ['login' => '   '],
        ['login' => ['unexpected']],
        ['email' => 'invalide'],
        ['email' => ['unexpected']],
        ['current_password' => ''],
        ['current_password' => 'incorrect'],
        ['current_password' => ['unexpected']],
        ['password' => ['unexpected']],
        ['confirm_password' => ['unexpected']],
        ['password' => 'abcdefgh1', 'confirm_password' => 'abcdefgh1'],
        ['password' => 'abcdefghij', 'confirm_password' => 'abcdefghij'],
        ['password' => 'éééééééé1', 'confirm_password' => 'éééééééé1'],
        ['password' => $newPassword, 'confirm_password' => 'different'],
        ['password' => $newPassword],
        ['confirm_password' => $newPassword],
        ['login' => ' ' . strtoupper($otherLogin) . ' '],
        ['email' => ' ' . strtoupper($otherEmail) . ' '],
        ['login' => $otherLogin, 'password' => $newPassword, 'confirm_password' => $newPassword],
    ];
    foreach ($invalidCases as $index => $invalid) {
        $response = $request('POST', '/settings.php', array_replace($fields, $invalid));
        $check($response['status'] === 422, 'Le cas invalide ' . $index . ' doit être refusé.');
        $check($findUser($ids[0]) === $original && $findUser($ids[1]) === $otherOriginal, 'Une demande invalide ne doit modifier aucun compte.');
        $check(!str_contains($response['body'], 'value="' . $password . '"') && !str_contains($response['body'], 'value="' . $newPassword . '"'), 'Les mots de passe ne doivent pas être réaffichés.');
    }
    $response = $request('POST', '/settings.php', array_replace($fields, ['login' => '<script>alert("test")</script>', 'email' => 'invalide']));
    $check(!str_contains($response['body'], '<script>alert("test")</script>') && str_contains($response['body'], '&lt;script&gt;'), 'Les champs réaffichés doivent être échappés.');

    // Conserver ses propres identifiants ne constitue pas un doublon.
    $response = $request('POST', '/settings.php', $fields);
    $check($response['status'] === 303 && $findUser($ids[0]) === $original, 'Sauvegarder sans changer le mot de passe doit conserver son hash.');
    $oldCsrf = $fields['csrf_token'];
    $response = $request('GET', '/settings.php');
    $check(str_contains($response['body'], 'Your changes have been saved.'), 'Le succès doit être affiché après redirection.');
    $fields['csrf_token'] = $csrf($response);
    $check($oldCsrf !== $fields['csrf_token'], 'Le CSRF doit être renouvelé après sauvegarde.');
    $check($request('POST', '/settings.php', array_replace($fields, ['csrf_token' => $oldCsrf]))['status'] === 403, 'L’ancien CSRF doit être refusé.');
    $check(!str_contains($request('GET', '/settings.php')['body'], 'Your changes have been saved.'), 'Le succès ne doit pas être répété au rechargement.');

    $newLogin = $login . "'updated";
    $newEmail = 'updated_' . $email;
    $response = $request('POST', '/settings.php', array_replace($fields, [
        'login' => ' ' . $newLogin . ' ',
        'email' => ' ' . strtoupper($newEmail) . ' ',
        'user_id' => $ids[1],
        'id' => $ids[1],
    ]));
    $check($response['status'] === 303, 'Le login et l’email doivent pouvoir être modifiés.');
    $updated = $findUser($ids[0]);
    $check($updated['login'] === $newLogin && $updated['email'] === $newEmail, 'Les données modifiées doivent être normalisées et enregistrées.');
    $check($updated['password'] === $original['password'], 'Des champs de nouveau mot de passe vides doivent conserver le hash.');
    $check($findUser($ids[1]) === $otherOriginal, 'Un identifiant envoyé en POST ne doit pas permettre de modifier un autre compte.');
    $response = $request('GET', '/settings.php');
    $fields = array_replace($fields, ['csrf_token' => $csrf($response), 'login' => $newLogin, 'email' => $newEmail, 'password' => $newPassword, 'confirm_password' => $newPassword]);
    $check($request('POST', '/settings.php', $fields)['status'] === 303, 'Le changement de mot de passe doit réussir.');
    $updated = $findUser($ids[0]);
    $check(password_verify($newPassword, $updated['password']) && !password_verify($password, $updated['password']), 'Seul le nouveau mot de passe doit correspondre au hash.');
    $check(password_get_info($updated['password'])['algoName'] === 'argon2id', 'Le nouveau mot de passe doit être haché avec Argon2id.');
    $check($request('PUT', '/settings.php')['status'] === 405, 'Une méthode inattendue doit être refusée.');

    $response = $request('GET', '/');
    $logoutToken = $csrf($response);
    $check($request('POST', '/logout.php', ['csrf_token' => $logoutToken])['status'] === 303, 'La déconnexion doit réussir.');
    $response = $request('GET', '/login.php');
    $loginToken = $csrf($response);
    foreach ([[$login, $password], [$newLogin, $password]] as [$name, $attempt]) {
        $response = $request('POST', '/login.php', ['login' => $name, 'password' => $attempt, 'csrf_token' => $loginToken]);
        $check($response['status'] === 422, 'Les anciens identifiants doivent être refusés.');
    }
    $response = $request('POST', '/login.php', ['login' => $newLogin, 'password' => $newPassword, 'csrf_token' => $loginToken]);
    $check($response['status'] === 303, 'Les nouveaux identifiants doivent permettre la connexion.');
    $response = $request('GET', '/settings.php');
    $check($response['status'] === 200 && str_contains($response['body'], $newEmail), 'La session doit toujours correspondre au même compte après modification.');

    echo $checks . " vérifications réussies (accès, CSRF, validation, doublons, isolation, sauvegarde et reconnexion).\n";
} finally {
    $delete = $database->prepare('DELETE FROM users WHERE id = :id');
    foreach ($ids as $id) {
        $delete->execute(['id' => $id]);
    }
}
