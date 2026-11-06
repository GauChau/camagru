<?php
declare(strict_types=1);

// docker compose exec -T web php tests/recovery.php
// Compte isolé supprimé en fin de test ; aucun email réel n'est envoyé.
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Repositories/UserRepository.php';
require_once dirname(__DIR__) . '/src/Services/PasswordRecoveryService.php';

use Camagru\Database;
use Camagru\Repositories\UserRepository;
use Camagru\Services\PasswordRecoveryService;

$database = Database::connect();
$users = new UserRepository($database);
$messages = [];
$mailSucceeds = true;
$recovery = new PasswordRecoveryService($users, static function (
    string $recipient, string $subject, string $body, array $headers
) use (&$messages, &$mailSucceeds): bool {
    $messages[] = ['recipient' => $recipient, 'body' => $body];
    return $mailSucceeds;
});
$login = 'recovery_test_' . bin2hex(random_bytes(8));
$email = $login . '@example.test';
$originalPassword = 'Original-password-2026!';
$newPassword = 'Replacement-password-2026!';
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$findUser = static function () use ($database, $login): array {
    $query = $database->prepare(
        'SELECT id, password, activation, pwdrecovery_token_hash, pwdrecovery_token_expires_at FROM users WHERE login = :login'
    );
    $query->execute(['login' => $login]);
    $user = $query->fetch();
    if ($user === false) {
        throw new RuntimeException('Compte de test introuvable.');
    }
    return $user;
};
$lastToken = static function () use (&$messages): string {
    if (!preg_match('/[?&]token=([0-9a-f]{64})(?![0-9a-f])/', $messages[array_key_last($messages)]['body'], $match)) {
        throw new RuntimeException('Le message doit contenir un lien de récupération.');
    }
    return $match[1];
};
$baseUrl = rtrim(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1', '/');
$http = curl_init();
curl_setopt_array($http, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_COOKIEFILE => '']);
$request = static function (string $path, ?array $data = null, string $method = 'GET') use ($http, $baseUrl): array {
    curl_setopt_array($http, [
        CURLOPT_URL => $baseUrl . $path,
        CURLOPT_POSTFIELDS => $data === null ? null : http_build_query($data),
        CURLOPT_CUSTOMREQUEST => $data === null ? $method : 'POST',
    ]);
    $html = curl_exec($http);
    if ($html === false) {
        throw new RuntimeException('Requête HTTP de test impossible.');
    }
    return [curl_getinfo($http, CURLINFO_RESPONSE_CODE), $html];
};
$field = static function (string $html, string $name): string {
    if (!preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', $html, $match)) {
        throw new RuntimeException('Champ de formulaire manquant : ' . $name);
    }
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
};

