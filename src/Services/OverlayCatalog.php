<?php
declare(strict_types=1);

namespace Camagru\Services;

use InvalidArgumentException;
use RuntimeException;

final class OverlayCatalog
{
    private const DIRECTORY = __DIR__ . '/../../storage/calcs';

    public function listImages(): array
    {
        if (!is_dir(self::DIRECTORY)) {
            return [];
        }
        if (!is_readable(self::DIRECTORY)) {
            throw new RuntimeException('Impossible de lire le stock de calques.');
        }
        $names = scandir(self::DIRECTORY);
        if ($names === false) {
            throw new RuntimeException('Impossible de lire le stock de calques.');
        }

        return array_values(array_filter($names, $this->isImage(...)));
    }

    public function readImage(string $name): string
    {
        if (!$this->isImage($name)) {
            throw new InvalidArgumentException('Choisis un calque disponible.');
        }
        $contents = file_get_contents(self::DIRECTORY . '/' . $name);
        if ($contents === false) {
            throw new RuntimeException('Impossible de lire le calque.');
        }

        return $contents;
    }

    private function isImage(string $name): bool
    {
        $expectedType = match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'png' => IMAGETYPE_PNG,
            'jpg', 'jpeg' => IMAGETYPE_JPEG,
            default => null,
        };
        if ($name !== basename($name) || str_contains($name, "\0")
            || $expectedType === null) {
            return false;
        }
        $path = self::DIRECTORY . '/' . $name;
        if (!is_file($path) || is_link($path)) {
            return false;
        }
        if (!is_readable($path)) {
            throw new RuntimeException('Un calque du stock est illisible.');
        }
        set_error_handler(static function (): never {
            throw new InvalidArgumentException('Le calque est invalide.');
        }, E_WARNING | E_NOTICE);
        try {
            $info = getimagesize($path);
        } catch (InvalidArgumentException $exception) {
            return false;
        } finally {
            restore_error_handler();
        }

        return $info !== false && $info[2] === $expectedType
            && $info[0] > 0 && $info[1] > 0 && $info[0] <= 4096 && $info[1] <= 4096
            && filesize($path) <= 5 * 1024 * 1024;
    }
}
