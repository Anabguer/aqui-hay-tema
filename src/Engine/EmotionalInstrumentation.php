<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * PLAYTEST SISTEMA EMOCIONAL — Instrumentación de observación.
 * Registra cada cambio emocional con contexto completo para análisis.
 * NO altera gameplay, probabilidades, duraciones ni modificadores.
 * Solo escribe en GameLogger y mantiene acumuladores en runtime.
 */
final class EmotionalInstrumentation
{
    private const RUNTIME_KEY = 'emotional_playtest';
    private const LOG_TIPO_CAMBIO = 'emocion_cambiada';
    private const LOG_TIPO_EXPIRACION = 'emocion_expirada';
    private const LOG_TIPO_RESUMEN_DIA = 'emocion_resumen_dia';

    /**
     * Asegura que la estructura de acumuladores exista en runtime.
     */
    public static function ensureRuntime(array &$partida): void
    {
        $partida['runtime'][self::RUNTIME_KEY] ??= [
            'eventos' => [],
            'acumuladores' => [],
            'expiraciones' => [],
        ];
    }

    /**
     * Registra un cambio emocional con todo el contexto.
     *
     * @param array<string, mixed> $partida
     * @param string $residenteId
     * @param array<string, mixed> $antes - estado emocional anterior
     * @param array<string, mixed> $despues - estado emocional nuevo
     * @param string $origen - motivo del cambio
     * @param array<string, mixed> $contexto - contexto adicional del trigger
     * @param GameLogger|null $logger
     */
    public static function registrarCambio(
        array &$partida,
        string $residenteId,
        array $antes,
        array $despues,
        string $origen,
        array $contexto = [],
        ?GameLogger $logger = null
    ): void {
        self::ensureRuntime($partida);
        $reloj = $partida['reloj'] ?? [];
        $antesId = EstadoEmocional::canonId((string) ($antes['id'] ?? EstadoEmocional::NEUTRO));
        $despuesId = EstadoEmocional::canonId((string) ($despues['id'] ?? EstadoEmocional::NEUTRO));

        $entry = [
            'residente_id' => $residenteId,
            'dia_juego' => (int) ($reloj['dia_pueblo'] ?? 1),
            'hora_juego' => (int) ($reloj['hora_actual'] ?? 0),
            'estado_anterior' => $antesId,
            'estado_nuevo' => $despuesId,
            'intensidad' => $despues['intensidad'] ?? null,
            'origen' => $origen,
            'duracion_horas' => $despues['duracion_horas'] ?? null,
            'contexto' => $contexto,
            'necesidades' => self::snapshotNecesidades($partida, $residenteId),
            'relaciones' => self::snapshotRelaciones($partida, $residenteId, $contexto),
            'rechazos_recientes' => self::rechazosRecientes($partida, $residenteId, 48),
            'acontecimientos_recientes' => self::acontecimientosRecientes($partida, $residenteId, 24),
            'planes_recientes' => self::planesRecientes($partida, $residenteId, 48),
        ];

        $partida['runtime'][self::RUNTIME_KEY]['eventos'][] = $entry;

        // Acumulador global
        $acum = &$partida['runtime'][self::RUNTIME_KEY]['acumuladores'];
        if (!isset($acum[$residenteId])) {
            $acum[$residenteId] = [
                'cambios_totales' => 0,
                'cambios_por_dia' => [],
                'tiempo_en_estado' => [
                    'neutro' => 0,
                    'alegre' => 0,
                    'triste' => 0,
                    'enfadado' => 0,
                ],
            ];
        }
        $acum[$residenteId]['cambios_totales']++;
        $dia = (string) ($reloj['dia_pueblo'] ?? 1);
        $acum[$residenteId]['cambios_por_dia'][$dia] = ($acum[$residenteId]['cambios_por_dia'][$dia] ?? 0) + 1;

        // Log en GameLogger si existe
        if ($logger !== null) {
            $logger->log($partida, self::LOG_TIPO_CAMBIO, $entry);
        }
    }

    /**
     * Registra una expiración de estado emocional.
     */
    public static function registrarExpiracion(
        array &$partida,
        string $residenteId,
        array $antes,
        ?GameLogger $logger = null
    ): void {
        self::ensureRuntime($partida);
        $reloj = $partida['reloj'] ?? [];
        $antesId = EstadoEmocional::canonId((string) ($antes['id'] ?? EstadoEmocional::NEUTRO));

        $entry = [
            'residente_id' => $residenteId,
            'dia_juego' => (int) ($reloj['dia_pueblo'] ?? 1),
            'hora_juego' => (int) ($reloj['hora_actual'] ?? 0),
            'estado_anterior' => $antesId,
            'origen_original' => $antes['origen'] ?? null,
            'dia_inicio' => $antes['desde']['dia'] ?? null,
            'hora_inicio' => $antes['desde']['hora'] ?? null,
            'hasta' => $antes['hasta'] ?? null,
            'duracion_horas_config' => $antes['duracion_horas'] ?? null,
        ];

        $partida['runtime'][self::RUNTIME_KEY]['expiraciones'][] = $entry;

        if ($logger !== null) {
            $logger->log($partida, self::LOG_TIPO_EXPIRACION, $entry);
        }
    }

