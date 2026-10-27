# Camagru : comprendre le projet et le réaliser pas à pas

Guide basé sur le [sujet Camagru version 4.1](/Users/gautierchauvot/Documents/42/camagru/en.subject.pdf), lu intégralement, et sur les documentations officielles liées ci-dessous. Recommandations vérifiées le 14 octobre 2026.

Les mentions **Obligatoire**, **Recommandation** et **Bonus** distinguent les exigences du sujet de mes choix de réalisation. Les numéros de pages ci-dessous sont les pages du fichier PDF ; la pagination imprimée est décalée d'une page.

## 1. Ce que tu dois construire

Camagru est une petite application de partage de photomontages. Un utilisateur crée un compte, le confirme par email, se connecte, prend une photo avec sa webcam ou importe une image, choisit un élément transparent à superposer et publie le montage. Les visiteurs consultent une galerie publique ; les utilisateurs connectés peuvent aimer et commenter les créations.

Le projet te fait travailler une application web complète : interface, serveur, base de données, fichiers, emails, sécurité et déploiement. La difficulté principale est de faire fonctionner ces éléments ensemble et de contrôler les données à chaque frontière.

**Point central : le serveur doit fabriquer l'image finale.** Un aperçu dans le navigateur peut aider l'utilisateur, mais recevoir une image déjà montée et simplement la sauvegarder ne satisfait pas cette exigence.

L'objectif général évoque la photo et la vidéo. La partie obligatoire détaille un parcours de capture photo et de montage ; elle n'impose pas un éditeur vidéo. Le GIF animé est une piste de bonus.

### Le parcours à obtenir

```text
Inscription → email de confirmation → activation → connexion
                                                    ↓
                              webcam OU import + superposition choisie
                                                    ↓
                                 validation et montage côté serveur
                                                    ↓
                                   fichier enregistré + entrée SQL
                                                    ↓
                                  galerie publique → likes/commentaires
                                                    ↓
                                  email au propriétaire si activé
```

## 2. Les exigences du sujet

### 2.1 Contraintes générales

| Exigence obligatoire | Conséquence concrète | PDF |
| --- | --- | --- |
| Serveur : langage libre, avec un équivalent dans la bibliothèque standard PHP pour chaque fonction utilisée | PHP est le chemin le plus direct ; un autre langage demande de justifier les équivalences | p. 6, 10 |
| Client : HTML, CSS et JavaScript, API natives du navigateur uniquement | Construire l'interface et les interactions sans dépendance JavaScript tierce | p. 6, 10 |
| Restrictions sur les frameworks et bibliothèques externes | Le sujet interdit ceux que tu n'écris pas et qui n'ont pas d'équivalent PHP standard ; ne pas supposer qu'un framework complet est autorisé | p. 6, 10 |
| Framework CSS toléré s'il n'ajoute pas de JavaScript interdit | Une feuille de style seule peut convenir ; CSS maison suffit | p. 6, 10 |
| Conteneurisation à jour et démarrage en une commande | Fournir Docker Compose ou un équivalent, avec configuration et initialisation documentées | p. 6, 10 |
| Aucun message d'erreur, warning ou ligne de log dans les consoles serveur/client | Corriger les causes et retirer les traces de debug ; exception explicitement tolérée pour les erreurs `getUserMedia()` liées à l'absence de HTTPS | p. 6 |
| Secrets et variables d'environnement dans un `.env` local ignoré par Git | Aucun identifiant ou secret réel dans les fichiers versionnés | p. 6 |
| Compatibilité minimale Firefox 41 et Chrome 46 | Vérifier la disponibilité des API choisies et prévoir les adaptations nécessaires | p. 6 |
| Header, contenu principal, footer et adaptation mobile | Layout lisible sur petit écran | p. 7 |
| Validation des formulaires et sécurité | Contrôles serveur pour SQL, XSS, CSRF, fichiers et permissions | p. 7 |
| Rendu dans le dépôt Git | Le code, la configuration et les ressources nécessaires doivent être dans le dépôt | p. 13 |

**Attention aux interprétations :** le sujet n'impose ni PHP, ni PostgreSQL, ni MVC, ni Docker Compose spécifiquement. Ce sont des choix possibles. La structure MVC est suggérée, pas obligatoire. Le texte ne fournit pas une liste exhaustive des extensions PHP admises : PDO, GD et Fileinfo sont ici recommandés parce qu'ils font partie des fonctionnalités documentées de PHP, avec activation selon l'image utilisée. Leur présence n'est pas automatique ; documente-les. Cela ne constitue pas une autorisation générale pour les paquets Composer ou npm.

### 2.2 Comptes et préférences

**Obligatoire, PDF p. 8 :**

- Inscription avec au moins une adresse email valide, un nom d'utilisateur et un mot de passe suffisamment complexe.
- Confirmation du compte par un lien unique envoyé à l'adresse renseignée.
- Connexion avec le nom d'utilisateur et le mot de passe.
- Demande de réinitialisation du mot de passe par email.
- Déconnexion en un clic depuis toutes les pages.
- Modification du nom d'utilisateur, de l'adresse email et du mot de passe après connexion.
- Préférence de notification des commentaires, activée par défaut et désactivable.

