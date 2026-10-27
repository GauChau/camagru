# JavaScript utile pour Camagru

Petit aide-mémoire adapté à ce dépôt : JavaScript natif, sans bibliothèque externe.

## Fiches à lire dans cet ordre

1. [DOM et formulaires](01-dom-formulaires.md) : sélectionner les éléments, écouter les actions, vérifier les champs et afficher les messages.
2. [Webcam et images](02-webcam-images.md) : démarrer la caméra, capturer une photo, importer un fichier et choisir une superposition.
3. [Envoi et galerie](03-envoi-galerie.md) : transmettre une photo à PHP, puis éventuellement gérer likes et commentaires sans rechargement.

## Ce qui existe déjà

- `public/assets/js/auth.js` utilise `querySelector()`, `addEventListener()`, `setCustomValidity()` et `preventDefault()`.
- Login et Register sont des maquettes. Leur soumission est actuellement interceptée pour afficher un message ; aucun compte n'est créé.
- La webcam, l'import et les échanges avec PHP restent à implémenter. Les exemples de ces fiches sont des modèles à adapter, pas des fonctionnalités déjà présentes.

## Répartition du travail

| Navigateur : JavaScript | Serveur : PHP |
| --- | --- |
| Réagir aux clics et aider à remplir un formulaire | Valider les données, authentifier et gérer la session |
| Afficher la webcam et capturer la photo brute | Vérifier le fichier et produire le montage final avec GD |
| Envoyer une photo et l'identifiant d'une superposition | Contrôler les droits, le jeton CSRF et la superposition autorisée |
| Afficher un résultat ou une erreur | Enregistrer en base, gérer les likes et envoyer les emails |

Une propriété comme `.value` se lit ou s'affecte ; une méthode comme `.addEventListener()` s'appelle avec des parenthèses. Les noms comme `capturePhoto()` dans les fiches sont des fonctions que tu écriras toi-même.

## Compatibilité

Les exemples visent les navigateurs actuels. Le guide du projet mentionne Firefox 41 et Chrome 46 : il faudra adapter la syntaxe moderne (`async` / `await`, notamment) et les API manquantes si ces versions sont visées. Détecter une méthode absente ne suffit pas à rendre une syntaxe non reconnue exécutable. Prévoir notamment un envoi classique ou `XMLHttpRequest` si `fetch()` manque, et une alternative à `toBlob()` si nécessaire.

Pour les exigences complètes, voir le [guide du projet](../../GUIDE_CAMAGRU.md).
