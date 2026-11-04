<?php
declare(strict_types=1);

// docker compose exec -T web php tests/overlays.php
// Le compte, ses captures et les fichiers de stock temporaires sont supprimés à la fin.
require_once dirname(__DIR__) . '/src/Database.php';

use Camagru\Database;

$database = null;
$userId = null;
$directory = dirname(__DIR__) . '/storage/calcs';
if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
    throw new RuntimeException('Impossible de créer le dossier des calques de test.');
}
$name = 'overlay_test_' . bin2hex(random_bytes(8)) . '.png';
$path = $directory . '/' . $name;
$invalidName = 'invalid_' . $name;
$linkName = 'linked_' . $name;
$overlay = imagecreatetruecolor(2, 2);
imagealphablending($overlay, false);
imagesavealpha($overlay, true);
imagefill($overlay, 0, 0, imagecolorallocatealpha($overlay, 0, 0, 0, 127));
imagesetpixel($overlay, 0, 0, imagecolorallocatealpha($overlay, 255, 0, 0, 0));
imagesetpixel($overlay, 1, 1, imagecolorallocatealpha($overlay, 0, 0, 255, 0));
imagesetpixel($overlay, 0, 1, imagecolorallocatealpha($overlay, 255, 255, 255, 64));
imagepng($overlay, $path);
file_put_contents($directory . '/' . $invalidName, 'not a PNG');
symlink($name, $directory . '/' . $linkName);

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$baseUrl = rtrim(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1', '/');
$cookie = '';
$request = static function (string $method, string $url, ?array $fields = null) use ($baseUrl, &$cookie): array {
    $handle = curl_init($baseUrl . $url);
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
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'type' => curl_getinfo($handle, CURLINFO_CONTENT_TYPE)];
};

