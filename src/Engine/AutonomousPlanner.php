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

        $planId = 'npc_plan_' . bin2hex(random_bytes(4));
        $plan = [
            'id' => $planId,
            'tipo' => 'autonomo',
            'accion' => $elegido['accion'] ?? 'visitar_lugar',
            'lugar' => $elegido['lugar'] ?? null,
            'dia' => $dia,
            'hora' => $hora,
            'estado' => 'programado',
            'participantes' => [$residenteId],
            'reserva_agenda' => ['tipo' => 'autonomo', 'origen' => 'npc_autonomo'],
            '_placeholder_dev' => true,
        ];
        $partida['npc_autonomo']['planes_pendientes'] ??= [];
        $partida['npc_autonomo']['planes_pendientes'][] = $plan;

        $logger?->log($partida, 'npc_autonomo_plan', [
            'residente' => $residenteId,
            'candidatos' => $candidatos,
            'elegido' => $elegido,
            'rng_state' => $rng->getState(),
            '_placeholder' => true,
            'plan' => $plan,
        ]);

        DomainEventDispatcher::emit($partida, DomainEvents::NPC_AUTONOMO_PLAN, [
            'residente_id' => $residenteId,
            'elegido' => $elegido,
            'plan_id' => $planId,
            'actores' => [$residenteId],
        ], $logger, 'AutonomousPlanner::planificarSlot');

        return ['ok' => true, 'plan' => $plan, 'candidatos' => $candidatos];
    }
}
