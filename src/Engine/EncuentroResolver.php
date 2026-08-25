<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Compatibility\PlaceholderEvaluator;

/** Resuelve encuentros. Social y romance evolucionan de forma independiente. */
final class EncuentroResolver
{
    public static function resolver(array &$partida, array $encuentro, ?GameLogger $logger = null, ?Catalog $catalog = null): array
    {
        $participantes = $encuentro['participantes'] ?? [];
        $tipo = $encuentro['tipo'] ?? 'conocerse';
        $cal = [];
        if ($catalog !== null) {
            $cal = CalibracionConfig::load($catalog->getRoot());
        }
        $reales = EncuentroDeltasReales::activo($partida, $cal);

        $deltaSocial = [];
        $deltaRomance = [];
        $conflicto = null;
        $por = [];
        foreach ($participantes as $pid) {
            $por[(string) $pid] = [
                'satisfaccion' => null,
                'texto' => null,
                'resultado' => null,
                '_bloqueado_decision' => ['satisfaccion_direccional', 'copy'],
            ];
        }

        $exp = null;
        if ($catalog !== null && count($participantes) >= 1) {
            $rng = RngService::fromPartida($partida);
            $exp = EncuentroExperiencia::resolver($partida, $encuentro, $catalog, $rng, $cal);
            $rng->persistToPartida($partida);
            foreach ($exp['por_participante'] ?? [] as $pid => $row) {
                $por[(string) $pid] = array_merge($por[(string) $pid] ?? [], $row);
            }
            \aht_log_optional($logger, $partida, 'encuentro_ponderacion', [
                'encuentro_id' => $encuentro['id'] ?? null,
                'factores_keys' => array_keys($exp['factores'] ?? []),
                '_interno' => true,
            ]);
            $narr = EncuentroExperienciaNarrativa::de($partida, $encuentro, $exp, $cal);
            if ($narr !== null) {
                $exp['experiencia_narrativa'] = $narr;
            }
        }

        if ($reales && $catalog !== null && count($participantes) >= 2) {
            $a = (string) $participantes[0];
            $b = (string) $participantes[1];
            $resA = (string) ($por[$a]['resultado'] ?? 'normal');
            $resB = (string) ($por[$b]['resultado'] ?? 'normal');
            $dA = EncuentroDeltasReales::deResultado($resA, (string) $tipo, $cal);
            $dB = EncuentroDeltasReales::deResultado($resB, (string) $tipo, $cal);

            // Aplicar multiplicador positivo y protección progresiva
            $multPos = (float) CalibracionConfig::get($cal, 'voluntad.delta_multiplier_positivo', 1.15);
            $socialA = RelacionEngine::valorSocialHacia($partida, $a, $b);
            $socialB = RelacionEngine::valorSocialHacia($partida, $b, $a);
            $dA['social'] = self::aplicarModificadoresDelta($dA['social'], $socialA, $cal, $multPos, $a, $b, $partida);
            $dB['social'] = self::aplicarModificadoresDelta($dB['social'], $socialB, $cal, $multPos, $b, $a, $partida);

            $deltaSocial = [
                'tipo' => 'reales',
                'a_hacia_b' => $dA['social'],
                'b_hacia_a' => $dB['social'],
                'calidad_a' => $dA['calidad'],
                'calidad_b' => $dB['calidad'],
                'intensidad' => (int) round(($dA['social'] + $dB['social']) / 2),
            ];
            $romA = $dA['romance'];
            $romB = $dB['romance'];
            if ((string) $tipo !== 'romantico' && (string) $tipo !== 'cita' && (string) $tipo !== 'primera_cita') {
                $yaA = RelacionEngine::romanceHacia($partida, $a, $b);
                $yaB = RelacionEngine::romanceHacia($partida, $b, $a);
                if (($yaA === null || $yaA === 0) && $romA > 0) {
                    $romA = 0;
                }
                if (($yaB === null || $yaB === 0) && $romB > 0) {
                    $romB = 0;
                }
            } else {
                $yaA = RelacionEngine::romanceHacia($partida, $a, $b);
                $yaB = RelacionEngine::romanceHacia($partida, $b, $a);
                $senalA = SenalRomantica::desdeHacia($partida, $a, $b, $cal);
                $senalB = SenalRomantica::desdeHacia($partida, $b, $a, $cal);
                if (empty($senalA['ok']) && ($yaA === null || $yaA === 0) && $romA > 0) {
                    $romA = 0;
                }
                if (empty($senalB['ok']) && ($yaB === null || $yaB === 0) && $romB > 0) {
                    $romB = 0;
                }
            }
            if ($romA !== 0 || $romB !== 0 || PropuestaNivel::esTipoCita((string) $tipo)) {
                $deltaRomance = [
                    'a_hacia_b' => $romA,
                    'b_hacia_a' => $romB,
                ];
            }
            $cA = $dA['conflicto'];
            $cB = $dB['conflicto'];
            if ($cA !== null || $cB !== null) {
                $conflicto = max((int) ($cA ?? 0), (int) ($cB ?? 0));
            }
            $resultado = [
                '_placeholder' => false,
                '_deltas_reales' => true,
                '_cal' => $cal,
                'delta_social' => $deltaSocial,
                'delta_romance' => $deltaRomance,
                'conflicto' => $conflicto,
                'descubrimientos' => [],
                'eventos_derivados' => [],
                'por_participante' => $por,
                'experiencia' => $exp,
                'experiencia_narrativa' => is_array($exp['experiencia_narrativa'] ?? null) ? $exp['experiencia_narrativa'] : null,
                'texto_resumen' => 'Encuentro ' . $tipo . ' (' . $resA . '/' . $resB . ').',
            ];
        } else {
            $evaluator = new PlaceholderEvaluator();
            if (count($participantes) >= 2) {
                $a = $participantes[0];
                $b = $participantes[1];
                $ctx = [
                    'tipo_encuentro' => $tipo,
                    'lugar' => $encuentro['lugar'] ?? null,
                    'dia' => $encuentro['dia'] ?? null,
                    'hora' => $encuentro['hora'] ?? null,
                ];
                $deltaSocial = $evaluator->evaluateSocial($partida, $a, $b, $ctx);
                if ($tipo === 'romantico' || $tipo === 'primera_cita' || $tipo === 'cita') {
                    $deltaRomance = $evaluator->evaluateRomantic($partida, $a, $b, $ctx);
                }
            }
            $resultado = [
                '_placeholder' => true,
                'delta_social' => $deltaSocial,
                'delta_romance' => $deltaRomance,
                'conflicto' => null,
                'descubrimientos' => [],
                'eventos_derivados' => [],
                'por_participante' => $por,
                'texto_resumen' => '[PLACEHOLDER] Encuentro ' . $tipo . ' terminado.',
            ];
        }

        \aht_log_optional($logger, $partida, 'encuentro_resuelto', [
            'encuentro_id' => $encuentro['id'] ?? null,
            'tipo' => $tipo,
            'delta_social' => $deltaSocial,
            'delta_romance' => $deltaRomance,
            'deltas_reales' => $reales,
        ]);

        if (FeatureConfig::isEnabled($partida, 'discovery_enabled') && count($participantes) >= 2) {
            $calDisc = $cal !== [] ? $cal : ($catalog !== null ? CalibracionConfig::load($catalog->getRoot()) : []);
            $cands = DiscoveryReveal::candidatosEncuentro($partida, (string) $participantes[0], (string) $participantes[1], $encuentro, $catalog);
            if ($cands !== [] && $calDisc !== []) {
                $rev = DiscoveryReveal::aplicarEvento($partida, $cands, $calDisc, 'encuentro', (string) ($encuentro['id'] ?? ''));
                $resultado['descubrimientos'] = $rev['descubiertos'] ?? [];
            }
        }

        return $resultado;
    }

