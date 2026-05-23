<?php
declare(strict_types=1);

class Database
{
    private const HOST = 'localhost';
    private const USER = 'root';
    private const PASS = '';
    private const NAME = 'seguridad_app';

    private static ?mysqli $connection = null;

    private function __construct()
    {
    }

    public static function getConnection(): mysqli
    {
        if (self::$connection === null) {
            self::$connection = new mysqli(self::HOST, self::USER, self::PASS, self::NAME);

            if (self::$connection->connect_error) {
                error_log('DB connection failed: ' . self::$connection->connect_error);
                throw new RuntimeException('Error interno del servidor. Intenta más tarde.');
            }

            self::$connection->set_charset('utf8mb4');
        }

        return self::$connection;
    }
}
