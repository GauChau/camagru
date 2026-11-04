<?php
// Les pages publiques doivent aussi retrouver l'état de connexion.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']);

// Le jeton doit exister avant d'être placé dans le formulaire de déconnexion.
if ($isLoggedIn && !is_string($_SESSION['logout_csrf'] ?? null)) {
    $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Camagru', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link rel="preload" href="/assets/fonts/the-future-black-italic.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/layout.css">
    <?php foreach ($pageStyles ?? [] as $stylesheet): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylesheet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <?php endforeach; ?>
    <?php foreach ($pageScripts ?? [] as $script): ?>
        <script src="<?= htmlspecialchars($script, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" defer></script>
    <?php endforeach; ?>
</head>
<body>
    <header class="site-header<?= $isLoggedIn ? ' site-header-authenticated' : '' ?>">
        <a class="wordmark site-brand" href="/" aria-label="Camagru — Accueil">Camagru</a>
        <nav class="site-nav" aria-label="Accès au compte">
            <?php if ($isLoggedIn): ?>
                <a class="button button-outline" href="/gallery.php">Gallery</a>
                <a class="button button-solid" href="/camedit.php">Booth</a>
                <form method="post" action="/logout.php">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['logout_csrf'], ENT_QUOTES, 'UTF-8') ?>">

                    <button class="button button-danger" type="submit">
                        Logout
                    </button>
                </form>
                <a class="settings-link" href="/settings.php" aria-label="User settings" title="User settings">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="m10 3-.6 2.7-1.6.9-2.6-.8-2 3.4 2 1.9v1.8l-2 1.9 2 3.4 2.6-.8 1.6.9.6 2.7h4l.6-2.7 1.6-.9 2.6.8 2-3.4-2-1.9v-1.8l2-1.9-2-3.4-2.6.8-1.6-.9L14 3Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                </a>
            <?php else: ?>
                <a class="button button-outline" href="/gallery.php">Gallery</a>
                <a class="button button-outline" href="/login.php">Login</a>
                <a class="button button-solid" href="/register.php">Register</a>
            <?php endif; ?>
        </nav>
    </header>
