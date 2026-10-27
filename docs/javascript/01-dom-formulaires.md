# 1. DOM et formulaires

Le DOM est la représentation HTML de la page que JavaScript peut lire et modifier.

## Méthodes à connaître

| Méthode | Utilité dans Camagru |
| --- | --- |
| `document.querySelector(selecteur)` | Récupérer un formulaire, un champ ou un bouton ; renvoie `null` si absent |
| `document.querySelectorAll(selecteur)` | Récupérer les boutons de superposition ou plusieurs cartes |
| `element.addEventListener(evenement, fonction)` | Réagir à `click`, `input`, `change` ou `submit` |
| `event.preventDefault()` | Intercepter une soumission lorsqu'on la traite en JavaScript |
| `champ.setCustomValidity(message)` | Définir une erreur ; passer `''` pour l'effacer |
| `form.reportValidity()` | Déclencher les vérifications HTML et afficher les erreurs |
| `element.classList.toggle(classe, condition)` | Afficher l'état sélectionné d'une superposition |
| `document.createElement(balise)` / `parent.appendChild(element)` | Ajouter une miniature ou un commentaire |
| `element.remove()` | Retirer une carte après confirmation de sa suppression par le serveur |

## Propriétés utiles

| Propriété | Exemple d'utilisation |
| --- | --- |
| `champ.value` | Lire le login ou comparer deux mots de passe |
| `caseACocher.checked` | Lire la préférence de notification |
| `element.textContent` | Afficher un message ou un commentaire comme du texte |
| `element.hidden` | Masquer ou afficher un message |
| `bouton.disabled` | Empêcher la capture sans photo disponible ou sans superposition |
| `element.dataset.overlayId` | Lire l'attribut HTML `data-overlay-id` |

## Exemple : confirmer le mot de passe

Ces sélecteurs correspondent à la page Register actuelle. Cet exemple illustre le code déjà présent dans `auth.js` : ne pas ajouter un second gestionnaire identique.

```js
const password = document.querySelector('#password');
const confirmation = document.querySelector('#confirm-password');

function validateConfirmation() {
    const mismatch = confirmation.value !== password.value;
    confirmation.setCustomValidity(
        mismatch ? 'Les mots de passe doivent être identiques.' : ''
    );
}

if (password && confirmation) {
    password.addEventListener('input', validateConfirmation);
    confirmation.addEventListener('input', validateConfirmation);
}
```

`input` réagit pendant la saisie. `change` est pratique pour un fichier ou une case à cocher. `submit` s'écoute sur le formulaire pour gérer aussi la touche Entrée.

## À retenir

- `required` et `type="email"` font déjà une partie du travail en HTML.
- `trim()` permet de repérer un commentaire vide ou de normaliser certains champs. Ne modifie pas silencieusement les mots de passe avec `trim()`.
- Pour un texte venant d'un utilisateur, utiliser `textContent` plutôt que `innerHTML`.
- Les contrôles JavaScript aident l'utilisateur ; PHP doit refaire les contrôles.
- Un formulaire HTML classique peut envoyer Login ou Register sans `fetch()`. Retirer le blocage de la maquette lorsque le traitement PHP sera prêt.

Référence : [MDN — setCustomValidity()](https://developer.mozilla.org/en-US/docs/Web/API/HTMLInputElement/setCustomValidity).
