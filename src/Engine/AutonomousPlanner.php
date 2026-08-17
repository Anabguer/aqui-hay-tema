<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Planner NPC autónomo — arquitectura; acciones placeholder dev. */
final class AutonomousPlanner
{
    public static function planificarSlot(
        array &$partida,
        string $residenteId,
        int $dia,
        int $hora,
        RngService $rng,
        ?GameLogger $logger = null
    ): array {
        $disp = AgendaEngine::estaDisponible($partida, $residenteId, $dia, $hora);
        if (!$disp['disponible']) {
            return ['ok' => false, 'error' => 'slot_ocupado', 'detalle' => $disp];
        }

        $operativos = $partida['celeste']['lugares_desbloqueados'] ?? [];
        $candidatos = [];
        foreach ($operativos as $lug) {
            $candidatos[] = [
                'accion' => 'visitar_lugar',
                'lugar' => $lug,
                '_placeholder_dev' => true,
            ];
        }
        if ($candidatos === []) {
            return ['ok' => false, 'error' => 'sin_lugares_validos'];
        }

        $idx = $rng->nextInt(0, count($candidatos) - 1);
        $rng->persistToPartida($partida);
        $elegido = $candidatos[$idx];

        $logger?->log($partida, 'npc_autonomo_plan', [
            'residente' => $residenteId,
            'candidatos' => $candidatos,
            'elegido' => $elegido,
            'rng_state' => $rng->getState(),
            '_placeholder' => true,
        ]);

        return ['ok' => true, 'plan' => $elegido, 'candidatos' => $candidatos];
    }
}
