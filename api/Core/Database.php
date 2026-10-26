<?php
namespace Api\Core;

use PDO;
use RuntimeException;

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            \Dotenv\Dotenv::createImmutable(__DIR__ . '/../config')->safeLoad();
            if (($_ENV['DB_DRIVER'] ?? 'mysql') === 'sqlite') {
                $path = $_ENV['DB_PATH'] ?? 'api/database/loja.sqlite';
                if (!str_starts_with($path, '/')) {
                    $path = dirname(__DIR__, 2) . '/' . $path;
                }
                if (!is_file($path)) {
                    throw new RuntimeException('Banco local não encontrado. Execute scripts/setup-local.php.');
                }
                self::$instance = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                self::$instance->exec('PRAGMA foreign_keys = ON');
            } else {
                foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
                    if (!array_key_exists($key, $_ENV)) {
                        throw new RuntimeException('Configure o banco em api/config/.env.');
                    }
                }
                self::$instance = new PDO(
                    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
                    $_ENV['DB_USER'], $_ENV['DB_PASS'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            }
        }
        return self::$instance;
    }
}
