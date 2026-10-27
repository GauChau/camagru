<?php
$pageTitle = 'Camagru';
$pageStyles = ['/assets/css/home.css'];
$pageScripts = ['/assets/js/main.js'];
require __DIR__ . '/partials/header.php';
?>
    <main class="welcome" aria-labelledby="welcome-title">
        <h1 class="wordmark" id="welcome-title">Camagru</h1>
        <div class="welcome-actions" role="group" aria-label="Accès au compte">
            <a class="button button-outline" href="/login.php">Login</a>
            <a class="button button-solid" href="/register.php">Register</a>
        </div>
    </main>
<?php require __DIR__ . '/partials/footer.php'; ?>
