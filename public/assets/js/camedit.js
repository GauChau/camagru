const video = document.querySelector('#camera');
const capturePreview = document.querySelector('#capture-preview');
const startButton = document.querySelector('#start');
const cameraStatus = document.querySelector('#camera-status');
const captureButton = document.querySelector('#snap');
const overlayPicker = document.querySelector('.overlay-picker');
const overlayPreview = document.querySelector('#overlay-preview');
const overlayInputs = [...overlayPicker.querySelectorAll('input[name="overlays[]"]')];
const photosList = document.querySelector('#photos-list');
const photosEmpty = document.querySelector('#photos-empty');
const photoInput = document.querySelector('#photo');
let cameraStream = null;
let requestId = 0;
let cameraReady = false;
let capturePending = false;
let importedFile = null;
let importedPreviewUrl = null;
let previewSaved = false;

function updateCaptureButton() {
    captureButton.disabled = (!cameraReady && !importedFile) || capturePending;
    captureButton.textContent = importedFile ? 'Enregistrer le montage' : 'Capturer';
}

function updateOverlayPreview() {
    const selectedOverlays = overlayInputs.filter((input) => input.checked);
    overlayPreview.toggleAttribute('hidden', selectedOverlays.length === 0 || previewSaved);
    overlayPreview.replaceChildren(...selectedOverlays.map((input) => {
        const image = document.createElementNS('http://www.w3.org/2000/svg', 'image');
        image.setAttribute('width', '100%');
        image.setAttribute('height', '100%');
        image.setAttribute('preserveAspectRatio', 'none');
        image.setAttribute('href', `/overlay.php?name=${encodeURIComponent(input.value)}`);
        return image;
    }));
    overlayInputs.forEach((input) => {
        input.disabled = !input.checked && selectedOverlays.length >= 20;
    });
    updateCaptureButton();
}

overlayPicker.addEventListener('change', updateOverlayPreview);
updateOverlayPreview();

function stopCamera() {
    requestId += 1;
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;
    cameraReady = false;
    video.srcObject = null;
    updateCaptureButton();
    startButton.disabled = capturePending;
    startButton.textContent = 'Activer la webcam';
    cameraStatus.textContent = 'Webcam arrêtée.';
}

function clearImportedImage(resetPicker = true) {
    importedFile = null;
    if (resetPicker) photoInput.value = '';
    if (importedPreviewUrl) URL.revokeObjectURL(importedPreviewUrl);
    importedPreviewUrl = null;
}

photoInput.addEventListener('change', async () => {
    const file = photoInput.files[0];
    if (!file) return;
    stopCamera();
    clearImportedImage(false);
    previewSaved = false;
    capturePreview.hidden = true;
    video.hidden = true;
    updateOverlayPreview();
    const currentRequest = requestId;
    let previewUrl = null;
    cameraStatus.textContent = 'Chargement de l’image…';
    try {
        if (!['image/png', 'image/jpeg'].includes(file.type) || file.size < 1 || file.size > 5 * 1024 * 1024) {
            throw new Error('Choisis une image PNG ou JPEG de 5 Mo maximum.');
        }
        previewUrl = URL.createObjectURL(file);
        const image = new Image();
        await new Promise((resolve, reject) => {
            image.onload = resolve;
            image.onerror = () => reject(new Error('Cette image ne peut pas être lue. Choisis un PNG ou JPEG valide.'));
            image.src = previewUrl;
        });
        if (currentRequest !== requestId) return;
        if (!image.naturalWidth || !image.naturalHeight || image.naturalWidth > 4096 || image.naturalHeight > 4096) {
            throw new Error('L’image doit mesurer au maximum 4096 × 4096 pixels.');
        }
        importedFile = file;
        importedPreviewUrl = previewUrl;
        previewUrl = null;
        capturePreview.src = importedPreviewUrl;
        capturePreview.style.objectFit = 'contain';
        capturePreview.hidden = false;
        overlayPreview.setAttribute('viewBox', `0 0 ${image.naturalWidth} ${image.naturalHeight}`);
        overlayPreview.setAttribute('preserveAspectRatio', 'xMidYMid meet');
        cameraStatus.textContent = 'Image prête. Ajoute des stickers si tu le souhaites, puis enregistre le montage.';
    } catch (error) {
        if (currentRequest !== requestId) return;
        photoInput.value = '';
        cameraStatus.textContent = error.message;
    } finally {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        if (currentRequest === requestId) updateCaptureButton();
    }
});

