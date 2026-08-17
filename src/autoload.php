<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'AquiHayTema\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $candidates = [__DIR__ . DIRECTORY_SEPARATOR . $relative . '.php'];
    if (str_starts_with($relative, 'Api' . DIRECTORY_SEPARATOR)) {
        $candidates[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'api'
            . DIRECTORY_SEPARATOR . substr($relative, strlen('Api' . DIRECTORY_SEPARATOR)) . '.php';
    }
    foreach ($candidates as $path) {
        if (is_file($path)) {
            require $path;
            return;
        }
    }
});
