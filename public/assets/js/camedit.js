const video = document.querySelector('#camera');
const startButton = document.querySelector('#start');
const cameraStatus = document.querySelector('#camera-status');
let cameraStream = null;
let requestId = 0;

function stopCamera() {
    requestId += 1;
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;
    video.srcObject = null;
    startButton.disabled = false;
    startButton.textContent = 'Activer la webcam';
    cameraStatus.textContent = 'Webcam arrêtée.';
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
        startButton.textContent = 'Arrêter la webcam';
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

window.addEventListener('pagehide', stopCamera);
