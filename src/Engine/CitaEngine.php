<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class CitaEngine
{
    public const ESTADOS = ['programado', 'en_curso', 'terminado', 'cancelado'];

    public static function programar(
        array &$partida,
        string $residenteA,
        string $residenteB,
        int $dia,
        int $hora,
        ?string $lugarId = null,
        ?string $actividad = null
    ): array {
        if ($residenteA === $residenteB) {
            return ['ok' => false, 'error' => 'mismos_participantes'];
        }
        foreach ([$residenteA, $residenteB] as $rid) {
            if (!isset($partida['residentes'][$rid])) {
                return ['ok' => false, 'error' => 'residente_inexistente', 'residente' => $rid];
            }
            if (($partida['residentes'][$rid]['presencia'] ?? '') !== 'residente') {
                return ['ok' => false, 'error' => 'no_residente_activo', 'residente' => $rid];
            }
        }

        $lugarId ??= 'lug_cafeteria';
        $operativos = $partida['celeste']['lugares_desbloqueados'] ?? [];
        if (!in_array($lugarId, $operativos, true)) {
            return ['ok' => false, 'error' => 'lugar_no_operativo', 'lugar' => $lugarId];
        }

        foreach ([$residenteA, $residenteB] as $rid) {
            $disp = AgendaEngine::estaDisponible($partida, $rid, $dia, $hora);
            if (!$disp['disponible']) {
                return ['ok' => false, 'error' => 'agenda_ocupada', 'residente' => $rid, 'detalle' => $disp];
            }
        }

        if (self::hayConflictoCita($partida, $residenteA, $residenteB, $dia, $hora)) {
            return ['ok' => false, 'error' => 'doble_reserva'];
        }

        $citaId = 'cita_' . bin2hex(random_bytes(4));
        $cita = [
            'id' => $citaId,
            'participantes' => [$residenteA, $residenteB],
            'lugar' => $lugarId,
            'hora' => $hora,
            'actividad' => $actividad,
            'ropa_elegida' => [],
            'estado_previo_relacion' => null,
            'eventos_ocurridos' => [],
            'causas' => [],
            'resultado' => null,
            'cambios_variables' => [],
            'recuerdos_generados' => [],
            'dia' => $dia,
            'coletilla_id' => null,
            'semilla_azar' => null,
            'estado' => 'programado',
            '_placeholder_resultado' => true,
        ];
        $partida['citas'][] = $cita;

        return ['ok' => true, 'cita' => $cita];
    }

    private static function hayConflictoCita(
        array $partida,
        string $a,
        string $b,
        int $dia,
        int $hora
    ): bool {
        foreach ($partida['citas'] as $cita) {
            if ((int) ($cita['dia'] ?? -1) !== $dia || (int) ($cita['hora'] ?? -1) !== $hora) {
                continue;
            }
            if (!in_array($cita['estado'] ?? '', ['programado', 'en_curso'], true)) {
                continue;
            }
            foreach ($cita['participantes'] ?? [] as $p) {
                if ($p === $a || $p === $b) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function cambiarEstado(array &$partida, string $citaId, string $nuevoEstado): array
    {
        if (!in_array($nuevoEstado, self::ESTADOS, true)) {
            return ['ok' => false, 'error' => 'estado_invalido'];
        }
        foreach ($partida['citas'] as &$cita) {
            if ($cita['id'] !== $citaId) {
                continue;
            }
            $cita['estado'] = $nuevoEstado;
            return ['ok' => true, 'cita' => $cita];
        }
        return ['ok' => false, 'error' => 'cita_no_encontrada'];
    }

    public static function cancelar(array &$partida, string $citaId): array
    {
        return self::cambiarEstado($partida, $citaId, 'cancelado');
    }

    public static function listarActivas(array $partida): array
    {
        return array_values(array_filter(
            $partida['citas'],
            static fn(array $c) => in_array($c['estado'] ?? '', ['programado', 'en_curso'], true)
        ));
    }
}
