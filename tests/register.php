<?php
declare(strict_types=1);

// Test HTTP + PostgreSQL : docker compose exec -T web php tests/register.php
// Les comptes créés par ce test sont supprimés dans le bloc finally.
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Repositories/UserRepository.php';

use Camagru\Database;
use Camagru\Repositories\UserRepository;

$database = Database::connect();
$suffix = bin2hex(random_bytes(8));
$login = 'registration_test_' . $suffix;
$email = $login . '@example.test';
$quotedLogin = $login . "'--";
$cookie = '';
$baseUrl = rtrim(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1', '/');
$checks = 0;

$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};

$request = static function (string $method, ?array $fields = null) use ($baseUrl, &$cookie): array {
    $handle = curl_init($baseUrl . '/register.php');
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIE => $cookie,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$cookie): int {
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
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body];
};

$getToken = static function (array $response): string {
    if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $response['body'], $match)) {
        throw new RuntimeException('Jeton CSRF absent du formulaire.');
    }
    return $match[1];
};

$findUser = static function (string $name) use ($database): array|false {
    $query = $database->prepare('SELECT id, login, email, password FROM users WHERE login = :login');
    $query->execute(['login' => $name]);
    return $query->fetch();
};

try {
    $response = $request('GET');
    $check($response['status'] === 200, 'Le formulaire doit être accessible.');
    $token = $getToken($response);
    $password = 'ééééééééé1'; // Exactement 10 caractères, plus de 10 octets.
    $fields = [
        'csrf_token' => $token,
        'login' => $login,
        'email' => $email,
        'password' => $password,
        'confirm_password' => $password,
    ];

    $response = $request('POST', array_replace($fields, ['csrf_token' => 'incorrect']));
    $check($response['status'] === 403, 'Un jeton CSRF incorrect doit être refusé.');

    $invalidCases = [
        ['password' => 'abcdefgh1', 'confirm_password' => 'abcdefgh1'],
        ['password' => 'abcdefghij', 'confirm_password' => 'abcdefghij'],
        ['password' => 'éééééééé1', 'confirm_password' => 'éééééééé1'],
        ['confirm_password' => 'different1'],
        ['email' => 'pas-un-email'],
        ['login' => '   '],
        ['login' => ['unexpected']],
        ['email' => ['unexpected']],
        ['password' => ['unexpected']],
        ['confirm_password' => ['unexpected']],
    ];
    foreach ($invalidCases as $index => $invalid) {
        $response = $request('POST', array_replace($fields, $invalid));
        $check($response['status'] === 422, 'Le cas invalide ' . $index . ' doit être refusé.');
        $check($findUser($login) === false, 'Une demande invalide ne doit créer aucun compte.');
    }

    $response = $request('POST', array_replace($fields, [
        'login' => '<script>alert("test")</script>',
        'email' => 'invalide',
    ]));
    $check(!str_contains($response['body'], '<script>alert("test")</script>'), 'Les champs réaffichés doivent être échappés.');
    $check(str_contains($response['body'], '&lt;script&gt;'), 'Le login doit être conservé sous forme de texte.');

    $response = $request('POST', array_replace($fields, [
        'login' => ' ' . $login . ' ',
        'email' => ' ' . strtoupper($email) . ' ',
    ]));
    $check($response['status'] === 303, 'Une inscription valide doit rediriger après insertion.');
    $user = $findUser($login);
    $check($user !== false && (int) $user['id'] > 0, 'Le compte doit avoir un identifiant généré.');
    $check($user['email'] === $email, 'L’email doit être normalisé.');
    $check($user['password'] !== $password, 'Le mot de passe brut ne doit pas être stocké.');
    $check(password_verify($password, $user['password']), 'Le hash doit correspondre au mot de passe.');
    $check(password_get_info($user['password'])['algoName'] === 'argon2id', 'Le hash doit utiliser Argon2id.');
    $check(!password_verify('incorrect1', $user['password']), 'Un autre mot de passe ne doit pas correspondre.');

    $response = $request('GET');
    $check(str_contains($response['body'], 'Ton compte a bien été créé.'), 'Le succès doit être affiché.');
    $newToken = $getToken($response);
    $check($newToken !== $token, 'Le jeton doit changer après inscription.');
    $check($request('POST', $fields)['status'] === 403, 'Un ancien jeton doit être refusé.');
    $fields['csrf_token'] = $newToken;
    $response = $request('GET');
    $check(!str_contains($response['body'], 'Ton compte a bien été créé.'), 'Recharger ne doit pas répéter le succès.');

    $response = $request('POST', array_replace($fields, [
        'login' => strtoupper($login),
        'email' => 'other_' . $email,
    ]));
    $check($response['status'] === 422 && str_contains($response['body'], 'Ce login est déjà utilisé.'), 'Le login doit être unique sans distinction de casse.');
    $response = $request('POST', array_replace($fields, [
        'login' => $login . '_other',
        'email' => strtoupper($email),
    ]));
    $check($response['status'] === 422 && str_contains($response['body'], 'Cette adresse email est déjà utilisée.'), 'L’email doit être unique sans distinction de casse.');

    // Vérifier les contraintes SQL indépendamment du contrôle préalable en PHP.
    $repository = new UserRepository($database);
    foreach ([
        [strtoupper($login), 'other_' . $email],
        [$login . '_other', strtoupper($email)],
    ] as [$duplicateLogin, $duplicateEmail]) {
        $database->beginTransaction();
        try {
            $repository->create($duplicateLogin, $duplicateEmail, $user['password']);
            $check(false, 'PostgreSQL doit refuser les doublons même sans validation PHP.');
        } catch (PDOException $exception) {
            $check($exception->getCode() === '23505', 'Une contrainte d’unicité doit bloquer le doublon.');
        } finally {
            $database->rollBack();
        }
    }

    $longPassword = str_repeat('a', 100) . '1';
    $response = $request('POST', array_replace($fields, [
        'login' => $quotedLogin,
        'email' => 'quoted_' . $email,
        'password' => $longPassword,
        'confirm_password' => $longPassword,
    ]));
    $check($response['status'] === 303, 'Une apostrophe dans le login doit rester une donnée SQL.');
    $quotedUser = $findUser($quotedLogin);
    $check($quotedUser !== false && password_verify($longPassword, $quotedUser['password']), 'Un mot de passe long doit être conservé entièrement dans le hash.');
    $check(!password_verify(str_repeat('a', 100) . '2', $quotedUser['password']), 'Le hash ne doit pas tronquer les mots de passe longs.');
    $check($request('PUT')['status'] === 405, 'Seules les méthodes GET et POST doivent être acceptées.');

    echo $checks . " vérifications réussies (HTTP, validation, hachage et contraintes SQL).\n";
} finally {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    // Le préfixe aléatoire isole les données de ce test des comptes du projet.
    $cleanup = $database->prepare('DELETE FROM users WHERE starts_with(lower(login), :prefix)');
    $cleanup->execute(['prefix' => $login]);
}
