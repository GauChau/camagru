# Camagru — coquille de départ

Structure basée sur les sections 5 et 6 (étape 2) de [GUIDE_CAMAGRU.md](GUIDE_CAMAGRU.md).
Apache/PHP, PostgreSQL et le transport mail local sont configurés. L'inscription
enregistre les utilisateurs dans PostgreSQL. La connexion et la confirmation
des comptes par email restent à implémenter.

## Lancer

Prérequis : Make, Docker avec Compose et un moteur démarré (OrbStack ou Docker Desktop).
Apache et PHP s'installent dans le conteneur ; aucune installation système n'est nécessaire.

Le `.env` local est déjà préparé sur cette machine. Depuis une copie du dépôt :

```sh
cp .env.example .env
```

Remplacer `DB_PASSWORD` dans `.env` par un mot de passe local, puis lancer :

```sh
make
```

- Site : http://localhost:8080
- Emails de développement : http://localhost:8025
- PostgreSQL : hôte `db`, port `5432`, accessible depuis les conteneurs.

Les ports se règlent dans `.env`. Si `APP_PORT` change, adapter aussi `APP_URL`.
Le code PHP lira les variables transmises au conteneur avec `getenv()`.
Les fichiers de `public/`, `src/` et `templates/` sont montés depuis le projet :
les modifications sont visibles sans reconstruction. Après un changement de
configuration dans `docker/` ou du Dockerfile, relancer `make`.

## Où écrire

| Emplacement | À compléter |
| --- | --- |
| `public/index.php` | Réception et routage des requêtes |
| `public/assets/` | CSS, JavaScript natif, PNG transparents |
| `src/Controllers/` | Actions HTTP |
| `src/Services/` | Logique métier et contrôles communs |
| `src/Repositories/` | Requêtes SQL via PDO |
| `templates/` | Pages HTML rendues par PHP |
| `database/001_schema.sql` | Tables et contraintes |
| `storage/` | Montages ; volume Docker hors racine web |
| `tests/` | Scénarios et vérifications à écrire |

Seul `public/` est exposé par Apache. Le volume `storage` est accessible dans le
conteneur à `/var/www/html/storage`, avec les droits de l'utilisateur Apache.
Son contenu n'apparaît pas dans le dossier `storage/` du Mac.

## Base et persistance

Les fichiers SQL de `database/` sont exécutés par PostgreSQL au premier démarrage
d'un volume vide. `001_schema.sql` crée la table `users` avec un identifiant
automatique et des index uniques séparés pour le login et l'email, sans distinction
de casse.

Pour une base contenant déjà l'ancienne table `users`, appliquer uniquement la
migration suivante. Elle ajoute l'identifiant automatique et les index uniques,
en conservant les lignes existantes, même incomplètes :

```sh
docker compose exec -T db sh -c 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < database/002_users_registration.sql
```

Les volumes `postgres_data` et `storage` conservent les données après un arrêt ou
une recréation des conteneurs. `docker compose down` les conserve.
`docker compose down -v` les supprime, ainsi que toutes leurs données.
Changer les identifiants PostgreSQL dans `.env` ne modifie pas une base déjà créée.

## Exemple : inscription

Le formulaire HTML envoie un `POST` à `/register.php`, sans besoin de `fetch()`.
Le parcours est réparti entre :

- `public/register.php` : réception des champs, protection CSRF et réponse HTTP.
- `src/Database.php` : connexion PDO à partir des variables transmises par Compose.
- `src/Services/RegistrationService.php` : validation, contrôle des doublons et
  hachage avec `password_hash()` / Argon2id, disponible dans l'image PHP utilisée.
- `src/Repositories/UserRepository.php` : recherches et insertion via des requêtes
  préparées. La colonne `password` reçoit uniquement le hash.
- `templates/register.php` : formulaire, erreurs et confirmation d'enregistrement.

Le mot de passe doit contenir **au moins 10 caractères et au moins un chiffre**.
Le serveur vérifie aussi sa confirmation et le format de l'email. Le login et
l'email sont uniques sans distinction de casse, avec une protection SQL contre
les inscriptions simultanées. Après insertion, une redirection évite de renvoyer
le formulaire au rafraîchissement. Les mots de passe ne sont jamais réaffichés.

Pour relancer les tests sur les services démarrés :

```sh
docker compose exec -T web php tests/register.php
```

Ils vérifient le parcours HTTP, les champs invalides, les doublons, le hash et
les contraintes PostgreSQL. Ils créent des comptes portant un préfixe aléatoire
et les suppriment à la fin.

## Calques de la webcam

Les PNG transparents du stock sont placés dans `storage/calcs/`. Créer ce dossier
s’il n’existe pas, puis y ajouter les images : elles apparaissent automatiquement
dans l’éditeur, sans reconstruction. Chaque fichier doit être un PNG de 5 Mo et
4096 × 4096 pixels maximum. Une combinaison trop grande pour la mémoire PHP est
refusée avec un message invitant à réduire la résolution ou la taille du calque.
Le stock reste local et est ignoré par Git.

