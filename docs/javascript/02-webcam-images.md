# 2. Webcam et images

L'éditeur n'existe pas encore. Les noms d'éléments ci-dessous sont des propositions.

## API à connaître

| Méthode ou propriété | Rôle |
| --- | --- |
| `navigator.mediaDevices.getUserMedia({ video: true, audio: false })` | Demander la webcam et obtenir un flux vidéo |
| `video.srcObject` | Attacher ce flux à un élément `<video>` |
| `video.play()` | Démarrer la lecture ; renvoie une promesse |
| `stream.getTracks()` / `track.stop()` | Arrêter les pistes et libérer la webcam |
| `video.videoWidth` / `video.videoHeight` | Connaître les dimensions réelles de la vidéo |
| `canvas.getContext('2d')` | Obtenir l'outil de dessin du canvas |
| `context.drawImage(video, x, y, largeur, hauteur)` | Copier une image de la vidéo dans le canvas |
| `canvas.toBlob(callback, 'image/png')` | Exporter le canvas en fichier binaire ; le callback reçoit un `Blob` ou `null` |
| `input.files[0]` | Lire le premier fichier choisi dans un `<input type="file">` |
| `URL.createObjectURL(file)` / `URL.revokeObjectURL(url)` | Créer puis libérer une URL temporaire pour l'aperçu |

## Exemple : démarrer et arrêter la webcam

Prévoir un `<video autoplay muted playsinline></video>`. Appeler `startCamera(video)` depuis un bouton de l'éditeur et gérer son échec avec `try` / `catch` pour afficher un message à l'écran.

```js
async function startCamera(video) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('La webcam n’est pas disponible dans ce navigateur.');
    }

    const stream = await navigator.mediaDevices.getUserMedia({
        video: true,
        audio: false
    });

    video.srcObject = stream;
    try {
        await video.play();
    } catch (error) {
        stopCamera(video);
        throw error;
    }
}

function stopCamera(video) {
    if (video.srcObject) {
        video.srcObject.getTracks().forEach(track => track.stop());
        video.srcObject = null;
    }
}
```

La webcam nécessite l'autorisation de l'utilisateur et un contexte sécurisé : HTTPS, ou `localhost` pour le développement. Prévoir l'import d'image si la caméra est absente ou refusée. Arrêter le flux quand l'utilisateur quitte l'éditeur ou passe à l'import. [MDN — getUserMedia()](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia).

## Exemple : capturer une photo brute

`video` et `canvas` sont des éléments HTML passés à cette fonction. La promesse permet ensuite d'écrire `const photo = await capturePhoto(video, canvas)` dans un `try` / `catch`.

```js
function capturePhoto(video, canvas) {
    return new Promise((resolve, reject) => {
        if (video.readyState < 2 || !video.videoWidth || !video.videoHeight) {
            reject(new Error('Attends que la caméra soit prête.'));
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext('2d');
        if (!context || !canvas.toBlob) {
            reject(new Error('La capture n’est pas disponible dans ce navigateur.'));
            return;
        }

        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(blob => {
            if (blob) resolve(blob);
            else reject(new Error('La capture a échoué.'));
        }, 'image/png');
    });
}
```

La capture transmise à PHP doit rester brute. Un aperçu peut montrer une superposition, mais le montage final se fait côté serveur. [MDN — toBlob()](https://developer.mozilla.org/en-US/docs/Web/API/HTMLCanvasElement/toBlob).

## Fonctions à écrire pour l'éditeur

| Nom proposé | Travail à effectuer |
| --- | --- |
| `previewFile(file)` | Vérifier le type et la taille pour guider l'utilisateur, puis afficher l'image avec une URL temporaire |
| `selectOverlay(id)` | Garder l'identifiant choisi, actualiser le style et l'attribut `aria-pressed` du bouton |
| `updateCaptureButton()` | Désactiver la capture si aucune superposition n'est choisie ou si la source n'est pas prête |

Libérer l'ancienne URL temporaire quand on remplace l'aperçu ou quitte l'éditeur. La taille et le type déclarés dans `file` ne remplacent pas la vérification du contenu par PHP.
