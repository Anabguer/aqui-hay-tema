<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Catch-up offline: el tiempo real pendiente avanza el reloj de juego con la tubería
 * existente (misiones, encuentros, caducidades reales). Sin penalización artificial.
 * Puerta: features.offline_events_enabled.
 */
final class CatchUpEngine
{
    public const FLAG = 'offline_events_enabled';

    /**
     * @return array<string, mixed>
     */
    public static function cfg(array $cal = []): array
    {
        return [
            'umbral_segundos' => (int) CalibracionConfig::get($cal, 'catch_up.umbral_segundos', 60),
            'max_dias' => (int) CalibracionConfig::get($cal, 'catch_up.max_dias', 90),
            'segundos_por_hora_juego' => (int) CalibracionConfig::get($cal, 'catch_up.segundos_por_hora_juego', 3600),
        ];
    }

    public static function activo(array $partida): bool
    {
        return FeatureConfig::isEnabled($partida, self::FLAG);
    }

    /**
     * Ejecuta al cargar partida si hay ausencia real pendiente.
     *
     * @return array<string, mixed>
     */
    public static function ejecutarAlCargar(
        array &$partida,
        string $projectRoot,
        array $cal = [],
        ?GameLogger $logger = null,
        ?Catalog $catalog = null,
        ?\DateTimeImmutable $ahoraUtc = null
    ): array {
        Reloj::ensure($partida);
        $cfg = self::cfg($cal);
        $ahora = $ahoraUtc ?? self::ahoraUtc();
        $desde = self::instanteDesde($partida);
        $segundos = max(0, $ahora->getTimestamp() - $desde->getTimestamp());

        if (!self::activo($partida) || $segundos < (int) $cfg['umbral_segundos']) {
            return self::marcarPlanSinEjecutar($partida, $segundos, $ahora, $cfg);
        }

        $sph = max(1, (int) $cfg['segundos_por_hora_juego']);
        $horasPedidas = intdiv($segundos, $sph);
        $maxHoras = max(0, (int) $cfg['max_dias']) * 24;
        if ($maxHoras > 0 && $horasPedidas > $maxHoras) {
            $horasPedidas = $maxHoras;
        }
        if ($horasPedidas <= 0) {
            return self::marcarPlanSinEjecutar($partida, $segundos, $ahora, $cfg);
        }

        $vidaAntes = FeatureConfig::isEnabled($partida, VidaPuebloEngine::FLAG)
            ? VidaPuebloEngine::valor($partida)
            : null;
        $diaAntes = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $horaAntes = (int) ($partida['reloj']['hora_actual'] ?? 0);

        $relojOps = new RelojOperations($projectRoot, $logger);
        $stats = self::avanzarHorasOffline($partida, $relojOps, $horasPedidas);

        $segundosProcesados = $horasPedidas * $sph;
        $hasta = $desde->modify('+' . $segundosProcesados . ' seconds');
        $partida['reloj']['ultimo_catch_up_iso'] = $hasta->format(DATE_ATOM);

        $plan = CatchUpPlanner::planificar($segundos);
        $plan['ejecutado'] = true;
        $plan['horas_juego_avanzadas'] = $horasPedidas;
        $plan['segundos_procesados'] = $segundosProcesados;

        $vidaDespues = FeatureConfig::isEnabled($partida, VidaPuebloEngine::FLAG)
            ? VidaPuebloEngine::valor($partida)
            : null;

        $partida['reloj']['catch_up_pendiente'] = [
            'segundos_pendientes' => $segundos,
            'segundos_procesados' => $segundosProcesados,
            'horas_juego_avanzadas' => $horasPedidas,
            'ejecutado' => true,
            'vida_antes' => $vidaAntes,
            'vida_despues' => $vidaDespues,
            'dia_antes' => $diaAntes,
            'dia_despues' => (int) ($partida['reloj']['dia_pueblo'] ?? $diaAntes),
            'hora_antes' => $horaAntes,
            'hora_despues' => (int) ($partida['reloj']['hora_actual'] ?? $horaAntes),
            'plan' => $plan,
            'stats' => $stats,
            'eventos_pendientes' => [],
            'eventos_offline' => (int) ($stats['eventos_offline'] ?? 0),
            'salidas_offline' => (int) ($stats['salidas_offline'] ?? 0),
            'encuentros_offline' => (int) ($stats['encuentros_resueltos'] ?? 0),
        ];

        DomainEventDispatcher::emit($partida, DomainEvents::CATCH_UP_EJECUTADO, [
            'segundos' => $segundos,
            'horas_juego' => $horasPedidas,
            'stats' => $stats,
            'vida_antes' => $vidaAntes,
            'vida_despues' => $vidaDespues,
        ], $logger, 'CatchUpEngine::ejecutarAlCargar');

        return [
            'segundos' => $segundos,
            'segundos_procesados' => $segundosProcesados,
            'horas_juego_avanzadas' => $horasPedidas,
            'aplicado' => true,
            'ejecutado' => true,
            'plan' => $plan,
            'stats' => $stats,
            'vida_antes' => $vidaAntes,
            'vida_despues' => $vidaDespues,
        ];
    }

