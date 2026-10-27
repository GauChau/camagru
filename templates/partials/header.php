<?php
// Les pages publiques doivent aussi retrouver l'état de connexion.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$isLoggedIn = ($_SESSION['login_success'] ?? false) === true;

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
    <header class="site-header">
        <a class="wordmark site-brand" href="/" aria-label="Camagru — Accueil">Camagru</a>
        <nav class="site-nav" aria-label="Accès au compte">
            <?php if ($isLoggedIn): ?>
                <a class="button button-outline" href="/gallery.php">Gallery</a>
                <a class="button button-solid" href="/profile.php">My profile</a>
                <form method="post" action="/logout.php">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['logout_csrf'], ENT_QUOTES, 'UTF-8') ?>">

                    <button class="button button-danger" type="submit">
                        Logout
                    </button>
                </form>
            <?php else: ?>
                <a class="button button-outline" href="/login.php">Login</a>
                <a class="button button-solid" href="/register.php">Register</a>
            <?php endif; ?>
        </nav>
    </header>
