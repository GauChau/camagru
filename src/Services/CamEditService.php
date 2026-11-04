<?php
declare(strict_types=1);

namespace Camagru\Services;

use Camagru\Repositories\ImageRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class CamEditService
{
    public function __construct(private ImageRepository $images, private OverlayCatalog $overlays)
    {
    }

    public function uploadImage(int $userId, string $image, string $overlayName): array
    {
        $prefix = 'data:image/png;base64,';
        if (!str_starts_with($image, $prefix) || strlen($image) > 7 * 1024 * 1024) {
            throw new InvalidArgumentException('La capture doit être un PNG de 5 Mo maximum.');
        }

        $contents = base64_decode(substr($image, strlen($prefix)), true);
        if ($contents === false || strlen($contents) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('La capture est invalide ou trop volumineuse.');
        }

        $info = @getimagesizefromstring($contents);
        if ($info === false || $info[2] !== IMAGETYPE_PNG
            || $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096) {
            throw new InvalidArgumentException('La capture doit être un PNG de 4096 × 4096 pixels maximum.');
        }

        $overlay = @imagecreatefromstring($this->overlays->readImage($overlayName));
        $capture = @imagecreatefromstring($contents);
        if ($overlay === false || $capture === false) {
            throw new InvalidArgumentException('La capture ou le calque est invalide.');
        }
        imagepalettetotruecolor($capture);
        imagealphablending($capture, true);
        imagesavealpha($capture, true);
        if (!imagecopyresampled($capture, $overlay, 0, 0, 0, 0,
            imagesx($capture), imagesy($capture), imagesx($overlay), imagesy($overlay))) {
            throw new RuntimeException('Impossible de composer le montage.');
        }
        ob_start();
        try {
            if (!imagepng($capture)) {
                throw new RuntimeException('Impossible d’encoder le montage.');
            }
            $contents = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        unset($capture, $overlay);

        $directory = dirname(__DIR__, 2) . '/storage/cam_images';
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de créer le dossier des captures.');
        }

        $fileKey = bin2hex(random_bytes(32));
        $path = $directory . '/' . $fileKey . '.png';
        if (@file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) {
            @unlink($path);
            throw new RuntimeException('Impossible d’enregistrer la capture.');
        }

        try {
            $this->images->saveImageInfo($userId, $fileKey);
        } catch (Throwable $exception) {
            @unlink($path);
            throw $exception;
        }

        return ['file_key' => $fileKey, 'image' => 'data:image/png;base64,' . base64_encode($contents)];
    }
}
