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
        $diaSemana = Reloj::diaSemana($diaPueblo, $partida['reloj'] ?? []);
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
        self::aplicarReservasProgramadas($slots, $partida, $residenteId, $diaPueblo);
        self::aplicarReservasDesdeDiaAnterior($slots, $partida, $residenteId, $diaPueblo);

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

    /** Reservas programadas: encuentros, planes autónomos futuros, etc. */
    private static function aplicarReservasProgramadas(array &$slots, array $partida, string $residenteId, int $diaPueblo): void
    {
        $items = EncuentroEngine::list($partida);
        foreach ($partida['npc_autonomo']['planes_pendientes'] ?? [] as $plan) {
            $items[] = $plan;
        }

        foreach ($items as $item) {
            if ((int) ($item['dia'] ?? -1) !== $diaPueblo) {
                continue;
            }
            $estado = $item['estado'] ?? 'programado';
            if (!in_array($estado, ['programado', 'en_curso'], true)) {
                continue;
            }
            $participantes = $item['participantes'] ?? [];
            if (!in_array($residenteId, $participantes, true)) {
                continue;
            }
            $hora = (int) ($item['hora'] ?? 0);
            $dur = LugarAtributos::horasDeEncuentro($item);
            $reserva = $item['reserva_agenda'] ?? ['tipo' => 'encuentro'];
            for ($off = 0; $off < $dur; $off++) {
                $h = $hora + $off;
                if ($h >= 24) {
                    break;
                }
                $slots[$h] = [
                    'hora' => $h,
                    'ocupado' => true,
                    'capa' => 'programado',
                    'tipo' => $reserva['tipo'] ?? 'encuentro',
                    'detalle' => $item['id'] ?? null,
                    'reserva_id' => $item['id'] ?? null,
                ];
            }
        }
    }

    /** Encuentros del día anterior que continúan tras medianoche. */
    private static function aplicarReservasDesdeDiaAnterior(
        array &$slots,
        array $partida,
        string $residenteId,
        int $diaPueblo
    ): void {
        if ($diaPueblo < 1) {
            return;
        }
        $diaAnterior = $diaPueblo - 1;
        $items = EncuentroEngine::list($partida);
        foreach ($partida['npc_autonomo']['planes_pendientes'] ?? [] as $plan) {
            $items[] = $plan;
        }

        foreach ($items as $item) {
            if ((int) ($item['dia'] ?? -1) !== $diaAnterior) {
                continue;
            }
            $estado = $item['estado'] ?? 'programado';
            if (!in_array($estado, ['programado', 'en_curso'], true)) {
                continue;
            }
            $participantes = $item['participantes'] ?? [];
            if (!in_array($residenteId, $participantes, true)) {
                continue;
            }
            $hora = (int) ($item['hora'] ?? 0);
            $dur = LugarAtributos::horasDeEncuentro($item);
            $fin = $hora + $dur;
            if ($fin <= 24) {
                continue;
            }
            $reserva = $item['reserva_agenda'] ?? ['tipo' => 'encuentro'];
            for ($h = 0; $h < $fin - 24; $h++) {
                $slots[$h] = [
                    'hora' => $h,
                    'ocupado' => true,
                    'capa' => 'programado',
                    'tipo' => $reserva['tipo'] ?? 'encuentro',
                    'detalle' => $item['id'] ?? null,
                    'reserva_id' => $item['id'] ?? null,
                ];
            }
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

    /**
     * Disponible durante todo el intervalo [hora, hora+duracion).
     *
     * El sueño estructural es rutina habitual: bloquea empezar un plan dentro de esa
     * ventana, pero no invalida horas posteriores si el plan ya empezó antes de dormir.
     * Trabajo, compromisos y encuentros programados siguen siendo bloqueos duros.
     *
     * @return array<string, mixed>
     */
    public static function estaDisponibleIntervalo(
        array $partida,
        string $residenteId,
        int $dia,
        int $hora,
        int $duracionHoras
    ): array {
        $duracionHoras = max(1, $duracionHoras);
        $dispInicio = self::estaDisponible($partida, $residenteId, $dia, $hora);
        if (!($dispInicio['disponible'] ?? false)) {
            return $dispInicio;
        }

        for ($offset = 1; $offset < $duracionHoras; $offset++) {
            [$d, $h] = self::desplazarDiaHora($dia, $hora + $offset);
            $disp = self::estaDisponible($partida, $residenteId, $d, $h);
            if ($disp['disponible'] ?? false) {
                continue;
            }
            if (self::esBloqueoSuenoHabitual($disp)) {
                continue;
            }
            return $disp;
        }
        return ['disponible' => true, 'motivo' => null];
    }

    /** @param array<string, mixed> $disp */
    private static function esBloqueoSuenoHabitual(array $disp): bool
    {
        return ($disp['tipo'] ?? '') === 'sueno' && ($disp['capa'] ?? '') === 'estructural';
    }

    /** @return array{0: int, 1: int} */
    private static function desplazarDiaHora(int $dia, int $hora): array
    {
        while ($hora >= 24) {
            $hora -= 24;
            $dia++;
        }
        while ($hora < 0) {
            $hora += 24;
            $dia--;
        }
        return [$dia, $hora];
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
