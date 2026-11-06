<?php
declare(strict_types=1);

// docker compose exec -T web php tests/overlays.php
// Le compte, ses captures et les fichiers de stock temporaires sont supprimés à la fin.
require_once dirname(__DIR__) . '/src/Database.php';

use Camagru\Database;

$database = null;
$userId = null;
$otherUserId = null;
$uploadPaths = [];
$directory = dirname(__DIR__) . '/storage/calcs';
if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
    throw new RuntimeException('Impossible de créer le dossier des calques de test.');
}
$name = 'overlay_test_' . bin2hex(random_bytes(8)) . '.png';
$path = $directory . '/' . $name;
$secondName = 'second_' . $name;
$secondPath = $directory . '/' . $secondName;
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
$secondOverlay = imagecreatetruecolor(2, 2);
imagealphablending($secondOverlay, false);
imagesavealpha($secondOverlay, true);
imagefill($secondOverlay, 0, 0, imagecolorallocatealpha($secondOverlay, 0, 0, 0, 127));
imagesetpixel($secondOverlay, 0, 0, imagecolorallocatealpha($secondOverlay, 255, 255, 0, 0));
imagesetpixel($secondOverlay, 1, 0, imagecolorallocatealpha($secondOverlay, 255, 0, 255, 0));
imagesetpixel($secondOverlay, 0, 1, imagecolorallocatealpha($secondOverlay, 0, 0, 0, 64));
imagepng($secondOverlay, $secondPath);
$jpegNames = [];
$jpegOverlay = imagecreatetruecolor(80, 40);
imagefill($jpegOverlay, 0, 0, imagecolorallocate($jpegOverlay, 255, 255, 0));
foreach (['jpg', 'jpeg', 'JPG'] as $index => $extension) {
    $jpegName = pathinfo($name, PATHINFO_FILENAME) . '_' . $index . '.' . $extension;
    $jpegNames[] = $jpegName;
    imagejpeg($jpegOverlay, $directory . '/' . $jpegName, 100);
    $uploadPaths[] = $directory . '/' . $jpegName;
}
$invalidJpegNames = [];
foreach (['fake' => 'not a JPEG', 'mismatched' => file_get_contents($path),
    'truncated' => "\xff\xd8\xff", 'oversized' => file_get_contents($directory . '/' . $jpegNames[0]) . str_repeat('x', 5 * 1024 * 1024)] as $prefix => $contents) {
    $invalidJpegName = $prefix . '_' . pathinfo($name, PATHINFO_FILENAME) . '.jpg';
    file_put_contents($directory . '/' . $invalidJpegName, $contents);
    $invalidJpegNames[] = $invalidJpegName;
    $uploadPaths[] = $directory . '/' . $invalidJpegName;
}
$linkedJpegName = 'linked_' . $jpegNames[0];
symlink($jpegNames[0], $directory . '/' . $linkedJpegName);
$invalidJpegNames[] = $linkedJpegName;
$uploadPaths[] = $directory . '/' . $linkedJpegName;
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
        // cURL exige des champs scalaires pour les formulaires multipart.
        if (($fields['photo'] ?? null) instanceof CURLFile && isset($fields['overlays']) && is_array($fields['overlays'])) {
            foreach ($fields['overlays'] as $index => $value) {
                $fields['overlays[' . $index . ']'] = $value;
            }
            unset($fields['overlays']);
        }
        curl_setopt($handle, CURLOPT_POSTFIELDS, ($fields['photo'] ?? null) instanceof CURLFile ? $fields : http_build_query($fields));
    }
    $body = curl_exec($handle);
    if ($body === false) {
        throw new RuntimeException('Requête HTTP impossible : ' . curl_error($handle));
    }
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'type' => curl_getinfo($handle, CURLINFO_CONTENT_TYPE)];
};

