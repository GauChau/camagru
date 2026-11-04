<?php
declare(strict_types=1);

namespace Camagru\Repositories;

use PDO;

final class GalleryRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function countImages(): int
    {
        return (int) $this->database->query('SELECT count(*) FROM images')->fetchColumn();
    }

    public function getPage(int $page, int $pageSize, ?int $viewerId): array
    {
        $query = $this->database->prepare(
            'SELECT i.id, i.created_at, u.login,
                (SELECT count(*) FROM image_likes l WHERE l.image_id = i.id) AS like_count,
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
            $comments = $this->database->prepare(
                'SELECT c.body, c.created_at, u.login FROM image_comments c
                JOIN users u ON u.id = c.user_id WHERE c.image_id = :image
                ORDER BY c.created_at, c.id'
            );
            $comments->execute(['image' => $image['id']]);
            $image['comments'] = $comments->fetchAll();
        }
        return $images;
    }

    public function findImage(int $id): ?array
    {
        $query = $this->database->prepare(
            'SELECT i.id, i.file_key, u.email, u.comment_notifications
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
