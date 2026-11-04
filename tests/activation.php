<?php
declare(strict_types=1);

// docker compose exec -T web php tests/activation.php
// PostgreSQL réel, email simulé ; toutes les écritures sont annulées à la fin.
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Repositories/UserRepository.php';
require_once dirname(__DIR__) . '/src/Services/ActivationService.php';
require_once dirname(__DIR__) . '/src/Services/RegistrationService.php';

use Camagru\Database;
use Camagru\Repositories\UserRepository;
use Camagru\Services\ActivationService;
use Camagru\Services\RegistrationService;

$database = Database::connect();
$users = new UserRepository($database);
$messages = [];
$mailSucceeds = true;
$activation = new ActivationService($users, static function (
    string $recipient,
    string $subject,
    string $body,
    array $headers
) use (&$messages, &$mailSucceeds): bool {
    $messages[] = ['recipient' => $recipient, 'body' => $body];
    return $mailSucceeds;
});
$registration = new RegistrationService($users, $activation);
$login = 'activation_test_' . bin2hex(random_bytes(8));
$email = $login . '@example.test';
$password = 'Activation-password-2026!';
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$findUser = static function (string $name) use ($database): array {
    $query = $database->prepare('SELECT id, activation, activation_token_hash FROM users WHERE login = :login');
    $query->execute(['login' => $name]);
    $user = $query->fetch();
    if ($user === false) {
        throw new RuntimeException('Le compte de test est introuvable.');
    }
    return $user;
};
$lastToken = static function () use (&$messages): string {
    $message = $messages[array_key_last($messages)];
    if (!preg_match('/[?&]token=([0-9a-f]{64})(?![0-9a-f])/', $message['body'], $match)) {
        throw new RuntimeException('Le message doit contenir un lien d’activation.');
    }
    return $match[1];
};

