<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Canon V3: el género es identidad; no hay veto romántico por orientación de género.
 */
final class IdentidadCanon
{
    /** @var list<string> */
    private const CAMPOS_ORIENTACION_LEGACY = ['atraido_por', 'etiqueta_orientacion_visible'];

    /**
     * @param array<string, mixed>|null $ident
     * @return array<string, mixed>|null
     */
    public static function sanitizarIdentidad(?array $ident): ?array
    {
        if (!is_array($ident)) {
            return $ident;
        }
        foreach (self::CAMPOS_ORIENTACION_LEGACY as $campo) {
            unset($ident[$campo]);
        }
        return $ident;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function sanitizarPersonaje(array $data): array
    {
        if (isset($data['identidad']) && is_array($data['identidad'])) {
            $data['identidad'] = self::sanitizarIdentidad($data['identidad']) ?? [];
        }
        return $data;
    }
}
