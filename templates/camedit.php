<?php
$pageTitle = 'Camedit — Camagru';
$pageStyles = ['/assets/css/camedit.css'];
$pageScripts = ['/assets/js/camedit.js'];
require __DIR__ . '/partials/header.php';
?>

<main class="camedit">
    <section class="camera-panel" aria-label="Aperçu de la webcam">
        <div class="camera-frame">
            <video id="camera" autoplay muted playsinline aria-label="Webcam en direct"></video>
        </div>
        <button id="start" class="button button-solid" type="button">Activer la webcam</button>
        <p id="camera-status" role="status">Active la webcam pour afficher l’aperçu en direct.</p>
        <button id="start" class="button button-solid" type="button">Activer la webcam</button>
    </section>
</main>

<?php
require __DIR__ . '/partials/footer.php';