$baseUrl = rtrim(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1', '/');
$handle = curl_init($baseUrl . '/activate.php');
curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$html = curl_exec($handle);
$check($html !== false && curl_getinfo($handle, CURLINFO_RESPONSE_CODE) === 200, 'Le lien de renvoi doit ouvrir une page accessible sans jeton.');
$check(str_contains($html, 'action="/activate.php"') && str_contains($html, 'name="csrf_token"'), 'La page de renvoi doit proposer son formulaire protégé par CSRF.');
$check(!str_contains($html, 'An error has occured.'), 'La page de renvoi sans jeton ne doit pas afficher une erreur d’activation.');
curl_setopt($handle, CURLOPT_URL, $baseUrl . '/activate.php?token=incorrect');
$html = curl_exec($handle);
$check($html !== false && curl_getinfo($handle, CURLINFO_RESPONSE_CODE) === 400, 'Un lien mal formé doit toujours être refusé par HTTP.');

$database->beginTransaction();
try {
    $check($registration->register($login, strtoupper($email), $password, $password) === [], 'L’inscription doit créer le compte et envoyer le lien.');
    $check(count($messages) === 1 && $messages[0]['recipient'] === $email, 'Le lien doit être envoyé à l’adresse normalisée.');
    $firstToken = $lastToken();
    $original = $findUser($login);
    $check($original['activation'] === false, 'Le compte doit rester inactif avant utilisation du lien.');
    $check($original['activation_token_hash'] === hash('sha256', $firstToken), 'Seul le hash du jeton doit être conservé en base.');
    $check(str_contains($messages[0]['body'], rtrim((string) getenv('APP_URL'), '/') . '/activate.php?token='), 'Le lien doit utiliser l’adresse de l’application.');

    $check($activation->resendActivationEmail('invalide') !== [], 'Une adresse invalide doit être refusée.');
    $check($activation->resendActivationEmail('unknown_' . $email) === [], 'Une adresse inconnue doit recevoir la réponse générique.');
    $check(count($messages) === 1 && $findUser($login) === $original, 'Ces demandes ne doivent ni envoyer d’email ni modifier le compte.');
    foreach (['', 'incorrect', str_repeat('a', 64)] as $invalidToken) {
        $check(!$activation->activate($invalidToken), 'Un jeton invalide ou inconnu doit être refusé.');
    }
    $check($findUser($login) === $original, 'Un jeton invalide ne doit pas modifier le compte.');

    $check($activation->resendActivationEmail(' ' . strtoupper($email) . ' ') === [], 'Le renvoi doit normaliser l’adresse email.');
    $secondToken = $lastToken();
    $check(count($messages) === 2 && $messages[1]['recipient'] === $email, 'Le renvoi doit envoyer un nouveau lien au même compte.');
    $check($secondToken !== $firstToken && !$activation->activate($firstToken), 'Le renvoi doit invalider le précédent lien.');
    $check($activation->activate($secondToken), 'Le nouveau lien doit activer le compte.');
    $active = $findUser($login);
    $check($active['id'] === $original['id'] && $active['activation'] === true && $active['activation_token_hash'] === null, 'L’activation doit conserver le compte et consommer son jeton.');
    $check(!$activation->activate($secondToken), 'Un lien déjà utilisé doit être refusé.');
    $check($activation->resendActivationEmail($email) === [], 'Un compte actif doit recevoir la réponse générique.');
    $check(count($messages) === 2 && $findUser($login) === $active, 'Un compte actif ne doit recevoir aucun nouveau lien.');

    $mailSucceeds = false;
    $failedLogin = $login . '_failed';
    $failedEmail = $failedLogin . '@example.test';
    $errors = $registration->register($failedLogin, $failedEmail, $password, $password);
    $check(isset($errors['activation_email']), 'L’échec d’envoi initial doit signaler que le compte a été créé.');
    $pending = $findUser($failedLogin);
    $failedFirstToken = $lastToken();
    $check($pending['activation'] === false && $pending['activation_token_hash'] === hash('sha256', $failedFirstToken), 'Le compte inactif doit être conservé après échec d’envoi.');

    // Vérifier la proposition de renvoi telle qu’elle est rendue après l’inscription.
    $values = ['login' => $failedLogin, 'email' => $failedEmail];
    $success = false;
    $csrfToken = bin2hex(random_bytes(32));
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ob_start();
    try {
        require dirname(__DIR__) . '/templates/register.php';
        $html = ob_get_contents();
    } finally {
        ob_end_clean();
    }
    $check(str_contains($html, 'href="/activate.php"') && str_contains($html, 'Renvoyer l’email d’activation'), 'Un accès au renvoi doit être proposé après échec d’envoi initial.');
    $check(str_contains($html, $escape($errors['activation_email'])), 'Le résultat doit expliquer que le compte existe déjà.');

    $check($activation->resendActivationEmail($failedEmail) !== [], 'L’échec d’un renvoi doit être signalé.');
    $failedSecondToken = $lastToken();
    $check($findUser($failedLogin)['activation_token_hash'] === hash('sha256', $failedSecondToken), 'Même si l’envoi échoue, le renvoi doit conserver le nouveau jeton.');
    $check(!$activation->activate($failedFirstToken), 'L’ancien lien doit rester invalidé après échec du renvoi.');

    $mailSucceeds = true;
    $check($activation->resendActivationEmail($failedEmail) === [], 'Un nouvel essai doit pouvoir envoyer le lien du compte conservé.');
    $recoveredToken = $lastToken();
    $check(!$activation->activate($failedSecondToken) && $activation->activate($recoveredToken), 'Seul le dernier lien doit activer le compte après reprise.');
    $check($findUser($failedLogin)['id'] === $pending['id'], 'La reprise doit activer le compte conservé, sans en créer un autre.');

    echo $checks . " vérifications réussies (activation, renvoi, liens invalidés, échecs d’envoi et reprise).\n";
} finally {
    $database->rollBack();
}