    /**
     * Solo planifica (flag apagado o ausencia bajo umbral).
     *
     * @return array<string, mixed>
     */
    public static function marcarPlanSinEjecutar(
        array &$partida,
        int $segundos,
        ?\DateTimeImmutable $ahora = null,
        ?array $cfg = null
    ): array {
        $ahora = $ahora ?? self::ahoraUtc();
        $plan = CatchUpPlanner::planificar($segundos);
        $partida['reloj']['catch_up_pendiente']['segundos_pendientes'] = $segundos;
        $partida['reloj']['catch_up_pendiente']['eventos_pendientes'] = [];
        $partida['reloj']['catch_up_pendiente']['plan'] = $plan;
        $partida['reloj']['catch_up_pendiente']['ejecutado'] = false;

        if ($segundos > 0) {
            DomainEventDispatcher::emit($partida, DomainEvents::CATCH_UP_PLANIFICADO, [
                'plan' => $plan,
            ], null, 'CatchUpEngine::marcarPlanSinEjecutar');
        }

        return [
            'segundos' => $segundos,
            'aplicado' => $segundos > 0,
            'ejecutado' => false,
            'plan' => $plan,
            'nota' => self::activo($partida)
                ? 'ausencia bajo umbral; sin avance de reloj'
                : 'offline_events_enabled apagado; solo plan',
        ];
    }

    /**
     * Avanza horas de juego en trozos que respetan medianoche (un cierre de día por trozo).
     *
     * @return array<string, mixed>
     */
    public static function avanzarHorasOffline(
        array &$partida,
        RelojOperations $relojOps,
        int $horas
    ): array {
        $stats = [
            'horas_pedidas' => $horas,
            'horas_avanzadas' => 0,
            'pasos' => 0,
            'encuentros_resueltos' => 0,
            'misiones_caducadas' => 0,
            'peticiones_caducadas' => 0,
            'dias_cruzados' => 0,
            'eventos_offline' => 0,
            'salidas_offline' => 0,
        ];
        $restantes = $horas;
        $guard = 0;
        $maxPasos = max(1, $horas + 48);

        while ($restantes > 0 && $guard < $maxPasos) {
            $guard++;
            $horaActual = (int) ($partida['reloj']['hora_actual'] ?? 0);
            $hastaMedianoche = 24 - $horaActual;
            if ($hastaMedianoche <= 0) {
                $hastaMedianoche = 24;
            }
            $chunk = min($restantes, $hastaMedianoche);
            if ($chunk <= 0) {
                break;
            }

            $diaAntes = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
            $r = $relojOps->avanzarPasoAPaso($partida, $chunk);
            $diaDespues = (int) ($partida['reloj']['dia_pueblo'] ?? $diaAntes);
            if ($diaDespues > $diaAntes) {
                $stats['dias_cruzados'] += ($diaDespues - $diaAntes);
            }

            $stats['horas_avanzadas'] += $chunk;
            $stats['pasos']++;
            $stats['encuentros_resueltos'] += (int) ($r['encuentros_resueltos'] ?? 0);
            $stats['peticiones_caducadas'] += (int) ($r['peticiones_caducadas'] ?? 0);
            $stats['eventos_offline'] += (int) ($r['offline_eventos'] ?? 0);
            $stats['salidas_offline'] += (int) ($r['offline_salidas'] ?? 0);
            $restantes -= $chunk;
        }

        return $stats;
    }

