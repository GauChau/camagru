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
    $check(str_contains($history['body'], 'type="file"') && str_contains($history['body'], 'accept="image/png,image/jpeg"'), 'L’éditeur propose une image PNG ou JPEG comme alternative à la webcam.');
    $uploadFields = ['photo' => new CURLFile($path, 'image/png', 'photo.png'), 'overlay' => $name, 'csrf_token' => $token[1]];
    $uploaded = $request('POST', '/camedit.php', $uploadFields);
    $uploadResult = json_decode($uploaded['body'], true, flags: JSON_THROW_ON_ERROR);
    $check($uploaded['status'] === 201 && isset($uploadResult['image_url']), 'Un fichier PNG peut être composé avec un calque sans webcam.');
    $check($request('POST', '/camedit.php', array_replace($uploadFields, ['csrf_token' => 'bad']))['status'] === 403, 'L’import de fichier exige un jeton CSRF valide.');
    $authenticatedCookie = $cookie;
    $cookie = '';
    $check($request('POST', '/camedit.php', $uploadFields)['status'] === 401, 'L’import de fichier exige un utilisateur connecté.');
    $cookie = $authenticatedCookie;
    $jpegPath = tempnam(sys_get_temp_dir(), 'camagru_jpeg_');
    $uploadPaths[] = $jpegPath;
    imagejpeg($capture, $jpegPath, 100);
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
    $check($request('POST', '/camedit.php', array_replace($uploadFields, ['overlay' => 'missing.png']))['status'] === 422, 'Un import nécessite un calque valide.');
    $composite = imagecreatefromstring(base64_decode(substr($result['image'], strlen('data:image/png;base64,')), true));
    $check($composite !== false && imagesx($composite) === 4 && imagesy($composite) === 4, 'Le montage doit conserver la taille de la capture.');
    $check((imagecolorat($composite, 0, 0) & 0xffffff) === 0xff0000, 'Le pixel rouge du calque doit recouvrir la capture.');
    $check((imagecolorat($composite, 3, 3) & 0xffffff) === 0x0000ff, 'Le calque doit être redimensionné jusqu’au bord opposé de la capture.');
    $check((imagecolorat($composite, 3, 0) & 0xffffff) === 0x00ff00, 'La transparence du calque doit laisser apparaître la capture.');
    $blended = imagecolorsforindex($composite, imagecolorat($composite, 0, 3));
    $check($blended['green'] === 255 && $blended['red'] >= 125 && $blended['red'] <= 129 && $blended['blue'] >= 125 && $blended['blue'] <= 129, 'Un pixel blanc à moitié transparent doit se mélanger avec la capture verte.');

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
    unlink($directory . '/' . $invalidName);
    unlink($directory . '/' . $linkName);
    foreach ($uploadPaths as $uploadPath) unlink($uploadPath);
}