function captureImage() {
    if (video.readyState < 2 || !video.videoWidth || !video.videoHeight) {
        throw new Error('La caméra n’est pas encore prête.');
    }

    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/png');
}

startButton.addEventListener('click', async () => {
    if (cameraStream) {
        stopCamera();
        return;
    }

    clearImportedImage();
    previewSaved = false;
    capturePreview.hidden = true;
    capturePreview.style.objectFit = 'cover';
    video.hidden = false;
    overlayPreview.setAttribute('preserveAspectRatio', 'xMidYMid slice');
    updateOverlayPreview();
    const currentRequest = ++requestId;
    startButton.disabled = true;
    cameraStatus.textContent = 'Autorise l’accès à la webcam dans ton navigateur.';

    try {
        if (!navigator.mediaDevices?.getUserMedia) {
            cameraStatus.textContent = 'Webcam indisponible. Utilise HTTPS ou localhost et un navigateur compatible.';
            return;
        }

        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
            audio: false
        });

        if (currentRequest !== requestId) {
            stream.getTracks().forEach((track) => track.stop());
            return;
        }

        cameraStream = stream;
        video.srcObject = stream;
        stream.getVideoTracks().forEach((track) => {
            track.addEventListener('ended', () => {
                if (cameraStream === stream) stopCamera();
            });
        });
        await video.play();

        if (currentRequest !== requestId) return;
        if (video.videoWidth && video.videoHeight) {
            overlayPreview.setAttribute('viewBox', `0 0 ${video.videoWidth} ${video.videoHeight}`);
        }
        capturePreview.hidden = true;
        video.hidden = false;
        startButton.textContent = 'Arrêter la webcam';
        cameraReady = true;
        updateCaptureButton();
        cameraStatus.textContent = 'Webcam en direct.';
    } catch (error) {
        if (currentRequest !== requestId) return;
        stopCamera();
        cameraStatus.textContent = error.name === 'NotAllowedError'
            ? 'Accès à la webcam refusé. Autorise-le dans les paramètres du navigateur puis réessaie.'
            : 'Caméra inaccessible. Vérifie qu’elle est branchée et disponible puis réessaie.';
    } finally {
        if (currentRequest === requestId) startButton.disabled = false;
    }
});

captureButton.addEventListener('click', async () => {
    const selectedOverlays = overlayInputs.filter((input) => input.checked);
    if (capturePending || (!cameraReady && !importedFile)) return;
    capturePending = true;
    captureButton.disabled = true;
    photoInput.disabled = true;
    overlayPicker.disabled = true;

    try {
        const body = new FormData();
        if (importedFile) {
            body.set('photo', importedFile);
        } else {
            const image = captureImage();
            body.set('image', image);
            capturePreview.src = image;
            capturePreview.hidden = false;
            video.hidden = true;
        }
        selectedOverlays.forEach((input) => body.append('overlays[]', input.value));
        body.set('csrf_token', captureButton.dataset.csrfToken);
        stopCamera();
        startButton.disabled = true;
        cameraStatus.textContent = 'Enregistrement du montage…';

        const response = await fetch('/camedit.php', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body
        });
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Impossible d’enregistrer la capture.');
        }

        capturePreview.src = result.image;
        previewSaved = true;
        clearImportedImage();
        updateOverlayPreview();

        const thumbnail = document.createElement('img');
        thumbnail.src = result.image_url;
        thumbnail.alt = 'Voir le montage que tu viens de créer en grand';
        thumbnail.loading = 'lazy';
        const link = document.createElement('a');
        link.className = 'photo-thumbnail';
        link.href = result.image_url;
        link.append(thumbnail);
        const item = document.createElement('li');
        item.append(link);
        photosList.prepend(item);
        photosList.hidden = false;
        photosEmpty.hidden = true;

        cameraStatus.textContent = 'Montage enregistré. Tu peux importer une autre image ou réactiver la webcam.';
    } catch (error) {
        cameraStatus.textContent = error.message || 'Impossible d’envoyer la capture.';
    } finally {
        capturePending = false;
        startButton.disabled = false;
        photoInput.disabled = false;
        overlayPicker.disabled = false;
        updateCaptureButton();
    }
});

window.addEventListener('pagehide', () => {
    stopCamera();
    clearImportedImage();
});
