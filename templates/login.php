<?php
$pageTitle = 'Login — Camagru';
$pageStyles = ['/assets/css/auth.css'];
$pageScripts = ['/assets/js/auth.js'];
require __DIR__ . '/partials/header.php';
?>
    <main class="auth-page" aria-labelledby="auth-title">
        <div class="auth-panel">
            <h1 id="auth-title">Login</h1>
            <form class="auth-form" method="post" action="/login.php" data-auth-form="login">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <?php foreach ($errors as $error): ?>
                    <p class="form-status" role="alert">
                        <?= $escape($error) ?>
                    </p>
                <?php endforeach; ?>
                <div class="form-field">
                    <label for="login">Login</label>
                    <input id="login" name="login" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                </div>
                <div class="form-field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>
                <button class="button button-solid auth-submit" type="submit">Login</button>
                <p class="form-status" role="status" hidden></p>
            </form>
            <p class="auth-switch">Pas encore de compte ? <a href="/register.php">Register</a></p>
        </div>
    </main>
<?php require __DIR__ . '/partials/footer.php'; ?>
