<?php

declare(strict_types=1);

namespace ContactModule\Database;

use ContactModule\Config\Config;
use PDO;

final class Connection
{
    public static function sqlite(string $path, bool $readOnly = false): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ];
        if ($readOnly) {
            if (class_exists(\Pdo\Sqlite::class)) {
                $options[\Pdo\Sqlite::ATTR_OPEN_FLAGS] = \Pdo\Sqlite::OPEN_READONLY;
            } else {
                $options[PDO::SQLITE_ATTR_OPEN_FLAGS] = PDO::SQLITE_OPEN_READONLY;
            }
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    public static function mysql(Config $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config->text('DB_HOST'),
            $config->integer('DB_PORT'),
            $config->text('DB_DATABASE')
        );
        return new PDO($dsn, $config->text('DB_USERNAME'), $config->text('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
            (class_exists(\Pdo\Mysql::class)
                ? \Pdo\Mysql::ATTR_MULTI_STATEMENTS : PDO::MYSQL_ATTR_MULTI_STATEMENTS) => false,
        ]);
    }
}
