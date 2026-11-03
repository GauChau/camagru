<?php
$pageTitle = 'Activation — Camagru';
$pageStyles = ['/assets/css/auth.css'];
require __DIR__ . '/partials/header.php';
?>
    <main class="auth-page" aria-labelledby="auth-title">
        <div class="auth-panel">
            <h1 id="auth-title">Account activation</h1>
            <?php if ($success): ?>
                <p class="form-status" role="status">Success, your account is now activated ! Please login to access your new account!</p>
            <?php else: ?>
                <?php if ($resendSuccess): ?>
                    <p class="form-status" role="status">If an account still needs activation for this email address, a new activation email has been sent.</p>
                <?php else: ?>
                    <p class="form-status" role="alert">An error has occured.</p>
                <?php endif; ?>
                <form class="auth-form" method="post" action="/activate.php">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <?php foreach ($errors as $error): ?>
                        <p class="form-status" role="alert"><?= $escape($error) ?></p>
                    <?php endforeach; ?>
                    <div class="form-field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="<?= $escape($email) ?>" autocomplete="email" autocapitalize="none" spellcheck="false" required>
                    </div>
                    <button class="button button-solid auth-submit" type="submit">Resend activation email</button>
                </form>
            <?php endif; ?>
            <p class="auth-switch"><a href="/login.php">Login</a></p>
        </div>
    </main>
<?php require __DIR__ . '/partials/footer.php'; ?>
