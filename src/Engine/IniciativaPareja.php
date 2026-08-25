<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

/**
 * ROMANCE_CIERRE · Evaluador autónomo de PAREJAS al cerrar el día.
 *
 * R5 Crisis: NUNCA por umbral ni RNG desnudo (canon *_nunca_auto_por_umbral).
 *   - CAUSAS observables (conflicto alto, racha mala, estabilidad en suelo por
 *     desgaste/malos registrados, abandono) deciden SI hay riesgo.
 *   - Con causas suficientes, UNA tirada condicionada decide CUÁNDO llega
 *     dentro del periodo de riesgo (revive claves crisis.probabilidad /
 *     bonus_si_estabilidad_baja, muertas hasta hoy).
 *   - Sin causas suficientes: p = 0 EXACTO y cero consumo de RNG.
 *
 * R6 Reparación y R7 Ruptura se enganchan en gestionarCrisis() (mismo pase).
 */
final class IniciativaPareja
{
    public static function crisisActiva(array $cal): bool
    {
        return (bool) CalibracionConfig::get($cal, 'romance_autonomo.crisis_activa', false);
    }

    public static function rupturaActiva(array $cal): bool
    {
        return (bool) CalibracionConfig::get($cal, 'romance_autonomo.ruptura_activa', false);
    }

    private static function knob(array $cal, string $sub, string $key, float $default): float
    {
        $v = CalibracionConfig::get($cal, 'romance_autonomo.' . $sub . '.' . $key, null);
        if (is_numeric($v)) {
            return (float) $v;
        }
        return (float) CalibracionConfig::get($cal, $sub . '.' . $key, $default);
    }

    private static function knobInt(array $cal, string $sub, string $key, int $default): int
    {
        return (int) self::knob($cal, $sub, $key, (float) $default);
    }

    public static function absNow(array $partida): int
    {
        return ((int) ($partida['reloj']['dia_pueblo'] ?? 1)) * 24 + (int) ($partida['reloj']['hora_actual'] ?? 0);
    }

    /**
     * Punto de enganche diario (RelojOperations::avanzar, junto a RelacionDesgaste).
     *
     * @param array<string, mixed> $cal
     * @return array<string, int>
     */
    public static function evaluarAlCerrarDia(array &$partida, array $cal, ?GameLogger $logger = null): array
    {
        $out = ['crisis' => 0, 'reparaciones_ok' => 0, 'reparaciones_fail' => 0, 'rupturas' => 0];
        if (!self::crisisActiva($cal)) {
            return $out;
        }
        foreach (($partida['relaciones_romanticas'] ?? []) as $i => $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $est = (string) ($rel['estado_pareja'] ?? '');
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            if ($a === '' || $b === '') {
                continue;
            }
            // Copia fresca por si otro paso del pase mutó el par.
            $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'];
            if (!is_array($rel)) {
                continue;
            }
            $est = (string) ($rel['estado_pareja'] ?? '');
            if ($est === ParejaEngine::CRISIS) {
                self::gestionarCrisis($partida, $a, $b, $cal, $out);
                continue;
            }
            if ($est === ParejaEngine::PAREJA) {
                self::evaluarCrisisNueva($partida, $a, $b, $cal, $out);
            }
        }
        return $out;
    }

    // ==================================================================
    // R5 · CRISIS CAUSAL
    // ==================================================================

