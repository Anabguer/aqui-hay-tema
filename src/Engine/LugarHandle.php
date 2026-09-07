<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Handles públicos de lugares para Cotilleos (estilo @cuenta).
 * Deriva el handle algorítmicamente desde lugares.json (fuente canónica).
 * Sin mapping manual — cualquier lugar nuevo obtiene su handle automáticamente.
 */
final class LugarHandle
{
    private const DEFAULT = '@puebloconfidencial';

    /** @var ?array<string, string> */
    private static ?array $cache = null;

    public static function de(?string $lugarId): string
    {
        if ($lugarId === null || $lugarId === '') {
            return self::DEFAULT;
        }

        $map = self::map();
        return $map[$lugarId] ?? self::DEFAULT;
    }

    /** @return array<string, string> */
    private static function map(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = dirname(__DIR__, 2) . '/data/lugares/lugares.json';
        if (!is_file($path)) {
            self::$cache = [];
            return self::$cache;
        }

        $data = JsonFile::read($path);
        $items = $data['items'] ?? [];
        $map = [];

        foreach ($items as $item) {
            $id = $item['id'] ?? '';
            $nombre = $item['nombre'] ?? '';
            if ($id !== '' && $nombre !== '') {
                $map[$id] = '@' . self::normalizar($nombre) . 'delpueblo';
            }
        }

        self::$cache = $map;
        return self::$cache;
    }

    private static function normalizar(string $texto): string
    {
        // Transliterar acentos: ñ→n, á→a, etc.
        $sinAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($sinAcentos === false) {
            $sinAcentos = $texto;
        }
        // Minúsculas, quitar espacios y guiones
        return str_replace([' ', '-', '_'], '', strtolower($sinAcentos));
    }
}
