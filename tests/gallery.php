<?php
declare(strict_types=1);

// docker compose exec -T web php tests/gallery.php
// Fixtures isolées ; seuls les comptes, montages et emails créés ici sont supprimés.
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Repositories/ImageRepository.php';

use Camagru\Database;
use Camagru\Repositories\ImageRepository;

$database = Database::connect();
$suffix = bin2hex(random_bytes(8));
$password = 'Gallery-password-2026!';
$users = [];
$keys = [];
$imageIds = [];
$cookie = '';
$checks = 0;
$baseUrl = rtrim(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1', '/');
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$request = static function (string $method, string $path, ?array $fields = null) use ($baseUrl, &$cookie): array {
    $headers = [];
    $handle = curl_init($baseUrl . $path);
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_COOKIE => $cookie,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$cookie, &$headers): int {
            $headers[] = trim($header);
            if (preg_match('/^Set-Cookie:\s*(PHPSESSID=[^;]+)/i', $header, $match)) $cookie = $match[1];
            return strlen($header);
        }]);
    if ($fields !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
    $body = curl_exec($handle);
    if ($body === false) throw new RuntimeException(curl_error($handle));
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers];
};
$token = static function (array $response, string $action): string {
    if (!preg_match('~<form[^>]+action="' . preg_quote($action, '~') . '[^"]*"[^>]*>.*?name="csrf_token"\s+value="([a-f0-9]{64})"~s', $response['body'], $matches)) {
        throw new RuntimeException('Jeton absent pour ' . $action);
    }
    return $matches[1];
};
$login = static function (string $name) use ($request, $token, $password, $check, &$cookie): void {
    $cookie = '';
    $page = $request('GET', '/login.php');
    $check($request('POST', '/login.php', ['login' => $name, 'password' => $password, 'csrf_token' => $token($page, '/login.php')])['status'] === 303, 'Connexion du compte temporaire.');
};
$mailApi = static function (string $path, string $method = 'GET'): array {
    $handle = curl_init('http://mailpit:8025/api/v1/' . $path);
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CUSTOMREQUEST => $method]);
    $body = curl_exec($handle);
    if ($body === false || curl_getinfo($handle, CURLINFO_RESPONSE_CODE) >= 400) throw new RuntimeException('API Mailpit indisponible.');
    return $body === '' ? [] : (json_decode($body, true) ?? []);
};
$messages = static function () use ($mailApi, $suffix): array {
    return $mailApi('search?query=' . rawurlencode('to:gallery_' . $suffix . '_owner@example.test'))['messages'] ?? [];
};
$cards = static function (array $page): array {
    preg_match_all('/<article class="gallery-card" id="image-(\d+)"/', $page['body'], $matches);
    return array_map('intval', $matches[1]);
};

