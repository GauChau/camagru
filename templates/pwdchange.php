<?php
$pageTitle = 'Password Change - Camagru';
$pageStyles = ['/assets/css/auth.css'];
// $pageScripts = ['/assets/js/main.js'];
require __DIR__ . '/partials/header.php';
?>
<main class="auth-page" aria-labelledby="auth-title">
	<div class="auth-panel">
		<h1 id="auth-title">Recover your password</h1>
		<?php if ($success): ?>
			<p class="form-status" role="status">Si un compte correspond à cette adresse, un email de récupération a été envoyé.</p>
		<?php endif; ?>
		<form class="auth-form" method="post" action="/pwdchange.php" data-auth-form="pwdchange">

			<input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <?php foreach ($errors as $error): ?>
                    <p class="form-status" role="alert">
                        <?= $escape($error) ?>
                    </p>
                <?php endforeach; ?>
			<div class="form-field">
				<label for="email">Your account's Email</label>
				<input id="email" name="email" type="email" autocomplete="email" autocapitalize="none" spellcheck="false" required>
			</div>
			<button class="button button-solid auth-submit" type="submit">Send recovery email</button>
			<p class="form-status" role="status" hidden></p>
		</form>
		<p class="auth-switch">Pas encore de compte ? <a href="/register.php">Register</a></p>
	</div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