    /**
     * Causas observables del par (deterministas, cada una con memoria real).
     *
     * @return list<string>
     */
    public static function causasCrisis(array $partida, string $a, string $b, array $cal): array
    {
        $causas = [];
        // C1 conflicto alto
        $confMin = self::knobInt($cal, 'crisis', 'conflicto_min', 6);
        $conf = RelacionEngine::obtenerEntre($partida, $a, $b)['conflicto']['intensidad'] ?? null;
        if (is_numeric($conf) && (int) $conf >= $confMin) {
            $causas[] = 'conflicto';
        }
        // C2 racha mala (últimos N encuentros del par)
        $ventana = max(1, self::knobInt($cal, 'crisis', 'racha_mala_ventana', 3));
        $umbralRacha = max(1, self::knobInt($cal, 'crisis', 'racha_mala', 2));
        $recientes = MemoriaEventos::recientes($partida, [$a, $b], 12);
        $encuentros = [];
        foreach ($recientes as $ev) {
            if (($ev['familia'] ?? '') === 'encuentro') {
                $encuentros[] = $ev;
                if (count($encuentros) >= $ventana) {
                    break;
                }
            }
        }
        $malos = 0;
        foreach ($encuentros as $ev) {
            if (in_array((string) ($ev['resultado_experiencia'] ?? ''), ['mal', 'muy_mal'], true)) {
                $malos++;
            }
        }
        if ($malos >= $umbralRacha) {
            $causas[] = 'racha_mala';
        }
        // C3 estabilidad en suelo
        $riesgo = self::knobInt($cal, 'crisis', 'estabilidad_riesgo', 30);
        $romWrap = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'] ?? null;
        $val = is_array($romWrap) ? ($romWrap['estabilidad_pareja']['valor'] ?? null) : null;
        if (is_numeric($val) && (int) $val <= $riesgo) {
            $causas[] = 'estabilidad_suelo';
        }
        // C4 abandono (días sin NINGÚN contacto social del par)
        $diasAbandono = max(1, self::knobInt($cal, 'crisis', 'dias_abandono', 6));
        $soc = RelacionEngine::obtenerEntre($partida, $a, $b)['social'] ?? null;
        $uc = is_array($soc) ? ($soc['ultimo_contacto']['dia'] ?? null) : null;
        if ($uc !== null) {
            $diasSin = max(0, (int) ($partida['reloj']['dia_pueblo'] ?? 1) - (int) $uc);
            if ($diasSin >= $diasAbandono) {
                $causas[] = 'abandono';
            }
        }
        return $causas;
    }

    /** p de crisis dadas las causas: 0 EXACTO sin causas suficientes. */
    public static function probabilidadCrisis(array $cal, array $causas): float
    {
        $minimas = max(1, self::knobInt($cal, 'crisis', 'causas_minimas', 2));
        if (count($causas) < $minimas) {
            return 0.0;
        }
        $p = self::knob($cal, 'crisis', 'probabilidad', 0.015);
        if (in_array('estabilidad_suelo', $causas, true)) {
            $p += self::knob($cal, 'crisis', 'bonus_si_estabilidad_baja', 0.04);
        }
        return min(1.0, $p);
    }