    public static function aplicarResultado(array &$partida, array $encuentro, array $resultado, ?GameLogger $logger = null): void
    {
        $participantes = $encuentro['participantes'] ?? [];
        if (count($participantes) === 1) {
            $pid = (string) $participantes[0];
            $exp = $resultado['por_participante'][$pid]['resultado'] ?? null;
            MemoriaEventos::registrar(
                $partida,
                'actividad_individual',
                $participantes,
                null,
                (string) ($encuentro['tipo'] ?? 'individual'),
                is_string($exp) ? $exp : null
            );
            return;
        }
        if (count($participantes) < 2) {
            return;
        }
        [$a, $b] = [(string) $participantes[0], (string) $participantes[1]];
        $cal = is_array($resultado['_cal'] ?? null) ? $resultado['_cal'] : [];
        if (!empty($resultado['_deltas_reales'])) {
            $ds = $resultado['delta_social'] ?? [];
            $dAb = (int) ($ds['a_hacia_b'] ?? $ds['intensidad'] ?? 0);
            $dBa = (int) ($ds['b_hacia_a'] ?? $ds['intensidad'] ?? 0);
            $qA = (string) ($ds['calidad_a'] ?? ContactoCalidad::NORMAL);
            $qB = (string) ($ds['calidad_b'] ?? ContactoCalidad::NORMAL);
            RelacionEngine::registrarContacto($partida, $a, $b, $qA, $cal, $dAb >= 0 ? 1 : -1, $dAb);
            RelacionEngine::registrarContacto($partida, $b, $a, $qB, $cal, $dBa >= 0 ? 1 : -1, $dBa);

            $dr = $resultado['delta_romance'] ?? [];
            if (!empty($dr)) {
                $ra = (int) ($dr['a_hacia_b'] ?? 0);
                $rb = (int) ($dr['b_hacia_a'] ?? 0);
                if ($ra !== 0) {
                    $act = RelacionEngine::romanceHacia($partida, $a, $b) ?? 0;
                    RelacionEngine::setRomanceHacia($partida, $a, $b, $act + $ra);
                }
                if ($rb !== 0) {
                    $act = RelacionEngine::romanceHacia($partida, $b, $a) ?? 0;
                    RelacionEngine::setRomanceHacia($partida, $b, $a, $act + $rb);
                }
            }
            $conf = $resultado['conflicto'] ?? null;
            if ($conf !== null && $conf !== false && $conf !== '' && (int) $conf > 0) {
                RelacionEngine::upsertConflicto($partida, $a, $b, (int) $conf, 'roce', 'encuentro');
            }
            $est = ParejaEngine::estado($partida, $a, $b);
            if ($est === ParejaEngine::PAREJA || $est === ParejaEngine::CRISIS) {
                $rel = ParejaEngine::ensureRomance($partida, $a, $b);
                if (!empty($rel['estabilidad_pareja']['activa'])) {
                    $deltaEst = 0;
                    $prom = ((int) ($ds['a_hacia_b'] ?? 0) + (int) ($ds['b_hacia_a'] ?? 0)) / 2.0;
                    if ($prom >= 4) {
                        $deltaEst = 2;
                    } elseif ($prom <= -3) {
                        $deltaEst = -3;
                    }
                    if ($deltaEst !== 0 && isset($rel['estabilidad_pareja']['valor']) && is_numeric($rel['estabilidad_pareja']['valor'])) {
                        $v = (int) $rel['estabilidad_pareja']['valor'] + $deltaEst;
                        $rel['estabilidad_pareja']['valor'] = max(0, min(100, $v));
                        RelacionEngine::persistirRomance($partida, $rel);
                    }
                }
            }
            \aht_log_optional($logger, $partida, 'relacion_delta_social', [
                'persona_a' => $a,
                'persona_b' => $b,
                'delta' => $ds,
                'reales' => true,
            ]);
        } else {
            $ds = $resultado['delta_social'] ?? [];
            if (!empty($ds)) {
                RelacionEngine::upsertSocial(
                    $partida,
                    $a,
                    $b,
                    (string) ($ds['tipo'] ?? 'conocidos'),
                    isset($ds['intensidad']) ? (int) $ds['intensidad'] : null,
                    isset($ds['se_soportan']) ? (bool) $ds['se_soportan'] : null
                );
                \aht_log_optional($logger, $partida, 'relacion_delta_social', [
                    'persona_a' => $a,
                    'persona_b' => $b,
                    'delta' => $ds,
                ]);
            }
            $dr = $resultado['delta_romance'] ?? [];
            if (!empty($dr)) {
                RelacionEngine::upsertRomance($partida, $a, $b, $dr);
                \aht_log_optional($logger, $partida, 'relacion_delta_romance', [
                    'persona_a' => $a,
                    'persona_b' => $b,
                    'delta' => $dr,
                ]);
            }
            $conf = $resultado['conflicto'] ?? null;
            if ($conf !== null && $conf !== false && $conf !== '') {
                $intensidad = is_numeric($conf) ? (int) $conf : null;
                $tipoConf = is_string($conf) ? $conf : 'roce';
                RelacionEngine::upsertConflicto($partida, $a, $b, $intensidad, $tipoConf, 'encuentro');
            }
        }

        $tipoEnc = PropuestaNivel::aliasTipo((string) ($encuentro['tipo'] ?? ''));
        if ($tipoEnc === PropuestaNivel::PRIMERA_CITA
            && !SenalRomantica::yaHuboPrimeraCita($partida, $a, $b)
        ) {
            RelacionBitacora::registrar($partida, RelacionBitacora::PRIMERA_CITA, [$a, $b]);
        }
        SenalRomantica::avisarSiAplica($partida, $a, $b, $cal);
        SenalRomantica::avisarSiAplica($partida, $b, $a, $cal);

        MemoriaEventos::registrar(
            $partida,
            'encuentro',
            $participantes,
            null,
            (string) ($encuentro['tipo'] ?? 'encuentro'),
            $resultado['por_participante'][$a]['resultado'] ?? null
        );

        // FASE 2A: tras resolver una cita romantica queda FECHADO el intento de
        // continuidad (ultima cita + gap canonico 48 h). Aqui solo se registra
        // el marcador; la cita futura, si llega, la decide un tick posterior
        // con voluntad real (IniciativaRomantica::procesarContinuidad).
        if ($tipoEnc === PropuestaNivel::PRIMERA_CITA || $tipoEnc === PropuestaNivel::CITA) {
            $expA = (string) ($resultado['por_participante'][$a]['resultado'] ?? '');
            $expB = (string) ($resultado['por_participante'][$b]['resultado'] ?? '');
            $rankExp = ['muy_mal' => 0, 'mal' => 1, 'normal' => 2, 'bien' => 3, 'muy_bien' => 4];
            $peor = $expA;
            if ($expB !== '' && ($peor === '' || ($rankExp[$expB] ?? 2) < ($rankExp[$peor] ?? 2))) {
                $peor = $expB;
            }
            IniciativaRomantica::registrarContinuidadPostCita($partida, $a, $b, $peor !== '' ? $peor : null, is_array($resultado['_cal'] ?? null) ? $resultado['_cal'] : []);
        }
    }