    /**
     * Genera resumen diario de estados emocionales de todos los residentes.
     */
    public static function generarResumenDia(array &$partida, ?GameLogger $logger = null): void
    {
        self::ensureRuntime($partida);
        $reloj = $partida['reloj'] ?? [];
        $diaActual = (int) ($reloj['dia_pueblo'] ?? 1);

        $resumen = [];
        foreach (array_keys($partida['residentes'] ?? []) as $id) {
            $res = $partida['residentes'][$id];
            EstadoEmocional::ensureResidente($res, $reloj);
            $emo = $res['runtime']['estado_emocional'] ?? [];
            $nec = NecesidadEstado::obtener($res);
            $acum = $partida['runtime'][self::RUNTIME_KEY]['acumuladores'][$id] ?? null;

            $resumen[$id] = [
                'estado_actual' => EstadoEmocional::canonId((string) ($emo['id'] ?? EstadoEmocional::NEUTRO)),
                'origen_estado' => $emo['origen'] ?? null,
                'desde_estado' => $emo['desde'] ?? null,
                'necesidades' => $nec,
                'cambios_totales' => $acum['cambios_totales'] ?? 0,
                'cambios_hoy' => $acum['cambios_por_dia'][(string) $diaActual] ?? 0,
            ];
        }

        $entry = [
            'dia_juego' => $diaActual,
            'hora_juego' => (int) ($reloj['hora_actual'] ?? 0),
            'resumen_por_residente' => $resumen,
        ];

        if ($logger !== null) {
            $logger->log($partida, self::LOG_TIPO_RESUMEN_DIA, $entry);
        }
    }

    /**
     * Obtiene snapshot de necesidades de un residente.
     *
     * @return array<string, array{valor: int, banda: string}>
     */
    private static function snapshotNecesidades(array $partida, string $residenteId): array
    {
        if (!isset($partida['residentes'][$residenteId])) {
            return [];
        }
        return NecesidadEstado::obtener($partida['residentes'][$residenteId]);
    }

    /**
     * Obtiene snapshot de relaciones relevantes.
     *
     * @return array<string, array{social: int, romance: int, conflicto: int}>
     */
    private static function snapshotRelaciones(array $partida, string $residenteId, array $contexto): array
    {
        $rels = [];
        $hacia = $contexto['hacia'] ?? $contexto['participante_contra'] ?? null;

        // Relación con la persona involucrada en el contexto
        if ($hacia !== null && $hacia !== $residenteId) {
            $rel = RelacionEngine::obtenerEntre($partida, $residenteId, $hacia);
            $rels[$hacia] = [
                'social' => (int) ($rel['social'] ?? 0),
                'romance' => (int) ($rel['romance'] ?? 0),
                'conflicto' => (int) ($rel['conflicto'] ?? 0),
            ];
        }

        // Top 3 relaciones por social (las más fuertes)
        $todas = [];
        foreach (array_keys($partida['residentes'] ?? []) as $otherId) {
            if ($otherId === $residenteId) {
                continue;
            }
            if (isset($rels[$otherId])) {
                continue;
            }
            $rel = RelacionEngine::obtenerEntre($partida, $residenteId, $otherId);
            $social = (int) ($rel['social'] ?? 0);
            $todas[$otherId] = [
                'social' => $social,
                'romance' => (int) ($rel['romance'] ?? 0),
                'conflicto' => (int) ($rel['conflicto'] ?? 0),
            ];
        }
        uasort($todas, static fn($a, $b) => $b['social'] <=> $a['social']);
        $rels = array_merge($rels, array_slice($todas, 0, 3, true));

        return $rels;
    }

