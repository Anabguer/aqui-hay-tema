<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class AgendaEngine
{
    public const CAPAS = ['programado', 'estructural', 'temporal', 'recurrente', 'autonomo'];

    /** Resuelve agenda de un día para un residente. */
    public static function resolverDia(
        array $partida,
        string $residenteId,
        ?int $diaPueblo = null,
        ?Catalog $catalog = null
    ): array {
        $diaPueblo ??= (int) $partida['reloj']['dia_pueblo'];
        $diaSemana = Reloj::diaSemana($diaPueblo);
        $residente = $partida['residentes'][$residenteId] ?? null;
        if ($residente === null) {
            throw new \InvalidArgumentException("residente desconocido: {$residenteId}");
        }

        $ocupacion = $residente['runtime']['ocupacion'] ?? 'autonomo';
        $slots = [];
        for ($h = 0; $h < 24; $h++) {
            $slots[$h] = [
                'hora' => $h,
                'ocupado' => false,
                'capa' => null,
                'tipo' => null,
                'detalle' => null,
                'reserva_id' => null,
            ];
        }

        self::aplicarEstructural($slots, $ocupacion, $diaSemana);
        self::aplicarRecurrentes($slots, $residente, $diaSemana);
        self::aplicarCitas($slots, $partida, $residenteId, $diaPueblo);

        return [
            'residente_id' => $residenteId,
            'dia_pueblo' => $diaPueblo,
            'dia_semana' => $diaSemana,
            'slots' => array_values($slots),
        ];
    }

    private static function aplicarEstructural(array &$slots, string $ocupacion, string $diaSemana): void
    {
        $sueno = AgendaTemplates::ventanaSueno($ocupacion);
        for ($h = 0; $h < 24; $h++) {
            if (self::horaEnRango($h, $sueno['hora_inicio'], $sueno['hora_fin'])) {
                $slots[$h] = [
                    'hora' => $h,
                    'ocupado' => true,
                    'capa' => 'estructural',
                    'tipo' => 'sueno',
                    'detalle' => 'sueño_estructural',
                    'reserva_id' => null,
                ];
            }
        }

        foreach (AgendaTemplates::bloquesTrabajo($ocupacion) as $bloque) {
            if (!in_array($diaSemana, $bloque['dias'], true)) {
                continue;
            }
            for ($h = $bloque['hora_inicio']; $h < min($bloque['hora_fin'], 24); $h++) {
                $slots[$h] = [
                    'hora' => $h,
                    'ocupado' => true,
                    'capa' => 'estructural',
                    'tipo' => $bloque['tipo'],
                    'detalle' => $ocupacion,
                    'reserva_id' => null,
                ];
            }
        }
    }

    private static function aplicarRecurrentes(array &$slots, array $residente, string $diaSemana): void
    {
        $compromisos = $residente['runtime']['compromisos_recurrentes'] ?? [];
        foreach ($compromisos as $c) {
            $diaComp = self::normalizarDiaSemana((string) ($c['dia_semana'] ?? ''));
            if ($diaComp !== $diaSemana) {
                continue;
            }
            $ini = (int) ($c['hora_inicio'] ?? 0);
            $fin = (int) ($c['hora_fin'] ?? $ini + 1);
            for ($h = $ini; $h < min($fin, 24); $h++) {
                if (!($slots[$h]['ocupado'] ?? false) || ($slots[$h]['capa'] ?? '') !== 'programado') {
                    $slots[$h] = [
                        'hora' => $h,
                        'ocupado' => true,
                        'capa' => 'recurrente',
                        'tipo' => 'compromiso',
                        'detalle' => $c['id'] ?? 'compromiso',
                        'reserva_id' => null,
                    ];
                }
            }
        }
    }

    private static function aplicarCitas(array &$slots, array $partida, string $residenteId, int $diaPueblo): void
    {
        foreach ($partida['citas'] as $cita) {
            if ((int) ($cita['dia'] ?? -1) !== $diaPueblo) {
                continue;
            }
            if (!in_array($cita['estado'] ?? '', ['programado', 'en_curso'], true)) {
                continue;
            }
            $participantes = $cita['participantes'] ?? [];
            if (!in_array($residenteId, $participantes, true)) {
                continue;
            }
            $hora = (int) ($cita['hora'] ?? 0);
            $slots[$hora] = [
                'hora' => $hora,
                'ocupado' => true,
                'capa' => 'programado',
                'tipo' => 'cita',
                'detalle' => $cita['id'],
                'reserva_id' => $cita['id'],
            ];
        }
    }

    public static function estaDisponible(array $partida, string $residenteId, int $dia, int $hora): array
    {
        $agenda = self::resolverDia($partida, $residenteId, $dia);
        $slot = $agenda['slots'][$hora] ?? null;
        if ($slot === null) {
            return ['disponible' => false, 'motivo' => 'hora_invalida'];
        }
        if ($slot['ocupado']) {
            return [
                'disponible' => false,
                'motivo' => 'ocupado',
                'capa' => $slot['capa'],
                'tipo' => $slot['tipo'],
                '_placeholder_rechazo_narrativo' => true,
            ];
        }
        return ['disponible' => true, 'motivo' => null];
    }

    public static function primerSlotLibre(array $partida, string $residenteId, int $dia, int $horaMin = 8): ?int
    {
        $agenda = self::resolverDia($partida, $residenteId, $dia);
        for ($h = max(0, $horaMin); $h < 24; $h++) {
            if (!$agenda['slots'][$h]['ocupado']) {
                return $h;
            }
        }
        return null;
    }

    private static function horaEnRango(int $hora, int $inicio, int $fin): bool
    {
        if ($inicio < $fin) {
            return $hora >= $inicio && $hora < $fin;
        }
        return $hora >= $inicio || $hora < $fin;
    }

    private static function normalizarDiaSemana(string $dia): string
    {
        $map = [
            'lunes' => 'lunes', 'martes' => 'martes', 'miercoles' => 'miercoles',
            'jueves' => 'jueves', 'viernes' => 'viernes', 'sabado' => 'sabado', 'domingo' => 'domingo',
        ];
        $k = strtolower(str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $dia));
        return $map[$k] ?? $k;
    }
}
