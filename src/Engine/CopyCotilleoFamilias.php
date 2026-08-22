<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Familias deterministas de copy para Cotilleo y Aquí hay tema.
 * Una sola fuente JSON; sin strings desperdigados por PHP.
 */
final class CopyCotilleoFamilias
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @param array<string, string|int|float> $vars
     */
    public static function linea(string $familia, array $vars, string $seed = ''): string
    {
        $data = self::load();
        $pool = $data[$familia] ?? null;
        if (!is_array($pool) || $pool === []) {
            return '';
        }
        $tpl = $pool[abs(crc32($seed !== '' ? $seed : $familia)) % count($pool)];
        if (!is_string($tpl)) {
            return '';
        }
        $out = $tpl;
        foreach ($vars as $k => $v) {
            $out = str_replace('{' . $k . '}', (string) $v, $out);
        }
        return $out;
    }

    /** @return array<string, mixed> */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = dirname(__DIR__, 2) . '/data/catalogos/cotilleo_familias.json';
        if (!is_file($path)) {
            self::$cache = [];
            return self::$cache;
        }
        $raw = file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        self::$cache = is_array($data) ? $data : [];
        return self::$cache;
    }
}
