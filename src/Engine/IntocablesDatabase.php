<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class IntocablesDatabase
{
    private static ?bool $available = null;

    public static function isAvailable(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }
        try {
            self::pdo();
            self::$available = true;
        } catch (\Throwable $e) {
            self::$available = false;
        }
        return self::$available;
    }

    public static function pdo(): \PDO
    {
        static $pdo = null;
        if ($pdo instanceof \PDO) {
            return $pdo;
        }
        $root = dirname(__DIR__, 2);
        $inc = IntocablesSession::findIncludesDir($root);
        if ($inc === null) {
            throw new \RuntimeException('intocables_includes_missing');
        }
        $config = $inc . DIRECTORY_SEPARATOR . 'config.php';
        if (is_file($config)) {
            require_once $config;
        } else {
            require_once $inc . DIRECTORY_SEPARATOR . 'database.php';
        }
        if (function_exists('getDBConnection')) {
            $pdo = getDBConnection();
            if ($pdo instanceof \PDO) {
                return $pdo;
            }
        }
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
            throw new \RuntimeException('db_config_missing');
        }
        $pdo = new \PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            defined('DB_PASSWORD') ? DB_PASSWORD : '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    }

    /** Para tests: resetea caché de disponibilidad. */
    public static function resetAvailabilityCache(): void
    {
        self::$available = null;
    }
}
