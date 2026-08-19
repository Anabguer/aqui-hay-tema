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
        $minuto = (int) ($partida['reloj']['minuto_actual'] ?? 0);
        $slots = [];
        $now = $desdeDia * 24 + $desdeHora;
        $reloj = $partida['reloj'] ?? [];

        for ($d = 0; $d < $maxDias && count($slots) < $maxSlots; $d++) {
            $dia = $desdeDia + $d;
            $horaMin = 0;
            if ($d === 0) {
                $horaMin = $desdeHora;
                if ($minuto > 0) {
                    $horaMin = $desdeHora + 1;
                }
            }
            for ($h = $horaMin; $h < 24 && count($slots) < $maxSlots; $h++) {
                if ($dia * 24 + $h < $now) {
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
                $n = (int) Reloj::fechaDeDia($reloj, $dia)->format('N');
                $slots[] = [
                    'dia' => $dia,
                    'hora' => $h,
                    'dia_semana' => Reloj::diaSemana($dia, $reloj),
                    'dia_semana_ui' => Reloj::diaSemanaUi($dia, $reloj),
                    'fecha_iso' => Reloj::fechaIso($reloj, $dia),
                    'fecha_corta' => Reloj::fechaCorta($reloj, $dia),
                    'etiqueta_hora' => str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00',
                    'participantes' => $participantes,
                    'tipo' => $tipoEncuentro,
                ];
                unset($n);
            }
        }

        $out = ['ok' => true, 'slots' => $slots, 'total' => count($slots), 'por_dia' => self::agruparPorDia($slots)];
        if ($slots === []) {
            $out['diagnostico'] = self::diagnosticarBloqueos($partida, $participantes, $desdeDia, $desdeHora, $maxDias);
        }
        return $out;
    }

    /**
     * Resume por qué no hay slots compatibles (mensajes técnicos, no narrativa).
     *
     * @return array{resumen: string, por_residente: array<string, array<string, int>>, muestras: list<array>}
     */
    public static function diagnosticarBloqueos(
        array $partida,
        array $participantes,
        int $desdeDia,
        int $desdeHora,
        int $maxDias = 7
    ): array {
        $porResidente = [];
        $muestras = [];
        $now = $desdeDia * 24 + $desdeHora;

        for ($d = 0; $d < $maxDias; $d++) {
            $dia = $desdeDia + $d;
            $horaMin = ($d === 0) ? $desdeHora : 0;
            for ($h = $horaMin; $h < 24; $h++) {
                if ($dia * 24 + $h < $now) {
                    continue;
                }
                $bloqueos = [];
                foreach ($participantes as $rid) {
                    $disp = AgendaEngine::estaDisponible($partida, $rid, $dia, $h);
                    if (!$disp['disponible']) {
                        $clave = self::claveBloqueo($disp);
                        $porResidente[$rid][$clave] = ($porResidente[$rid][$clave] ?? 0) + 1;
                        $bloqueos[$rid] = $clave;
                    }
                }
                if ($bloqueos !== [] && count($muestras) < 6) {
                    $muestras[] = ['dia' => $dia, 'hora' => $h, 'bloqueos' => $bloqueos];
                }
                if (EncuentroEngine::hayConflictoHorario($partida, $participantes, $dia, $h)) {
                    foreach ($participantes as $rid) {
                        $porResidente[$rid]['encuentro_programado'] =
                            ($porResidente[$rid]['encuentro_programado'] ?? 0) + 1;
                    }
                }
            }
        }

        $partes = [];
        $partesUi = [];
        $nombres = [];
        foreach ($porResidente as $rid => $motivos) {
            arsort($motivos);
            $top = array_key_first($motivos);
            if ($top !== null) {
                $etiqueta = self::etiquetaBloqueo($top);
                $partes[] = "{$rid}: " . $etiqueta;
                $nombre = IdentidadPublica::nombre($partida, (string) $rid);
                $nombres[$rid] = $nombre;
                $partesUi[] = "{$nombre}: " . $etiqueta;
            }
        }

        return [
            'resumen' => $partes !== []
                ? 'Sin horas compatibles en los próximos ' . $maxDias . ' días. Motivos principales: ' . implode('; ', $partes)
                : 'Sin horas compatibles en el rango buscado.',
            'resumen_ui' => $partesUi !== []
                ? 'Sin horas compatibles en los próximos ' . $maxDias . ' días. Motivos principales: ' . implode('; ', $partesUi)
                : 'Sin horas compatibles en el rango buscado.',
            'nombres' => $nombres,
            'por_residente' => $porResidente,
            'muestras' => $muestras,
        ];
    }

  /** @param array{motivo?: string, tipo?: string|null} $disp */
    private static function claveBloqueo(array $disp): string
    {
        $tipo = (string) ($disp['tipo'] ?? '');
        switch ($tipo) {
            case 'sueno':
                return 'durmiendo';
            case 'trabajo':
            case 'trabajo_blando':
            case 'trabajo_generico':
            case 'estudio':
                return 'trabajo';
            case 'compromiso':
                return 'otro_compromiso';
            case 'encuentro':
                return 'encuentro_programado';
            default:
                return (string) ($disp['motivo'] ?? 'ocupado');
        }
    }

    private static function etiquetaBloqueo(string $clave): string
    {
        switch ($clave) {
            case 'durmiendo':
                return 'durmiendo';
            case 'trabajo':
                return 'trabajo';
            case 'otro_compromiso':
                return 'otro compromiso';
            case 'encuentro_programado':
                return 'encuentro ya programado';
            case 'doble_reserva':
                return 'doble reserva';
            default:
                return $clave;
        }
    }

    /**
     * @param list<array<string, mixed>> $slots
     * @return list<array<string, mixed>>
     */
    private static function agruparPorDia(array $slots): array
    {
        $grupos = [];
        foreach ($slots as $s) {
            $dia = (int) ($s['dia'] ?? 0);
            if (!isset($grupos[$dia])) {
                $grupos[$dia] = [
                    'dia' => $dia,
                    'fecha_iso' => $s['fecha_iso'] ?? null,
                    'fecha_corta' => $s['fecha_corta'] ?? null,
                    'dia_semana_ui' => $s['dia_semana_ui'] ?? null,
                    'horas' => [],
                    'total' => 0,
                ];
            }
            $grupos[$dia]['horas'][] = (int) ($s['hora'] ?? 0);
            $grupos[$dia]['total']++;
        }
        return array_values($grupos);
    }
}