Choisir un calque puis activer la webcam, ou l’inverse. Le bouton de capture
reste désactivé tant que les deux ne sont pas prêts. Le serveur redimensionne le
calque à la taille entière de la capture, conserve sa transparence et enregistre
le montage. L’aperçu affiche ensuite ce montage.

Les tests HTTP créent leurs propres PNG, un compte et des captures temporaires,
puis les suppriment :

```sh
docker compose exec -T web php tests/overlays.php
```

L’éditeur est réservé aux utilisateurs connectés. Sa section « Mes créations »
affiche les miniatures de tous les montages du compte connecté, du plus récent au
plus ancien. Les nouvelles captures s’ajoutent immédiatement en tête de liste et
restent présentes après rechargement. Cliquer sur une miniature ouvre le montage
en grand. Sur mobile, cette section passe sous l’éditeur.

## Activation du compte

Le module d’activation gère la création du compte inactif, le lien envoyé par
email, son renouvellement et sa consommation. Si le premier email ne peut pas
être envoyé, le compte est conservé et l’inscription propose un accès au renvoi.
Un renvoi remplace le précédent lien, même si l’envoi échoue.

Les tests utilisent PostgreSQL et un envoi d’email simulé. Toutes leurs écritures
sont annulées à la fin :

```sh
docker compose exec -T web php tests/activation.php
```

## Galerie publique

`/gallery.php` affiche les montages de tous les utilisateurs, du plus récent au
plus ancien, à raison de six par page. Les visiteurs peuvent consulter les
images et commentaires ; les utilisateurs connectés peuvent aimer, retirer leur
like et publier des commentaires (2 000 caractères maximum).
Les commentaires sont affichés par groupes de dix, du plus récent au plus ancien.

Chaque commentaire déclenche un email à l’auteur du montage. Cette notification
est active par défaut et peut être désactivée dans `/settings.php`. En local,
les emails sont capturés par Mailpit.

Sur une base déjà initialisée, appliquer la migration avant d’utiliser la galerie
ou les paramètres du compte :

```sh
docker compose exec -T db sh -c 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < database/004_gallery.sql
docker compose exec -T web php tests/gallery.php
```

Les tests HTTP créent puis suppriment leurs propres comptes, images et emails.

## Paramètres du compte

Les utilisateurs connectés accèdent à `/settings.php` avec l’engrenage situé en
haut à droite du header. Ils peuvent modifier leur login, leur email et leur mot
de passe en confirmant leur mot de passe actuel. Les champs de nouveau mot de
passe sont facultatifs ; s’ils sont remplis, le mot de passe doit contenir au
moins 10 caractères et un chiffre, avec confirmation. Le login et l’email restent
uniques sans distinction de casse.

Les tests créent deux comptes temporaires puis les suppriment automatiquement :

```sh
docker compose exec -T web php tests/settings.php
```

## Emails locaux

PHP dispose de GD (JPEG/PNG), Fileinfo et PDO PostgreSQL. `mail()` utilise le
transport fourni par Mailpit vers `mailpit:1025`. Les messages restent dans
Mailpit pour inspection ; la livraison à une vraie boîte sera à configurer plus
tard. Les emails capturés sont temporaires et disparaissent à la recréation de Mailpit.

Voir l'[aide-mémoire Mailpit](docs/mailpit.md) pour envoyer un email depuis PHP,
tester le transport et diagnostiquer un problème.

## Commandes utiles

```sh
make ps
make logs
docker compose exec web php -m
make stop
make up
make build
make restart
make config
make down
```

`make` (ou `make up`) construit les images, démarre les services en arrière-plan
et attend qu'ils soient prêts. `make down` retire les conteneurs en conservant
les volumes de données.

`make build` construit les images sans démarrer les services. `make restart`
redémarre les conteneurs existants ; pour appliquer les changements du `.env`
ou de `compose.yaml`, relancer `make`. `make config` affiche la configuration
résolue, y compris les valeurs du `.env` utilisées par Compose.

`make delete-volumes` retire les conteneurs et supprime les volumes du projet,
donc les données PostgreSQL et les fichiers du volume `storage`.

Versions fixées : PHP `8.5.11-apache-trixie`, PostgreSQL `18.6-trixie`,
Mailpit `v1.31.5`. Les mises à jour se font explicitement dans le Dockerfile et
`compose.yaml`, suivies d'une reconstruction.

Vérifications réalisées lors de la préparation : démarrage des trois services,
configuration Apache et syntaxe PHP, réponse HTTP de la page, fichiers internes
inaccessibles, extensions présentes, connexion SQL, absence de tables applicatives,
écriture dans le stockage par Apache et réception d'un email de test dans Mailpit.
Le message de test a été supprimé. Aucun compte ni contenu applicatif n'a été créé.

Références d'infrastructure : [image PHP officielle](https://hub.docker.com/_/php),
[image PostgreSQL officielle](https://hub.docker.com/_/postgres),
[Mailpit Docker](https://mailpit.axllent.org/docs/install/docker/),
[transport sendmail Mailpit](https://mailpit.axllent.org/docs/install/sendmail/).
# camagru
