<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class DevCalendarService
{
    public static function vistaDia(array $partida, int $dia, ?Catalog $catalog = null): array
    {
        $filas = [];
        $conflictos = [];

        foreach (array_keys($partida['residentes'] ?? []) as $rid) {
            $agenda = AgendaEngine::resolverDia($partida, $rid, $dia, $catalog);
            $nombre = $partida['residentes'][$rid]['identidad_publica']['nombre'] ?? $rid;
            $horas = [];
            foreach ($agenda['slots'] as $slot) {
                $h = (int) $slot['hora'];
                $horas[$h] = $slot['ocupado']
                    ? ['ocupado' => true, 'tipo' => $slot['tipo'], 'detalle' => $slot['detalle'], 'reserva_id' => $slot['reserva_id']]
                    : ['ocupado' => false];
            }
            $filas[] = ['residente_id' => $rid, 'nombre' => $nombre, 'horas' => $horas];
        }

        foreach ($partida['encuentros'] ?? [] as $enc) {
            if ((int) ($enc['dia'] ?? -1) !== $dia) {
                continue;
            }
            if (!in_array($enc['estado'] ?? '', ['programado', 'en_curso'], true)) {
                continue;
            }
            $h = (int) ($enc['hora'] ?? 0);
            $parts = $enc['participantes'] ?? [];
            if (count($parts) >= 2) {
                foreach ($parts as $rid) {
                    $disp = AgendaEngine::estaDisponible($partida, $rid, $dia, $h);
                    if (!$disp['disponible']) {
                        $conflictos[] = [
                            'encuentro_id' => $enc['id'],
                            'residente_id' => $rid,
                            'hora' => $h,
                            'motivo' => $disp['motivo'] ?? 'ocupado',
                        ];
                    }
                }
            }
        }

        return [
            'ok' => true,
            'dia' => $dia,
            'dia_semana' => Reloj::diaSemana($dia),
            'residentes' => $filas,
            'encuentros_dia' => array_values(array_filter(
                $partida['encuentros'] ?? [],
                static fn($e) => (int) ($e['dia'] ?? -1) === $dia
            )),
            'conflictos' => $conflictos,
        ];
    }
}
