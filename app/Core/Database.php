<?php

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;

        $host = (string) env('DB_HOST', 'localhost');
        $port = (string) env('DB_PORT', '3306');
        $db   = (string) env('DB_DATABASE', 'fiscalizacao_obras');
        $user = (string) env('DB_USERNAME', 'root');
        $pass = (string) env('DB_PASSWORD', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$pdo;
    }
}
