# Organisation des pages PHP

Les URL restent dans `public/`. Chaque point d’entrée prépare les données,
appelle les services métier puis choisit sa réponse. Les mécanismes communs
sont regroupés dans `src/Http/` et `src/View/`.

| Emplacement | Rôle |
| --- | --- |
| `src/bootstrap.php` | Chargement automatique des classes `Camagru` et de la fonction d’échappement |
| `src/Http/Csrf.php` | Création, vérification et renouvellement des jetons par action |
| `src/Http/Session.php` | Session, identité connectée (`login`, `forget`, `userId`), renouvellement, déconnexion et messages temporaires |
| `src/Http/Response.php` | Méthodes autorisées, redirections et réponses JSON |
| `src/View/Page.php` | Rendu du header, du contenu et du footer avec des données explicites |
| `src/View/functions.php` | Échappement HTML commun |
| `src/Services/` | Règles métier |
| `src/Repositories/` | Requêtes SQL |
| `templates/` | Contenu HTML propre à chaque page |

## Ajouter une page

Dans `public/example.php` :

```php
<?php
declare(strict_types=1);

use Camagru\Http\Response;
use Camagru\Http\Session;
use Camagru\View\Page;

require_once dirname(__DIR__) . '/src/bootstrap.php';

Session::start();
Response::allowMethods('GET');

Page::render(
    'example',
    ['message' => 'Bienvenue'],
    title: 'Exemple — Camagru',
    styles: ['/assets/css/example.css']
);
```

Dans `templates/example.php` :

```php
<main>
    <h1><?= $escape($message) ?></h1>
</main>
```

Quand une page redirige les visiteurs déjà connectés (ou, à l'inverse, les
visiteurs anonymes), cette redirection précède `Response::allowMethods()` :
une méthode non prévue reçoit donc d'abord la redirection, pas un 405. C'est le
comportement d'origine, conservé par le refactoring.

Le nom du template est une constante choisie dans le code. Le template reçoit
les données passées à `Page::render()`, ainsi que `$escape` et `$isLoggedIn`.
Les variables locales du point d’entrée ne sont pas implicitement partagées.
Le titre, les feuilles de style et les scripts sont des arguments nommés du rendu.
Le header et le footer sont inclus une seule fois par `Page`.

Les templates affichent les données ; la préparation de la session et du jeton
de déconnexion se fait avant leur affichage. Les endpoints `image.php` et
`overlay.php` chargent le bootstrap sans démarrer de session ni rendre de page.

## Formulaires et CSRF

Chaque action conserve son propre jeton : `login`, `registration`,
`activation_resend`, `pwdchange`, `recovery`, `settings`, `gallery`, `camedit`
et `logout`.

- `Csrf::token('action')` retourne le jeton existant ou en crée un.
- `Csrf::isValid('action', $_POST['csrf_token'] ?? null)` vérifie sans créer
  de jeton. Une valeur absente, vide ou non textuelle est refusée.
- `Csrf::renew('action')` remplace le jeton après l’opération concernée.

Le jeton est transmis à la vue dans `$csrfToken`. Un formulaire affiche :

```php
<input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
```

Les jetons restent stables entre deux affichages pour permettre plusieurs
onglets. Les points de renouvellement sont conservés : après inscription,
connexion, sauvegarde des paramètres, demande ou exécution de récupération,
et renvoi d’activation réussi. La galerie et l’éditeur réutilisent leur jeton.
La validation CSRF ne remplace pas le contrôle de connexion ou de propriété.

## Réponses et messages temporaires

`Response::redirect()` utilise une redirection 303 par défaut et termine la
requête. `Response::json()` fixe le code et le type de contenu, encode les
données avec `JSON_THROW_ON_ERROR` puis termine la requête : une erreur
d'encodage devient une erreur 503 dans `camedit.php`, au lieu d'échouer en
silence. Les messages et les codes d’erreur propres
à chaque action restent dans son point d’entrée.

Après une opération suivie d’une redirection :

```php
Session::flash('example_success', true);
Response::redirect('/example.php');
```

La page suivante consomme le message une seule fois avec
`Session::pullFlash('example_success', false)`.

## Changements de comportement du refactoring

Le découpage garde les URL, les redirections et les messages, avec ces
durcissements volontaires :

- `Session::start()` envoie `Cache-Control: no-store` sur toutes les pages de
  session, `profile.php` comprise.
- `Session::logout()` expire aussi le cookie de session, y compris quand
  `settings.php` détecte un compte disparu.
- `Session::regenerate()` lève une exception si le renouvellement échoue ;
  `settings.php` répond alors 503 (l'ancien appel n'était pas vérifié).
- `Response::json()` ne fixe `Content-Type` que sur les réponses JSON ;
  `camedit.php` ne le fixe plus avant les contrôles de méthode et de connexion.

## Conventions et vérification

Les classes suivent leur namespace dans `src/`, utilisent des types explicites
et les conventions de présentation [PSR-12](https://www.php-fig.org/psr/psr-12/).
Le mécanisme CSRF suit le modèle de jeton synchronisé conservé en session
décrit par [OWASP](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html).
L’échappement HTML utilise `ENT_QUOTES | ENT_SUBSTITUTE` en UTF-8 ; l’encodage
des paramètres d’URL reste distinct, avec `rawurlencode()`.

Avec les conteneurs démarrés, exécuter les scénarios dans l’ordre :

```sh
docker compose exec -T web sh -c 'for test in tests/*.php; do php "$test" || exit; done'
```

`tests/http.php` vérifie les réponses communes, les jetons absents, mal formés,
issus d’un autre formulaire ou d’une autre session, le rendu des formulaires,
de l'accueil et de la galerie, et les redirections des visiteurs anonymes.
Le rendu des pages authentifiées et le renouvellement des jetons de
`recovery` et d'`activation_resend` ne sont pas couverts par ce scénario.
Les autres scénarios couvrent les opérations métier, les sessions authentifiées,
les renouvellements de jetons, les images et les notifications.
