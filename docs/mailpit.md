# Aide-mémoire Mailpit — Camagru

Mailpit capture les emails envoyés par ton application pour les consulter pendant
le développement. Dans la configuration du projet, ils restent dans Mailpit :
ils ne sont pas livrés dans une vraie boîte Gmail ou autre.

## Démarrer et ouvrir la boîte

Depuis la racine du projet, avec Docker démarré :

```sh
make
```

Ouvre ensuite **http://localhost:8025** dans ton navigateur. Si tu as changé
`MAILPIT_PORT` dans `.env`, utilise ce port à la place de `8025`.

Clique sur un message pour consulter son contenu, son destinataire et ses
en-têtes. Tu peux utiliser une adresse fictive comme `test@example.test`.

## Qui communique avec qui ?

```text
Ton code PHP : mail(...)
        ↓
Le programme « mailpit sendmail » dans le conteneur web
        ↓ SMTP vers mailpit:1025
Le conteneur Mailpit capture le message
        ↓
Tu le consultes sur http://localhost:8025
```

| Adresse | Utilisation |
| --- | --- |
| `http://localhost:8025` | Lire les emails dans ton navigateur |
| `mailpit:1025` | Recevoir les emails depuis le conteneur `web` |

**8025 est le port de consultation ; 1025 est le port SMTP d'envoi.**
Le port SMTP reste interne au réseau Docker dans ce projet.
Voir les [ports et le fonctionnement Docker de Mailpit](https://mailpit.axllent.org/docs/install/docker/).

## Envoyer un email depuis PHP

Dans du code exécuté par le conteneur `web` :

```php
<?php
$destinataire = 'test@example.test';
$sujet = 'Test Camagru';
$message = "Bonjour !\r\n\r\nCeci est un email de test.\r\n";
$entetes = [
    'From' => 'Camagru <no-reply@camagru.test>',
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
];

$accepte = mail($destinataire, $sujet, $message, $entetes);

if (!$accepte) {
    error_log('Le transport email a refusé le message.');
}
```

Les quatre arguments correspondent au destinataire, au sujet, au contenu et aux
en-têtes. `From` indique l'expéditeur ; `Content-Type` précise le format du contenu.
Garde l'expéditeur fixe et valide l'adresse du destinataire avant de l'utiliser.

`mail()` retourne `true` si le transport a accepté le message. Cela ne prouve pas
une réception dans une boîte réelle. Pour le test local, vérifie son apparition
dans Mailpit. Voir le [manuel PHP de mail()](https://www.php.net/manual/fr/function.mail.php).

## Tester directement depuis le terminal

Cette commande envoie un message de test à Mailpit, sans créer de page PHP :

```sh
docker compose exec -T web php <<'PHP'
<?php
$ok = mail(
    'test@example.test',
    'Test Mailpit',
    "Test depuis le conteneur PHP.\r\n",
    ['From' => 'no-reply@camagru.test']
);
var_dump($ok);
PHP
```

Résultat attendu : `bool(true)`, puis le message dans **http://localhost:8025**.
Le code est exécuté dans Docker, avec la configuration PHP du projet.

## Configuration déjà présente

Dans `docker/php.ini` :

```ini
sendmail_path = "/usr/local/bin/mailpit sendmail -S mailpit:1025"
```

PHP appelle ce programme lorsqu'il exécute `mail()`. Le Dockerfile copie le
binaire Mailpit dans l'image `web`. L'option `-S` choisit le serveur SMTP.
Voir la [configuration sendmail de Mailpit](https://mailpit.axllent.org/docs/install/sendmail/).

Si tu modifies `docker/php.ini`, relance `make` pour reconstruire l'image.
Une modification de ton code PHP est prise en compte sans reconstruire.

## Si aucun email n'apparaît

Vérifie l'état des services :

```sh
docker compose ps web mailpit
```

Lis leurs logs, puis retente l'envoi :

```sh
docker compose logs -f web mailpit
```

Vérifie la configuration utilisée par PHP :

```sh
docker compose exec web php -r 'echo ini_get("sendmail_path"), PHP_EOL;'
```

La valeur attendue contient `mailpit sendmail -S mailpit:1025`.
Dans le conteneur `web`, `localhost` désigne ce même conteneur : le serveur SMTP
doit donc être désigné par le nom de service **`mailpit`**.

Les emails sont temporaires : aucun volume Mailpit n'est configuré dans
`compose.yaml`. Une recréation du conteneur peut les faire disparaître.

## Pour confirmer un compte

Ton code PHP devra préparer un lien contenant un jeton aléatoire associé à
l'utilisateur, avec une expiration, puis envoyer ce lien avec `mail()`.
Construis l'adresse du site à partir de `APP_URL`, qui est accessible au navigateur.

Tu ouvres ensuite le message dans Mailpit et cliques sur le lien. La page PHP de
confirmation doit vérifier le jeton, activer le compte et rendre le jeton
inutilisable après validation. **Mailpit sert à lire l'email ; la validation du
compte est faite par ton application.**
