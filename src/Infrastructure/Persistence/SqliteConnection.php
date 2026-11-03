<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Persistence;

use PDO;

/** Abre o banco SQLite local; o arquivo fica fora do Git (`database/*.sqlite`). */
final class SqliteConnection
{
    public const DEFAULT_FILE = 'database/jogo.sqlite';

    public static function open(string $file): PDO
    {
        $directory = dirname($file);
        if ($file !== ':memory:' && ! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $pdo = new PDO('sqlite:'.$file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
