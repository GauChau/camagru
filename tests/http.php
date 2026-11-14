<?php
declare(strict_types=1);

// docker compose exec -T web php tests/http.php
// Vérifications HTTP sans création de compte, de montage ou d'email.
$baseUrl = rtrim(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1', '/');
$cookie = '';
$checks = 0;

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
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$cookie, &$headers): int {
            if (preg_match('/^Set-Cookie:\s*(PHPSESSID=[^;]+)/i', $line, $match)) {
                $cookie = $match[1];
            }
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }

            return strlen($line);
        },
    ]);
    if ($fields !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = curl_exec($handle);
    if ($body === false) {
        throw new RuntimeException('Requête HTTP impossible : ' . curl_error($handle));
    }

    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'headers' => $headers, 'body' => $body];
};

$forms = ['/login.php', '/register.php', '/activate.php', '/pwdchange.php'];
$tokens = [];
foreach ($forms as $path) {
    $page = $request('GET', $path);
    $check($page['status'] === 200, $path . ' doit afficher son formulaire.');
    $check(substr_count(strtolower($page['body']), '<!doctype html>') === 1
        && substr_count($page['body'], 'class="site-footer"') === 1,
        $path . ' doit recevoir une seule enveloppe HTML.');
    $check(($page['headers']['cache-control'] ?? '') === 'no-store', 'Les pages de session ne doivent pas être mises en cache.');
    $check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $page['body'], $match) === 1,
        $path . ' doit afficher un jeton CSRF.');
    $tokens[$path] = $match[1];

    foreach ([[], ['csrf_token' => ''], ['csrf_token' => ['invalid']]] as $fields) {
        $response = $request('POST', $path, $fields);
        $check($response['status'] === 403, $path . ' doit refuser un jeton absent, vide ou non textuel.');
        $check(!str_contains($response['body'], '<b>Warning</b>')
            && !str_contains($response['body'], '<b>Fatal error</b>'), 'Le refus CSRF doit rester une réponse normale.');
    }

    $again = $request('GET', $path);
    $check(str_contains($again['body'], 'value="' . $tokens[$path] . '"'),
        'Afficher à nouveau le formulaire ne doit pas invalider les autres onglets.');
}

foreach ($forms as $index => $path) {
    $other = $forms[($index + 1) % count($forms)];
    $response = $request('POST', $path, ['csrf_token' => $tokens[$other]]);
    $check($response['status'] === 403, 'Un jeton provenant d’un autre formulaire doit être refusé.');
}

$cookie = '';
$response = $request('POST', '/login.php', ['csrf_token' => $tokens['/login.php']]);
$check($response['status'] === 403, 'Le jeton d’une autre session ne doit pas être accepté.');
$response = $request('POST', '/recovery.php', ['csrf_token' => ['invalid']]);
$check($response['status'] === 403, 'La réinitialisation doit refuser un jeton CSRF non textuel.');
$check(($response['headers']['referrer-policy'] ?? '') === 'no-referrer', 'La récupération doit conserver sa politique de confidentialité.');
$response = $request('GET', '/activate.php');
$check(($response['headers']['referrer-policy'] ?? '') === 'no-referrer', 'L’activation doit conserver sa politique de confidentialité.');

foreach (array_merge($forms, ['/gallery.php', '/camedit.php', '/recovery.php', '/logout.php', '/image.php', '/overlay.php']) as $path) {
    $response = $request('PUT', $path);
    $allowed = match ($path) {
        '/logout.php' => 'POST',
        '/image.php', '/overlay.php' => 'GET',
        default => 'GET, POST',
    };
    $check($response['status'] === 405 && ($response['headers']['allow'] ?? '') === $allowed,
        $path . ' doit annoncer ses méthodes HTTP autorisées.');
}

foreach (['/index.php', '/gallery.php'] as $path) {
    $page = $request('GET', $path);
    $check($page['status'] === 200
        && substr_count(strtolower($page['body']), '<!doctype html>') === 1
        && substr_count($page['body'], 'class="site-footer"') === 1,
        $path . ' doit s’afficher avec une seule enveloppe HTML.');
}
foreach (['/settings.php' => '/login.php', '/profile.php' => '/index.php'] as $path => $target) {
    $response = $request('GET', $path);
    $check($response['status'] === 303 && ($response['headers']['location'] ?? '') === $target,
        $path . ' doit rediriger les visiteurs anonymes vers ' . $target . '.');
}

$response = $request('GET', '/camedit.php');
$check($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/login.php',
    'L’éditeur doit rediriger les visiteurs vers la connexion.');
$response = $request('POST', '/camedit.php', []);
$check($response['status'] === 401
    && ($response['headers']['content-type'] ?? '') === 'application/json; charset=utf-8'
    && is_string(json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR)['error'] ?? null),
    'Un enregistrement anonyme doit recevoir une erreur JSON, sans page HTML.');
$response = $request('POST', '/logout.php', ['csrf_token' => $tokens['/login.php']]);
$check($response['status'] === 403, 'La déconnexion ne doit pas créer un jeton pour valider son POST.');

// Le service des fichiers ne doit pas créer de session ni inclure le layout HTML.
$cookie = '';
foreach (['/image.php', '/overlay.php'] as $path) {
    $response = $request('GET', $path);
    $check($response['status'] === 404 && !isset($response['headers']['set-cookie'])
        && ($response['headers']['x-content-type-options'] ?? '') === 'nosniff'
        && !str_contains($response['body'], '<!doctype html>'), 'Les endpoints d’images doivent rester indépendants du rendu des pages.');
}

echo $checks . " vérifications réussies (rendu, CSRF, sessions, méthodes HTTP et réponses JSON).\n";
