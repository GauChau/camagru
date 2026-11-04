<?php
declare(strict_types=1);

namespace Camagru\Repositories;

use PDO;

final class ImageRepository
{
    public function __construct(private PDO $database)
    {
    }

	public function saveImageInfo(int $user_id, string $filekey): void
	{
		$query = $this->database->prepare(
			'INSERT INTO images(user_id, file_key) VALUES(:user_id, :file_key)'
		);
		$query->execute([
			'user_id'=> $user_id,
			'file_key'=> $filekey,
		]);
	}

    public function getUserImageListByDate(int $user_id): ?array
    {
        $query = $this->database->prepare(
            'SELECT file_key FROM images WHERE user_id = :user_id ORDER BY created_at DESC, id DESC'
        );
        $query->execute([
            'user_id' => $user_id
        ]);
        
        // return $query->fetchAll(\PDO::FETCH_COLUMN);
        return $query->fetchAll();
    }

	public function deleteImage(int $user_id, string $file_key): void
	{
		$query = $this->database->prepare(
			'DELETE FROM images
			WHERE user_id = :user_id AND file_key = :file_key'
		);

		$query->execute([
			'user_id' => $user_id,
			'file_key' => $file_key,
		]);
	}
    

}
