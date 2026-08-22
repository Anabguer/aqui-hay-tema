<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Edad jugable derivada de meta visual (`apparent_age`). */
final class EdadCanonica
{
    public const MIN = 22;
    public const MAX = 72;

    /**
     * Convierte rango visual "28-32" en edad entera dentro de [22, 72].
     * Regla: redondeo al entero más cercano del punto medio del rango.
     */
    public static function desdeApparentAge(string $apparentAge): ?int
    {
        if (!preg_match('/^(\d+)-(\d+)$/', trim($apparentAge), $m)) {
            return null;
        }
        $lo = (int) $m[1];
        $hi = (int) $m[2];
        if ($lo > $hi) {
            return null;
        }
        $edad = (int) round(($lo + $hi) / 2);
        if ($edad < self::MIN) {
            $edad = self::MIN;
        }
        if ($edad > self::MAX) {
            $edad = self::MAX;
        }
        return $edad;
    }

    public static function desdePackMeta(string $root, string $packId): ?int
    {
        $safe = preg_replace('/[^A-Za-z0-9_]/', '', $packId) ?? '';
        if ($safe === '') {
            return null;
        }
        $path = rtrim($root, DIRECTORY_SEPARATOR)
            . '/assets/personajes/aprobados/' . $safe . '/' . $safe . '_meta.json';
        if (!is_file($path)) {
            return null;
        }
        $meta = JsonFile::read($path);
        $range = $meta['apparent_age'] ?? null;
        return is_string($range) && $range !== '' ? self::desdeApparentAge($range) : null;
    }
}
