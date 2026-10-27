<?php
$pageTitle = 'Register — Camagru';
$pageStyles = ['/assets/css/auth.css'];
$pageScripts = ['/assets/js/auth.js'];
require __DIR__ . '/partials/header.php';
?>
    <main class="auth-page" aria-labelledby="auth-title">
        <div class="auth-panel">
            <h1 id="auth-title">Register</h1>
            <form class="auth-form" method="post" action="/register.php" data-auth-form="register">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <?php if ($success): ?>
                    <p class="form-status" role="status">Ton compte a bien été créé.</p>
                <?php endif; ?>
                <?php if ($errors !== []): ?>
                    <ul class="form-status" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <li><?= $escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="form-field">
                    <label for="login">Login</label>
                    <input id="login" name="login" type="text" value="<?= $escape($values['login']) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                </div>
                <div class="form-field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="1" pattern="(?=.*[0-9])[\s\S]{1,}" aria-describedby="password-help" required>
                    <small id="password-help">Au moins 10 caractères et au moins un chiffre.</small>
                </div>
                <div class="form-field">
                    <label for="confirm-password">Confirm password</label>
                    <input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" required>
                </div>
                <div class="form-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= $escape($values['email']) ?>" autocomplete="email" autocapitalize="none" spellcheck="false" required>
                </div>
                <button class="button button-solid auth-submit" type="submit">Register</button>
            </form>
            <p class="auth-switch">Déjà un compte ? <a href="/login.php">Login</a></p>
        </div>
    </main>
<?php require __DIR__ . '/partials/footer.php'; ?>
