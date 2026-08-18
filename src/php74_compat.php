<?php
declare(strict_types=1);

/**
 * Compatibilidad PHP 7.4 (Hostalia). No cambia reglas de juego.
 * En 8.x las funciones nativas tienen preferencia.
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);

        return $len <= strlen($haystack) && substr($haystack, -$len) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

/**
 * Equivalente 7.4 de $logger?->log(...). No cambia el contrato de GameLogger.
 *
 * @param object|null $logger
 */
function aht_log_optional($logger, array &$partida, string $tipo, array $detalle = []): void
{
    if (is_object($logger) && method_exists($logger, 'log')) {
        $logger->log($partida, $tipo, $detalle);
    }
}