try {
    $page = $request('GET', '/camedit.php');
    $check($page['status'] === 303 && !str_contains($page['body'], 'photos-panel'), 'Un visiteur doit être redirigé avant de voir l’éditeur.');
    $thumbnail = $request('GET', '/overlay.php?name=' . rawurlencode($name));
    $check($thumbnail['status'] === 200 && $thumbnail['type'] === 'image/png', 'Le calque doit être accessible comme vignette PNG.');
    $check(imagecreatefromstring($thumbnail['body']) !== false, 'La vignette doit contenir une image valide.');
    foreach ($jpegNames as $jpegName) {
        $jpegThumbnail = $request('GET', '/overlay.php?name=' . rawurlencode($jpegName));
        $check($jpegThumbnail['status'] === 200 && $jpegThumbnail['type'] === 'image/jpeg'
            && getimagesizefromstring($jpegThumbnail['body'])[2] === IMAGETYPE_JPEG,
            'Les extensions jpg, jpeg et JPG sont servies avec leur véritable type JPEG.');
    }
    foreach ($invalidJpegNames as $invalidJpegName) {
        $check($request('GET', '/overlay.php?name=' . rawurlencode($invalidJpegName))['status'] === 404,
            'Les faux JPEG, extensions trompeuses, fichiers tronqués, trop volumineux et liens symboliques sont exclus.');
    }
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
    $insert->execute(['login' => $login . '_other', 'email' => $login . '_other@example.test', 'password' => password_hash($password, PASSWORD_ARGON2ID)]);
    $otherUserId = (int) $insert->fetchColumn();
    $insert->closeCursor();
    $loginPage = $request('GET', '/login.php');
    $check(preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $loginPage['body'], $token) === 1, 'Le formulaire de connexion doit fournir son CSRF.');
    $loggedIn = $request('POST', '/login.php', ['login' => $login, 'password' => $password, 'csrf_token' => $token[1]]);
    $check($loggedIn['status'] === 303, 'Le compte de test doit pouvoir se connecter.');
    $page = $request('GET', '/camedit.php');
    $check($page['status'] === 200 && str_contains($page['body'], 'value="' . $name . '"'), 'L’éditeur doit proposer les calques du stock après connexion.');
    $check(str_contains($page['body'], 'type="checkbox" name="overlays[]"') && str_contains($page['body'], 'value="' . $secondName . '"'), 'Plusieurs stickers doivent pouvoir être cochés dans l’éditeur.');
    foreach ($jpegNames as $jpegName) {
        $check(str_contains($page['body'], 'value="' . $jpegName . '"'), 'Le catalogue propose aussi les calques JPEG dans l’éditeur.');
    }
    foreach ($invalidJpegNames as $invalidJpegName) {
        $check(!str_contains($page['body'], 'value="' . $invalidJpegName . '"'), 'Un JPEG invalide ne doit pas être proposé dans le catalogue.');
    }
    $check(!str_contains($page['body'], 'value="' . $invalidName . '"') && !str_contains($page['body'], 'value="' . $linkName . '"'), 'Le catalogue doit exclure les faux PNG et les liens symboliques.');
    $check(str_contains($page['body'], 'id="photos-empty"') && !str_contains($page['body'], 'class="photo-thumbnail"'), 'Un nouveau compte voit l’état vide de ses créations.');
    $imageDirectory = dirname(__DIR__) . '/storage/cam_images';
    if (!is_dir($imageDirectory)) mkdir($imageDirectory, 0770, true);
    $insertImage = $database->prepare("INSERT INTO images (user_id, file_key, created_at) VALUES (:user, :key, '2000-01-01 00:00:00+00') RETURNING id");
    $previousIds = [];
    for ($i = 0; $i < 9; $i++) {
        $key = bin2hex(random_bytes(32));
        file_put_contents($imageDirectory . '/' . $key . '.png', file_get_contents($path));
        $insertImage->execute(['user' => $i === 8 ? $otherUserId : $userId, 'key' => $key]);
        $id = (int) $insertImage->fetchColumn();
        $insertImage->closeCursor();
        if ($i === 8) {
            $otherImageId = $id;
        } else {
            $previousIds[] = $id;
        }
    }
    $page = $request('GET', '/camedit.php?user_id=' . $otherUserId);
    preg_match_all('/class="photo-thumbnail" href="\/image.php\?id=(\d+)"/', $page['body'], $thumbnails);
    $check(array_map('intval', $thumbnails[1]) === array_reverse($previousIds), 'La section latérale contient tous les montages du compte connecté, dans un ordre stable du plus récent au plus ancien.');
    $check(!str_contains($page['body'], '/image.php?id=' . $otherImageId . '"'), 'Un identifiant dans l’URL ne permet pas de remplacer l’historique par celui d’un autre compte.');
    $check(preg_match('/data-csrf-token="([a-f0-9]{64})"/', $page['body'], $token) === 1, 'L’éditeur doit fournir son CSRF.');
    $capture = imagecreatetruecolor(4, 4);
    imagefill($capture, 0, 0, imagecolorallocate($capture, 0, 255, 0));
    ob_start();
    imagepng($capture);
    $image = 'data:image/png;base64,' . base64_encode(ob_get_clean());
    $fields = ['image' => $image, 'csrf_token' => $token[1]];
    foreach (['unknown.png', '../' . $name, [$name]] as $invalidOverlay) {
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
    $truncated = 'data:image/png;base64,' . base64_encode(substr(base64_decode(substr($image, strlen('data:image/png;base64,')), true), 0, 33));
    $check($request('POST', '/camedit.php', array_replace($validFields, ['image' => $truncated]))['status'] === 422, 'Un PNG tronqué après son en-tête doit être refusé proprement.');
    $saved = $request('POST', '/camedit.php', $fields + ['overlay' => $name]);
    $result = json_decode($saved['body'], true, flags: JSON_THROW_ON_ERROR);
    $check($saved['status'] === 201 && isset($result['file_key'], $result['image']), 'Le serveur doit enregistrer le montage et retourner son aperçu.');
    $check(isset($result['image_url']) && str_starts_with($result['image_url'], '/image.php?id='), 'La capture retourne une URL persistante pour ajouter sa miniature sans rechargement.');
    $check($request('GET', $result['image_url'])['status'] === 200, 'La miniature nouvellement enregistrée est accessible.');
    $history = $request('GET', '/camedit.php');
    preg_match_all('/class="photo-thumbnail" href="([^\"]+)"/', $history['body'], $thumbnails);
    $check(count($thumbnails[1]) === 9 && $thumbnails[1][0] === $result['image_url'], 'Après rechargement, la nouvelle capture est en tête de tous les montages précédents.');
    foreach ([$fields, $fields + ['overlay' => '']] as $withoutOverlay) {
        $savedWithoutOverlay = $request('POST', '/camedit.php', $withoutOverlay);
        $plainResult = json_decode($savedWithoutOverlay['body'], true, flags: JSON_THROW_ON_ERROR);
        $check($savedWithoutOverlay['status'] === 201 && isset($plainResult['image_url']), 'Une capture accepte un calque absent ou vide.');
        $plainImage = $request('GET', $plainResult['image_url']);
        $decoded = imagecreatefromstring($plainImage['body']);
        $check($plainImage['status'] === 200 && $plainImage['type'] === 'image/png'
            && $decoded !== false && imagesx($decoded) === 4 && imagesy($decoded) === 4
            && (imagecolorat($decoded, 0, 0) & 0xffffff) === 0x00ff00,
            'La capture sans calque est enregistrée en PNG et conserve les pixels de base.');
    }
    $check($request('POST', '/camedit.php', array_replace($fields, ['image' => $truncated]))['status'] === 422, 'Un PNG tronqué reste refusé sans calque.');
    $check(str_contains($history['body'], 'type="file"') && str_contains($history['body'], 'accept="image/png,image/jpeg"'), 'L’éditeur propose une image PNG ou JPEG comme alternative à la webcam.');
    $uploadFields = ['photo' => new CURLFile($path, 'image/png', 'photo.png'), 'overlay' => $name, 'csrf_token' => $token[1]];
    $uploaded = $request('POST', '/camedit.php', $uploadFields);
    $uploadResult = json_decode($uploaded['body'], true, flags: JSON_THROW_ON_ERROR);
    $check($uploaded['status'] === 201 && isset($uploadResult['image_url']), 'Un fichier PNG peut être composé avec un calque sans webcam.');
    $plainUpload = $uploadFields;
    unset($plainUpload['overlay']);
    foreach ([$plainUpload, $plainUpload + ['overlay' => '']] as $withoutOverlay) {
        $uploadedWithoutOverlay = $request('POST', '/camedit.php', $withoutOverlay);
        $plainResult = json_decode($uploadedWithoutOverlay['body'], true, flags: JSON_THROW_ON_ERROR);
        $check($uploadedWithoutOverlay['status'] === 201 && isset($plainResult['image_url']), 'Un import PNG accepte un calque absent ou vide.');
        $plainImage = $request('GET', $plainResult['image_url']);
        $decoded = imagecreatefromstring($plainImage['body']);
        $check($plainImage['status'] === 200 && $decoded !== false && imagesx($decoded) === 2
            && imagesy($decoded) === 2 && imagecolorsforindex($decoded, imagecolorat($decoded, 1, 0))['alpha'] === 127,
            'Le PNG importé sans calque conserve ses dimensions et sa transparence.');
    }
    $check($request('POST', '/camedit.php', array_replace($uploadFields, ['csrf_token' => 'bad']))['status'] === 403, 'L’import de fichier exige un jeton CSRF valide.');
    $authenticatedCookie = $cookie;
    $cookie = '';
    $check($request('POST', '/camedit.php', $uploadFields)['status'] === 401, 'L’import de fichier exige un utilisateur connecté.');
    $cookie = $authenticatedCookie;
    $jpegPath = tempnam(sys_get_temp_dir(), 'camagru_jpeg_');
    $uploadPaths[] = $jpegPath;
    imagejpeg($capture, $jpegPath, 100);
    $plainJpeg = $request('POST', '/camedit.php', array_replace($plainUpload, ['photo' => new CURLFile($jpegPath, 'image/jpeg', 'photo.jpg')]));
    $plainResult = json_decode($plainJpeg['body'], true, flags: JSON_THROW_ON_ERROR);
    $check($plainJpeg['status'] === 201 && isset($plainResult['image_url']), 'Un JPEG importé sans calque peut être enregistré.');
    $plainImage = $request('GET', $plainResult['image_url']);
    $decoded = imagecreatefromstring($plainImage['body']);
    $check($plainImage['status'] === 200 && $plainImage['type'] === 'image/png' && $decoded !== false
        && imagesx($decoded) === 4 && imagesy($decoded) === 4
        && imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0))['green'] >= 250,
        'Le JPEG sans calque est réencodé en PNG en conservant l’image de base.');
    $uploaded = $request('POST', '/camedit.php', array_replace($uploadFields, ['photo' => new CURLFile($jpegPath, 'image/jpeg', 'photo.jpg')]));
    $uploadResult = json_decode($uploaded['body'], true, flags: JSON_THROW_ON_ERROR);
    $check($uploaded['status'] === 201, 'Un fichier JPEG peut être composé avec un calque sans webcam.');
    $composedUpload = $request('GET', $uploadResult['image_url']);
    $decodedUpload = imagecreatefromstring($composedUpload['body']);
    $check($composedUpload['status'] === 200 && $decodedUpload !== false && imagesx($decodedUpload) === 4
        && (imagecolorat($decodedUpload, 0, 0) & 0xffffff) === 0xff0000, 'Le fichier importé est décodé et le calque est ajouté côté serveur dans le PNG enregistré.');
    $history = $request('GET', '/camedit.php');
    $check(str_contains($history['body'], 'class="photo-thumbnail" href="' . $uploadResult['image_url'] . '"'), 'Le montage importé est conservé dans les créations personnelles.');
    $orientationImage = imagecreatetruecolor(80, 40);
    imagefilledrectangle($orientationImage, 0, 0, 39, 19, 0xff0000);
    imagefilledrectangle($orientationImage, 40, 0, 79, 19, 0x00ff00);
    imagefilledrectangle($orientationImage, 0, 20, 39, 39, 0x0000ff);
    imagefilledrectangle($orientationImage, 40, 20, 79, 39, 0xffff00);
    ob_start();
    imagejpeg($orientationImage, null, 100);
    $jpegBytes = ob_get_clean();
    foreach ([1 => 0x00ff00, 2 => 0xff0000, 3 => 0x0000ff, 4 => 0xffff00, 5 => 0x0000ff, 6 => 0xff0000, 7 => 0x00ff00, 8 => 0xffff00] as $orientation => $expectedColor) {
        $exif = "Exif\0\0II*\0" . pack('Vv', 8, 1) . pack('vvVv', 0x112, 3, 1, $orientation) . "\0\0" . pack('V', 0);
        file_put_contents($jpegPath, substr($jpegBytes, 0, 2) . "\xff\xe1" . pack('n', strlen($exif) + 2) . $exif . substr($jpegBytes, 2));
        $uploaded = $request('POST', '/camedit.php', array_replace($uploadFields, ['photo' => new CURLFile($jpegPath, 'image/jpeg', 'phone.jpg')]));
        $orientedResult = json_decode($uploaded['body'], true, flags: JSON_THROW_ON_ERROR);
        $check($uploaded['status'] === 201, 'Un JPEG avec orientation EXIF peut être importé.');
        $oriented = imagecreatefromstring(base64_decode(substr($orientedResult['image'], strlen('data:image/png;base64,')), true));
        $check(imagesx($oriented) === ($orientation >= 5 ? 40 : 80) && imagesy($oriented) === ($orientation >= 5 ? 80 : 40), 'L’orientation EXIF ' . $orientation . ' détermine les dimensions du montage.');
        $color = imagecolorat($oriented, imagesx($oriented) - 1, 0);
        foreach ([0, 8, 16] as $shift) {
            $check(abs((($color >> $shift) & 255) - (($expectedColor >> $shift) & 255)) <= 10, 'La rotation ou réflexion EXIF ' . $orientation . ' doit précéder l’ajout du calque.');
        }
    }
    $badPath = tempnam(sys_get_temp_dir(), 'camagru_bad_');
    $uploadPaths[] = $badPath;
    foreach (['<?php echo "bad";', '<svg xmlns="http://www.w3.org/2000/svg"></svg>', substr(file_get_contents($path), 0, 33), str_repeat('x', 5 * 1024 * 1024 + 1)] as $invalidFile) {
        file_put_contents($badPath, $invalidFile);
        $check($request('POST', '/camedit.php', array_replace($uploadFields, ['photo' => new CURLFile($badPath, 'image/png', 'fake.png')]))['status'] === 422, 'Un fichier faux, tronqué ou trop volumineux est refusé malgré son extension et son type déclarés.');
    }
    $check($request('POST', '/camedit.php', array_replace($uploadFields, ['overlay' => 'missing.png']))['status'] === 422, 'Un calque fourni lors de l’import doit être valide.');
    $composite = imagecreatefromstring(base64_decode(substr($result['image'], strlen('data:image/png;base64,')), true));
    $check($composite !== false && imagesx($composite) === 4 && imagesy($composite) === 4, 'Le montage doit conserver la taille de la capture.');
    $check((imagecolorat($composite, 0, 0) & 0xffffff) === 0xff0000, 'Le pixel rouge du calque doit recouvrir la capture.');
    $check((imagecolorat($composite, 3, 3) & 0xffffff) === 0x0000ff, 'Le calque doit être redimensionné jusqu’au bord opposé de la capture.');
    $check((imagecolorat($composite, 3, 0) & 0xffffff) === 0x00ff00, 'La transparence du calque doit laisser apparaître la capture.');
    $blended = imagecolorsforindex($composite, imagecolorat($composite, 0, 3));
    $check($blended['green'] === 255 && $blended['red'] >= 125 && $blended['red'] <= 129 && $blended['blue'] >= 125 && $blended['blue'] <= 129, 'Un pixel blanc à moitié transparent doit se mélanger avec la capture verte.');

    foreach ($jpegNames as $jpegName) {
        $jpegSaved = $request('POST', '/camedit.php', $fields + ['overlays' => [$jpegName]]);
        $jpegResult = json_decode($jpegSaved['body'], true, flags: JSON_THROW_ON_ERROR);
        $check($jpegSaved['status'] === 201 && isset($jpegResult['image_url']), 'Une capture accepte un calque JPEG.');
        $jpegMontage = $request('GET', $jpegResult['image_url']);
        $decoded = imagecreatefromstring($jpegMontage['body']);
        $color = imagecolorsforindex($decoded, imagecolorat($decoded, 3, 0));
        $check($jpegMontage['type'] === 'image/png' && $color['red'] >= 250 && $color['green'] >= 250 && $color['blue'] <= 5,
            'Le calque JPEG opaque recouvre la capture et le montage reste enregistré en PNG.');
    }
    foreach ([$fields, ['photo' => new CURLFile($path, 'image/png', 'photo.png'), 'csrf_token' => $token[1]]] as $baseFields) {
        $mixed = $request('POST', '/camedit.php', $baseFields + ['overlays' => [$jpegNames[0], $name]]);
        $mixedResult = json_decode($mixed['body'], true, flags: JSON_THROW_ON_ERROR);
        $check($mixed['status'] === 201, 'Une capture ou un import accepte un mélange de calques PNG et JPEG.');
        $mixedMontage = $request('GET', $mixedResult['image_url']);
        $decoded = imagecreatefromstring($mixedMontage['body']);
        $color = imagecolorsforindex($decoded, imagecolorat($decoded, imagesx($decoded) - 1, 0));
        $check((imagecolorat($decoded, 0, 0) & 0xffffff) === 0xff0000
            && $color['red'] >= 250 && $color['green'] >= 250 && $color['blue'] <= 5,
            'Le PNG se superpose au JPEG et ses pixels transparents laissent voir celui-ci.');
    }
    $orientedName = 'oriented_' . $jpegNames[0];
    $uploadPaths[] = $directory . '/' . $orientedName;
    $exif = "Exif\0\0II*\0" . pack('Vv', 8, 1) . pack('vvVv', 0x112, 3, 1, 6) . "\0\0" . pack('V', 0);
    file_put_contents($directory . '/' . $orientedName, substr($jpegBytes, 0, 2) . "\xff\xe1"
        . pack('n', strlen($exif) + 2) . $exif . substr($jpegBytes, 2));
    $orientedSaved = $request('POST', '/camedit.php', $fields + ['overlays' => [$orientedName]]);
    $orientedResult = json_decode($orientedSaved['body'], true, flags: JSON_THROW_ON_ERROR);
    $check($orientedSaved['status'] === 201, 'Un calque JPEG contenant une orientation EXIF est accepté.');
    $orientedMontage = $request('GET', $orientedResult['image_url']);
    $decoded = imagecreatefromstring($orientedMontage['body']);
    $topRight = imagecolorsforindex($decoded, imagecolorat($decoded, 3, 0));
    $check($topRight['red'] >= 245 && $topRight['green'] <= 10 && $topRight['blue'] <= 10,
        'Le calque JPEG est orienté avant sa superposition, comme dans l’aperçu du navigateur.');
    foreach ($invalidJpegNames as $invalidJpegName) {
        $check($request('POST', '/camedit.php', $fields + ['overlays' => [$invalidJpegName]])['status'] === 422,
            'Un JPEG exclu du catalogue est aussi refusé lors de la composition.');
    }

    $multiFields = $fields + ['overlays' => [$name, $secondName]];
    $multi = $request('POST', '/camedit.php', $multiFields);
    $multiResult = json_decode($multi['body'], true, flags: JSON_THROW_ON_ERROR);
    $check($multi['status'] === 201 && isset($multiResult['image_url']), 'Une capture accepte plusieurs stickers.');
    $persisted = $request('GET', $multiResult['image_url']);
    $multiImage = imagecreatefromstring($persisted['body']);
    $check($persisted['status'] === 200 && (imagecolorat($multiImage, 0, 0) & 0xffffff) === 0xffff00,
        'Le dernier sticker recouvre les précédents dans le fichier enregistré.');
    $check((imagecolorat($multiImage, 3, 0) & 0xffffff) === 0xff00ff
        && (imagecolorat($multiImage, 3, 3) & 0xffffff) === 0x0000ff,
        'Chaque sticker contribue au montage et sa transparence laisse apparaître les précédents.');
    $multiBlend = imagecolorsforindex($multiImage, imagecolorat($multiImage, 0, 3));
    $check($multiBlend['red'] >= 61 && $multiBlend['red'] <= 65
        && $multiBlend['green'] >= 125 && $multiBlend['green'] <= 129,
        'La semi-transparence de plusieurs stickers est composée successivement.');
    $reverse = $request('POST', '/camedit.php', array_replace($multiFields, ['overlays' => [$secondName, $name]]));
    $reverseResult = json_decode($reverse['body'], true, flags: JSON_THROW_ON_ERROR);
    $reverseImage = imagecreatefromstring(base64_decode(substr($reverseResult['image'], strlen('data:image/png;base64,')), true));
    $check($reverse['status'] === 201 && (imagecolorat($reverseImage, 0, 0) & 0xffffff) === 0xff0000,
        'Le serveur respecte l’ordre des stickers transmis.');

    foreach ([$path => 'image/png', $jpegPath => 'image/jpeg'] as $uploadPath => $mime) {
        $multiUpload = $request('POST', '/camedit.php', [
            'photo' => new CURLFile($uploadPath, $mime, 'photo'),
            'csrf_token' => $token[1], 'overlays' => [$name, $secondName],
        ]);
        $multiUploadResult = json_decode($multiUpload['body'], true, flags: JSON_THROW_ON_ERROR);
        $check($multiUpload['status'] === 201 && isset($multiUploadResult['image_url']), 'Un import PNG ou JPEG accepte plusieurs stickers.');
        $uploadedImage = $request('GET', $multiUploadResult['image_url']);
        $decoded = imagecreatefromstring($uploadedImage['body']);
        $check((imagecolorat($decoded, 0, 0) & 0xffffff) === 0xffff00,
            'Le fichier importé est composé avec les deux stickers dans le bon ordre.');
    }
    $countImages = $database->prepare('SELECT COUNT(*) FROM images WHERE user_id = :id');
    $countImages->execute(['id' => $userId]);
    $countBeforeInvalid = (int) $countImages->fetchColumn();
    foreach (['not-an-array', [$name, 'missing.png'], [$name, '../' . $name], [$name, $invalidName],
        [$name, $linkName], [$name, [$secondName]], [$name, ''], [$name, $name],
        ['unexpected' => $name], array_fill(0, 21, $name)] as $invalidOverlays) {
        $invalid = $request('POST', '/camedit.php', $fields + ['overlays' => $invalidOverlays]);
        $check($invalid['status'] === 422 && isset(json_decode($invalid['body'], true)['error']),
            'Une sélection de stickers mal formée, trop longue ou hors catalogue est refusée proprement.');
    }
    $check($request('POST', '/camedit.php', $multiFields + ['overlay' => $name])['status'] === 422,
        'Les anciens et nouveaux champs de sélection ne peuvent pas être mélangés.');
    $countImages->execute(['id' => $userId]);
    $check((int) $countImages->fetchColumn() === $countBeforeInvalid, 'Aucun montage partiel n’est enregistré si la sélection est invalide.');
    $check($request('POST', '/camedit.php', array_replace($multiFields, ['csrf_token' => 'invalid']))['status'] === 403,
        'Une capture avec plusieurs stickers reste protégée contre les requêtes sans CSRF valide.');

    $permissions = fileperms($path) & 0777;
    chmod($path, 0000);
    try {
        $check($request('GET', '/overlay.php?name=' . rawurlencode($name))['status'] === 503, 'Un fichier de stock illisible doit produire une indisponibilité contrôlée.');
        $unavailable = $request('GET', '/camedit.php');
        $check($unavailable['status'] === 503 && str_contains($unavailable['body'], 'momentanément indisponibles'), 'Un stock illisible ne doit pas être présenté comme un stock vide.');
    } finally {
        chmod($path, $permissions);
    }

    // Produire de grands PNG ligne par ligne, sans allouer de grandes images GD dans le test.
    $largePng = static function (string $pixel): string {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        $compressor = deflate_init(ZLIB_ENCODING_DEFLATE);
        $row = "\0" . str_repeat($pixel, 4096);
        $compressed = '';
        for ($y = 0; $y < 4096; $y++) {
            $compressed .= deflate_add($compressor, $row, ZLIB_NO_FLUSH);
        }
        $compressed .= deflate_add($compressor, '', ZLIB_FINISH);
        return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 4096, 4096, 8, 6, 0, 0, 0))
            . $chunk('IDAT', $compressed) . $chunk('IEND', '');
    };
    file_put_contents($path, $largePng("\xff\x00\x00\x80"));
    $largeImage = 'data:image/png;base64,' . base64_encode($largePng("\x00\xff\x00\xff"));
    $maximum = $request('POST', '/camedit.php', array_replace($validFields, ['image' => $largeImage]));
    $maximumResult = json_decode($maximum['body'], true);
    $check(($maximum['status'] === 422 && isset($maximumResult['error']))
        || ($maximum['status'] === 201 && isset($maximumResult['file_key'], $maximumResult['image'])), 'La taille maximale doit produire un montage ou un refus contrôlé, jamais une erreur fatale.');
    $maximumWithoutOverlay = $request('POST', '/camedit.php', array_replace($fields, ['image' => $largeImage]));
    $check($maximumWithoutOverlay['status'] === 422 && isset(json_decode($maximumWithoutOverlay['body'], true)['error']),
        'Le contrôle mémoire reste actif pour une grande image sans calque.');
    echo $checks . " vérifications réussies (stock, vignettes et montage serveur).\n";
} finally {
    foreach ([$userId, $otherUserId] as $cleanupUserId) {
        if ($cleanupUserId === null) continue;
        $delete = $database->prepare('DELETE FROM images WHERE user_id = :id RETURNING file_key');
        $delete->execute(['id' => $cleanupUserId]);
        foreach ($delete->fetchAll(PDO::FETCH_COLUMN) as $key) {
            @unlink(dirname(__DIR__) . '/storage/cam_images/' . $key . '.png');
        }
        $delete = $database->prepare('DELETE FROM users WHERE id = :id');
        $delete->execute(['id' => $cleanupUserId]);
    }
    unlink($path);
    unlink($secondPath);
    unlink($directory . '/' . $invalidName);
    unlink($directory . '/' . $linkName);
    foreach ($uploadPaths as $uploadPath) unlink($uploadPath);
}