    /** @param array<string, int> $out */
    private static function evaluarCrisisNueva(array &$partida, string $a, string $b, array $cal, array &$out): void
    {
        // Anti-spam canónico: cooldown de familia crisis (48 h) por par.
        if (MemoriaEventos::enCooldown($partida, 'crisis', [$a, $b], $cal)) {
            return;
        }
        // Máximo de crisis por par y mes (720 h).
        $maxMes = self::knobInt($cal, 'crisis', 'max_por_par_mes', 2);
        $now = self::absNow($partida);
        $nMes = 0;
        foreach (($partida['memoria_eventos'] ?? []) as $ev) {
            if (!is_array($ev) || ($ev['familia'] ?? '') !== 'crisis') {
                continue;
            }
            $pp = is_array($ev['participantes'] ?? null) ? $ev['participantes'] : [];
            if (in_array($a, $pp, true) && in_array($b, $pp, true)
                && ($now - (((int) ($ev['dia'] ?? 0)) * 24 + (int) ($ev['hora'] ?? 0))) <= 720) {
                $nMes++;
            }
        }
        if ($nMes >= $maxMes) {
            return;
        }
        if (!IniciativaRomantica::capHitosDisponible($partida, $cal)) {
            return;
        }

        $causas = self::causasCrisis($partida, $a, $b, $cal);
        $p = self::probabilidadCrisis($cal, $causas);
        if ($p <= 0.0) {
            return; // SIN CAUSAS NO HAY CRISIS JAMÁS (y cero draws)
        }
        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        if (!($tirada < $p)) {
            SimFunnelProbe::on($partida, 'declaracion', [
                'ev' => 'crisis_riesgo_sin_crisis',
                '_k' => 'crisis_no',
                'par' => [$a, $b],
                'causas' => $causas,
                'p' => round($p, 4),
            ]);
            return;
        }

        $r = ParejaEngine::crisis($partida, $a, $b);
        if (!($r['ok'] ?? false)) {
            return;
        }
        // Flavor narrativo comprensible cuando la causa dominante es social.
        if (in_array('conflicto', $causas, true) || in_array('racha_mala', $causas, true)) {
            RelacionBitacora::registrar($partida, RelacionBitacora::DISCUSION_FUERTE, [$a, $b]);
        }
        $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'];
        if (is_array($rel)) {
            $rel['crisis_desde'] = [
                'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            ];
            $rel['fallos_reparacion'] = 0;
            $rel['ultimo_intento_reparacion'] = null;
            RelacionEngine::persistirRomance($partida, $rel);
        }
        MemoriaEventos::registrar($partida, 'crisis', [$a, $b], null, 'crisis');
        self::tristezaMutua($partida, $a, $b, $cal);
        SimFunnelProbe::on($partida, 'declaracion', [
            'ev' => 'crisis',
            '_k' => 'crisis_ok',
            'par' => [$a, $b],
            'causas' => $causas,
            'p' => round($p, 4),
        ]);
        $out['crisis']++;
    }

    /** Tristeza simultánea al entrar en crisis / ruptura. */
    protected static function tristezaMutua(array &$partida, string $a, string $b, array $cal, ?int $durOverride = null): void
    {
        $dur = $durOverride ?? (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas_default.triste', 10);
        $root = dirname(__DIR__, 2);
        $emoSvc = new EmotionalStateService(
            new VisualPackStore($root),
            new CatalogStore($root),
            null
        );
        foreach ([[$a, $b], [$b, $a]] as [$quien, $otro]) {
            $hasta = EstadoEmocional::hastaDesdeDuracion($partida['reloj'] ?? [], $dur);
            $emoSvc->aplicar(
                $partida,
                $quien,
                EstadoEmocional::TRISTE,
                'crisis_pareja',
                null,
                $hasta,
                ['hacia' => $otro],
                $dur
            );
        }
    }

    // ==================================================================
    // R6 · REPARACIÓN (salida de crisis SIN ruptura).
    //
    // Reutiliza la infraestructura viva: el canal conflicto ya decae y ya se
    // repara con encuentros bien/muy_bien. La reparación exige un encuentro
    // REAL bueno DURANTE la crisis + voluntad geométrica de ambos. Puede
    // fallar (deja memoria) y no convierte cada crisis en ruptura.
    // ==================================================================

    /** @param array<string, int> $out */
    private static function gestionarCrisis(array &$partida, string $a, string $b, array $cal, array &$out): void
    {
        $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'];
        if (!is_array($rel)) {
            return;
        }
        // Crisis legacy sin sello: sellarla para habilitar los carriles.
        if (!is_array($rel['crisis_desde'] ?? null)) {
            $rel['crisis_desde'] = [
                'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            ];
            RelacionEngine::persistirRomance($partida, $rel);
        }

        if (self::intentarReparacion($partida, $a, $b, $cal, $out)) {
            return; // reparada hoy: nada más que gestionar
        }
        self::evaluarRiesgoRuptura($partida, $a, $b, $cal, $out);
    }

    /**
     * Intento de reparación si procede. Devuelve true si el par SALIÓ de crisis.
     *
     * @param array<string, int> $out
     */
    private static function intentarReparacion(array &$partida, string $a, string $b, array $cal, array &$out): bool
    {
        $now = self::absNow($partida);
        $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'];
        if (!is_array($rel)) {
            return false;
        }
        $crisisDesdeAbs = is_array($rel['crisis_desde'] ?? null)
            ? ((int) ($rel['crisis_desde']['dia'] ?? 0)) * 24 + (int) ($rel['crisis_desde']['hora'] ?? 0)
            : $now;
        $ultimo = is_array($rel['ultimo_intento_reparacion'] ?? null)
            ? ((int) ($rel['ultimo_intento_reparacion']['dia'] ?? 0)) * 24 + (int) ($rel['ultimo_intento_reparacion']['hora'] ?? 0)
            : -1;
        $gapIntentos = max(1, self::knobInt($cal, 'reparacion', 'gap_intentos_horas', 24));
        if ($ultimo >= 0 && ($now - $ultimo) < $gapIntentos) {
            return false;
        }
        // Conflicto debe estar YA rebajado (el canal vivo repara niveles con citas buenas).
        $confMin = self::knobInt($cal, 'crisis', 'conflicto_min', 6);
        $conf = RelacionEngine::obtenerEntre($partida, $a, $b)['conflicto']['intensidad'] ?? null;
        if (is_numeric($conf) && (int) $conf >= $confMin) {
            return false;
        }
        // Debe existir ≥1 encuentro REAL bueno DESPUÉS de entrar en crisis.
        $hayBuenaEnCrisis = false;
        foreach (($partida['memoria_eventos'] ?? []) as $ev) {
            if (!is_array($ev) || ($ev['familia'] ?? '') !== 'encuentro') {
                continue;
            }
            $pp = is_array($ev['participantes'] ?? null) ? $ev['participantes'] : [];
            if (!in_array($a, $pp, true) || !in_array($b, $pp, true)) {
                continue;
            }
            if (!in_array((string) ($ev['resultado_experiencia'] ?? ''), ['bien', 'muy_bien'], true)) {
                continue;
            }
            if (((int) ($ev['dia'] ?? 0)) * 24 + (int) ($ev['hora'] ?? 0) >= $crisisDesdeAbs) {
                $hayBuenaEnCrisis = true;
                break;
            }
        }
        if (!$hayBuenaEnCrisis) {
            return false;
        }

        // Voluntad geométrica de AMBOS (tipo cita; conflicto/emocional ya penalizan).
        $prop = [
            'participantes' => [$a, $b],
            'tipo' => 'cita',
            'lugar' => null,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 12),
        ];
        $vol = new VoluntadPonderadaEvaluator($cal);
        $ra = $vol->evaluar($partida, $prop, $a);
        $rb = $vol->evaluar($partida, $prop, $b);
        $pPlan = sqrt(max(0.0, (float) ($ra['p'] ?? 0)) * max(0.0, (float) ($rb['p'] ?? 0)));
        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);

        $marcarIntento = static function () use (&$partida, $a, $b): void {
            $relX = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'];
            if (!is_array($relX)) {
                return;
            }
            $relX['ultimo_intento_reparacion'] = [
                'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
                'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
            ];
            RelacionEngine::persistirRomance($partida, $relX);
        };

        if (!($tirada < $pPlan)) {
            $relF = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'];
            if (is_array($relF)) {
                $relF['fallos_reparacion'] = (int) ($relF['fallos_reparacion'] ?? 0) + 1;
                RelacionEngine::persistirRomance($partida, $relF);
            }
            $marcarIntento();
            SimFunnelProbe::on($partida, 'declaracion', [
                'ev' => 'reparacion_fallida',
                '_k' => 'reparacion_fail',
                'par' => [$a, $b],
                'p_plan' => round($pPlan, 4),
            ]);
            $out['reparaciones_fail']++;
            return false;
        }

        // ÉXITO: volver a PAREJA con estabilidad restaurada PARCIALMENTE.
        $deltaRep = self::knobInt($cal, 'reparacion', 'delta_estabilidad', 20);
        $relOk = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'];
        if (!is_array($relOk)) {
            return false;
        }
        $valorActual = is_numeric($relOk['estabilidad_pareja']['valor'] ?? null)
            ? (int) $relOk['estabilidad_pareja']['valor']
            : 0;
        $relOk['estado_pareja'] = ParejaEngine::PAREJA;
        $relOk['estabilidad_pareja']['activa'] = true;
        $relOk['estabilidad_pareja']['valor'] = min(100, $valorActual + $deltaRep);
        $relOk['crisis_desde'] = null;
        $relOk['fallos_reparacion'] = 0;
        $relOk['ultimo_intento_reparacion'] = null;
        RelacionEngine::persistirRomance($partida, $relOk);
        RelacionBitacora::registrar($partida, RelacionBitacora::APOYO_IMPORTANTE, [$a, $b]);
        MemoriaEventos::registrar($partida, 'pareja', [$a, $b], null, 'reparacion');
        SimFunnelProbe::on($partida, 'declaracion', [
            'ev' => 'reparacion_ok',
            '_k' => 'reparacion_ok',
            'par' => [$a, $b],
        ]);
        $out['reparaciones_ok']++;
        return true;
    }

    // ==================================================================
    // R7 — Riesgo de ruptura desde crisis sin salida (stub hasta R7).
    // ==================================================================

    /**
     * @param array<string, int> $out
     */
    private static function evaluarRiesgoRuptura(array &$partida, string $a, string $b, array $cal, array &$out): void
    {
        if (!self::rupturaActiva($cal)) {
            return;
        }
        // Implementación completa en bloque R7.
    }
}
