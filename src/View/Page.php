<?php
declare(strict_types=1);

namespace Camagru\View;

use Camagru\Http\Csrf;
use Camagru\Http\Session;
use InvalidArgumentException;

final class Page
{
    /** Les noms des templates sont choisis par le code, jamais par une entrée HTTP. */
    public static function render(
        string $template,
        array $data = [],
        string $title = 'Camagru',
        array $styles = [],
        array $scripts = []
    ): void {
        if (preg_match('/\A[a-z][a-z0-9_-]*\z/', $template) !== 1) {
            throw new InvalidArgumentException('Nom de template invalide.');
        }

        $templatePath = dirname(__DIR__, 2) . '/templates/' . $template . '.php';
        if (!is_file($templatePath)) {
            throw new InvalidArgumentException('Template introuvable.');
        }

        $pageTitle = $title;
        $pageStyles = $styles;
        $pageScripts = $scripts;
        $escape = escape(...);
        $isLoggedIn = Session::userId() !== null;
        $logoutToken = $isLoggedIn ? Csrf::token('logout') : null;

        require dirname(__DIR__, 2) . '/templates/partials/header.php';
        self::content($templatePath, array_merge($data, [
            'escape' => $escape,
            'isLoggedIn' => $isLoggedIn,
        ]));
        require dirname(__DIR__, 2) . '/templates/partials/footer.php';
    }

    private static function content(string $templatePath, array $data): void
    {
        // Seules les données explicitement transmises par la page sont disponibles dans la vue.
        extract($data, EXTR_SKIP);
        require $templatePath;
    }
}
