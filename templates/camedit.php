<?php
$pageTitle = 'Camedit — Camagru';
$pageStyles = ['/assets/css/camedit.css'];
$pageScripts = ['/assets/js/camedit.js'];
require __DIR__ . '/partials/header.php';
?>

<main class="camedit">
    <div class="editor-layout">
        <section class="camera-panel" aria-label="Aperçu de la webcam">
            <div class="camera-frame">
                <video id="camera" autoplay muted playsinline aria-label="Webcam en direct"></video>
                <img id="capture-preview" alt="Dernière capture de la webcam" hidden>
            </div>
            <button id="start" class="button button-solid" type="button">Activer la webcam</button>
            <button id="snap" class="button button-solid" type="button" disabled
                data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">Capturer</button>
            <p id="camera-status" role="status">Active la webcam pour afficher l’aperçu en direct.</p>
            <fieldset class="overlay-picker">
                <legend>Choisis un calque</legend>
                <?php if ($overlayError !== null): ?>
                    <p role="alert"><?= htmlspecialchars($overlayError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <?php elseif ($overlayNames === []): ?>
                    <p>Aucun calque disponible pour le moment.</p>
                <?php else: ?>
                    <div class="overlay-list">
                        <?php foreach ($overlayNames as $overlayName): ?>
                            <label class="overlay-option">
                                <input type="radio" name="overlay" value="<?= htmlspecialchars($overlayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                <img src="/overlay.php?name=<?= rawurlencode($overlayName) ?>" alt="" loading="lazy">
                                <span><?= htmlspecialchars(pathinfo($overlayName, PATHINFO_FILENAME), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </fieldset>
        </section>
        <aside class="photos-panel" aria-labelledby="photos-title">
            <h2 id="photos-title">Mes créations</h2>
            <p>Aucune création pour le moment.</p>
        </aside>
    </div>
</main>

<?php
require __DIR__ . '/partials/footer.php';
