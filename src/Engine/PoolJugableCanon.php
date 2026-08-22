<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Fuente canónica del pool jugable de producción: 200 personajes per_p001…per_p200.
 * La capacidad simultánea del pueblo (46) es independiente — ver CapacidadViviendas::CAP_PRODUCTO.
 */
final class PoolJugableCanon
{
    public const TOTAL = 200;
    public const ID_PATTERN = '/^per_p\d{3}$/';

    /** Fichas técnicas: nunca en pool aleatorio ni llegadas. */
    private const EXCLUIDOS = ['per_qa_valid', 'per_i02', 'per_i03'];

    /** @var list<string>|null */
    private static ?array $idsCache = null;

    /** @var array<string, mixed>|null */
    private static ?array $manifestCache = null;

    /**
     * @return list<string>
     */
    public static function ids(?string $root = null): array
    {
        if (self::$idsCache !== null) {
            return self::$idsCache;
        }
        if ($root !== null) {
            $path = rtrim($root, DIRECTORY_SEPARATOR) . '/data/personajes/_pool_canonico.json';
            if (is_file($path)) {
                $data = JsonFile::read($path);
                if (isset($data['ids']) && is_array($data['ids']) && count($data['ids']) === self::TOTAL) {
                    self::$idsCache = array_values(array_map('strval', $data['ids']));
                    return self::$idsCache;
                }
            }
        }
        $out = [];
        for ($i = 1; $i <= self::TOTAL; $i++) {
            $out[] = self::idDesdeIndice($i);
        }
        self::$idsCache = $out;
        return $out;
    }

    public static function idDesdeIndice(int $n): string
    {
        if ($n < 1 || $n > self::TOTAL) {
            throw new \InvalidArgumentException("Índice fuera de pool: $n");
        }
        return 'per_p' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    public static function packIdDesdeCatalogId(string $catalogId): ?string
    {
        if (!preg_match('/^per_p(\d{3})$/', $catalogId, $m)) {
            return null;
        }
        return 'P' . $m[1];
    }

    public static function esIdCanonico(string $id): bool
    {
        if ($id === '' || in_array($id, self::EXCLUIDOS, true)) {
            return false;
        }
        if (!preg_match(self::ID_PATTERN, $id)) {
            return false;
        }
        if (!preg_match('/^per_p(\d{3})$/', $id, $m)) {
            return false;
        }
        $n = (int) $m[1];
        return $n >= 1 && $n <= self::TOTAL;
    }

    /** @return array<string, mixed> */
    public static function manifest(?string $root = null): array
    {
        if (self::$manifestCache !== null) {
            return self::$manifestCache;
        }
        if ($root === null) {
            return ['total' => self::TOTAL, 'capacidad_simultanea' => CapacidadViviendas::CAP_PRODUCTO];
        }
        $path = rtrim($root, DIRECTORY_SEPARATOR) . '/data/personajes/_pool_canonico.json';
        if (!is_file($path)) {
            self::$manifestCache = [
                'version' => 1,
                'total' => self::TOTAL,
                'capacidad_simultanea' => CapacidadViviendas::CAP_PRODUCTO,
                'ids' => self::ids(),
            ];
            return self::$manifestCache;
        }
        $data = JsonFile::read($path);
        self::$manifestCache = is_array($data) ? $data : [];
        return self::$manifestCache;
    }

    public static function resetCache(): void
    {
        self::$idsCache = null;
        self::$manifestCache = null;
    }
}
