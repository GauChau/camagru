<?php
declare(strict_types=1);

namespace Camagru\Services;

use Camagru\Repositories\GalleryRepository;
use InvalidArgumentException;

final class GalleryService
{
    public function __construct(private GalleryRepository $gallery)
    {
    }

    /** Retourne false si le montage n'existe plus. */
    public function react(int $imageId, int $userId, string $action, string $comment): bool
    {
        if (!in_array($action, ['like', 'unlike', 'comment'], true)) {
            throw new InvalidArgumentException('Cette action est invalide.');
        }
        $comment = trim($comment);
        if ($action === 'comment' && ($comment === '' || strlen($comment) > 8000
            || preg_match('/\A.{1,2000}\z/us', $comment) !== 1 || str_contains($comment, "\0"))) {
            throw new InvalidArgumentException('Le commentaire doit contenir entre 1 et 2 000 caractères.');
        }
        $image = $this->gallery->findImage($imageId);
        if ($image === null) {
            return false;
        }
        if ($action !== 'comment') {
            $this->gallery->setLike($imageId, $userId, $action === 'like');
            return true;
        }

        $this->gallery->addComment($imageId, $userId, $comment);
        if ($image['comment_notifications']) {
            // Un échec du transport ne doit pas inciter à publier le commentaire deux fois.
            $sent = @mail(
                $image['email'],
                'Nouveau commentaire sur ton montage Camagru',
                "Ton montage a reçu un nouveau commentaire :\r\n\r\n" . $comment
                    . "\r\n\r\nVoir la galerie : " . rtrim((string) getenv('APP_URL'), '/') . '/gallery.php'
                    . "\r\nTu peux désactiver ces emails dans les paramètres de ton compte.",
                ['From' => 'no-reply@camagru.test', 'Content-Type' => 'text/plain; charset=UTF-8']
            );
            if (!$sent) {
                error_log('Notification de commentaire non envoyée.');
            }
        }
        return true;
    }
}