    public static function instanteDesde(array $partida): \DateTimeImmutable
    {
        $iso = $partida['reloj']['ultimo_catch_up_iso']
            ?? $partida['reloj']['ultima_sesion_iso']
            ?? null;
        if ($iso === null) {
            return self::ahoraUtc();
        }
        try {
            return new \DateTimeImmutable($iso);
        } catch (\Exception $ignored) {
            return self::ahoraUtc();
        }
    }

    public static function marcarSesion(array &$partida): void
    {
        $partida['reloj']['ultima_sesion_iso'] = self::ahoraUtc()->format(DATE_ATOM);
        if (!isset($partida['reloj']['ultimo_catch_up_iso'])) {
            $partida['reloj']['ultimo_catch_up_iso'] = $partida['reloj']['ultima_sesion_iso'];
        }
    }

    /**
     * Genera resumen de lo ocurrido durante la ausencia para mostrar al regreso.
     *
     * @return array<string, mixed>
     */
    public static function resumenRegreso(array $partida): array
    {
        $cu = $partida['reloj']['catch_up_pendiente'] ?? [];
        if (empty($cu['ejecutado'])) {
            return ['hay' => false];
        }
        $dias = (int) ($cu['stats']['dias_cruzados'] ?? 0);
        $vidaAntes = $cu['vida_antes'] ?? null;
        $vidaDespues = $cu['vida_despues'] ?? null;
        $deltaVida = ($vidaDespues !== null && $vidaAntes !== null) ? $vidaDespues - $vidaAntes : null;
        $eventos = (int) ($cu['eventos_offline'] ?? 0);
        $salidas = (int) ($cu['salidas_offline'] ?? 0);
        $encuentros = (int) ($cu['encuentros_offline'] ?? 0);
        $misionesCaducadas = 0;
        foreach ($partida['vida_pueblo']['ledger'] ?? [] as $e) {
            if (($e['causa'] ?? '') === VidaPuebloEngine::CAUSA_DIA_MISIONES_IGNORADO) {
                $misionesCaducadas++;
            }
        }

        $puntos = [];
        if ($dias > 0) {
            $puntos[] = "Han pasado {$dias} día" . ($dias !== 1 ? 's' : '') . ".";
        }
        if ($deltaVida !== null && $deltaVida !== 0) {
            $puntos[] = "Vida del pueblo: " . ($deltaVida > 0 ? '+' : '') . "{$deltaVida}.";
        }
        if ($eventos > 0) {
            $puntos[] = "{$eventos} evento" . ($eventos !== 1 ? 's' : '') . " npc generado" . ($eventos !== 1 ? 's' : '') . ".";
        }
        if ($salidas > 0) {
            $puntos[] = "{$salidas} salida" . ($salidas !== 1 ? 's' : '') . " individual.";
        }
        if ($encuentros > 0) {
            $puntos[] = "{$encuentros} encuentro" . ($encuentros !== 1 ? 's' : '') . " resuelto" . ($encuentros !== 1 ? 's' : '') . ".";
        }
        if ($misionesCaducadas > 0) {
            $puntos[] = "{$misionesCaducadas} misión" . ($misionesCaducadas !== 1 ? 'es' : '') . " caducada" . ($misionesCaducadas !== 1 ? 's' : '') . ".";
        }

        return [
            'hay' => true,
            'dias' => $dias,
            'vida_antes' => $vidaAntes,
            'vida_despues' => $vidaDespues,
            'delta_vida' => $deltaVida,
            'eventos_offline' => $eventos,
            'salidas_offline' => $salidas,
            'encuentros_offline' => $encuentros,
            'misiones_caducadas' => $misionesCaducadas,
            'puntos' => $puntos,
        ];
    }

    private static function ahoraUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
