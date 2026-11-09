# Organisation des pages PHP

Les URL restent dans `public/`. Chaque point d’entrée prépare les données,
appelle les services métier puis choisit sa réponse. Les mécanismes communs
sont regroupés dans `src/Http/` et `src/View/`.

| Emplacement | Rôle |
| --- | --- |
| `src/bootstrap.php` | Chargement automatique des classes `Camagru` et de la fonction d’échappement |
| `src/Http/Csrf.php` | Création, vérification et renouvellement des jetons par action |
| `src/Http/Session.php` | Session, identité connectée, renouvellement, déconnexion et messages temporaires |
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
données puis termine la requête. Les messages et les codes d’erreur propres
à chaque action restent dans son point d’entrée.

Après une opération suivie d’une redirection :

```php
Session::flash('example_success', true);
Response::redirect('/example.php');
```

La page suivante consomme le message une seule fois avec
`Session::pullFlash('example_success', false)`.

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
issus d’un autre formulaire ou d’une autre session, ainsi que le rendu des pages.
Les autres scénarios couvrent les opérations métier, les sessions authentifiées,
les renouvellements de jetons, les images et les notifications.