    /**
     * Aplica multiplicador a positivos y protección progresiva a negativos según social.
     *
     * @param array<string, mixed> $cal
     */
    private static function aplicarModificadoresDelta(int $delta, int $social, array $cal, float $multPos, string $a = '', string $b = '', array $partida = []): int
    {
        if ($delta >= 0) {
            $nuevo = (int) round($delta * $multPos);
            $techo = (int) CalibracionConfig::get($cal, 'contacto.techo_por_encuentro_canal', 10);
            return min($nuevo, $techo);
        }
        $proteccionCurva = (float) CalibracionConfig::get($cal, 'desgaste_social.proteccion_curva', 0.005);
        $proteccionMin = (float) CalibracionConfig::get($cal, 'desgaste_social.proteccion_min', 0.3);
        $proteccion = max($proteccionMin, 1.0 - $proteccionCurva * $social);

        // Nivel 3: primer muy_mal reciente con daño máximo -5
        if ($social >= 82 && $delta <= -7 && $a !== '' && $b !== '' && $partida !== []) {
            $maxDañoNivel3 = (int) CalibracionConfig::get($cal, 'desgaste_social.nivel3_primer_muy_mal_max_daño', 5);
            // Solo aplicar si es el primer muy_mal reciente (verificamos en memoria_eventos)
            $recientes = MemoriaEventos::recientes($partida, [$a, $b], 5);
            $yaTuvoMuyMalReciente = false;
            foreach ($recientes as $ev) {
                if (($ev['resultado_experiencia'] ?? '') === 'muy_mal') {
                    $yaTuvoMuyMalReciente = true;
                    break;
                }
            }
            if (!$yaTuvoMuyMalReciente) {
                $proteccion = min($proteccion, (float) $maxDañoNivel3 / abs($delta));
            }
        }

        $nuevo = (int) round($delta * $proteccion);
        $techo = (int) CalibracionConfig::get($cal, 'contacto.techo_por_encuentro_canal', 10);
        return max($nuevo, -$techo);
    }
}
