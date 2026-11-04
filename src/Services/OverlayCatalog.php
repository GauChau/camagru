<?php
declare(strict_types=1);

namespace Camagru\Services;

use InvalidArgumentException;

final class OverlayCatalog
{
    private const DIRECTORY = __DIR__ . '/../../storage/calcs';

    public function listImages(): array
    {
        $names = @scandir(self::DIRECTORY);
        if ($names === false) {
            return [];
        }

        $images = [];
        foreach ($names as $name) {
            $path = self::DIRECTORY . '/' . $name;
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'png' || !is_file($path) || is_link($path)) {
                continue;
            }
            $info = @getimagesize($path);
            if ($info !== false && $info[2] === IMAGETYPE_PNG
                && $info[0] > 0 && $info[1] > 0 && $info[0] <= 4096 && $info[1] <= 4096
                && filesize($path) <= 5 * 1024 * 1024) {
                $images[] = $name;
            }
        }

        return $images;
    }

    public function readImage(string $name): string
    {
        if (!in_array($name, $this->listImages(), true)) {
            throw new InvalidArgumentException('Choisis un calque disponible.');
        }
        $contents = @file_get_contents(self::DIRECTORY . '/' . $name);
        if ($contents === false) {
            throw new InvalidArgumentException('Ce calque est indisponible. Recharge la page.');
        }

        return $contents;
    }
}
