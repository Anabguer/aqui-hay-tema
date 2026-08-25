<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

/**
 * ROMANCE_CIERRE Â· Evaluador autÃ³nomo de PAREJAS al cerrar el dÃ­a.
 *
 * R5 Crisis: NUNCA por umbral ni RNG desnudo (canon *_nunca_auto_por_umbral).
 *   - CAUSAS observables (conflicto alto, racha mala, estabilidad en suelo por
 *     desgaste/malos registrados, abandono) deciden SI hay riesgo.
 *   - Con causas suficientes, UNA tirada condicionada decide CUÃNDO llega
 *     dentro del periodo de riesgo (revive claves crisis.probabilidad /
 *     bonus_si_estabilidad_baja, muertas hasta hoy).
 *   - Sin causas suficientes: p = 0 EXACTO y cero consumo de RNG.
 *
 * R6 ReparaciÃ³n y R7 Ruptura se enganchan en gestionarCrisis() (mismo pase).
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
        $out = ['crisis' => 0, 'reparaciones_ok' => 0, 'reparaciones_fail' => 0, 'rupturas' => 0, 'vueltas' => 0];
        // R5-R8: el pase diario corre si CUALQUIER carril tiene su flag ON.
        // Todo OFF (producciÃ³n pre-cierre) â‡’ no-op puro sin consumo RNG.
        if (!self::crisisActiva($cal) && !self::rupturaActiva($cal) && !self::vueltaActiva($cal)) {
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
            // Copia fresca por si otro paso del pase mutÃ³ el par.
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
                self::evaluarGolpeDuro($partida, $a, $b, $cal, $out);
                continue;
            }
            if ($est === ParejaEngine::EX && self::vueltaActiva($cal)) {
                self::evaluarVuelta($partida, $a, $b, $cal, $out);
            }
        }
        return $out;
    }

    // ==================================================================
    // R5 Â· CRISIS CAUSAL
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
        // C2 racha mala (Ãºltimos N encuentros del par)
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
        // C4 abandono (dÃ­as sin NINGÃšN contacto social del par)
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
        // Anti-spam canÃ³nico: cooldown de familia crisis (48 h) por par.
        if (MemoriaEventos::enCooldown($partida, 'crisis', [$a, $b], $cal)) {
            return;
        }
        // MÃ¡ximo de crisis por par y mes (720 h).
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
            return; // SIN CAUSAS NO HAY CRISIS JAMÃS (y cero draws)
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

    /** Tristeza simultÃ¡nea al entrar en crisis / ruptura. */
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
    // R6 Â· REPARACIÃ“N (salida de crisis SIN ruptura).
    //
    // Reutiliza la infraestructura viva: el canal conflicto ya decae y ya se
    // repara con encuentros bien/muy_bien. La reparaciÃ³n exige un encuentro
    // REAL bueno DURANTE la crisis + voluntad geomÃ©trica de ambos. Puede
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
            return; // reparada hoy: nada mÃ¡s que gestionar
        }
        self::evaluarRiesgoRuptura($partida, $a, $b, $cal, $out);
    }

    /**
     * Intento de reparaciÃ³n si procede. Devuelve true si el par SALIÃ“ de crisis.
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
        // Debe existir â‰¥1 encuentro REAL bueno DESPUÃ‰S de entrar en crisis.
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

        // Voluntad geomÃ©trica de AMBOS (tipo cita; conflicto/emocional ya penalizan).
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

        // Ã‰XITO: volver a PAREJA con estabilidad restaurada PARCIALMENTE.
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
    // R7 Â· RUPTURA â€” orÃ­genes Ãºnicos O1/O2, decisiÃ³n unilateral, memoria.
    //
    // O1: crisis sin salida (dÃ­as en crisis â‰¥ lÃ­mite OR fallos de reparaciÃ³n).
    // O2: golpe duro en pareja estable (muy_mal reciente + conflicto + suelo).
    // Nunca por umbral silencioso: cada ruptura lleva causa registrada y
    // autorÃ­a (rompe el de MENOR romance, con SU voluntad individual).
    // ==================================================================

    /**
     * @param array<string, int> $out
     */
    private static function evaluarRiesgoRuptura(array &$partida, string $a, string $b, array $cal, array &$out): void
    {
        if (!self::rupturaActiva($cal)) {
            return;
        }
        $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'];
        if (!is_array($rel) || !is_array($rel['crisis_desde'] ?? null)) {
            return;
        }
        $crisisDesdeAbs = ((int) ($rel['crisis_desde']['dia'] ?? 0)) * 24 + (int) ($rel['crisis_desde']['hora'] ?? 0);
        $diasEnCrisis = intdiv(max(0, self::absNow($partida) - $crisisDesdeAbs), 24);
        $fallos = (int) ($rel['fallos_reparacion'] ?? 0);
        $diasLimite = self::knobInt($cal, 'ruptura_politica', 'dias_crisis_sin_salida', 7);
        $maxFallos = self::knobInt($cal, 'reparacion', 'max_fallos', 2);

        $origen = null;
        if ($diasEnCrisis >= $diasLimite || $fallos >= $maxFallos) {
            $origen = 'crisis_sin_salida';
            $factorFallo = self::knob($cal, 'ruptura_politica', 'factor_por_fallo', 0.03);
            $p = self::knob($cal, 'ruptura', 'probabilidad', 0.01) + $factorFallo * max(0, $fallos);
        } else {
            return;
        }
        if (!IniciativaRomantica::capHitosDisponible($partida, $cal)) {
            return;
        }
        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        if (!($tirada < min(1.0, $p))) {
            SimFunnelProbe::on($partida, 'declaracion', [
                'ev' => 'ruptura_riesgo_sin_ruptura',
                '_k' => 'ruptura_no',
                'par' => [$a, $b],
                'origen' => $origen,
                'p' => round($p, 4),
            ]);
            return;
        }

        // AutorÃ­a: rompe el de MENOR romance; decisiÃ³n UNILATERAL con su voluntad.
        $quienRompe = self::elDeMenorRomance($partida, $a, $b);
        $receptor = $quienRompe === $a ? $b : $a;
        if (!self::voluntadDeRomper($partida, $quienRompe, $receptor, $cal)) {
            SimFunnelProbe::on($partida, 'declaracion', [
                'ev' => 'ruptura_declinada',
                '_k' => 'ruptura_declinada',
                'par' => [$a, $b],
                'quien' => $quienRompe,
            ]);
            return;
        }
        self::ejecutarRuptura($partida, $quienRompe, $receptor, $origen, $cal);
        $out['rupturas']++;
    }

    /**
     * O2 Â· Golpe duro en pareja ESTABLE: muy_mal reciente + conflicto alto +
     * estabilidad en suelo. Combo explÃ­cito o p=0.
     *
     * @param array<string, int> $out
     */
    private static function evaluarGolpeDuro(array &$partida, string $a, string $b, array $cal, array &$out): void
    {
        if (!self::rupturaActiva($cal)) {
            return;
        }
        $confMin = self::knobInt($cal, 'crisis', 'conflicto_min', 6);
        $riesgo = self::knobInt($cal, 'crisis', 'estabilidad_riesgo', 30);
        $conf = RelacionEngine::obtenerEntre($partida, $a, $b)['conflicto']['intensidad'] ?? null;
        $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'];
        $val = is_array($rel) ? ($rel['estabilidad_pareja']['valor'] ?? null) : null;
        if (!is_numeric($conf) || (int) $conf < $confMin) {
            return;
        }
        if (!is_numeric($val) || (int) $val > $riesgo) {
            return;
        }
        $recientes = MemoriaEventos::recientes($partida, [$a, $b], 5);
        $ultimaMuyMal = false;
        foreach ($recientes as $ev) {
            if (($ev['familia'] ?? '') !== 'encuentro') {
                continue;
            }
            $ultimaMuyMal = (($ev['resultado_experiencia'] ?? '') === 'muy_mal');
            break;
        }
        if (!$ultimaMuyMal) {
            return;
        }
        if (!IniciativaRomantica::capHitosDisponible($partida, $cal)) {
            return;
        }
        $p = min(1.0, self::knob($cal, 'ruptura', 'probabilidad', 0.01));
        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        if (!($tirada < $p)) {
            return;
        }
        $quienRompe = self::elDeMenorRomance($partida, $a, $b);
        $receptor = $quienRompe === $a ? $b : $a;
        if (!self::voluntadDeRomper($partida, $quienRompe, $receptor, $cal)) {
            return;
        }
        self::ejecutarRuptura($partida, $quienRompe, $receptor, 'golpe_duro', $cal);
        $out['rupturas']++;
    }

    private static function elDeMenorRomance(array $partida, string $a, string $b): string
    {
        $romAB = RelacionEngine::romanceHacia($partida, $a, $b) ?? 0;
        $romBA = RelacionEngine::romanceHacia($partida, $b, $a) ?? 0;
        // Empate â†’ orden canÃ³nico del par (coherente con el resto del motor).
        return $romAB <= $romBA ? $a : $b;
    }

    /** DecisiÃ³n individual de romper (tirada propia, NO media geomÃ©trica). */
    private static function voluntadDeRomper(array &$partida, string $quien, string $otro, array $cal): bool
    {
        $calInd = $cal;
        $calInd['voluntad']['resolucion_plan'] = 'producto'; // tirada individual real
        $prop = [
            'participantes' => [$quien, $otro],
            'tipo' => 'ruptura',
            'lugar' => null,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 12),
        ];
        $vol = new VoluntadPonderadaEvaluator($calInd);
        $r = $vol->evaluar($partida, $prop, $quien);
        return ($r['decision'] ?? '') === PropuestaEncuentro::DECISION_ACEPTA;
    }

    /** Ejecuta la ruptura completa: estado, citas futuras, marcadores, emociones. */
    public static function ejecutarRuptura(
        array &$partida,
        string $iniciador,
        string $receptor,
        string $comoAcabo,
        array $cal
    ): void {
        // Citas FUTURAS del par (programadas) se cancelan con motivo; quedadas amistosas siguen.
        foreach (($partida['encuentros'] ?? []) as $i => $enc) {
            if (!is_array($enc) || ($enc['estado'] ?? '') !== 'programado') {
                continue;
            }
            $tipoEnc = (string) ($enc['tipo'] ?? '');
            if ($tipoEnc !== 'primera_cita' && $tipoEnc !== 'cita') {
                continue;
            }
            $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
            if (in_array($iniciador, $parts, true) && in_array($receptor, $parts, true)) {
                $partida['encuentros'][$i]['estado'] = 'cancelado';
                $partida['encuentros'][$i]['motivo_cancelacion'] = 'ruptura';
            }
        }
        // Marcadores de continuidad fuera.
        IniciativaRomantica::purgarMarcadoresPar($partida, $iniciador, $receptor);
        // Hito canÃ³nico de ruptura (estado EX + estabilidad memoria + historial).
        ParejaEngine::romper($partida, $iniciador, $receptor, $comoAcabo);
        // Emociones asimÃ©tricas: pesa mÃ¡s a quien la recibe.
        $durReceptor = self::knobInt($cal, 'ruptura_politica', 'triste_receptor_horas', 10);
        $durIniciador = self::knobInt($cal, 'ruptura_politica', 'triste_iniciador_horas', 5);
        self::tristezaIndividual($partida, $receptor, $durReceptor, $iniciador, $cal);
        self::tristezaIndividual($partida, $iniciador, $durIniciador, $receptor, $cal);
        MemoriaEventos::registrar($partida, 'romance_hito', [$iniciador, $receptor], null, 'ruptura');
        SimFunnelProbe::on($partida, 'declaracion', [
            'ev' => 'ruptura',
            '_k' => 'ruptura_ok',
            'par' => [$iniciador, $receptor],
            'como_acabo' => $comoAcabo,
        ]);
    }

    private static function tristezaIndividual(array &$partida, string $id, int $dur, string $hacia, array $cal): void
    {
        $root = dirname(__DIR__, 2);
        $emoSvc = new EmotionalStateService(
            new VisualPackStore($root),
            new CatalogStore($root),
            null
        );
        $durEf = $dur > 0 ? $dur : (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas_default.triste', 10);
        $hasta = EstadoEmocional::hastaDesdeDuracion($partida['reloj'] ?? [], $durEf);
        $emoSvc->aplicar(
            $partida,
            $id,
            EstadoEmocional::TRISTE,
            'ruptura_pareja',
            null,
            $hasta,
            ['hacia' => $hacia],
            $durEf
        );
    }

    // ==================================================================
    // R8 Â· VUELTA â€” posible pero CON CONTRATO (no telenovela infinita).
    //
    // cooldown post-ruptura = romance_hito (336 h canon) Â· seÃ±al mutua viva
    // Â· â‰¥1 encuentro real bueno POST-ruptura Â· voluntad geomÃ©trica con
    // penalti por vuelta Â· cap absoluto max_vueltas â‡’ historia_cerrada.
    // ==================================================================

    public static function vueltaActiva(array $cal): bool
    {
        return (bool) CalibracionConfig::get($cal, 'romance_autonomo.vuelta_activa', false)
            && (bool) CalibracionConfig::get($cal, 'romance_autonomo.declaracion_activa', false);
    }

    /**
     * @param array<string, int> $out
     */
    private static function evaluarVuelta(array &$partida, string $a, string $b, array $cal, array &$out): void
    {
        // Cooldown post-ruptura: Ãºltima RUPTURA â‰¥ romance_hito (336 h canon).
        $rupturas = RelacionBitacora::entre($partida, $a, $b, RelacionBitacora::RUPTURA);
        if ($rupturas === []) {
            return;
        }
        $last = $rupturas[count($rupturas) - 1];
        $absRup = ((int) ($last['fecha']['dia'] ?? 0)) * 24 + (int) ($last['fecha']['hora'] ?? 0);
        $cooldownRup = max(1, (int) CalibracionConfig::get($cal, 'cooldowns.por_familia.romance_hito', 336));
        if ((self::absNow($partida) - $absRup) < $cooldownRup) {
            return;
        }

        // Cap absoluto de vueltas: historia cerrada.
        $vueltasPrevias = count(RelacionBitacora::entre($partida, $a, $b, RelacionBitacora::VUELTA));
        $maxVueltas = self::knobInt($cal, 'pareja_vuelta', 'max_vueltas', 2);
        if ($vueltasPrevias >= $maxVueltas) {
            SimFunnelProbe::on($partida, 'declaracion', [
                'ev' => 'vuelta_bloqueada',
                '_k' => 'historia_cerrada',
                'par' => [$a, $b],
            ]);
            return;
        }

        // SeÃ±al mutua viva.
        $ab = SenalRomantica::desdeHacia($partida, $a, $b, $cal);
        $ba = SenalRomantica::desdeHacia($partida, $b, $a, $cal);
        if (empty($ab['ok']) || empty($ba['ok'])) {
            return;
        }

        // Deben haberse tratado DESPUÃ‰S de romper: â‰¥1 encuentro real bueno post-ruptura.
        $hayBuenaPost = false;
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
            if (((int) ($ev['dia'] ?? 0)) * 24 + (int) ($ev['hora'] ?? 0) >= $absRup) {
                $hayBuenaPost = true;
                break;
            }
        }
        if (!$hayBuenaPost) {
            return;
        }
        if (MemoriaEventos::enCooldown($partida, 'romance_hito', [$a, $b], $cal)) {
            return;
        }
        if (PropuestaCooldown::activo($partida, $a, $b, 'declaracion', $cal)
            || PropuestaCooldown::activo($partida, $b, $a, 'declaracion', $cal)) {
            return;
        }
        if (!IniciativaRomantica::capHitosDisponible($partida, $cal)) {
            return;
        }

        // Memoria pesa: penalti de voluntad proporcional a vecesPareja.
        $veces = RelacionBitacora::vecesPareja($partida, $a, $b);
        $penPorVuelta = self::knobInt($cal, 'pareja_vuelta', 'penalti_voluntad_por_vuelta', 8);
        $penalti = -$penPorVuelta * max(0, $veces);

        $prop = [
            'participantes' => [$a, $b],
            'tipo' => 'declaracion',
            'lugar' => null,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 12),
            '_bonus_voluntad' => [$a => $penalti, $b => $penalti],
        ];
        $vol = new VoluntadPonderadaEvaluator($cal);
        $ra = $vol->evaluar($partida, $prop, $a);
        $rb = $vol->evaluar($partida, $prop, $b);
        foreach ([[$ra, $a, $b], [$rb, $b, $a]] as [$r, $quien, $otro]) {
            if (($r['decision'] ?? '') !== PropuestaEncuentro::DECISION_RECHAZA) {
                continue;
            }
            if (($r['clase'] ?? '') === PropuestaEncuentro::CLASE_COOLDOWN) {
                return;
            }
            $motivo = (string) ($r['motivo_tipo'] ?? 'banal');
            RechazoMemoria::registrar($partida, $quien, $otro, $motivo, $cal, 'declaracion');
            IniciativaRomantica::registrarRechazoDeclaracion($partida, $otro, $quien, $cal);
            SimFunnelProbe::on($partida, 'declaracion', [
                'ev' => 'vuelta_rechazada',
                '_k' => 'vuelta_no',
                'par' => [$a, $b],
                'motivo' => $motivo,
            ]);
            return;
        }
        $pPlan = sqrt(max(0.0, (float) ($ra['p'] ?? 0)) * max(0.0, (float) ($rb['p'] ?? 0)));
        $rng = RngService::fromPartida($partida);
        $tirada = $rng->nextFloat();
        $rng->persistToPartida($partida);
        if (!($tirada < $pPlan)) {
            SimFunnelProbe::on($partida, 'declaracion', [
                'ev' => 'vuelta_geom_no',
                '_k' => 'vuelta_no',
                'par' => [$a, $b],
                'p_plan' => round($pPlan, 4),
            ]);
            return;
        }

        $rec = ParejaEngine::reconciliar($partida, $a, $b, true, true, $cal);
        if (!($rec['ok'] ?? false)) {
            return;
        }
        MemoriaEventos::registrar($partida, 'pareja', [$a, $b], null, 'vuelta');
        IniciativaRomantica::purgarMarcadoresPar($partida, $a, $b);
        SimFunnelProbe::on($partida, 'declaracion', [
            'ev' => 'vuelta_ok',
            '_k' => 'vuelta_ok',
            'par' => [$a, $b],
        ]);
        $out['vueltas']++;
    }
}
