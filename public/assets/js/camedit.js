const video = document.querySelector('#camera');
const capturePreview = document.querySelector('#capture-preview');
const startButton = document.querySelector('#start');
const cameraStatus = document.querySelector('#camera-status');
const captureButton = document.querySelector('#snap');
const overlayPicker = document.querySelector('.overlay-picker');
let cameraStream = null;
let requestId = 0;
let cameraReady = false;
let capturePending = false;

function updateCaptureButton() {
    captureButton.disabled = !cameraReady || capturePending
        || !document.querySelector('input[name="overlay"]:checked');
}

overlayPicker.addEventListener('change', updateCaptureButton);

function stopCamera() {
    requestId += 1;
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;
    cameraReady = false;
    video.srcObject = null;
    captureButton.disabled = true;
    startButton.disabled = capturePending;
    startButton.textContent = 'Activer la webcam';
    cameraStatus.textContent = 'Webcam arrêtée.';
}

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
    const selectedOverlay = document.querySelector('input[name="overlay"]:checked');
    if (!selectedOverlay) return;
    capturePending = true;
    captureButton.disabled = true;

    try {
        const image = captureImage();
        capturePreview.src = image;
        capturePreview.hidden = false;
        video.hidden = true;
        stopCamera();
        startButton.disabled = true;
        cameraStatus.textContent = 'Envoi de la capture…';

        const response = await fetch('/camedit.php', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: new URLSearchParams({
                image,
                overlay: selectedOverlay.value,
                csrf_token: captureButton.dataset.csrfToken
            })
        });
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Impossible d’enregistrer la capture.');
        }

        capturePreview.src = result.image;
        cameraStatus.textContent = 'Montage enregistré. Tu peux réactiver la webcam.';
    } catch (error) {
        cameraStatus.textContent = error.message || 'Impossible d’envoyer la capture.';
    } finally {
        capturePending = false;
        startButton.disabled = false;
        updateCaptureButton();
    }
});

window.addEventListener('pagehide', stopCamera);
