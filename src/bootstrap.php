<?php
declare(strict_types=1);

// Les classes Camagru suivent la même arborescence que leur namespace dans src/.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Camagru\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require_once __DIR__ . '/View/functions.php';
