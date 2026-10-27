# 3. Envoi et galerie

## API à connaître

| API | Utilité |
| --- | --- |
| `new FormData(formulaire)` | Récupérer les champs nommés d'un formulaire, y compris un jeton CSRF caché |
| `formData.set(nom, valeur)` | Ajouter ou remplacer un champ, par exemple la photo capturée |
| `fetch(url, options)` | Envoyer une requête sans recharger la page |
| `response.ok` | Vérifier que le statut HTTP est compris entre 200 et 299 |
| `response.json()` | Lire une réponse JSON ; opération asynchrone qui peut échouer |
| `try` / `catch` / `finally` | Gérer les erreurs et réactiver un bouton après l'envoi |

`async` et `await` permettent d'attendre une promesse. Ce sont des mots-clés, pas des fonctions.

## Exemple : envoyer une photo à PHP

Cet exemple suppose un futur formulaire avec `method="post"`, une `action` locale vers un traitement PHP, un champ caché `csrf_token`, et un champ `overlay_id`. `photo` est le fichier importé ou le `Blob` brut de la webcam. Cette route et ces champs ne sont pas encore implémentés.

```js
async function uploadPhoto(form, photo) {
    const data = new FormData(form);
    data.set('photo', photo, 'capture.png');

    const response = await fetch(form.action, {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    });

    if (!response.ok) {
        throw new Error('L’envoi de la photo a échoué.');
    }

    return await response.json();
}
```

Le nom `capture.png` ne convertit pas le fichier : PHP doit identifier et décoder son contenu réel. Ne pas définir manuellement `Content-Type` pour `FormData` : le navigateur ajoute la séparation nécessaire au format multipart. [MDN — FormData](https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest_API/Using_FormData_Objects).

Dans le gestionnaire `submit` : appeler `preventDefault()`, désactiver le bouton, attendre `uploadPhoto()` dans un `try`, afficher l'échec dans `catch`, puis réactiver le bouton dans `finally`. Vérifier `response.ok` : un statut HTTP 400 ou 500 ne fait pas automatiquement échouer la promesse de `fetch()`. Une réponse HTML ou vide fera échouer `response.json()` ; prévoir le format JSON avec le traitement PHP. [MDN — Utiliser Fetch](https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API/Using_Fetch).

## Likes, commentaires et suppression

Les formulaires HTML classiques suffisent pour commencer. Les fonctions suivantes servent si tu ajoutes des interactions sans rechargement :

| Nom proposé | Rôle |
| --- | --- |
| `toggleLike(imageId)` | Envoyer l'action puis afficher l'état et le compteur renvoyés par PHP |
| `submitComment(form)` | Envoyer le commentaire et l'ajouter avec `createElement()` et `textContent` après succès |
| `deletePhoto(imageId)` | Demander la suppression et retirer la miniature seulement après succès |

Chaque action qui modifie des données doit transmettre le jeton CSRF selon le contrat prévu avec PHP. Les droits viennent de la session serveur ; un identifiant envoyé en JavaScript n'accorde aucun droit. Pour la pagination, de simples liens vers les pages suivantes suffisent.
