<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Slots horarios objetivamente compatibles para varios participantes. */
final class DisponibilidadEngine
{
    public static function slotsCompatibles(
        array $partida,
        array $participantes,
        string $tipoEncuentro = 'conocerse',
        ?int $desdeDia = null,
        ?int $desdeHora = null,
        int $maxDias = 7,
        int $maxSlots = 24,
        ?Catalog $catalog = null
    ): array {
        $participantes = array_values(array_unique(array_filter($participantes)));
        if (count($participantes) < 2) {
            return ['ok' => false, 'error' => 'participantes_insuficientes', 'slots' => []];
        }

        foreach ($participantes as $rid) {
            if (!isset($partida['residentes'][$rid])) {
                return ['ok' => false, 'error' => 'participante_inexistente', 'residente' => $rid, 'slots' => []];
            }
        }

        if (!in_array($tipoEncuentro, EncuentroEngine::TIPOS, true)) {
            return ['ok' => false, 'error' => 'tipo_invalido', 'slots' => []];
        }

        $desdeDia ??= (int) $partida['reloj']['dia_pueblo'];
        $desdeHora ??= (int) $partida['reloj']['hora_actual'];
        $slots = [];
        $now = $desdeDia * 24 + $desdeHora;

        for ($d = 0; $d < $maxDias && count($slots) < $maxSlots; $d++) {
            $dia = $desdeDia + $d;
            $horaMin = ($d === 0) ? $desdeHora : 0;
            for ($h = $horaMin; $h < 24 && count($slots) < $maxSlots; $h++) {
                if ($dia * 24 + $h < $now) {
                    continue;
                }
                if ($h === 23 && $dia === $desdeDia && $desdeHora > 23) {
                    continue;
                }
                $motivos = [];
                $libre = true;
                foreach ($participantes as $rid) {
                    $disp = AgendaEngine::estaDisponible($partida, $rid, $dia, $h);
                    if (!$disp['disponible']) {
                        $libre = false;
                        $motivos[$rid] = $disp['motivo'] ?? 'ocupado';
                    }
                }
                if (!$libre) {
                    continue;
                }
                if (EncuentroEngine::hayConflictoHorario($partida, $participantes, $dia, $h)) {
                    continue;
                }
                $slots[] = [
                    'dia' => $dia,
                    'hora' => $h,
                    'dia_semana' => Reloj::diaSemana($dia),
                    'participantes' => $participantes,
                    'tipo' => $tipoEncuentro,
                ];
            }
        }

        return ['ok' => true, 'slots' => $slots, 'total' => count($slots)];
    }
}
