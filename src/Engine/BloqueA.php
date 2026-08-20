<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class BloqueA
{
    public static function viviendasVacias(): array
    {
        $viviendas = [];
        foreach (PartidaSchema::VIVIENDA_IDS as $id) {
            $viviendas[] = [
                'id' => $id,
                'ocupante_id' => null,
                'estado' => 'libre',
            ];
        }
        return $viviendas;
    }

    /** @return array{vivienda_id: string|null, error: string|null} */
    public static function asignarAutomatico(array &$partida, string $residenteId): array
    {
        // Delega a CapacidadViviendas (A, y B/C si están abiertos en lab/gate).
        $r = CapacidadViviendas::asignarAutomatico($partida, $residenteId);
        if (($r['error'] ?? null) === 'viviendas_llenas') {
            return ['vivienda_id' => null, 'error' => 'bloque_a_lleno'];
        }
        return $r;
    }

    public static function liberar(array &$partida, string $viviendaId): bool
    {
        foreach ($partida['bloque_a']['viviendas'] as &$vivienda) {
            if ($vivienda['id'] !== $viviendaId) {
                continue;
            }
            $ocupante = $vivienda['ocupante_id'];
            $vivienda['ocupante_id'] = null;
            $vivienda['estado'] = 'libre';
            if ($ocupante !== null && isset($partida['residentes'][$ocupante])) {
                $partida['residentes'][$ocupante]['vivienda_id'] = null;
                $partida['residentes'][$ocupante]['presencia'] = 'antiguo_residente';
            }
            return true;
        }
        return false;
    }

    public static function resumen(array $partida): array
    {
        $ocupadas = 0;
        foreach ($partida['bloque_a']['viviendas'] as $v) {
            if ($v['ocupante_id'] !== null) {
                $ocupadas++;
            }
        }
        return [
            'capacidad' => $partida['bloque_a']['capacidad'],
            'ocupadas' => $ocupadas,
            'libres' => $partida['bloque_a']['capacidad'] - $ocupadas,
            'viviendas' => $partida['bloque_a']['viviendas'],
        ];
    }
}
