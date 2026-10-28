<?php

namespace Database;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {

            $config = require __DIR__ . '/../config/Database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['database']
            );

            date_default_timezone_set('Asia/Ho_Chi_Minh');

            try {
                self::$connection = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                self::$connection->exec("SET time_zone = '+07:00'");
            } catch (PDOException $e) {
                die(
                    'Kết nối database thất bại: '
                    . $e->getMessage()
                );
            }
        }

        return self::$connection;
    }
}