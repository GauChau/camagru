<?php
$pageTitle = 'Activation — Camagru';
$pageStyles = ['/assets/css/auth.css'];
require __DIR__ . '/partials/header.php';
?>
    <main class="auth-page" aria-labelledby="auth-title">
        <div class="auth-panel">
            <h1 id="auth-title">Activation du compte</h1>
            <?php if ($success): ?>
                <p class="form-status" role="status">Ton compte est activé. Tu peux te connecter.</p>
            <?php endif; ?>
            <?php foreach ($errors as $error): ?>
                <p class="form-status" role="alert"><?= $escape($error) ?></p>
            <?php endforeach; ?>
            <p class="auth-switch"><a href="/login.php">Se connecter</a></p>
        </div>
    </main>
<?php require __DIR__ . '/partials/footer.php'; ?>