Le sujet ne fixe pas la longueur exacte des mots de passe ni la durée de vie des liens. Tu dois choisir des règles, les appliquer et les expliquer.

### 2.3 Galerie

**Obligatoire, PDF p. 8 :**

- Galerie publique avec les images montées de tous les utilisateurs, triées par date de création.
- Likes et commentaires réservés aux utilisateurs connectés.
- Email au propriétaire lorsqu'un nouveau commentaire arrive, si sa préférence l'autorise.
- Pagination avec au moins cinq éléments par page. Prévois une capacité de cinq ou plus ; la dernière page peut naturellement en contenir moins.

**Recommandation :** afficher les plus récentes en premier, avec un second tri par identifiant pour stabiliser l'ordre quand deux dates sont identiques. Le sens du tri n'est pas précisé dans le sujet.

### 2.4 Éditeur

**Obligatoire, PDF p. 9 :**

- Accès réservé aux utilisateurs authentifiés ; refuser aussi les appels directs aux actions privées.
- Zone principale avec aperçu webcam, liste de superpositions et bouton de capture.
- Zone latérale avec les miniatures des photos précédentes. Dans ce guide, elle affiche les créations de l'utilisateur connecté.
- Superpositions sélectionnables ; bouton de capture désactivé tant qu'aucune n'est choisie.
- Création et superposition finales côté serveur.
- Import d'une image comme alternative à la webcam.
- Suppression des créations du propriétaire uniquement.

Les éléments à superposer doivent comporter de la transparence, autrement ils masqueraient la photo (PDF p. 5). Le schéma de la p. 9 illustre le layout attendu ; il n'impose pas ses dimensions exactes.

## 3. Les outils que je recommande

Le « meilleur » outil dépend du sujet. Ici, je privilégie des outils qui rendent les mécanismes visibles, limitent les dépendances et permettent de défendre ton code.

### 3.1 Une stack cohérente pour démarrer

**Choix recommandé : PHP sans framework + Apache + PostgreSQL via PDO + GD + HTML/CSS/JavaScript natifs + Docker Compose.**

Pour PHP, choisis une version stable maintenue et fixe sa version dans les conteneurs. Au moment de cette vérification, PHP 8.5 est une branche en support actif. Vérifie les correctifs disponibles au moment de l'installation. [Versions PHP maintenues](https://www.php.net/supported-versions.php).

