<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Nombre público de un residente para UI. Los IDs técnicos se conservan en logs. */
final class IdentidadPublica
{
    public static function nombre(array $partida, string $residenteId): string
    {
        $n = $partida['residentes'][$residenteId]['identidad_publica']['nombre'] ?? null;
        if (is_string($n) && trim($n) !== '') {
            return $n;
        }
        return $residenteId;
    }

    /**
     * @param list<string> $ids
     * @return array<string, string> id => nombre público
     */
    public static function mapa(array $partida, array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            if ($id === '') {
                continue;
            }
            $out[$id] = self::nombre($partida, $id);
        }
        return $out;
    }
}
