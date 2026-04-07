<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            config('db.host'),
            (int) config('db.port', 3306),
            config('db.database'),
            config('db.charset', 'utf8mb4')
        );

        try {
            self::$connection = new PDO($dsn, config('db.username'), config('db.password'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            throw new PDOException('Não foi possível conectar ao banco de dados.', (int) $exception->getCode(), $exception);
        }

        return self::$connection;
    }
}
