<?php
$pageTitle = 'Gallery — Camagru';
$pageStyles = ['/assets/css/gallery.css'];
$pageScripts = [];
require __DIR__ . '/partials/header.php';
?>
    <main class="gallery-page" aria-labelledby="gallery-title">
        <div class="gallery-heading">
            <div>
                <p class="gallery-eyebrow">Les créations de la communauté</p>
                <h1 id="gallery-title">Gallery</h1>
                <p class="gallery-intro">Tous les montages, les plus récents en premier.</p>
            </div>
            <?php if ($isLoggedIn): ?>
                <a class="button button-solid" href="/camedit.php">Créer un montage</a>
            <?php endif; ?>
        </div>
        <?php if (is_string($notice)): ?>
            <p class="gallery-notice" role="status"><?= $escape($notice) ?></p>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <p class="gallery-error" role="alert"><?= $escape($error) ?></p>
        <?php endif; ?>
        <?php if ($images === [] && $error === null): ?>
            <div class="gallery-empty">
                <h2>La galerie attend son premier montage.</h2>
                <p>Une capture, un calque et une nouvelle création à partager.</p>
                <a class="button button-outline" href="<?= $isLoggedIn ? '/camedit.php' : '/login.php' ?>">Créer un montage</a>
            </div>
        <?php elseif ($images !== []): ?>
            <div class="gallery-meta"><span><?= $total ?> montage<?= $total > 1 ? 's' : '' ?></span><span>Page <?= $page ?> sur <?= $pageCount ?></span></div>
            <div class="gallery-grid">
                <?php foreach ($images as $image): ?>
                    <?php $date = new DateTimeImmutable($image['created_at']); ?>
                    <article class="gallery-card" id="image-<?= $image['id'] ?>" aria-label="Montage de <?= $escape($image['login']) ?>">
                        <a class="gallery-image-link" href="/image.php?id=<?= $image['id'] ?>" aria-label="Voir le montage de <?= $escape($image['login']) ?> en grand">
                            <img class="gallery-image" src="/image.php?id=<?= $image['id'] ?>" alt="Montage de <?= $escape($image['login']) ?>" loading="lazy">
                        </a>
                        <div class="gallery-card-content">
                            <div class="gallery-author"><strong><?= $escape($image['login']) ?></strong><time datetime="<?= $escape($date->format(DATE_ATOM)) ?>"><?= $date->format('d/m/Y · H:i') ?></time></div>
                            <div class="gallery-reactions">
                                <?php if ($isLoggedIn): ?>
                                    <form method="post" action="/gallery.php?page=<?= $page ?>#image-<?= $image['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="image_id" value="<?= $image['id'] ?>">
                                        <button class="gallery-like" type="submit" name="action" value="<?= $image['liked'] ? 'unlike' : 'like' ?>" aria-pressed="<?= $image['liked'] ? 'true' : 'false' ?>">
                                            <span aria-hidden="true"><?= $image['liked'] ? '♥' : '♡' ?></span> <?= $image['liked'] ? 'Retirer mon like' : 'J’aime' ?> <span class="gallery-count">(<?= $image['like_count'] ?>)</span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span>♡ <?= $image['like_count'] ?> j’aime</span>
                                <?php endif; ?>
                            </div>
                            <details class="gallery-comments"<?= $commentImageId === (int) $image['id'] || $commentsFor === (int) $image['id'] ? ' open' : '' ?>>
                                <summary>Commentaires (<?= $image['comment_count'] ?>)</summary>
                                <?php if ($image['comments'] === []): ?>
                                    <p class="gallery-help">Aucun commentaire pour le moment.</p>
                                <?php else: ?>
                                    <ol class="gallery-comment-list">
                                        <?php foreach ($image['comments'] as $comment): ?>
                                            <li><strong><?= $escape($comment['login']) ?></strong><p><?= $escape($comment['body']) ?></p></li>
                                        <?php endforeach; ?>
                                    </ol>
                                    <?php if ($image['comment_pages'] > 1): ?>
                                        <nav class="gallery-comment-pagination" aria-label="Commentaires du montage de <?= $escape($image['login']) ?>">
                                            <?php if ($image['comment_page'] > 1): ?>
                                                <a href="/gallery.php?page=<?= $page ?>&amp;comments_for=<?= $image['id'] ?>&amp;comments_page=<?= $image['comment_page'] - 1 ?>#image-<?= $image['id'] ?>">Plus récents</a>
                                            <?php endif; ?>
                                            <span>Page <?= $image['comment_page'] ?> sur <?= $image['comment_pages'] ?></span>
                                            <?php if ($image['comment_page'] < $image['comment_pages']): ?>
                                                <a href="/gallery.php?page=<?= $page ?>&amp;comments_for=<?= $image['id'] ?>&amp;comments_page=<?= $image['comment_page'] + 1 ?>#image-<?= $image['id'] ?>">Plus anciens</a>
                                            <?php endif; ?>
                                        </nav>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($isLoggedIn): ?>
                                    <form class="gallery-comment-form" method="post" action="/gallery.php?page=<?= $page ?>#image-<?= $image['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <input type="hidden" name="image_id" value="<?= $image['id'] ?>">
                                        <input type="hidden" name="action" value="comment">
                                        <label for="comment-<?= $image['id'] ?>">Ajouter un commentaire</label>
                                        <textarea id="comment-<?= $image['id'] ?>" name="comment" rows="3" maxlength="2000" required><?= $commentImageId === (int) $image['id'] ? $escape($commentValue) : '' ?></textarea>
                                        <button class="button button-solid" type="submit">Publier</button>
                                    </form>
                                <?php endif; ?>
                            </details>
                            <?php if (!$isLoggedIn): ?>
                                <p class="gallery-help"><a href="/login.php">Connecte-toi</a> pour aimer ou commenter.</p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($pageCount > 1): ?>
                <nav class="gallery-pagination" aria-label="Pagination de la galerie">
                    <?php if ($page > 1): ?><a class="button button-outline" href="/gallery.php?page=<?= $page - 1 ?>" rel="prev">Précédente</a><?php endif; ?>
                    <span aria-current="page">Page <?= $page ?> sur <?= $pageCount ?></span>
                    <?php if ($page < $pageCount): ?><a class="button button-outline" href="/gallery.php?page=<?= $page + 1 ?>" rel="next">Suivante</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/partials/footer.php'; ?>
