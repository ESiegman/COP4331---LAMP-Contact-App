<?php

namespace App;

class Database
{
    private static ?\PDO $connection = null;

    public static function get(): \PDO
    {
        if (self::$connection === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $name = getenv('DB_NAME') ?: 'ContactsAppDB';
            $user = getenv('DB_USER') ?: 'ContactsAppUser';
            $pass = getenv('DB_PASS') ?: '';

            self::$connection = new \PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        }

        return self::$connection;
    }
}
