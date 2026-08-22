<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Pistas humanas de discovery. Sin IDs de motor ni scores. */
final class CopyDescubrimiento
{
    public static function texto(string $nombre, string $campo, $valor, CatalogStore $store, ?string $genero = null): ?string
    {
        $id = is_string($valor) && $valor !== '' ? $valor : self::idDeCampo($campo);
        if ($id === '') {
            return null;
        }
        if (str_starts_with($campo, 'hobby:')) {
            return 'Has descubierto que a ' . $nombre . ' le va ' . EtiquetaFicha::hobby($id, $store) . '.';
        }
        if (str_starts_with($campo, 'rasgo:')) {
            return $nombre . ' parece ' . self::min(EtiquetaFicha::rasgoParaGenero($id, $genero, $store)) . '.';
        }
        if (str_starts_with($campo, 'rechazo_personalidad:')) {
            return 'Has descubierto que ' . $nombre . ' no soporta a la gente '
                . self::min(EtiquetaFicha::rasgoParaGenero($id, $genero, $store)) . '.';
        }
        if (str_starts_with($campo, 'gusto_personalidad:')) {
            return $nombre . ' parece tener debilidad por la gente '
                . self::min(EtiquetaFicha::rasgoParaGenero($id, $genero, $store)) . '.';
        }
        if (str_starts_with($campo, 'rechazo_visual:')) {
            return 'A ' . $nombre . ' el look “' . EtiquetaFicha::visual($id, $store) . '” le echa para atrás. Sin más.';
        }
        if (str_starts_with($campo, 'gusto_visual:')) {
            return 'A ' . $nombre . ' le hace gracia el look “' . EtiquetaFicha::visual($id, $store) . '”. No preguntes.';
        }
        if (str_starts_with($campo, 'rechazo_hobby:')) {
            return $nombre . ' pone cara rara si sale el tema de '
                . EtiquetaFicha::hobby($id, $store) . '. Evítalo un rato.';
        }
        if (str_starts_with($campo, 'gusto_hobby:')) {
            return 'Si mencionas ' . EtiquetaFicha::hobby($id, $store) . ', ' . $nombre . ' se anima. Anota, anota.';
        }
        return null;
    }

    private static function min(string $s): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($s, 'UTF-8');
        }
        return strtolower($s);
    }

    public static function idDeCampo(string $campo): string
    {
        $p = strrpos($campo, ':');
        if ($p === false) {
            return '';
        }
        return substr($campo, $p + 1);
    }
}