try {
    $users->create($login, $email, password_hash($originalPassword, PASSWORD_ARGON2ID), hash('sha256', random_bytes(32)));
    $initial = $findUser();
    $check($recovery->requestRecovery('incorrect') !== [], 'Une adresse invalide doit être refusée.');
    $check($recovery->requestRecovery('unknown_' . $email) === [], 'Une adresse inconnue doit recevoir un succès générique.');
    $check($messages === [] && $findUser() === $initial, 'Ces demandes ne doivent ni envoyer un mail ni modifier le compte.');

    $check($recovery->requestRecovery(' ' . strtoupper($email) . ' ') === [], 'La demande doit accepter une adresse normalisée.');
    $token = $lastToken();
    $user = $findUser();
    $check(count($messages) === 1 && $messages[0]['recipient'] === $email, 'Le mail doit être envoyé au compte demandé.');
    $check(str_contains($messages[0]['body'], rtrim((string) getenv('APP_URL'), '/') . '/recovery.php?token='), 'Le lien doit utiliser APP_URL et le jeton original.');
    $check($user['pwdrecovery_token_hash'] === hash('sha256', $token), 'Seul le hash doit être stocké.');
    $remaining = (new DateTimeImmutable($user['pwdrecovery_token_expires_at']))->getTimestamp() - time();
    $check($remaining >= 1790 && $remaining <= 1800, 'Le jeton doit expirer dans 30 minutes.');
    $check($recovery->findUserIdByToken($token) === $user['id'], 'Le jeton doit retrouver le bon compte.');
    foreach (['', 'incorrect', str_repeat('a', 64)] as $invalid) {
        $check($recovery->findUserIdByToken($invalid) === null, 'Un jeton mal formé ou inconnu doit être refusé.');
        $check($recovery->resetPassword($invalid, $newPassword, $newPassword) !== [], 'Un jeton invalide ne doit pas autoriser le changement.');
    }
    $check($recovery->resetPassword($token, 'short1', 'short1') !== [], 'Un mot de passe court doit être refusé.');
    $check($recovery->resetPassword($token, 'PasswordWithoutNumber', 'PasswordWithoutNumber') !== [], 'Un mot de passe sans chiffre doit être refusé.');
    $check($recovery->resetPassword($token, $newPassword, 'different') !== [], 'Une confirmation différente doit être refusée.');
    $check($findUser() === $user, 'Les validations refusées doivent conserver le mot de passe et le jeton.');

    $expire = $database->prepare("UPDATE users SET pwdrecovery_token_expires_at = CURRENT_TIMESTAMP - INTERVAL '1 minute' WHERE id = :id");
    $expire->execute(['id' => $user['id']]);
    $check($recovery->findUserIdByToken($token) === null, 'Un jeton expiré doit être refusé.');
    $check($recovery->resetPassword($token, $newPassword, $newPassword) !== [], 'Un jeton expiré ne doit pas modifier le mot de passe.');
    $check(!$users->resetPasswordByRecoveryHash(hash('sha256', $token), password_hash($newPassword, PASSWORD_ARGON2ID)), 'La requête finale doit aussi refuser un jeton expiré.');

    $check($recovery->requestRecovery($email) === [], 'Un nouveau lien doit pouvoir être demandé.');
    $secondToken = $lastToken();
    $check($secondToken !== $token && $recovery->findUserIdByToken($token) === null, 'Le renvoi doit remplacer le précédent jeton.');
    $check($recovery->resetPassword($secondToken, $newPassword, $newPassword) === [], 'Un jeton valide doit autoriser le changement.');
    $updated = $findUser();
    $check(password_verify($newPassword, $updated['password']) && !password_verify($originalPassword, $updated['password']), 'Seul le nouveau mot de passe doit correspondre au hash stocké.');
    $check($updated['pwdrecovery_token_hash'] === null && $updated['pwdrecovery_token_expires_at'] === null, 'Le changement doit effacer le jeton et son expiration.');
    $check($updated['activation'] === $initial['activation'], 'La récupération ne doit pas activer le compte.');
    $check($recovery->resetPassword($secondToken, $originalPassword, $originalPassword) !== [], 'Un jeton consommé ne doit pas être réutilisable.');

    $mailSucceeds = false;
    $check($recovery->requestRecovery($email) === [], 'Un échec de transport ne doit pas révéler le compte dans la réponse publique.');
    $mailSucceeds = true;
    $check($recovery->requestRecovery($email) === [], 'Un nouvel essai doit rester possible après un échec de transport.');
    $httpToken = $lastToken();

    [$status, $html] = $request('/recovery.php?token=' . $httpToken);
    $check($status === 200 && str_contains($html, 'name="confirm_password"'), 'Le lien doit afficher le formulaire de nouveau mot de passe.');
    $csrf = $field($html, 'csrf_token');
    $check($field($html, 'token') === $httpToken, 'Le formulaire doit transmettre le jeton au POST.');
    $form = ['token' => $httpToken, 'password' => $originalPassword, 'confirm_password' => $originalPassword];
    [$status] = $request('/recovery.php', $form);
    $check($status === 403 && password_verify($newPassword, $findUser()['password']), 'Un POST sans CSRF ne doit pas modifier le compte.');
    [$status] = $request('/recovery.php', $form + ['csrf_token' => $csrf]);
    $check($status === 303 && password_verify($originalPassword, $findUser()['password']), 'Le POST valide doit changer le mot de passe et rediriger.');
    [$status, $html] = $request('/login.php');
    $check($status === 200 && str_contains($html, 'Ton mot de passe a été modifié.'), 'La connexion doit afficher le succès du changement.');
    [$status] = $request('/recovery.php?token=' . $httpToken);
    $check($status === 400, 'Le lien consommé doit être refusé par HTTP.');
    foreach (['', '?token[]=incorrect', '?token=incorrect'] as $query) {
        [$status] = $request('/recovery.php' . $query);
        $check($status === 400, 'Un lien absent ou mal formé doit être refusé sans erreur serveur.');
    }
    [$status] = $request('/recovery.php', null, 'PUT');
    $check($status === 405, 'Une méthode non autorisée doit être refusée.');
    [$status, $html] = $request('/pwdchange.php');
    $csrf = $field($html, 'csrf_token');
    $check($status === 200, 'La demande de récupération doit être accessible.');
    [$status] = $request('/pwdchange.php', ['email' => $email]);
    $check($status === 403, 'La demande de récupération doit être protégée par CSRF.');
    [$status] = $request('/pwdchange.php', ['email' => 'incorrect', 'csrf_token' => $csrf]);
    $check($status === 422, 'Une adresse invalide doit être refusée par HTTP.');
    [$status] = $request('/pwdchange.php', ['email' => 'unknown_' . $email, 'csrf_token' => $csrf]);
    $check($status === 303, 'Une adresse inconnue doit suivre le parcours de succès générique.');
    [$status, $html] = $request('/pwdchange.php');
    $check($status === 200 && str_contains($html, 'Si un compte correspond'), 'Le résultat doit afficher le message générique.');

    echo $checks . " vérifications réussies (récupération, expiration, usage unique, validation et HTTP).\n";
} finally {
    $cleanup = $database->prepare('DELETE FROM users WHERE login = :login AND email = :email');
    $cleanup->execute(['login' => $login, 'email' => $email]);
}
