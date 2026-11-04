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

        return $this->composeImage($userId, $contents, $overlayName);
    }

    public function uploadFile(int $userId, array $file, string $overlayName): array
    {
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK
            || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])
            || !is_int($file['size'] ?? null) || $file['size'] < 1 || $file['size'] > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Choisis une image PNG ou JPEG de 5 Mo maximum.');
        }
        $contents = file_get_contents($file['tmp_name']);
        if ($contents === false || strlen($contents) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Impossible de lire cette image, ou sa taille dépasse 5 Mo.');
        }
        return $this->composeImage($userId, $contents, $overlayName);
    }

    private function composeImage(int $userId, string $contents, string $overlayName): array
    {
        $info = @getimagesizefromstring($contents);
        if ($info === false || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)
            || $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096) {
            throw new InvalidArgumentException('L’image doit être un PNG ou JPEG de 4096 × 4096 pixels maximum.');
        }

        $overlayContents = $this->overlays->readImage($overlayName);
        set_error_handler(static function (): never {
            throw new InvalidArgumentException('La capture ou le calque est invalide.');
        }, E_WARNING | E_NOTICE);
        try {
            $overlayInfo = getimagesizefromstring($overlayContents);
            if ($overlayInfo === false) {
                throw new InvalidArgumentException('Le calque est invalide.');
            }
            // GD garde les deux images décodées et un tampon supplémentaire lors de l’encodage PNG.
            $estimatedMemory = $info[0] * $info[1] * 8 + $overlayInfo[0] * $overlayInfo[1] * 4 + 32 * 1024 * 1024;
            $memoryLimit = ini_parse_quantity((string) ini_get('memory_limit'));
            if ($memoryLimit > 0 && $estimatedMemory > $memoryLimit - memory_get_usage(true)) {
                throw new InvalidArgumentException('La capture et le calque sont trop grands pour être combinés. Utilise un calque plus petit ou réduis la résolution de la webcam.');
            }
            $overlay = imagecreatefromstring($overlayContents);
            unset($overlayContents);
            $capture = imagecreatefromstring($contents);
            if ($overlay === false || $capture === false) {
                throw new InvalidArgumentException('La capture ou le calque est invalide.');
            }
        } finally {
            restore_error_handler();
        }
        if ($info[2] === IMAGETYPE_JPEG) {
            $capture = $this->orientJpeg($capture, $contents);
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
            $imageId = $this->images->saveImageInfo($userId, $fileKey);
        } catch (Throwable $exception) {
            @unlink($path);
            throw $exception;
        }

        return [
            'file_key' => $fileKey,
            'image' => 'data:image/png;base64,' . base64_encode($contents),
            'image_url' => '/image.php?id=' . $imageId,
        ];
    }

    private function orientJpeg(\GdImage $image, string $contents): \GdImage
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Impossible de lire l’orientation du JPEG.');
        }
        try {
            fwrite($stream, $contents);
            rewind($stream);
            // Les JPEG sans métadonnées sont valides ; les tags inconnus sont ignorés.
            $metadata = @exif_read_data($stream);
        } finally {
            fclose($stream);
        }
        $orientation = (int) ($metadata['Orientation'] ?? 1);
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }
        $angle = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => 270,
            default => 0,
        };
        if ($angle !== 0) {
            $image = imagerotate($image, $angle, 0);
            if ($image === false) {
                throw new RuntimeException('Impossible d’orienter le JPEG.');
            }
        }
        return $image;
    }
}
