<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Calidad conceptual de un contacto. No todo protege igual del desgaste. */
final class ContactoCalidad
{
    public const LEVE = 'leve';
    public const NORMAL = 'normal';
    public const SIGNIFICATIVO = 'significativo';

    /**
     * @param array<string, mixed> $cal
     */
    public static function deltaSocial(string $calidad, array $cal, int $signo = 1): int
    {
        $mapa = CalibracionConfig::get($cal, 'contacto.delta_social', []);
        $base = 1;
        if (is_array($mapa) && isset($mapa[$calidad]) && is_numeric($mapa[$calidad])) {
            $base = (int) $mapa[$calidad];
        } elseif ($calidad === self::NORMAL) {
            $base = 3;
        } elseif ($calidad === self::SIGNIFICATIVO) {
            $base = 6;
        }
        $d = $signo < 0 ? -$base : $base;
        return self::techo($d, $cal);
    }

    /**
     * @param array<string, mixed> $cal
     */
    public static function techo(int $delta, array $cal): int
    {
        $t = CalibracionConfig::get($cal, 'contacto.techo_por_encuentro_canal', 10);
        $cap = is_numeric($t) ? abs((int) $t) : 10;
        if ($delta > $cap) {
            return $cap;
        }
        if ($delta < -$cap) {
            return -$cap;
        }
        return $delta;
    }

    public static function canon(string $calidad): string
    {
        if ($calidad === self::NORMAL || $calidad === self::SIGNIFICATIVO) {
            return $calidad;
        }
        return self::LEVE;
    }
}
