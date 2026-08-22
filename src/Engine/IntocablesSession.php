<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Sesión Intocables (login propio) — sin OAuth ni usuarios AHT. */
final class IntocablesSession
{
    public static function findIncludesDir(string $projectRoot): ?string
    {
        $juegosRoot = dirname(rtrim($projectRoot, DIRECTORY_SEPARATOR));
        $htdocs = dirname($juegosRoot);
        $candidates = [
            $htdocs . DIRECTORY_SEPARATOR . 'intocables' . DIRECTORY_SEPARATOR . 'includes',
            $htdocs . DIRECTORY_SEPARATOR . 'includes',
            $juegosRoot . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'intocables' . DIRECTORY_SEPARATOR . 'includes',
        ];
        foreach ($candidates as $dir) {
            $real = realpath($dir);
            if ($real !== false && is_file($real . DIRECTORY_SEPARATOR . 'auth.php')) {
                return $real;
            }
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    public static function currentUser(string $projectRoot): ?array
    {
        static $loaded = false;
        static $user = null;
        if ($loaded) {
            return $user;
        }
        $loaded = true;
        try {
            $inc = self::findIncludesDir($projectRoot);
            if ($inc === null) {
                return null;
            }
            $config = $inc . DIRECTORY_SEPARATOR . 'config.php';
            if (is_file($config)) {
                require_once $config;
            } else {
                require_once $inc . DIRECTORY_SEPARATOR . 'database.php';
            }
            require_once $inc . DIRECTORY_SEPARATOR . 'auth.php';
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            if (!function_exists('isLoggedIn') || !isLoggedIn()) {
                return null;
            }
            if (!function_exists('getCurrentUser')) {
                return null;
            }
            $u = getCurrentUser();
            if (!is_array($u) || empty($u['id'])) {
                return null;
            }
            $user = $u;
            return $user;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function currentUserId(string $projectRoot): ?int
    {
        $u = self::currentUser($projectRoot);
        if ($u === null) {
            return null;
        }
        $id = (int) $u['id'];
        return $id > 0 ? $id : null;
    }
}
