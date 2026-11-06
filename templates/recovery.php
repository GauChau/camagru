<?php
$pageTitle = 'Password Change - Camagru';
$pageStyles = ['/assets/css/auth.css'];
$pageScripts = ['/assets/js/auth.js'];
require __DIR__ . '/partials/header.php';
?>
<main class="auth-page" aria-labelledby="auth-title">
	<div class="auth-panel">
		<h1 id="auth-title">Change your password</h1>
        <?php foreach ($errors as $error): ?>
            <p class="form-status" role="alert"><?= $escape($error) ?></p>
        <?php endforeach; ?>
        <?php if ($canReset): ?>
		<form class="auth-form" method="post" action="/recovery.php" data-auth-form="recovery">

			<input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="token" value="<?= $escape($token) ?>">
			<div class="form-field">
                    <label for="password">Your new password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" pattern="(?=.*[0-9])[\s\S]{10,}" aria-describedby="password-help" required>
                    <small id="password-help">Au moins 10 caractères et au moins un chiffre.</small>
                </div>
                <div class="form-field">
                    <label for="confirm-password">Confirm password</label>
                    <input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" required>
                </div>
			<button class="button button-solid auth-submit" type="submit">Change my password</button>
			<p class="form-status" role="status" hidden></p>
		</form>
        <?php endif; ?>
		<p class="auth-switch"><a href="/pwdchange.php">Demander un nouveau lien</a></p>
		<p class="auth-switch">Pas encore de compte ? <a href="/register.php">Register</a></p>
	</div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
