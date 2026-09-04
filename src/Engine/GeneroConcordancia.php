<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Autoridad central de concordancia de género para copy dinámico.
 *
 * Fuente canónica: identidad_publica.genero del residente en partida.
 * Fallback: forma no marcada (masculino neutro).
 * NUNCA produce "/a" visible.
 */
final class GeneroConcordancia
{
    /**
     * Género canónico del residente. Fallback: null.
     */
    public static function genero(array $partida, string $rid): ?string
    {
        $g = (string) ($partida['residentes'][$rid]['identidad_publica']['genero'] ?? '');
        return $g !== '' ? $g : null;
    }

    /**
     * Concordancia sufijo o/a. Fallback: 'o'.
     */
    public static function oa(array $partida, string $rid): string
    {
        return self::genero($partida, $rid) === 'mujer' ? 'a' : 'o';
    }

    /**
     * Concordancia pronombre clítico lo/la. Fallback: 'lo'.
     */
    public static function loLa(array $partida, string $rid): string
    {
        return self::genero($partida, $rid) === 'mujer' ? 'la' : 'lo';
    }
}
