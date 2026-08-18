<?php
declare(strict_types=1);

require_once __DIR__ . '/php74_compat.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'AquiHayTema\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    // Rutas POSIX: Hostalia es Linux y distingue mayúsculas. api/handlers/ en disco.
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $candidates = [__DIR__ . '/' . $relative . '.php'];
    if (str_starts_with($relative, 'Api/')) {
        $rest = substr($relative, strlen('Api/'));
        $slash = strrpos($rest, '/');
        if ($slash === false) {
            $apiRel = $rest . '.php';
        } else {
            $apiRel = strtolower(substr($rest, 0, $slash)) . '/' . substr($rest, $slash + 1) . '.php';
        }
        $candidates[] = dirname(__DIR__) . '/api/' . $apiRel;
    }
    foreach ($candidates as $path) {
        if (is_file($path)) {
            require $path;
            return;
        }
    }
});
