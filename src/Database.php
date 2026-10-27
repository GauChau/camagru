<?php
declare(strict_types=1);

namespace Camagru;

use PDO;
use RuntimeException;

final class Database
{
    public static function connect(): PDO
    {
        // Compose transmet ces variables du .env au conteneur PHP.
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $name) {
            if (getenv($name) === false || getenv($name) === '') {
                throw new RuntimeException('Configuration de la base de données incomplète.');
            }
        }

        return new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                getenv('DB_HOST'),
                getenv('DB_PORT'),
                getenv('DB_NAME')
            ),
            getenv('DB_USER'),
            getenv('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
