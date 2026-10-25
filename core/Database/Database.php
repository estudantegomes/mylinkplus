<?php

namespace Core\Database;

use Core\Constants\Constants;
use PDO;

class Database
{
    public static function getDatabaseConn(): PDO
    {
        $host = $_ENV['DB_HOST'];
        $port = $_ENV['DB_PORT'];
        $db   = $_ENV['DB_DATABASE'];
        $user = $_ENV['DB_USERNAME'];
        $pwd  = $_ENV['DB_PASSWORD'];

        $pdo = new PDO(
            "pgsql:host={$host};port={$port};dbname={$db}",
            $user,
            $pwd,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        return $pdo;
    }

    public static function getConn(): PDO
    {
        $host = $_ENV['DB_HOST'];
        $port = $_ENV['DB_PORT'];
        $user = $_ENV['DB_USERNAME'];
        $pwd  = $_ENV['DB_PASSWORD'];

        return new PDO(
            "pgsql:host={$host};port={$port};dbname=postgres",
            $user,
            $pwd,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    public static function create(): void
    {
        $db = str_replace('"', '""', $_ENV['DB_DATABASE']);
        self::getConn()->exec('CREATE DATABASE "' . $db . '"');
    }

    public static function drop(): void
    {
        $db = str_replace('"', '""', $_ENV['DB_DATABASE']);
        self::getConn()->exec('DROP DATABASE IF EXISTS "' . $db . '"');
    }

    public static function migrate(): void
    {
        $sql = file_get_contents(Constants::databasePath()->join('schema.sql'));
        self::getDatabaseConn()->exec($sql);
    }

    public static function exec(string $sql): void
    {
        self::getDatabaseConn()->exec($sql);
    }
}
