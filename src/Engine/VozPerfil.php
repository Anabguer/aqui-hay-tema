<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Voz: string legado = solo registro, o perfil con dimensiones opcionales.
 * No genera textos. Catálogo de registros extensible.
 */
final class VozPerfil
{
    public static function normalizar(mixed $voz): array
    {
        if (is_string($voz) && $voz !== '') {
            return [
                'registro' => $voz,
                'verbosidad' => null,
                'humor' => null,
                'frontalidad' => null,
                'calidez' => null,
                'muletilla' => null,
                '_legado_string' => true,
            ];
        }
        if (!is_array($voz)) {
            return [
                'registro' => null,
                'verbosidad' => null,
                'humor' => null,
                'frontalidad' => null,
                'calidez' => null,
                'muletilla' => null,
                '_legado_string' => false,
            ];
        }
        return [
            'registro' => $voz['registro'] ?? $voz['id'] ?? null,
            'verbosidad' => $voz['verbosidad'] ?? null,
            'humor' => $voz['humor'] ?? null,
            'frontalidad' => $voz['frontalidad'] ?? null,
            'calidez' => $voz['calidez'] ?? null,
            'muletilla' => $voz['muletilla'] ?? null,
            '_legado_string' => false,
        ];
    }

    public static function desdeFicha(array $ficha): array
    {
        return self::normalizar($ficha['narrativa']['voz'] ?? $ficha['voz'] ?? null);
    }
}
