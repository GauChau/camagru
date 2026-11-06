<?php
declare(strict_types=1);

namespace Camagru\Repositories;

use PDO;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ImageRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function saveImageInfo(int $user_id, string $filekey): int
    {
        $query = $this->database->prepare(
            'INSERT INTO images(user_id, file_key) VALUES(:user_id, :file_key) RETURNING id'
        );
        $query->execute([
            'user_id' => $user_id,
            'file_key' => $filekey,
        ]);
        return (int) $query->fetchColumn();
    }

    public function getUserImageListByDate(int $user_id): array
    {
        $query = $this->database->prepare(
            'SELECT id, file_key FROM images WHERE user_id = :user_id ORDER BY created_at DESC, id DESC'
        );
        $query->execute([
            'user_id' => $user_id
        ]);

        return $query->fetchAll();
    }

    public function deleteImage(int $userId, string $fileKey): bool
    {
        if (preg_match('/\A[0-9a-f]{64}\z/', $fileKey) !== 1) {
            throw new InvalidArgumentException('La clé du montage est invalide.');
        }

        $this->database->beginTransaction();
        try {
            $query = $this->database->prepare(
                'DELETE FROM images
                 WHERE user_id = :user_id AND file_key = :file_key'
            );
            $query->execute(['user_id' => $userId, 'file_key' => $fileKey]);

            // Ne jamais toucher au fichier si le compte ne possède pas ce montage.
            if ($query->rowCount() !== 1) {
                $this->database->rollBack();
                return false;
            }

            $path = dirname(__DIR__, 2) . '/storage/cam_images/' . $fileKey . '.png';
            // Un fichier déjà absent n'empêche pas de nettoyer son enregistrement.
            if ((file_exists($path) || is_link($path)) && !@unlink($path)) {
                throw new RuntimeException('Impossible de supprimer le fichier du montage.');
            }

            $this->database->commit();
            return true;
        } catch (Throwable $exception) {
            // Si unlink échoue, conserver aussi les likes et commentaires en base.
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    public function countImages(): int
    {
        return (int) $this->database->query('SELECT count(*) FROM images')->fetchColumn();
    }

    public function getPage(int $page, int $pageSize, ?int $viewerId, ?int $commentsFor = null, int $commentPage = 1): array
    {
        $query = $this->database->prepare(
            'SELECT i.id, i.user_id, i.created_at, u.login,
                (SELECT count(*) FROM image_likes l WHERE l.image_id = i.id) AS like_count,
                (SELECT count(*) FROM image_comments c WHERE c.image_id = i.id) AS comment_count,
                EXISTS (SELECT 1 FROM image_likes l WHERE l.image_id = i.id AND l.user_id = :viewer) AS liked
            FROM images i JOIN users u ON u.id = i.user_id
            ORDER BY i.created_at DESC, i.id DESC LIMIT :limit OFFSET :offset'
        );
        $query->bindValue('viewer', $viewerId, $viewerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $query->bindValue('limit', $pageSize, PDO::PARAM_INT);
        $query->bindValue('offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $query->execute();
        $images = $query->fetchAll();
        foreach ($images as &$image) {
            $image['comment_pages'] = max(1, (int) ceil($image['comment_count'] / 10));
            $image['comment_page'] = (int) $image['id'] === $commentsFor ? min($commentPage, $image['comment_pages']) : 1;
            $comments = $this->database->prepare(
                'SELECT c.body, c.created_at, u.login FROM image_comments c
                JOIN users u ON u.id = c.user_id WHERE c.image_id = :image
                ORDER BY c.created_at DESC, c.id DESC LIMIT 10 OFFSET :offset'
            );
            $comments->bindValue('image', (int) $image['id'], PDO::PARAM_INT);
            $comments->bindValue('offset', ($image['comment_page'] - 1) * 10, PDO::PARAM_INT);
            $comments->execute();
            $image['comments'] = $comments->fetchAll();
        }
        return $images;
    }

    public function findImage(int $id): ?array
    {
        $query = $this->database->prepare(
            'SELECT i.id, i.file_key, u.email, u.comment_notifications, i.user_id
            FROM images i JOIN users u ON u.id = i.user_id WHERE i.id = :id'
        );
        $query->execute(['id' => $id]);
        return $query->fetch() ?: null;
    }

    public function setLike(int $imageId, int $userId, bool $liked): void
    {
        $query = $this->database->prepare($liked
            ? 'INSERT INTO image_likes (image_id, user_id) VALUES (:image, :user) ON CONFLICT DO NOTHING'
            : 'DELETE FROM image_likes WHERE image_id = :image AND user_id = :user');
        $query->execute(['image' => $imageId, 'user' => $userId]);
    }

    public function addComment(int $imageId, int $userId, string $body): void
    {
        $query = $this->database->prepare(
            'INSERT INTO image_comments (image_id, user_id, body) VALUES (:image, :user, :body)'
        );
        $query->execute(['image' => $imageId, 'user' => $userId, 'body' => $body]);
    }

}