| Tâche | Outil recommandé | Pourquoi / point d'attention |
| --- | --- | --- |
| Serveur et logique métier | PHP sans framework | Évite de transposer les fonctionnalités vers les équivalents PHP ; écrire un petit routeur suffit |
| Servir le site | [Apache](https://httpd.apache.org/docs/2.4/) avec PHP dans un conteneur | Évite de gérer immédiatement un couple Nginx/PHP-FPM ; le serveur intégré PHP reste une option de développement |
| Stocker comptes, images et interactions | PostgreSQL + `pdo_pgsql` | Relations, contraintes et SQL explicite ; prévoir le pilote PHP et un volume persistant |
| Accéder aux données | PDO, requêtes préparées | Interface PHP documentée, sans ORM ; paramètres séparés du texte SQL |
| Produire le montage | Extension PHP GD | Décoder la photo, redimensionner et superposer un PNG transparent côté serveur |
| Authentifier | Sessions PHP et fonctions de mot de passe | Pas besoin de JWT pour un site classique rendu côté serveur |
| Liens uniques et protection CSRF | `random_bytes()`, `hash()`, `hash_equals()` | Générer des valeurs imprévisibles et vérifier les jetons |
| Valider les images importées | Fileinfo + décodage GD + limites de taille et dimensions | Ne pas faire confiance à l'extension ni au type annoncé par le navigateur |
| Capturer la webcam | `getUserMedia()`, élément `<video>`, Canvas | API du navigateur ; permission utilisateur et contexte adapté nécessaires |
| Envoyer une capture | `toBlob()` + `FormData` pour les navigateurs compatibles | Transport d'un fichier ; prévoir une alternative si l'API manque dans les versions minimales |
| Interface | HTML sémantique, CSS maison, JavaScript natif | Moins de dépendances à justifier ; formulaires classiques pour commencer |
| Mise en page | Flexbox et media queries | Suffisant pour l'éditeur et la galerie ; vérifier la compatibilité du CSS retenu |
| Envoyer les emails | `mail()` et un transport système configuré | Le code PHP reste simple ; `mail()` seul ne configure pas la livraison |
| Observer les emails en développement | Mailpit dans un conteneur | Capture les messages et affiche les liens ; outil d'infrastructure de test |
| Lancer tous les services | Docker Compose | Configuration commune et commande unique |
| Déboguer l'interface et HTTP | DevTools de Firefox/Chrome | Inspecter DOM, réseau, cookies et console |
| Vérifier les routes | `curl` et navigateur | Tester une route sans passer par les boutons, notamment les permissions |
| Explorer la base | Client [`psql`](https://www.postgresql.org/docs/current/app-psql.html) | Inspecter tables et contraintes sans dépendance dans le site |
| Préparer les PNG transparents | [GIMP](https://www.gimp.org/) ou un éditeur d'image équivalent | Créer quelques superpositions locales, dont tu peux vérifier la transparence |
| Versionner | Git et `.gitignore` | Petits commits compréhensibles ; exclure secrets et fichiers générés |

Fondements techniques : [PDO et ses pilotes](https://www.php.net/manual/en/book.pdo.php), [requêtes préparées](https://www.php.net/manual/en/pdo.prepared-statements.php), [GD](https://www.php.net/manual/en/book.image.php), [capture webcam](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia), [Canvas `toBlob()`](https://developer.mozilla.org/en-US/docs/Web/API/HTMLCanvasElement/toBlob), [Docker Compose](https://docs.docker.com/compose/).

**Alternative plus légère :** SQLite via PDO enlève le service de base de données. C'est raisonnable pour un petit projet local ; PostgreSQL est mon choix ici pour apprendre aussi une base séparée et ses contraintes. MySQL/MariaDB est une autre possibilité. Choisis une seule base et garde-la pendant la réalisation.

### 3.2 Dépendances : rester dans le cadre

Pour cette réalisation, je recommande de ne pas introduire React, Vue, Angular, jQuery, Axios, Laravel, Symfony, Express, un ORM, PHPMailer ou une bibliothèque de traitement d'image tierce. Ils ajoutent des dépendances et des équivalences à défendre, alors que les fonctions nécessaires existent dans PHP ou dans le navigateur.

Un serveur web, une base SQL et un transport mail sont des services d'infrastructure. Mailpit est un outil de test, pas une bibliothèque chargée par le site. Documente tout de même ces choix et leur rôle. Ne déduis pas de cette distinction que toute dépendance externe serait autorisée.

L'éditeur de code, les DevTools, `curl`, `psql` et l'éditeur d'images servent à travailler. Ils ne doivent pas devenir des prérequis manuels pour faire démarrer l'application rendue.

### 3.3 Le piège des emails

`mail()` remet un message au transport configuré ; un retour positif ne prouve pas sa réception. Dans un conteneur Linux, configure le transport compatible sendmail et sa destination. Une configuration PHP pointant vaguement vers un serveur SMTP ne suffit pas à assurer la livraison. [Documentation PHP de `mail()`](https://www.php.net/manual/en/function.mail.php).

Pendant le développement, utilise Mailpit pour contrôler destinataire, contenu et liens. Pour satisfaire le parcours du sujet, prévois aussi un transport capable de livrer les messages à une vraie boîte et teste la réception. Un message visible dans Mailpit prouve le parcours local, pas une livraison externe. [Mailpit](https://mailpit.axllent.org/docs/), [configuration sendmail](https://mailpit.axllent.org/docs/install/sendmail/).

## 4. Ce que tu dois apprendre

Apprends chaque notion avec une petite manipulation, puis réutilise-la dans le projet. Tu n'as pas besoin de connaître tout PHP avant de commencer.

| Notion | Ce qu'il faut comprendre | Exercice concret |
| --- | --- | --- |
| HTTP | Requête, réponse, URL, GET/POST, statut, redirection, en-têtes | Envoyer un formulaire et observer son aller-retour dans l'onglet Réseau |
| Navigateur et serveur | Le navigateur présente et collecte ; le serveur décide et persiste | Modifier une valeur envoyée et constater que le serveur la revérifie |
| HTML | Structure, formulaires, labels, validation ergonomique | Créer une inscription utilisable au clavier |
| CSS responsive | Taille fluide, disposition et media queries | Afficher l'éditeur en deux zones sur grand écran puis en colonne sur mobile |
| JavaScript et DOM | Événements, sélection d'éléments et états de l'interface | Activer le bouton uniquement lorsqu'une superposition est choisie |
| PHP | Tableaux, fonctions, classes simples, exceptions et entrées HTTP | Lire un formulaire, valider ses champs et rendre une page |
| Organisation du code | Séparer réception HTTP, métier, données et affichage | Déplacer une requête SQL hors d'un template |
| SQL | Tables, clés, jointures, index, contraintes et transactions | Lister les images avec leur auteur et empêcher deux likes identiques |
| Sessions et cookies | HTTP ne conserve pas naturellement l'identité entre deux requêtes | Se connecter, changer de page, puis détruire la session |
| Authentification et autorisation | Identifier quelqu'un et vérifier ses droits sont deux contrôles différents | Avec le compte B, tenter de supprimer une image du compte A |
| Hachage de mots de passe | Stocker une empreinte adaptée, jamais le mot de passe récupérable | Créer un hash puis vérifier un mot de passe correct et un incorrect |
| Jetons | Valeur imprévisible, usage précis, expiration, invalidation | Utiliser un lien de reset, puis vérifier qu'il ne fonctionne plus |
| SQL injection | Une entrée utilisateur ne doit pas devenir du code SQL | Passer une chaîne contenant une apostrophe via une requête préparée |
| XSS | Une donnée affichée peut devenir du HTML ou du JavaScript actif | Poster `<script>alert(1)</script>` et obtenir du texte inoffensif |
| CSRF | Une autre page peut tenter de provoquer une action avec les cookies de la victime | Envoyer un formulaire sans jeton et vérifier son refus |
| CORS | Le navigateur encadre certaines lectures entre origines différentes | Comprendre pourquoi même protocole/hôte/port simplifie les échanges |
| Uploads | Le nom et le type déclarés d'un fichier ne prouvent pas son contenu | Renommer un fichier texte en `.png` et vérifier son refus |
| Images et alpha | Dimensions en pixels, proportions et transparence | Superposer un PNG dont les zones transparentes laissent voir la photo |
| Webcam et Canvas | Flux vidéo, capture d'une frame et export d'image | Afficher la webcam et capturer une photo brute |
| Conteneurs | Image, conteneur, réseau, variables et volumes | Recréer les services sans perdre les photos ni les comptes |
| Vérification | Cas heureux, erreurs, entrées hostiles et droits | Exécuter une checklist avec deux comptes et un visiteur anonyme |

Pour les mots de passe, utilise `password_hash()` et `password_verify()` ; laisse PHP gérer le sel. Argon2id est un choix recommandé s'il est disponible dans ton image. Si tu utilises bcrypt, gère explicitement sa limite de 72 octets pour éviter les troncatures. Prévois une colonne assez large pour le hash. [Fonctions de hachage PHP](https://www.php.net/manual/en/function.password-hash.php).

Pour les sessions, régénère l'identifiant après connexion et configure les cookies : `HttpOnly`, `SameSite` et `Secure` sous HTTPS. Ne rends pas `Secure` obligatoire dans un environnement local HTTP où il empêcherait le parcours de fonctionner. [Sécurité des sessions PHP](https://www.php.net/manual/en/session.security.ini.php).

Pour XSS, échappe au moment de l'affichage selon le contexte. `htmlspecialchars()` convient au texte et aux attributs HTML correctement encadrés ; il ne sécurise pas automatiquement une insertion dans du JavaScript ou une URL. Côté DOM, préfère `textContent` pour afficher un commentaire. [Échappement HTML PHP](https://www.php.net/manual/en/function.htmlspecialchars.php).

Pour CSRF, ajoute un jeton lié à la session aux actions qui modifient des données et vérifie-le côté serveur. Un cookie `SameSite` est une protection complémentaire. Une règle CORS ne remplace pas ce contrôle. Héberger pages et actions sur la même origine évite généralement d'avoir à ouvrir CORS. [Prévention CSRF OWASP](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html), [CORS sur MDN](https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/CORS).

## 5. Une architecture assez simple

Cette arborescence est une proposition, pas une exigence du sujet. Les fichiers suivants sont à créer pendant la réalisation ; ce guide ne les implémente pas.

```text
camagru/
├── compose.yaml
├── Dockerfile
├── .env                 # local, ignoré par Git
├── .env.example         # noms de variables, valeurs fictives
├── .gitignore
├── README.md
├── public/              # seule racine exposée par le serveur web
│   ├── index.php        # réception et routage des requêtes
│   └── assets/
│       ├── css/
│       ├── js/
│       └── overlays/    # PNG transparents fournis par le projet
├── src/
│   ├── Controllers/     # lire la requête, appeler le métier, répondre
│   ├── Services/        # comptes, montage, emails, contrôles communs
│   └── Repositories/    # requêtes SQL via PDO
├── templates/           # pages HTML rendues par PHP
├── database/            # schéma et migrations SQL
├── storage/             # montages, hors racine web et dans un volume
└── tests/               # scénarios et petits scripts de vérification
```

Expose les images publiques par une route qui associe un identifiant à un fichier enregistré, avec un type de contenu fixe. Ne transforme pas un chemin fourni par l'utilisateur en chemin de lecture. Le `.env`, les scripts internes et les uploads bruts ne doivent pas être accessibles par URL.

**Services recommandés :** `web`, `db` et un service Mailpit pour les tests locaux. Les images et la base ont chacune leur stockage persistant. Compose charge les valeurs locales du `.env` et les transmet explicitement aux services concernés ; l'application peut les lire avec `getenv()`, sans bibliothèque dotenv. Le `.env` de Compose n'injecte pas automatiquement toutes les variables dans les conteneurs : configure leur transmission. [Variables des conteneurs avec Compose](https://docs.docker.com/compose/how-tos/environment-variables/set-environment-variables/).

### Modèle de données proposé

| Table | Champs principaux | Rôle |
| --- | --- | --- |
| `users` | `id`, `username`, `email`, `password_hash`, `email_verified_at`, `notify_comments`, `created_at` | Compte et préférences |
| `account_tokens` | `id`, `user_id`, `token_hash`, `purpose`, `expires_at`, `used_at` | Confirmation, reset et éventuellement changement d'email |
| `images` | `id`, `user_id`, `file_key`, `created_at` | Auteur et référence du montage final |
| `likes` | `user_id`, `image_id`, `created_at` | Relation entre utilisateur et création |
| `comments` | `id`, `user_id`, `image_id`, `body`, `created_at` | Commentaires |

Recommandations : unicité des noms/emails selon une règle de normalisation documentée ; clé unique sur `(user_id, image_id)` dans `likes` ; clés étrangères ; notification par défaut à `true` ; index utiles aux tris et recherches. Définis ce qui arrive aux likes et commentaires quand une image est supprimée. Les fichiers sont stockés sur disque et la base conserve leur référence.

Ces contraintes protègent aussi contre les requêtes concurrentes : une simple vérification en PHP avant insertion ne suffit pas toujours. [Contraintes PostgreSQL](https://www.postgresql.org/docs/current/ddl-constraints.html).

## 6. Le pas à pas de réalisation

Les étapes suivantes sont ordonnées pour limiter les dépendances. La sécurité intervient dès chaque fonctionnalité ; l'étape de contrôle final vérifie l'ensemble.

### Étape 1 - Transformer le sujet en critères vérifiables

**À faire :**

- [ ] Relire les exigences de la section 2 et garder la checklist finale à portée de main.
- [ ] Dessiner les pages : galerie, inscription, connexion, confirmation, oubli/reset, préférences et éditeur.
- [ ] Choisir la base et écrire la liste des dépendances avec leur justification.
- [ ] Fixer tes règles : complexité des mots de passe, formats importés, limites des images, taille des pages et expiration des liens.

**À apprendre :** distinguer une exigence, un choix d'implémentation et un bonus.

**Outils :** sujet, Markdown, croquis papier.

**Terminé quand :** chaque exigence correspond à une page, une action ou un test. Aucun bonus ne bloque le démarrage.

### Étape 2 - Obtenir un environnement reproductible

**À faire :**

- [ ] Initialiser le dépôt Git s'il n'existe pas ; préparer `.gitignore` avant les secrets.
- [ ] Ajouter `.env.example` avec des valeurs fictives et créer le `.env` local.
- [ ] Configurer PHP/Apache, PostgreSQL et les volumes avec Compose.
- [ ] Activer GD, Fileinfo et le pilote PDO de la base choisie.
- [ ] Limiter la racine web à `public/` et afficher une première page.
- [ ] Automatiser l'initialisation du schéma ; attendre que la base soit prête, pas seulement que son conteneur soit lancé.

**À apprendre :** conteneurs, réseau des services, configuration et persistance.

**Outils :** Docker Compose, Dockerfile, Git.

**Terminé quand :** après la préparation locale du `.env`, `docker compose up --build` lance un site utilisable sans création manuelle des tables. Un redémarrage conserve les données.

### Étape 3 - Créer le schéma et les fondations HTTP

**À faire :**

- [ ] Écrire les tables et contraintes proposées en section 5.
- [ ] Centraliser la connexion PDO et la gestion des erreurs attendues.
- [ ] Créer un petit routage par chemin et méthode HTTP.
- [ ] Créer les templates communs : header, main, footer, messages de succès/erreur.
- [ ] Ajouter les helpers d'échappement, de session, de CSRF et de validation.
- [ ] Utiliser des POST pour les actions de modification, puis une redirection après succès.

**À apprendre :** SQL relationnel, requête/réponse et séparation du code.

**Outils :** PDO, SQL, `psql`, DevTools.

**Terminé quand :** une requête paramétrée fonctionne, une route inconnue produit une réponse propre et les pages partagent le même layout.

### Étape 4 - Construire l'inscription et vérifier le transport mail

**À faire :**

- [ ] Valider email, nom et mot de passe côté serveur ; ajouter les aides côté navigateur.
- [ ] Gérer les doublons et stocker uniquement un hash de mot de passe.
- [ ] Créer un compte non confirmé, avec notifications activées par défaut.
- [ ] Générer un jeton aléatoire, stocker son empreinte avec une expiration et un usage « confirmation ».
- [ ] Configurer le transport de `mail()` et envoyer un lien construit depuis une URL de base de confiance dans le `.env`.
- [ ] Activer le compte lors de la confirmation et consommer le jeton ; refuser les liens invalides, expirés ou réutilisés.

**À apprendre :** validation, hachage, tokens et état du compte. Pour un jeton, `bin2hex(random_bytes(32))` est une proposition adaptée ; le jeton brut part dans le lien, son hash reste en base. [Aléatoire cryptographique PHP](https://www.php.net/manual/en/function.random-bytes.php).

**Outils :** PHP, PDO, `mail()`, transport système, Mailpit.

**Terminé quand :** un utilisateur s'inscrit, reçoit le lien et active son compte ; un lien réutilisé ne l'active pas de nouveau. Tu as aussi vérifié une livraison dans une boîte réelle avec le transport prévu.

### Étape 5 - Ajouter connexion, déconnexion et permissions

**À faire :**

- [ ] Vérifier le mot de passe et l'état confirmé du compte.
- [ ] Régénérer l'identifiant de session après connexion.
- [ ] Protéger les pages privées et toutes leurs actions côté serveur.
- [ ] Ajouter au header un bouton de déconnexion en un clic, utilisant POST et CSRF.
- [ ] Détruire la session et son cookie à la déconnexion.
- [ ] Prévoir une limitation raisonnable des tentatives de connexion.

**À apprendre :** sessions, cookies, authentification et autorisation.

**Outils :** sessions PHP, `password_verify()`, DevTools, `curl`.

**Terminé quand :** un visiteur ne peut ni afficher l'éditeur ni appeler son endpoint de montage. La déconnexion fonctionne depuis chaque page.

### Étape 6 - Réinitialiser le mot de passe et modifier le compte

**À faire :**

- [ ] Ajouter la demande de reset avec une réponse qui ne révèle pas si l'email est inscrit.
- [ ] Envoyer un jeton dédié, expirant et à usage unique ; permettre la saisie d'un nouveau mot de passe.
- [ ] Consommer le jeton de manière atomique et invalider les anciens liens de reset.
- [ ] Permettre la modification du nom, de l'email, du mot de passe et des notifications.
- [ ] Revérifier les unicités et protéger les changements sensibles avec le mot de passe actuel.
- [ ] Recommandation : confirmer une nouvelle adresse avant de la rendre effective et invalider les sessions après un reset.

**À apprendre :** transitions d'état, durée de vie et usage des liens.

**Outils :** PDO, fonctions de mot de passe, tokens, transport mail.

**Terminé quand :** l'ancien mot de passe échoue après reset, le lien consommé échoue et chaque préférence est conservée après reconnexion.

### Étape 7 - Réussir un montage avec une image importée

Commence par l'import : il permet de valider le serveur sans dépendre de la webcam.

**À faire :**

- [ ] Préparer quelques PNG transparents fournis par le projet.
- [ ] Accepter une photo et l'identifiant d'une superposition autorisée.
- [ ] Vérifier session et CSRF, erreur d'upload, taille, type réel et décodage.
- [ ] Limiter les dimensions et le nombre de pixels avant le traitement lourd.
- [ ] Avec GD, normaliser la photo, ajuster la superposition et produire le montage.
- [ ] Donner au fichier final un nom généré par le serveur et enregistrer l'auteur depuis la session.
- [ ] Prévoir le nettoyage si la sauvegarde SQL ou fichier échoue.

**À apprendre :** fichiers non fiables, proportions et composition d'images.

**Outils :** Fileinfo, GD, PDO, formulaire `multipart/form-data`.

**Terminé quand :** une image importée produit un montage où les zones transparentes fonctionnent. Un faux PNG, un fichier trop gros et une superposition inconnue sont refusés proprement.

### Étape 8 - Brancher la webcam et compléter l'éditeur

**À faire :**

- [ ] Demander la permission webcam et afficher le flux dans `<video>`.
- [ ] Capturer une frame brute dans Canvas, puis la transmettre au même traitement serveur que l'import.
- [ ] Afficher la liste des superpositions et gérer leur sélection.
- [ ] Désactiver la capture sans superposition et revérifier ce choix côté serveur.
- [ ] Afficher les créations précédentes dans la zone latérale.
- [ ] Gérer caméra absente, permission refusée et flux pas encore prêt ; proposer l'import.
- [ ] Libérer le flux quand il n'est plus utilisé.

**À apprendre :** événements, opérations asynchrones, flux vidéo et état de l'interface.

**Outils :** `getUserMedia()`, `<video>`, Canvas, `FormData`, JavaScript natif.

**Terminé quand :** capture et import produisent un montage via le serveur, et l'éditeur reste utilisable sans caméra.

Les navigateurs actuels exigent un contexte sécurisé pour `getUserMedia()` ; `localhost` bénéficie d'un traitement particulier. Une adresse IP locale en HTTP n'offre pas forcément le même comportement. Le sujet tolère certains messages liés à l'absence de HTTPS, mais tu dois prévoir un parcours fonctionnel. [Conditions d'accès à la webcam](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia).

### Étape 9 - Afficher la galerie et sa pagination

**À faire :**

- [ ] Rendre la galerie accessible sans compte.
- [ ] Afficher tous les montages, leur auteur et leur date.
- [ ] Choisir une taille de page d'au moins cinq, par exemple douze.
- [ ] Utiliser un tri stable et une pagination SQL `LIMIT`/`OFFSET` pour commencer.
- [ ] Valider le numéro de page et gérer galerie vide, dernière page et page inexistante.

**À apprendre :** tri, pagination et requêtes de lecture.

**Outils :** SQL, PDO, HTML/CSS.

**Terminé quand :** au moins treize images de test permettent de vérifier le passage de la première à la seconde page, sans doublons liés au tri sur un jeu de données inchangé.

### Étape 10 - Ajouter likes, commentaires et notifications

**À faire :**

- [ ] Exiger une session et un jeton CSRF pour les interactions.
- [ ] Vérifier l'existence de l'image et empêcher les likes en double grâce à la contrainte SQL.
- [ ] Valider les commentaires : texte non vide et longueur bornée.
- [ ] Afficher leur contenu comme du texte et échapper les données des auteurs.
- [ ] Après création d'un commentaire, consulter la préférence du propriétaire et envoyer l'email si activé.
- [ ] Traiter un échec du transport sans perdre le commentaire ni dupliquer l'action.

**À apprendre :** relations SQL, intégrité et effets secondaires d'une action.

**Outils :** PDO, échappement PHP, `mail()`, Mailpit, navigateur.

**Terminé quand :** un visiteur ne peut pas interagir ; un compte connecté peut le faire ; le propriétaire reçoit le message avec la préférence activée et ne le reçoit plus après désactivation.

### Étape 11 - Supprimer les créations avec les bons droits

**À faire :**

- [ ] Ajouter l'action de suppression avec session et CSRF.
- [ ] Contrôler le propriétaire en base à partir de l'utilisateur en session.
- [ ] Supprimer ou nettoyer les relations liées et le fichier du montage.
- [ ] Gérer proprement un fichier déjà absent et une image inconnue.

**À apprendre :** contrôle d'accès sur un objet et cohérence fichier/base.

**Outils :** PHP, PDO, `curl`, deux comptes de test.

**Terminé quand :** A peut supprimer son montage ; B ne le peut pas même en modifiant l'identifiant envoyé. Cacher le bouton chez B ne suffit pas.

### Étape 12 - Vérifier mobile et compatibilité

**À faire :**

- [ ] Adapter galerie, formulaires et éditeur aux petits écrans.
- [ ] Ajouter labels, focus visibles, textes d'erreur et boutons compréhensibles.
- [ ] Vérifier les parcours dans Firefox et Chrome actuels.
- [ ] Examiner aussi les API/CSS utilisés face aux versions minimales annoncées dans le sujet.
- [ ] Prévoir les adaptations natives nécessaires : par exemple export Canvas alternatif si `toBlob()` manque, syntaxe JavaScript compatible et layout sans dépendance obligatoire à CSS Grid.
- [ ] Si les anciennes versions sont effectivement testées, le faire dans un environnement isolé ; consigner les versions et limites vérifiées.

**À apprendre :** responsive, détection de fonctionnalités et distinction entre compatibilité présumée et testée.

**Outils :** DevTools, Firefox, Chrome, tableaux de compatibilité MDN.

**Terminé quand :** le site fonctionne sur mobile sans débordement et tu sais préciser ce qui a été testé. Deux navigateurs actuels ne prouvent pas à eux seuls le respect des minima historiques.

### Étape 13 - Exécuter les contrôles de sécurité et de robustesse

**À faire :**

- [ ] Passer la checklist de la section 8 avec un visiteur et deux comptes.
- [ ] Tester les routes directement, les champs manquants, identifiants invalides et jetons incorrects.
- [ ] Vérifier que tous les textes utilisateur sont affichés sans exécution HTML/JS.
- [ ] Tester les fichiers interdits, les erreurs mail et les échecs de traitement.
- [ ] Vérifier qu'aucun secret, détail SQL ou chemin interne ne fuit dans les réponses.
- [ ] Corriger warnings et erreurs ; retirer les traces de debug, sans masquer les problèmes avec `@`.

**À apprendre :** cas limites, entrées hostiles et observation des erreurs.

**Outils :** DevTools, `curl`, petits scripts PHP maison et inspection SQL.

**Terminé quand :** tous les critères obligatoires passent. La tolérance webcam prévue par le sujet n'est pas une permission de laisser des erreurs générales.

### Étape 14 - Préparer le rendu et la soutenance

**À faire :**

- [ ] Écrire le README : prérequis, `.env`, commande de lancement, services, transport mail, base, volumes et parcours de test.
- [ ] Tester une installation depuis une copie propre du dépôt, avec stockage initialement vide.
- [ ] Vérifier que les assets et l'initialisation SQL sont versionnés, que les secrets et fichiers générés ne le sont pas.
- [ ] Préparer une démonstration avec deux comptes, une webcam et une image importée.
- [ ] Expliquer chaque protection et montrer que le montage est réalisé par le serveur.

**À apprendre :** reproductibilité et justification des choix.

**Outils :** Git, Docker Compose, README et checklist.

**Terminé quand :** une autre personne peut lancer le projet avec les seules instructions du dépôt et parcourir toutes les fonctionnalités obligatoires.

## 7. Les deux traitements à bien comprendre

### 7.1 Le montage côté serveur

```text
Navigateur : photo brute + identifiant de superposition + CSRF
                              ↓
Serveur : session → validations → décodage → composition GD
                              ↓
Stockage : nouveau fichier final + métadonnées SQL
                              ↓
Navigateur : affichage de l'image produite par le serveur
```

**Recommandation de départ :** une taille de sortie fixe, une superposition à position fixe et des règles simples de redimensionnement. Le déplacement libre, la rotation et le redimensionnement interactif ne sont pas exigés. Choisir des PNG conçus pour la taille de sortie évite beaucoup de problèmes.

Pour l'alpha, teste vraiment le résultat. GD fournit les fonctions nécessaires, notamment `imagecopy()` et `imagealphablending()` ; leur configuration dépend de la destination et du format de sortie. Une superposition transparente doit laisser voir la base sans créer de rectangle opaque. [Copie GD](https://www.php.net/manual/en/function.imagecopy.php), [composition alpha GD](https://www.php.net/manual/en/function.imagealphablending.php).

Ne fais jamais confiance à un `user_id`, un chemin de fichier ou un chemin de superposition transmis par le client. L'auteur vient de la session et la superposition d'une liste autorisée côté serveur.

### 7.2 Le contrôle d'un upload

Un fichier `.png` peut contenir autre chose qu'une image. La validation doit combiner une liste de formats acceptés, des limites, le type détecté et un décodage réel. Réencoder uniquement le résultat généré, utiliser un nom choisi par le serveur et garder les sources hors de la racine publique réduit les risques. [Contrôles d'upload OWASP](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html).

**Choix de départ proposé :** accepter JPEG et PNG, définir une limite en octets et une limite en pixels, puis produire un seul format final. Les valeurs exactes sont à dimensionner selon la mémoire disponible, pas à présenter comme des exigences du sujet.

## 8. Checklist avant de déclarer la partie obligatoire terminée

### Fonctionnement

- [ ] Inscription : email valide, nom et mot de passe complexe ; doublons traités.
- [ ] Email de confirmation reçu, lien unique et activation effective.
- [ ] Connexion par nom d'utilisateur et mot de passe.
- [ ] Reset par email fonctionnel et ancien mot de passe invalidé.
- [ ] Déconnexion en un clic depuis toutes les pages.
- [ ] Modification du nom, de l'email et du mot de passe.
- [ ] Préférence de notification activée par défaut et désactivable.
- [ ] Galerie publique avec tous les montages et tri par date.
- [ ] Pagination d'une capacité d'au moins cinq images par page.
- [ ] Likes et commentaires réservés aux comptes connectés.
- [ ] Nouveau commentaire : email au propriétaire selon sa préférence.
- [ ] Éditeur privé avec aperçu, superpositions, capture et miniatures précédentes.
- [ ] Capture désactivée sans sélection de superposition.
- [ ] Import d'image disponible sans webcam.
- [ ] Montage final effectivement fabriqué côté serveur.
- [ ] Suppression possible uniquement pour le propriétaire.

### Sécurité et erreurs

- [ ] Aucun mot de passe en clair, hash inadapté ou chiffrement réversible à la place du hachage.
- [ ] Entrées SQL passées comme paramètres ; tris/champs dynamiques limités à une liste autorisée.
- [ ] Commentaires, noms et erreurs affichés sans injection HTML/JS.
- [ ] Actions de modification protégées contre CSRF.
- [ ] Utilisateur anonyme refusé sur les actions privées, même par appel direct.
- [ ] Le compte B ne peut ni supprimer ni modifier les ressources du compte A.
- [ ] Jetons invalides, expirés et consommés refusés.
- [ ] Faux fichiers image, dimensions excessives et chemins arbitraires refusés.
- [ ] `.env` et fichiers internes inaccessibles depuis le navigateur.
- [ ] Secrets absents du dépôt et de son historique versionné.
- [ ] Pas de debug, warning ou erreur inattendue dans les consoles.

### Livraison

- [ ] Conteneurs sur des versions maintenues, démarrage en une commande.
- [ ] Initialisation automatique et données persistantes après redémarrage.
- [ ] Header, main et footer ; interface adaptée au mobile.
- [ ] Firefox et Chrome vérifiés ; compatibilité minimale étudiée et résultats documentés.
- [ ] Transport mail et réception réelle vérifiés.
- [ ] Installation propre testée, README suffisant, fichiers nécessaires dans Git.

## 9. Bonus : seulement après validation complète

Le sujet exige une partie obligatoire intégralement fonctionnelle pour évaluer les bonus (PDF p. 11-12). Il propose des pistes ; cinq petits ajouts ne garantissent pas cinq points. La valeur dépend de l'utilité, du fonctionnement, de la qualité technique et de la cohérence.

| Bonus proposé dans le sujet | Intérêt | Outils possibles / difficulté |
| --- | --- | --- |
| Échanges AJAX | Liker/commenter sans recharger toute la page | API native `fetch()` ou `XMLHttpRequest` ; conserver les contrôles serveur |
| Aperçu du montage en direct | Voir la superposition avant la capture | CSS/Canvas ; le rendu définitif reste réalisé côté serveur |
| Pagination infinie | Charger progressivement la galerie | JavaScript natif et pagination serveur ; traiter aussi la compatibilité |
| Partage sur réseaux sociaux | Faciliter le partage d'un lien vers une création | Liens de partage ; vérifier les mécanismes actuels du réseau choisi avant réalisation |
| GIF animé | Étendre le montage à une animation | Plus complexe ; GD ne résout pas à lui seul l'encodage d'une animation multi-frame, et les dépendances restent contraintes |

**Ordre recommandé :** aperçu en direct, puis interactions AJAX. La pagination classique reste un bon moyen de conserver un parcours robuste. Le GIF demande plus de recherche et n'est pas un bon premier chantier.

## 10. Questions à savoir expliquer en soutenance

1. Que se passe-t-il entre le clic sur un bouton et l'enregistrement en base ?
2. Comment le serveur sait-il qui effectue l'action ?
3. Pourquoi vérifier la propriété d'une image si le bouton est déjà caché ?
4. Pourquoi hacher un mot de passe plutôt que le chiffrer ?
5. Comment une requête préparée empêche-t-elle une entrée de modifier le SQL ?
6. Où échappes-tu les commentaires, et pourquoi à cet endroit ?
7. Comment ton jeton CSRF bloque-t-il une action provoquée depuis une autre page ?
8. Pourquoi CORS ne remplace-t-il pas les permissions ou CSRF ?
9. Comment détectes-tu un fichier qui prétend être une image ?
10. Comment garantis-tu qu'un lien de reset n'est utilisable qu'une fois ?
11. Où la superposition finale est-elle réalisée, et comment peux-tu le montrer ?
12. Que reste-t-il après la recréation des conteneurs ?
13. Quelle différence fais-tu entre un email capturé localement et un email reçu ?
14. Quels navigateurs as-tu effectivement testés, et quelles adaptations as-tu prévues ?

Pour démarrer, traite les étapes 1 à 3, puis termine le parcours compte/email. Le premier jalon photo est ensuite : **importer une image, la monter côté serveur et la retrouver dans la galerie**. Branche la webcam une fois ce parcours fiable.