    /**
     * Rechazos de propuestas en las últimas N horas.
     *
     * @return list<array{quien: string, hacia: string, motivo: string, dia: int, hora: int}>
     */
    private static function rechazosRecientes(array $partida, string $residenteId, int $horas): array
    {
        $reloj = $partida['reloj'] ?? [];
        $diaActual = (int) ($reloj['dia_pueblo'] ?? 1);
        $horaActual = (int) ($reloj['hora_actual'] ?? 0);
        $resultado = [];

        foreach ($partida['rechazos_propuesta'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            // Filtrar por residente (quien rechaza O hacia quien)
            if (($row['quien'] ?? '') !== $residenteId && ($row['hacia'] ?? '') !== $residenteId) {
                continue;
            }
            // Filtrar por tiempo
            $diaRechazo = (int) ($row['dia'] ?? 0);
            $horaRechazo = (int) ($row['hora'] ?? 0);
            $diasDiff = $diaActual - $diaRechazo;
            $horasDiff = $diasDiff * 24 + ($horaActual - $horaRechazo);
            if ($horasDiff <= $horas) {
                $resultado[] = $row;
            }
        }

        return $resultado;
    }

    /**
     * Acontecimientos que involucraron al residente en las últimas N horas.
     *
     * @return list<array{id: string, participantes: list<string>, resultado: string|null}>
     */
    private static function acontecimientosRecientes(array $partida, string $residenteId, int $horas): array
    {
        $reloj = $partida['reloj'] ?? [];
        $diaActual = (int) ($reloj['dia_pueblo'] ?? 1);
        $horaActual = (int) ($reloj['hora_actual'] ?? 0);
        $resultado = [];

        foreach ($partida['acontecimientos_log'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!in_array($residenteId, $row['participantes'] ?? [], true)) {
                continue;
            }
            $diaEv = (int) ($row['dia'] ?? 0);
            $horaEv = (int) ($row['hora'] ?? 0);
            $diasDiff = $diaActual - $diaEv;
            $horasDiff = $diasDiff * 24 + ($horaActual - $horaEv);
            if ($horasDiff <= $horas) {
                $resultado[] = [
                    'id' => $row['id'] ?? '',
                    'participantes' => $row['participantes'] ?? [],
                    'resultado' => $row['resultado'] ?? null,
                ];
            }
        }

        return $resultado;
    }

    /**
     * Planes propuestos/aceptados/rechazados en las últimas N horas.
     *
     * @return list<array{tipo: string, participantes: list<string>, resultado: string|null}>
     */
    private static function planesRecientes(array $partida, string $residenteId, int $horas): array
    {
        $reloj = $partida['reloj'] ?? [];
        $diaActual = (int) ($reloj['dia_pueblo'] ?? 1);
        $horaActual = (int) ($reloj['hora_actual'] ?? 0);
        $resultado = [];

        foreach ($partida['encuentros'] ?? [] as $enc) {
            if (!is_array($enc)) {
                continue;
            }
            $participantes = $enc['participantes'] ?? [];
            if (!in_array($residenteId, $participantes, true)) {
                continue;
            }
            $diaEnc = (int) ($enc['dia'] ?? 0);
            $horaEnc = (int) ($enc['hora'] ?? 0);
            $diasDiff = $diaActual - $diaEnc;
            $horasDiff = $diasDiff * 24 + ($horaActual - $horaEnc);
            if ($horasDiff <= $horas) {
                $res = $enc['resultado'] ?? null;
                $resultado[] = [
                    'tipo' => $enc['tipo'] ?? $enc['plan'] ?? 'desconocido',
                    'participantes' => $participantes,
                    'resultado' => is_array($res) ? ($res['resultado_global'] ?? null) : $res,
                ];
            }
        }

        return $resultado;
    }

    /**
     * Obtiene el log completo de eventos emocionales (solo lectura).
     *
     * @return list<array<string, mixed>>
     */
    public static function obtenerEventos(array $partida): array
    {
        self::ensureRuntime($partida);
        return $partida['runtime'][self::RUNTIME_KEY]['eventos'] ?? [];
    }

    /**
     * Obtiene los acumuladores (solo lectura).
     *
     * @return array<string, mixed>
     */
    public static function obtenerAcumuladores(array $partida): array
    {
        self::ensureRuntime($partida);
        return $partida['runtime'][self::RUNTIME_KEY]['acumuladores'] ?? [];
    }

    /**
     * Obtiene las expiraciones (solo lectura).
     *
     * @return list<array<string, mixed>>
     */
    public static function obtenerExpiraciones(array $partida): array
    {
        self::ensureRuntime($partida);
        return $partida['runtime'][self::RUNTIME_KEY]['expiraciones'] ?? [];
    }

    /**
     * Genera estadísticas consolidadas de la partida.
     *
     * @return array<string, mixed>
     */
    public static function estadisticas(array $partida): array
    {
        self::ensureRuntime($partida);
        $eventos = $partida['runtime'][self::RUNTIME_KEY]['eventos'] ?? [];
        $acum = $partida['runtime'][self::RUNTIME_KEY]['acumuladores'] ?? [];
        $expiraciones = $partida['runtime'][self::RUNTIME_KEY]['expiraciones'] ?? [];

        $porOrigen = [];
        $porEmocion = ['alegre' => 0, 'triste' => 0, 'enfadado' => 0];
        foreach ($eventos as $ev) {
            $origen = $ev['origen'] ?? 'desconocido';
            $porOrigen[$origen] = ($porOrigen[$origen] ?? 0) + 1;
            $nueva = $ev['estado_nuevo'] ?? 'neutro';
            if (isset($porEmocion[$nueva])) {
                $porEmocion[$nueva]++;
            }
        }

        return [
            'total_cambios' => count($eventos),
            'total_expiraciones' => count($expiraciones),
            'por_origen' => $porOrigen,
            'por_emocion_nueva' => $porEmocion,
            'por_residente' => $acum,
        ];
    }
}
