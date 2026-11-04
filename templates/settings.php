<?php
$pageTitle = 'User settings — Camagru';
$pageStyles = ['/assets/css/auth.css', '/assets/css/settings.css'];
$pageScripts = ['/assets/js/auth.js'];
require __DIR__ . '/partials/header.php';
?>
    <main class="auth-page settings-page" aria-labelledby="settings-title">
        <div class="auth-panel settings-panel">
            <h1 id="settings-title">User settings</h1>
            <?php if ($success): ?>
                <p class="form-status settings-status" role="status">Your changes have been saved.</p>
            <?php endif; ?>
            <?php if ($errors !== []): ?>
                <ul class="form-status settings-status" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form class="auth-form" method="post" action="/settings.php" data-auth-form="settings">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <div class="form-field">
                    <label for="login">Username</label>
                    <input id="login" name="login" type="text" value="<?= $escape($values['login']) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                </div>
                <div class="form-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= $escape($values['email']) ?>" autocomplete="email" autocapitalize="none" spellcheck="false" required>
                </div>
                <label class="settings-notifications">
                    <input type="checkbox" name="comment_notifications" value="1"<?= $values['comment_notifications'] ? ' checked' : '' ?>>
                    <span>Notify me by email when someone comments on my images.</span>
                </label>
                <fieldset class="settings-password">
                    <legend>Change password</legend>
                    <p class="settings-help" id="password-help">Leave these fields blank to keep your current password. A new password must contain at least 10 characters and one number.</p>
                    <div class="form-field">
                        <label for="password">New password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" pattern="(?=.*[0-9])[\s\S]{10,}" aria-describedby="password-help">
                    </div>
                    <div class="form-field">
                        <label for="confirm-password">Confirm new password</label>
                        <input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password">
                    </div>
                </fieldset>
                <div class="form-field">
                    <label for="current-password">Current password</label>
                    <input id="current-password" name="current_password" type="password" autocomplete="current-password" aria-describedby="current-password-help" required>
                    <small class="settings-help" id="current-password-help">Enter your current password to save your changes.</small>
                </div>
                <button class="button button-solid auth-submit" type="submit">Save changes</button>
            </form>
            <p class="auth-switch"><a href="/profile.php">Back to my profile</a></p>
        </div>
    </main>
<?php require __DIR__ . '/partials/footer.php'; ?>