try {
    $page = $request('GET', '/gallery.php');
    $check($page['status'] === 200, 'La galerie est publique.');
    $check(!str_contains($page['body'], 'name="comment"'), 'Un visiteur ne reçoit pas de formulaire de commentaire.');
    $check(str_contains($request('GET', '/')['body'], 'href="/gallery.php"'), 'La galerie est accessible depuis l’accueil public.');
    foreach (['like', 'unlike', 'comment', 'delete'] as $action) {
        $check($request('POST', '/gallery.php', ['action' => $action, 'image_id' => 1, 'comment' => 'Visiteur'])['status'] === 401, 'Les réactions anonymes sont refusées.');
    }
    $check($request('PUT', '/gallery.php')['status'] === 405, 'Méthodes non prévues refusées.');
    foreach (['0', '-1', '../.env', '2147483648', '1 OR 1=1', ''] as $invalid) {
        $check($request('GET', '/image.php?id=' . rawurlencode($invalid))['status'] === 404, 'Identifiant de montage invalide refusé.');
    }
    $check($request('GET', '/image.php?id[]=1')['status'] === 404, 'Identifiant tableau refusé.');
    $check($request('POST', '/image.php?id=1')['status'] === 405, 'Une image ne peut être modifiée par sa route publique.');

    $insert = $database->prepare('INSERT INTO users (login, email, password, activation) VALUES (:login, :email, :password, TRUE) RETURNING id, login, email, comment_notifications');
    foreach (['owner', 'viewer'] as $role) {
        $name = 'gallery_' . $suffix . '_' . $role;
        $insert->execute(['login' => $name, 'email' => $name . '@example.test', 'password' => password_hash($password, PASSWORD_ARGON2ID)]);
        $users[$role] = $insert->fetch();
        $insert->closeCursor();
    }
    $check($users['owner']['comment_notifications'] === true, 'Les notifications sont actives par défaut.');
    $directory = dirname(__DIR__) . '/storage/cam_images';
    if (!is_dir($directory)) mkdir($directory, 0770, true);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1sAAAAASUVORK5CYII=');
    $insertImage = $database->prepare("INSERT INTO images (user_id, file_key, created_at) VALUES (:user, :key, '2099-01-01 12:00:00+00') RETURNING id");
    for ($i = 0; $i < 7; $i++) {
        $key = bin2hex(random_bytes(32));
        $keys[] = $key;
        file_put_contents($directory . '/' . $key . '.png', $png);
        $insertImage->execute(['user' => $users[$i % 2 === 0 ? 'owner' : 'viewer']['id'], 'key' => $key]);
        $imageIds[] = (int) $insertImage->fetchColumn();
        $insertImage->closeCursor();
    }
    $target = $imageIds[6];
    $page = $request('GET', '/gallery.php');
    $expected = array_reverse(array_slice($imageIds, 1));
    $check($cards($page) === $expected, 'Six montages de tous les auteurs, les plus récents en premier, avec ordre stable à date égale.');
    $second = $cards($request('GET', '/gallery.php?page=2'));
    $check(($second[0] ?? null) === $imageIds[0] && array_intersect($expected, $second) === [], 'La deuxième page continue sans doublon.');
    foreach (['0', '-1', 'invalid', '1 OR 1=1', '999999999999999999999999'] as $invalid) {
        $check($cards($request('GET', '/gallery.php?page=' . rawurlencode($invalid))) === $expected, 'Les pages invalides reviennent à la première page.');
    }
    $check($request('GET', '/gallery.php?page[]=1')['status'] === 200, 'Une page sous forme de tableau ne produit pas d’erreur.');
    $check($request('GET', '/gallery.php?page=999999999')['status'] === 200, 'Une page hors limites reste navigable.');
    $image = $request('GET', '/image.php?id=' . $target);
    $check($image['status'] === 200 && $image['body'] === $png && in_array('Content-Type: image/png', $image['headers'], true), 'Un visiteur peut lire le PNG enregistré.');

    $login($users['viewer']['login']);
    $page = $request('GET', '/gallery.php');
    $fields = ['csrf_token' => $token($page, '/gallery.php'), 'image_id' => $target, 'action' => 'like'];
    $check($request('POST', '/gallery.php', array_replace($fields, ['csrf_token' => 'bad']))['status'] === 403, 'Les réactions exigent un CSRF valide.');
    foreach (['like', 'like'] as $action) {
        $check($request('POST', '/gallery.php', $fields)['status'] === 303, 'Un like accepté redirige.');
    }
    $page = $request('GET', '/gallery.php');
    $check(str_contains($page['body'], 'aria-pressed="true"') && str_contains($page['body'], 'gallery-count">(1)'), 'Deux envois du même like conservent un seul like.');
    $check($request('POST', '/gallery.php', array_replace($fields, ['action' => 'unlike']))['status'] === 303, 'Un utilisateur peut retirer son like.');
    $page = $request('GET', '/gallery.php');
    $check(!str_contains($page['body'], 'aria-pressed="true"'), 'Le like retiré disparaît.');
    foreach (['', '   ', str_repeat('é', 2001), ['invalid'], "bad\0comment", "\xff"] as $invalid) {
        $check($request('POST', '/gallery.php', array_replace($fields, ['action' => 'comment', 'comment' => $invalid]))['status'] === 422, 'Un commentaire invalide est refusé.');
    }
    $check($request('POST', '/gallery.php', array_replace($fields, ['image_id' => 2147483647]))['status'] === 404, 'Un montage absent est refusé.');
    $check($request('POST', '/gallery.php', array_replace($fields, ['action' => 'unknown']))['status'] === 422, 'Action inconnue refusée.');
    $check($request('POST', '/gallery.php', array_replace($fields, ['action' => 'delete', 'csrf_token' => 'bad']))['status'] === 403, 'La suppression exige un CSRF valide.');
    $check($request('POST', '/gallery.php', array_replace($fields, ['action' => 'delete', 'user_id' => $users['owner']['id']]))['status'] === 403, 'Un autre compte ne peut pas supprimer le montage, même en falsifiant le propriétaire.');
    $repository = new ImageRepository($database);
    $check(!$repository->deleteImage((int) $users['viewer']['id'], $keys[6]), 'Le repository refuse aussi une suppression par un autre propriétaire.');
    $check($request('GET', '/image.php?id=' . $target)['status'] === 200 && is_file($directory . '/' . $keys[6] . '.png'), 'Une suppression refusée conserve le montage et son PNG.');
    $body = '<script>alert("test")</script> Une photo !';
    $check($request('POST', '/gallery.php', array_replace($fields, ['action' => 'comment', 'comment' => $body, 'user_id' => $users['owner']['id']]))['status'] === 303, 'Le commentaire peut être publié.');
    $page = $request('GET', '/gallery.php');
    $check(str_contains($page['body'], '&lt;script&gt;') && !str_contains($page['body'], '<script>alert'), 'Le commentaire est affiché comme texte.');
    $check(str_contains($page['body'], 'Ton commentaire a été publié.'), 'La publication est confirmée après redirection.');
    $check(count($messages()) === 1, 'L’auteur reçoit exactement un email.');
    $comment = $database->query('SELECT user_id FROM image_comments WHERE image_id = ' . $target)->fetchColumn();
    $check((int) $comment === (int) $users['viewer']['id'], 'L’auteur du commentaire vient de la session.');
    $cookie = '';
    $check(str_contains($request('GET', '/gallery.php')['body'], '&lt;script&gt;'), 'Les visiteurs peuvent lire les commentaires.');

    $login($users['owner']['login']);
    $page = $request('GET', '/settings.php');
    $check(str_contains($page['body'], 'name="comment_notifications" value="1" checked'), 'La préférence est cochée initialement.');
    $settings = ['csrf_token' => $token($page, '/settings.php'), 'login' => $users['owner']['login'], 'email' => $users['owner']['email'], 'current_password' => $password];
    $check($request('POST', '/settings.php', $settings)['status'] === 303, 'La préférence peut être désactivée.');
    $page = $request('GET', '/settings.php');
    $check(!str_contains($page['body'], 'name="comment_notifications" value="1" checked'), 'La préférence désactivée persiste.');
    $ownerCookie = $cookie;
    $login($users['viewer']['login']);
    $page = $request('GET', '/gallery.php');
    $fields['csrf_token'] = $token($page, '/gallery.php');
    $check($request('POST', '/gallery.php', array_replace($fields, ['action' => 'comment', 'comment' => 'Sans email']))['status'] === 303, 'Commenter reste possible sans notification.');
    $check(count($messages()) === 1, 'Aucun email supplémentaire lorsque la préférence est désactivée.');
    $cookie = $ownerCookie;
    $page = $request('GET', '/settings.php');
    $settings['csrf_token'] = $token($page, '/settings.php');
    $check($request('POST', '/settings.php', $settings + ['comment_notifications' => '1'])['status'] === 303, 'La préférence peut être réactivée.');
    $page = $request('GET', '/gallery.php');
    $fields['csrf_token'] = $token($page, '/gallery.php');
    $check($request('POST', '/gallery.php', array_replace($fields, ['action' => 'comment', 'comment' => str_repeat('é', 2000)]))['status'] === 303, 'La limite de 2 000 caractères Unicode est acceptée.');
    $check(count($messages()) === 2, 'Les emails reprennent après réactivation, y compris pour un commentaire de l’auteur.');
    $fixtureComment = $database->prepare('INSERT INTO image_comments (image_id, user_id, body) VALUES (:image, :user, :body)');
    for ($i = 1; $i <= 12; $i++) {
        $fixtureComment->execute(['image' => $target, 'user' => $users['viewer']['id'], 'body' => 'pagination_comment_' . $i]);
    }
    $page = $request('GET', '/gallery.php');
    $check(str_contains($page['body'], 'Commentaires (15)') && substr_count($page['body'], '<li><strong>') === 10, 'Le compteur reste complet avec dix commentaires au maximum par montage.');
    $check(str_contains($page['body'], 'pagination_comment_12') && !str_contains($page['body'], 'pagination_comment_1</p>'), 'Les commentaires les plus récents sont affichés en premier.');
    $page = $request('GET', '/gallery.php?comments_for=' . $target . '&comments_page=2');
    $check(substr_count($page['body'], '<li><strong>') === 5 && str_contains($page['body'], 'pagination_comment_1</p>'), 'La deuxième page donne accès aux commentaires plus anciens.');
    $check(str_contains($page['body'], 'class="gallery-comments" open'), 'La navigation des commentaires conserve leur section ouverte.');
    $page = $request('GET', '/gallery.php?comments_for=' . $target . '&comments_page=999999999');
    $check(str_contains($page['body'], 'Page 2 sur 2'), 'Une page de commentaires hors limites est bornée.');

    $deleteFields = ['csrf_token' => $token($page, '/gallery.php'), 'image_id' => $target, 'action' => 'delete'];
    $check($request('POST', '/gallery.php', array_replace($deleteFields, ['action' => 'like']))['status'] === 303, 'Le montage à supprimer peut avoir un like.');
    $deleted = $request('POST', '/gallery.php', $deleteFields);
    $check($deleted['status'] === 303 && in_array('Location: /gallery.php?page=1', $deleted['headers'], true), 'La suppression réussie redirige vers la galerie sans ancre obsolète.');
    $page = $request('GET', '/gallery.php');
    $check(str_contains($page['body'], 'Ton montage a été supprimé.'), 'La suppression est confirmée dans la galerie.');
    $check($repository->findImage($target) === null && !file_exists($directory . '/' . $keys[6] . '.png'), 'La suppression efface la ligne SQL et le PNG.');
    $check((int) $database->query('SELECT count(*) FROM image_likes WHERE image_id = ' . $target)->fetchColumn() === 0
        && (int) $database->query('SELECT count(*) FROM image_comments WHERE image_id = ' . $target)->fetchColumn() === 0, 'Les likes et commentaires sont supprimés en cascade.');
    $check($request('GET', '/image.php?id=' . $target)['status'] === 404, 'Le PNG supprimé ne peut plus être consulté.');
    $check($request('POST', '/gallery.php', $deleteFields)['status'] === 404, 'Une deuxième suppression est refusée proprement.');
    $check(count($messages()) === 2, 'La suppression ne publie aucun commentaire et n’envoie aucun email.');

    // Une ligne dont le fichier est déjà absent doit aussi pouvoir être supprimée.
    unlink($directory . '/' . $keys[4] . '.png');
    $check($request('POST', '/gallery.php', array_replace($deleteFields, ['image_id' => $imageIds[4]]))['status'] === 303
        && $repository->findImage($imageIds[4]) === null, 'Un PNG déjà absent ne bloque pas le nettoyage du montage.');

    // Un répertoire à la place du PNG provoque un échec unlink sans toucher au stock réel.
    $blockedPath = $directory . '/' . $keys[2] . '.png';
    $backupPath = $blockedPath . '.test-backup';
    rename($blockedPath, $backupPath);
    try {
        mkdir($blockedPath);
        $fixtureComment->execute(['image' => $imageIds[2], 'user' => $users['viewer']['id'], 'body' => 'Conserver si le fichier ne peut pas être supprimé.']);
        $repository->setLike($imageIds[2], (int) $users['viewer']['id'], true);
        $check($request('POST', '/gallery.php', array_replace($deleteFields, ['image_id' => $imageIds[2]]))['status'] === 503, 'Un échec du fichier produit une erreur contrôlée.');
        $check($repository->findImage($imageIds[2]) !== null, 'Un échec du fichier annule le DELETE SQL.');
        $check((int) $database->query('SELECT count(*) FROM image_likes WHERE image_id = ' . $imageIds[2])->fetchColumn() === 1
            && (int) $database->query('SELECT count(*) FROM image_comments WHERE image_id = ' . $imageIds[2])->fetchColumn() === 1, 'Le rollback conserve aussi les likes et commentaires.');
    } finally {
        if (is_dir($blockedPath)) rmdir($blockedPath);
        rename($backupPath, $blockedPath);
    }
    $check($request('POST', '/gallery.php', array_replace($deleteFields, ['image_id' => $imageIds[2]]))['status'] === 303, 'La suppression peut être retentée après correction du fichier.');
    echo $checks . " vérifications réussies (galerie, suppression, propriétaires, fichiers, rollback et notifications).\n";
} finally {
    $mailApi('search?query=' . rawurlencode('to:gallery_' . $suffix . '_owner@example.test'), 'DELETE');
    $delete = $database->prepare('DELETE FROM images WHERE id = :id');
    foreach ($imageIds as $id) $delete->execute(['id' => $id]);
    $delete = $database->prepare('DELETE FROM users WHERE id = :id');
    foreach ($users as $user) $delete->execute(['id' => $user['id']]);
    foreach ($keys as $key) @unlink(dirname(__DIR__) . '/storage/cam_images/' . $key . '.png');
}
