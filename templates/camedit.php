<main class="camedit">
    <div class="editor-layout">
        <section class="camera-panel" aria-label="Création d’un montage">
            <div class="camera-frame">
                <video id="camera" autoplay muted playsinline aria-label="Webcam en direct"></video>
                <img id="capture-preview" alt="Aperçu de l’image à composer" hidden>
                <svg id="overlay-preview" viewBox="0 0 1 1" preserveAspectRatio="xMidYMid slice" aria-hidden="true" hidden>
                </svg>
            </div>
            <button id="start" class="button button-solid" type="button">Activer la webcam</button>
            <div class="image-import">
                <label for="photo">Ou importer une image</label>
                <input id="photo" name="photo" type="file" accept="image/png,image/jpeg" aria-describedby="photo-help">
                <small id="photo-help">PNG ou JPEG · 5 Mo maximum · 4096 × 4096 pixels maximum.</small>
            </div>
            <button id="snap" class="button button-solid" type="button" disabled
                data-csrf-token="<?= $escape($csrfToken) ?>">Capturer</button>
            <p id="camera-status" role="status">Active la webcam et choisis au moins un sticker, ou importe une image avec ou sans sticker.</p>
            <fieldset class="overlay-picker">
                <legend>Choisis tes stickers</legend>
                <p>Coche plusieurs stickers pour les superposer (20 maximum). Décoche-les pour les retirer. Ils se superposent dans l’ordre du catalogue. Au moins un sticker est nécessaire pour la capture webcam.</p>
                <?php if ($overlayError !== null): ?>
                    <p role="alert"><?= $escape($overlayError) ?></p>
                <?php elseif ($overlayNames === []): ?>
                    <p>Aucun calque disponible pour le moment.</p>
                <?php else: ?>
                    <div class="overlay-list">
                        <?php foreach ($overlayNames as $overlayName): ?>
                            <label class="overlay-option">
                                <input type="checkbox" name="overlays[]" value="<?= $escape($overlayName) ?>">
                                <img src="/overlay.php?name=<?= rawurlencode($overlayName) ?>" alt="" loading="lazy">
                                <span><?= $escape(pathinfo($overlayName, PATHINFO_FILENAME)) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </fieldset>
        </section>
        <aside class="photos-panel" aria-labelledby="photos-title">
            <h2 id="photos-title">Mes créations</h2>
            <?php if ($photosError !== null): ?>
                <p id="photos-error" role="alert"><?= $escape($photosError) ?></p>
            <?php endif; ?>
            <p id="photos-empty"<?= $previousImages !== [] || $photosError !== null ? ' hidden' : '' ?>>Aucune création pour le moment.</p>
            <ul id="photos-list" class="photos-list"<?= $previousImages === [] ? ' hidden' : '' ?>>
                <?php foreach ($previousImages as $previousImage): ?>
                    <li>
                        <a class="photo-thumbnail" href="/image.php?id=<?= (int) $previousImage['id'] ?>">
                            <img src="/image.php?id=<?= (int) $previousImage['id'] ?>" alt="Voir le montage n° <?= (int) $previousImage['id'] ?> en grand" loading="lazy">
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>
    </div>
</main>
