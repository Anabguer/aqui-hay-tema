<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Etiquetas conceptuales. Cortes en calibración. Pareja no es banda romántica. */
final class RelacionBandas
{
    /**
     * @param array<string, mixed> $cal
     */
    public static function social(?int $valor, bool $conocidos, array $cal = []): string
    {
        if (!$conocidos) {
            return (string) CalibracionConfig::get($cal, 'social.etiqueta_sin_contacto', 'desconocido');
        }
        $v = $valor ?? 0;
        if ($v < 0) {
            $cortes = CalibracionConfig::get($cal, 'social.cortes_negativo', []);
            return self::deCortesDesc($v, is_array($cortes) ? $cortes : [], 'cae_mal');
        }
        $cortes = CalibracionConfig::get($cal, 'social.cortes_positivo', []);
        return self::deCortesAsc($v, is_array($cortes) ? $cortes : [], 'conocido');
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function romance(?int $valor, array $cal = []): string
    {
        $v = $valor === null ? 0 : max(0, $valor);
        $cortes = CalibracionConfig::get($cal, 'romance.cortes', []);
        return self::deCortesAsc($v, is_array($cortes) ? $cortes : [], 'sin_interes');
    }

    /**
     * @param array<string, int> $cortes
     */
    private static function deCortesAsc(int $valor, array $cortes, string $fallback): string
    {
        if ($cortes === []) {
            return $fallback;
        }
        asort($cortes, SORT_NUMERIC);
        $elegida = $fallback;
        foreach ($cortes as $nombre => $min) {
            if ($valor >= (int) $min) {
                $elegida = (string) $nombre;
            }
        }
        return $elegida;
    }

    /**
     * @param array<string, int> $cortes  umbral máximo (más negativo = peor)
     */
    private static function deCortesDesc(int $valor, array $cortes, string $fallback): string
    {
        if ($cortes === []) {
            return $fallback;
        }
        asort($cortes, SORT_NUMERIC);
        $elegida = $fallback;
        foreach ($cortes as $nombre => $umbral) {
            if ($valor <= (int) $umbral) {
                $elegida = (string) $nombre;
            }
        }
        return $elegida;
    }

    public static function clampSocial(?int $v): int
    {
        $n = $v ?? 0;
        if ($n < -100) {
            return -100;
        }
        if ($n > 100) {
            return 100;
        }
        return $n;
    }

    public static function clampRomance(?int $v): int
    {
        $n = $v ?? 0;
        if ($n < 0) {
            return 0;
        }
        if ($n > 100) {
            return 100;
        }
        return $n;
    }
}