try {
    $page = $request('GET', '/camedit.php');
    $check($page['status'] === 200 && str_contains($page['body'], 'value="' . $name . '"'), 'L’éditeur doit proposer les calques du stock.');
    $thumbnail = $request('GET', '/overlay.php?name=' . rawurlencode($name));
    $check($thumbnail['status'] === 200 && $thumbnail['type'] === 'image/png', 'Le calque doit être accessible comme vignette PNG.');
    $check(imagecreatefromstring($thumbnail['body']) !== false, 'La vignette doit contenir une image valide.');
    $check(!str_contains($page['body'], 'value="' . $invalidName . '"') && !str_contains($page['body'], 'value="' . $linkName . '"'), 'Le catalogue doit exclure les faux PNG et les liens symboliques.');
    foreach (['../.gitkeep', $invalidName, $linkName, 'unknown.png'] as $invalidNameRequest) {
        $response = $request('GET', '/overlay.php?name=' . rawurlencode($invalidNameRequest));
        $check($response['status'] === 404, 'Les fichiers hors catalogue doivent être inaccessibles.');
    }
    $check($request('GET', '/overlay.php?name[]=unexpected')['status'] === 404, 'La vignette doit refuser un nom sous forme de tableau.');
    $check($request('POST', '/overlay.php?name=' . rawurlencode($name))['status'] === 405, 'La vignette doit refuser les méthodes non prévues.');

    $database = Database::connect();
    $login = pathinfo($name, PATHINFO_FILENAME);
    $password = 'Overlay-password-2026!';
    $insert = $database->prepare('INSERT INTO users (login, email, password, activation) VALUES (:login, :email, :password, TRUE) RETURNING id');
    $insert->execute(['login' => $login, 'email' => $login . '@example.test', 'password' => password_hash($password, PASSWORD_ARGON2ID)]);
    $userId = (int) $insert->fetchColumn();
    $insert->closeCursor();
    $loginPage = $request('GET', '/login.php');
    $check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $loginPage['body'], $token) === 1, 'Le formulaire de connexion doit fournir son CSRF.');
    $loggedIn = $request('POST', '/login.php', ['login' => $login, 'password' => $password, 'csrf_token' => $token[1]]);
    $check($loggedIn['status'] === 303, 'Le compte de test doit pouvoir se connecter.');
    $page = $request('GET', '/camedit.php');
    $check(preg_match('/data-csrf-token="([a-f0-9]{64})"/', $page['body'], $token) === 1, 'L’éditeur doit fournir son CSRF.');
    $capture = imagecreatetruecolor(4, 4);
    imagefill($capture, 0, 0, imagecolorallocate($capture, 0, 255, 0));
    ob_start();
    imagepng($capture);
    $image = 'data:image/png;base64,' . base64_encode(ob_get_clean());
    $fields = ['image' => $image, 'csrf_token' => $token[1]];
    $missing = $request('POST', '/camedit.php', $fields);
    $check($missing['status'] === 422, 'Une capture sans calque doit être refusée.');
    foreach (['', 'unknown.png', '../' . $name, [$name]] as $invalidOverlay) {
        $response = $request('POST', '/camedit.php', $fields + ['overlay' => $invalidOverlay]);
        $check($response['status'] === 422, 'La capture doit refuser un calque hors catalogue ou mal formé.');
    }
    $validFields = $fields + ['overlay' => $name];
    $check($request('POST', '/camedit.php', array_replace($validFields, ['csrf_token' => 'incorrect']))['status'] === 403, 'La protection CSRF doit rester active.');
    $authenticatedCookie = $cookie;
    $cookie = '';
    $check($request('POST', '/camedit.php', $validFields)['status'] === 401, 'Les captures doivent rester réservées aux utilisateurs connectés.');
    $cookie = $authenticatedCookie;
    $check($request('POST', '/camedit.php', array_replace($validFields, ['image' => 'data:image/png;base64,bm90IGFuIGltYWdl']))['status'] === 422, 'La capture doit toujours être une véritable image PNG.');
    $saved = $request('POST', '/camedit.php', $fields + ['overlay' => $name]);
    $result = json_decode($saved['body'], true, flags: JSON_THROW_ON_ERROR);
    $check($saved['status'] === 201 && isset($result['file_key'], $result['image']), 'Le serveur doit enregistrer le montage et retourner son aperçu.');
    $composite = imagecreatefromstring(base64_decode(substr($result['image'], strlen('data:image/png;base64,')), true));
    $check($composite !== false && imagesx($composite) === 4 && imagesy($composite) === 4, 'Le montage doit conserver la taille de la capture.');
    $check((imagecolorat($composite, 0, 0) & 0xffffff) === 0xff0000, 'Le pixel rouge du calque doit recouvrir la capture.');
    $check((imagecolorat($composite, 3, 3) & 0xffffff) === 0x0000ff, 'Le calque doit être redimensionné jusqu’au bord opposé de la capture.');
    $check((imagecolorat($composite, 3, 0) & 0xffffff) === 0x00ff00, 'La transparence du calque doit laisser apparaître la capture.');
    $blended = imagecolorsforindex($composite, imagecolorat($composite, 0, 3));
    $check($blended['green'] === 255 && $blended['red'] >= 125 && $blended['red'] <= 129 && $blended['blue'] >= 125 && $blended['blue'] <= 129, 'Un pixel blanc à moitié transparent doit se mélanger avec la capture verte.');
    echo $checks . " vérifications réussies (stock, vignettes et montage serveur).\n";
} finally {
    if ($userId !== null) {
        $delete = $database->prepare('DELETE FROM images WHERE user_id = :id RETURNING file_key');
        $delete->execute(['id' => $userId]);
        foreach ($delete->fetchAll(PDO::FETCH_COLUMN) as $key) {
            @unlink(dirname(__DIR__) . '/storage/cam_images/' . $key . '.png');
        }
        $delete = $database->prepare('DELETE FROM users WHERE id = :id');
        $delete->execute(['id' => $userId]);
    }
    unlink($path);
    unlink($directory . '/' . $invalidName);
    unlink($directory . '/' . $linkName);
}
