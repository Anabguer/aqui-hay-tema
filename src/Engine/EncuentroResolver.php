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
        }

        if ($reales && $catalog !== null && count($participantes) >= 2) {
            $a = (string) $participantes[0];
            $b = (string) $participantes[1];
            $resA = (string) ($por[$a]['resultado'] ?? 'normal');
            $resB = (string) ($por[$b]['resultado'] ?? 'normal');
            $dA = EncuentroDeltasReales::deResultado($resA, (string) $tipo, $cal);
            $dB = EncuentroDeltasReales::deResultado($resB, (string) $tipo, $cal);
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
            if ((string) $tipo !== 'romantico' && (string) $tipo !== 'cita') {
                $yaA = RelacionEngine::romanceHacia($partida, $a, $b);
                $yaB = RelacionEngine::romanceHacia($partida, $b, $a);
                if (($yaA === null || $yaA === 0) && $romA > 0) {
                    $romA = 0;
                }
                if (($yaB === null || $yaB === 0) && $romB > 0) {
                    $romB = 0;
                }
            }
            if ($romA !== 0 || $romB !== 0 || (string) $tipo === 'romantico') {
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
                if ($tipo === 'romantico') {
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
            $cands = self::candidatosDiscovery($partida, (string) $participantes[0], (string) $participantes[1]);
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

        MemoriaEventos::registrar(
            $partida,
            'encuentro',
            $participantes,
            null,
            (string) ($encuentro['tipo'] ?? 'encuentro'),
            $resultado['por_participante'][$a]['resultado'] ?? null
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function candidatosDiscovery(array $partida, string $a, string $b): array
    {
        $out = [];
        foreach ([$a, $b] as $rid) {
            $perfil = PerfilPartida::deOLegacy($partida, $rid);
            $hobbies = is_array($perfil['hobbies'] ?? null) ? $perfil['hobbies'] : [];
            $rasgos = is_array($perfil['rasgos'] ?? null) ? $perfil['rasgos'] : [];
            foreach ($hobbies as $h) {
                if (!is_string($h) || $h === '') {
                    continue;
                }
                if (DiscoveryEngine::estado($partida, $rid, ConocimientoNpc::campoHobby($h)) === DiscoveryEngine::DESCUBIERTO) {
                    continue;
                }
                $out[] = [
                    'residente_id' => $rid,
                    'campo' => ConocimientoNpc::campoHobby($h),
                    'valor' => $h,
                    'observadores' => ['jugador'],
                ];
                break;
            }
            foreach ($rasgos as $r) {
                if (!is_string($r) || $r === '') {
                    continue;
                }
                if (DiscoveryEngine::estado($partida, $rid, ConocimientoNpc::campoRasgo($r)) === DiscoveryEngine::DESCUBIERTO) {
                    continue;
                }
                $out[] = [
                    'residente_id' => $rid,
                    'campo' => ConocimientoNpc::campoRasgo($r),
                    'valor' => $r,
                    'observadores' => ['jugador'],
                ];
                break;
            }
        }
        return $out;
    }
}
