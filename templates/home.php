    <main class="welcome" aria-labelledby="welcome-title">
        <h1 class="wordmark" id="welcome-title">Camagru</h1>
        <div class="welcome-actions" role="group" aria-label="Accès au compte">
            <?php if ($isLoggedIn): ?>
                <a class="button button-outline" href="/gallery.php">Gallery</a>
                <a class="button button-solid" href="/profile.php">My profile</a>
            <?php else: ?>
                <a class="button button-outline" href="/gallery.php">Gallery</a>
                <a class="button button-outline" href="/login.php">Login</a>
                <a class="button button-solid" href="/register.php">Register</a>
            <?php endif; ?>
        </div>
    </main>
