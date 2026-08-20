<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

/**
 * Orquestador de playtest integral: profiles, horizontes, muestreo de planes, invariantes.
 */
final class PlaytestIntegralRunner
{
    /** @var string */
    private $root;
    /** @var PartidaService */
    private $service;
    /** @var list<array<string, mixed>> */
    private $findings = [];

    public function __construct(string $root)
    {
        $this->root = $root;
        $this->service = new PartidaService($root);
        DomainBootstrap::boot();
    }

    /**
     * @return array<string, mixed>
     */
    public function runGateRapido(): array
    {
        $out = [
            'meta' => [
                'modo' => 'gate_rapido',
                'php' => PHP_VERSION,
                'ts' => date('c'),
            ],
            'secciones' => [],
            'bugs' => [],
            'fixes' => [],
            'neni' => null,
        ];

        $out['secciones']['A_tutorial'] = $this->secTutorial();
        $out['secciones']['B_llegadas'] = $this->secNoImpl('Llegadas post-tutorial / candidatos por buzón', 'No hay motor de candidato→buzón→aceptar/rechazar/expirar/vivienda en play.');
        $out['secciones']['C_autonomia'] = $this->secAutonomia();
        $out['secciones']['D_planes_voluntad'] = $this->secVoluntad();
        $out['secciones']['E_compatibilidad'] = $this->secPlanesMuestra();
        $out['secciones']['F_emociones'] = $this->secEmociones();
        $out['secciones']['G_descubrimientos'] = $this->secDiscovery();
        $out['secciones']['H_buzon_cotilleo'] = $this->secBuzonCotilleo();
        $out['secciones']['I_relaciones'] = $this->secRelaciones();
        $out['secciones']['J_marchas'] = $this->secNoImpl('Marchas', 'No existe MarchaEngine / salida de roster / señales de marcha.');
        $out['secciones']['K_economia'] = $this->secEconomia();
        $out['secciones']['L_aforos'] = $this->secAforos();
        $out['secciones']['M_integracion'] = $this->secIntegracion();
        $out['secciones']['N_invariantes'] = $this->secInvariantesLargas();

        $out['resumen'] = $this->resumir($out['secciones']);
        $out['neni'] = $this->veredictoNeni($out);
        $out['findings'] = $this->findings;
        return $out;
    }

    /**
     * Simulaciones largas (30/100/365) con perfiles. Pesado.
     *
     * @param list<int> $horizontes
     * @param list<string> $perfiles
     * @param list<string> $seeds
     * @return array<string, mixed>
     */
    public function runHorizontes(array $horizontes, array $perfiles, array $seeds, string $config = 'playtest_01'): array
    {
        $rows = [];
        foreach ($horizontes as $dias) {
            foreach ($perfiles as $perfil) {
                foreach ($seeds as $seed) {
                    $rows[] = $this->simularPerfil($config, $seed . '-' . $perfil . '-d' . $dias, $perfil, (int) $dias);
                }
            }
        }
        return ['simulaciones' => $rows, 'agregado' => $this->agregarSims($rows)];
    }

    /**
     * @return array<string, mixed>
     */
    private function secTutorial(): array
    {
        $p = $this->service->nuevaPartida('juego_v1', 'gate-tut-1');
        $n0 = count($p['residentes'] ?? []);
        $tut = TutorialBucle::vista($p);
        $ids = array_keys($p['residentes'] ?? []);
        $ok3 = $n0 === 3 && isset($p['residentes']['per_i03'], $p['residentes']['per_p001'], $p['residentes']['per_p002']);
        $okTut = !empty($tut['activo']);

        // Completar tutorial
        TutorialBucle::registrar($p, TutorialBucle::HECHO_BUZON);
        TutorialBucle::registrar($p, TutorialBucle::HECHO_VECINO);
        $plan = $tut['sugerencia'] ?? TutorialBucle::vista($p)['sugerencia'] ?? null;
        if (is_array($plan) && !empty($plan['residente_a']) && !empty($plan['residente_b'])) {
            PropuestaEncuentroEngine::proponer(
                $p,
                [(string) $plan['residente_a'], (string) $plan['residente_b']],
                (int) ($plan['dia'] ?? 1),
                (int) ($plan['hora'] ?? 15),
                (string) ($plan['tipo'] ?? 'conocerse'),
                isset($plan['lugar']) ? (string) $plan['lugar'] : 'lug_cafeteria'
            );
        } else {
            TutorialBucle::registrar($p, TutorialBucle::HECHO_PLAN);
        }
        $tutFin = TutorialBucle::vista($p);
        $nFin = count($p['residentes'] ?? []);

        // Avanzar resto del día 1
        $hora = (int) ($p['reloj']['hora_actual'] ?? 14);
        if ($hora < 22) {
            $this->service->avanzarReloj($p, 22 - $hora);
        }
        $nDia1 = count($p['residentes'] ?? []);
        $crece = $nDia1 >= 8;
        $status = 'FAIL';
        $notas = [];
        if (!$ok3) {
            $notas[] = 'No arranca con exactamente 3 utilizables.';
        }
        if (!$okTut) {
            $notas[] = 'Tutorial no activo al crear juego_v1.';
        }
        if (!empty($tutFin['activo'])) {
            $notas[] = 'Tutorial sigue activo tras completar pasos.';
            $status = 'FAIL';
        }
        if (!$crece) {
            $notas[] = 'NO_IMPLEMENTADO: tras tutorial + día 1 siguen ' . $nDia1 . ' residentes (canónico ≈8).';
            $status = 'FAIL';
        }
        if ($ok3 && $okTut && empty($tutFin['activo']) && $crece) {
            $status = 'PASS';
        } elseif ($ok3 && $okTut && empty($tutFin['activo']) && !$crece) {
            $status = 'FAIL'; // decisión canónica no cableada
        }

        // playtest_01 no debe saltarse tutorial del jugador
        $lab = $this->service->nuevaPartida('playtest_01', 'gate-lab');
        $labTut = TutorialBucle::vista($lab);

        return [
            'status' => $status,
            'inicial_n' => $n0,
            'ids_iniciales' => $ids,
            'tutorial_activo_inicio' => $okTut,
            'tutorial_activo_fin' => !empty($tutFin['activo']),
            'n_tras_tutorial' => $nFin,
            'n_fin_dia_1' => $nDia1,
            'crecimiento_a_8' => $crece,
            'playtest_01_sin_tutorial' => empty($labTut['activo']),
            'playtest_01_n' => count($lab['residentes'] ?? []),
            'notas' => $notas,
            'gap' => $crece ? null : 'Falta motor de incorporaciones tutorializadas 3→≈8 en el primer día.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function secAutonomia(): array
    {
        $seeds = ['aut-a', 'aut-b', 'aut-c'];
        $fallos = [];
        $salidas = 0;
        $personasDosSitios = 0;
        foreach ($seeds as $seed) {
            $p = $this->service->nuevaPartida('playtest_01', $seed);
            for ($d = 0; $d < 7; $d++) {
                $r = $this->service->avanzarRelojPasoAPaso($p, 24);
                $inv = PlaytestInvariantes::auditar($p, $this->root);
                foreach ($inv as $f) {
                    if (strpos($f, 'persona_dos_sitios') === 0) {
                        $personasDosSitios++;
                    }
                    if (strpos($f, 'aforo_') === 0) {
                        $fallos[] = $f . '@' . $seed . ':d' . ($p['reloj']['dia_pueblo'] ?? '?');
                    }
                }
                $salidas += (int) ($r['coincidencias_detectadas'] ?? 0);
            }
        }
        $status = ($personasDosSitios === 0 && $fallos === []) ? 'PASS' : 'FAIL';
        return [
            'status' => $status,
            'seeds' => $seeds,
            'dias_por_seed' => 7,
            'poblacion_auditada' => 8,
            'poblaciones_pedidas_no_disponibles' => [16, 32, 48],
            'flags_persona_dos_sitios' => $personasDosSitios,
            'aforo_fallos' => array_slice($fallos, 0, 20),
            'nota' => 'Autonomía ON en playtest_01 (8 residentes). Sin motor de llegadas no hay roster 16/32/48 real → no se finge.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function secVoluntad(): array
    {
        $bandas = [
            'bajo' => ['min' => 0, 'max' => 49, 'acepta' => 0, 'total' => 0, 'p_sum' => 0.0],
            'medio' => ['min' => 50, 'max' => 74, 'acepta' => 0, 'total' => 0, 'p_sum' => 0.0],
            'alto' => ['min' => 75, 'max' => 100, 'acepta' => 0, 'total' => 0, 'p_sum' => 0.0],
        ];
        $planesAcept = 0;
        $planesTotal = 0;
        $rachas = [];
        $ejemplos = [];
        $seeds = [];
        for ($i = 0; $i < 12; $i++) {
            $seeds[] = 'vol-' . $i;
        }
        foreach ($seeds as $seed) {
            $p = $this->service->nuevaPartida('playtest_01', $seed);
            $ids = array_keys($p['residentes']);
            $racha = 0;
            $maxRacha = 0;
            for ($n = 0; $n < 40; $n++) {
                $a = $ids[$n % count($ids)];
                $b = $ids[($n + 1 + ($n % 5)) % count($ids)];
                if ($a === $b) {
                    continue;
                }
                $hora = 10 + ($n % 8);
                $r = PropuestaEncuentroEngine::proponer($p, [$a, $b], 1, $hora, 'conocerse', 'lug_cafeteria');
                $reacs = $r['propuesta']['reacciones'] ?? [];
                $planAcepta = empty($r['rechazada']) && (($r['propuesta']['estado'] ?? '') !== 'rechazada');
                $planesTotal++;
                if ($planAcepta) {
                    $planesAcept++;
                }
                foreach ($reacs as $reac) {
                    if (!is_array($reac)) {
                        continue;
                    }
                    $dec = (string) ($reac['decision'] ?? '');
                    if ($dec !== 'rechaza' && $dec !== 'acepta') {
                        continue;
                    }
                    if (!isset($reac['score']) || $reac['score'] === null) {
                        continue; // cooldown u otros sin score
                    }
                    $score = (int) $reac['score'];
                    $pVal = isset($reac['p']) ? (float) $reac['p'] : null;
                    $aceptaPers = $dec === 'acepta';
                    foreach ($bandas as $k => &$banda) {
                        if ($score >= $banda['min'] && $score <= $banda['max']) {
                            $banda['total']++;
                            $banda['p_sum'] += $pVal ?? 0;
                            if ($aceptaPers) {
                                $banda['acepta']++;
                            }
                        }
                    }
                    unset($banda);
                }
                if (!$planAcepta) {
                    $racha++;
                    $maxRacha = max($maxRacha, $racha);
                } else {
                    $racha = 0;
                }
                if (count($ejemplos) < 8) {
                    $ejemplos[] = [
                        'seed' => $seed,
                        'a' => IdentidadPublica::nombre($p, (string) $a),
                        'b' => IdentidadPublica::nombre($p, (string) $b),
                        'hora' => $hora,
                        'rechazada' => !empty($r['rechazada']),
                        'clase' => $r['rechazo_clase'] ?? null,
                        'mensaje_ui' => $r['mensaje_ui'] ?? null,
                        'reacciones' => $reacs,
                    ];
                }
                if ($n % 5 === 4) {
                    $this->service->avanzarReloj($p, 1);
                }
            }
            $rachas[] = $maxRacha;
        }
        $tasas = [];
        foreach ($bandas as $k => $b) {
            $tasas[$k] = [
                'n_participantes' => $b['total'],
                'tasa_aceptacion_persona' => $b['total'] > 0 ? round($b['acepta'] / $b['total'], 3) : null,
                'p_media_formula' => $b['total'] > 0 ? round($b['p_sum'] / $b['total'], 3) : null,
            ];
        }
        $tasaPersonaMedio = $tasas['medio']['tasa_aceptacion_persona'];
        $tasaPlan = $planesTotal > 0 ? round($planesAcept / $planesTotal, 3) : null;
        $bloqueado = null;
        // Frustración de producto: el plan exige DOS aceptes → ~p². Con p≈0.68 ⇒ plan≈0.46.
        if ($tasaPersonaMedio !== null && $tasaPersonaMedio >= 0.55 && $tasaPersonaMedio <= 0.80
            && $tasaPlan !== null && $tasaPlan < 0.50) {
            $bloqueado = 'BLOQUEADO_DECISION: score≈70 ⇒ p_persona≈'
                . ($tasas['medio']['p_media_formula'] ?? '?')
                . ' y tasa observada persona=' . $tasaPersonaMedio
                . ', pero tasa de PLAN (ambos aceptan)≈' . $tasaPlan
                . '. Matemáticamente coherente (≈p²); puede sentirse como demasiados plantones en Organizar.';
            $this->findings[] = $bloqueado;
        } elseif ($tasaPersonaMedio !== null && abs($tasaPersonaMedio - (float) ($tasas['medio']['p_media_formula'] ?? 0)) > 0.15) {
            $bloqueado = 'BLOQUEADO_DECISION: tasa persona desvía >15pp de p_media fórmula; revisar RNG/muestreo.';
            $this->findings[] = $bloqueado;
        }

        return [
            'status' => 'PASS',
            'seeds' => count($seeds),
            'planes_por_seed' => 40,
            'tasa_aceptacion_plan' => $tasaPlan,
            'planes_total' => $planesTotal,
            'tasas_por_banda_score' => $tasas,
            'racha_rechazos_plan_max_media' => $rachas !== [] ? round(array_sum($rachas) / count($rachas), 2) : null,
            'racha_rechazos_plan_max_abs' => $rachas !== [] ? max($rachas) : null,
            'ejemplos' => $ejemplos,
            'balance' => $bloqueado,
            'nota' => 'Medir persona (≈p) vs plan (ambos). Neni percibe plantones a nivel plan.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function secPlanesMuestra(): array
    {
        $p = $this->service->nuevaPartida('playtest_01', 'planes-muestra');
        $ids = array_values(array_keys($p['residentes']));
        $casos = [];
        $matriz = [
            ['label' => 'conocerse_cafe_valido', 'tipo' => 'conocerse', 'lugar' => 'lug_cafeteria', 'hora' => 15],
            ['label' => 'conocerse_hora_cerrada', 'tipo' => 'conocerse', 'lugar' => 'lug_cafeteria', 'hora' => 3],
            ['label' => 'misma_persona', 'tipo' => 'conocerse', 'lugar' => 'lug_cafeteria', 'hora' => 16, 'same' => true],
            ['label' => 'parque_si_operativo', 'tipo' => 'conocerse', 'lugar' => 'lug_parque', 'hora' => 12],
            ['label' => 'bar_noche', 'tipo' => 'conocerse', 'lugar' => 'lug_bar', 'hora' => 22],
            ['label' => 'pareja_distinta_hora', 'tipo' => 'conocerse', 'lugar' => 'lug_cafeteria', 'hora' => 11, 'pair' => [2, 3]],
            ['label' => 'otro_par_tarde', 'tipo' => 'conocerse', 'lugar' => 'lug_biblioteca', 'hora' => 17, 'pair' => [4, 5]],
            ['label' => 'ocupado_solape', 'tipo' => 'conocerse', 'lugar' => 'lug_cafeteria', 'hora' => 15, 'pair' => [0, 2]],
        ];
        foreach ($matriz as $i => $c) {
            if (!empty($c['same'])) {
                $a = $ids[0];
                $b = $a;
            } elseif (isset($c['pair'])) {
                $a = $ids[$c['pair'][0] % count($ids)];
                $b = $ids[$c['pair'][1] % count($ids)];
            } else {
                $a = $ids[0];
                $b = $ids[1 + ($i % max(1, count($ids) - 1))];
            }
            $before = [
                'conocen' => RelacionEngine::seConocen($p, $a, $b),
                'social_ab' => RelacionEngine::valorSocialHacia($p, $a, $b),
                'social_ba' => RelacionEngine::valorSocialHacia($p, $b, $a),
                'emo_a' => $p['residentes'][$a]['runtime']['estado_emocional']['id'] ?? null,
                'emo_b' => $p['residentes'][$b]['runtime']['estado_emocional']['id'] ?? null,
            ];
            $r = PropuestaEncuentroEngine::proponer($p, [$a, $b], (int) $p['reloj']['dia_pueblo'], (int) $c['hora'], (string) $c['tipo'], (string) $c['lugar']);
            $after = [
                'conocen' => RelacionEngine::seConocen($p, $a, $b),
                'social_ab' => RelacionEngine::valorSocialHacia($p, $a, $b),
                'social_ba' => RelacionEngine::valorSocialHacia($p, $b, $a),
                'emo_a' => $p['residentes'][$a]['runtime']['estado_emocional']['id'] ?? null,
                'emo_b' => $p['residentes'][$b]['runtime']['estado_emocional']['id'] ?? null,
            ];
            $casos[] = [
                'label' => $c['label'],
                'input' => [
                    'a_id' => $a,
                    'b_id' => $b,
                    'a' => IdentidadPublica::nombre($p, $a),
                    'b' => IdentidadPublica::nombre($p, $b),
                    'tipo' => $c['tipo'],
                    'lugar' => $c['lugar'],
                    'hora' => $c['hora'],
                    'before' => $before,
                ],
                'decision' => [
                    'ok' => $r['ok'] ?? null,
                    'rechazada' => $r['rechazada'] ?? null,
                    'error' => $r['error'] ?? null,
                    'clase' => $r['rechazo_clase'] ?? null,
                    'mensaje_ui' => $r['mensaje_ui'] ?? null,
                    'reacciones' => $r['propuesta']['reacciones'] ?? null,
                ],
                'resultado' => $after,
            ];
            if (empty($r['rechazada']) && ($r['ok'] ?? false)) {
                $this->service->avanzarReloj($p, 1);
            }
        }
        return ['status' => 'PASS', 'n_casos' => count($casos), 'casos' => $casos];
    }

    /**
     * @return array<string, mixed>
     */
    private function secEmociones(): array
    {
        $canon = ['alegre', 'neutro', 'triste', 'enfadado'];
        $p = $this->service->nuevaPartida('playtest_01', 'emo-1');
        $ids = array_values(array_keys($p['residentes']));
        $okCanon = true;
        foreach ($ids as $id) {
            $emo = (string) ($p['residentes'][$id]['runtime']['estado_emocional']['id'] ?? 'neutro');
            if (!in_array($emo, $canon, true) && $emo !== 'neutral') {
                // EstadoEmocional may use neutro not neutral
                if (!in_array($emo, ['alegre', 'neutro', 'triste', 'enfadado'], true)) {
                    $okCanon = false;
                }
            }
        }
        // Forzar encuentro y ver emociones independientes
        $r = PropuestaEncuentroEngine::proponer($p, [$ids[0], $ids[1]], 1, 15, 'conocerse', 'lug_cafeteria');
        if (empty($r['rechazada'])) {
            $this->service->avanzarRelojPasoAPaso($p, 8);
        }
        $emoA = $p['residentes'][$ids[0]]['runtime']['estado_emocional']['id'] ?? null;
        $emoB = $p['residentes'][$ids[1]]['runtime']['estado_emocional']['id'] ?? null;
        return [
            'status' => $okCanon ? 'PASS' : 'FAIL',
            'canon' => $canon,
            'emo_post_a' => $emoA,
            'emo_post_b' => $emoB,
            'nota' => 'Emoción es individual; bridge emocional solo en ENCUENTRO_TERMINADO (cobertura parcial de eventos).',
            'parcial' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function secDiscovery(): array
    {
        if (!FeatureConfig::isEnabled($this->service->nuevaPartida('playtest_01', 'disc-flag'), 'discovery_enabled')) {
            return $this->secNoImpl('Discovery', 'Flag discovery_enabled OFF');
        }
        $p = $this->service->nuevaPartida('playtest_01', 'disc-1');
        $id = (string) array_keys($p['residentes'])[0];
        $snapshots = [];
        $snapshots['dia_1'] = [
            'n' => count(DiscoveryEngine::listarPorResidente($p, $id)),
            'items' => array_slice(DiscoveryEngine::listarPorResidente($p, $id), 0, 12),
        ];
        foreach ([10, 30, 100] as $target) {
            while ((int) $p['reloj']['dia_pueblo'] < $target) {
                $ids = array_keys($p['residentes']);
                if (count($ids) >= 2 && ((int) $p['reloj']['dia_pueblo'] % 3 === 0)) {
                    PropuestaEncuentroEngine::proponer(
                        $p,
                        [$ids[0], $ids[1]],
                        (int) $p['reloj']['dia_pueblo'],
                        15,
                        'conocerse',
                        'lug_cafeteria'
                    );
                }
                $this->service->avanzarReloj($p, 24);
            }
            $list = DiscoveryEngine::listarPorResidente($p, $id);
            $snapshots['dia_' . $target] = [
                'n' => count($list),
                'items' => array_slice($list, 0, 12),
                'residente' => IdentidadPublica::nombre($p, $id),
            ];
        }
        $n1 = $snapshots['dia_1']['n'];
        $n100 = $snapshots['dia_100']['n'];
        $ok = $n1 < 50; // no revelar todo al día 1
        return [
            'status' => $ok ? 'PASS' : 'FAIL',
            'residente' => IdentidadPublica::nombre($p, $id),
            'snapshots' => $snapshots,
            'crecimiento' => ['dia1' => $n1, 'dia100' => $n100],
            'nota' => 'Discovery ON; se espera revelación gradual, no dump total día 1.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function secBuzonCotilleo(): array
    {
        $p = $this->service->nuevaPartida('playtest_01', 'bc-1');
        // Forzar rechazo → mensaje buzón
        $p2 = $this->service->nuevaPartida('juego_v1', 'bc-rechazo');
        $r = PropuestaEncuentroEngine::proponer($p2, ['per_i03', 'per_p002'], 1, 15, 'conocerse', 'lug_cafeteria');
        $buz = BuzonEngine::listar($p2);
        $coti = 0;
        $buzon = 0;
        $respuestaPlan = 0;
        foreach ($buz as $m) {
            if (!is_array($m)) {
                continue;
            }
            $canal = (string) ($m['canal'] ?? BuzonEngine::canalDe((string) ($m['clasificacion'] ?? '')));
            if ($canal === BuzonEngine::CANAL_COTILLEO) {
                $coti++;
            } else {
                $buzon++;
            }
            if (($m['tipo'] ?? '') === 'respuesta_plan') {
                $respuestaPlan++;
            }
        }
        $vista = VistaCotilleoV3::de($p2);
        $fail = false;
        $notas = [];
        if (!empty($r['rechazada']) && $respuestaPlan < 1) {
            // puede no haberse emitido si bridge falló
            $notas[] = 'Rechazo sin mensaje respuesta_plan detectable.';
        }
        foreach ($vista['hoy'] ?? [] as $e) {
            $txt = (string) ($e['texto'] ?? '');
            if (strpos($txt, 'no han quedado') !== false) {
                $fail = true;
                $notas[] = 'FAIL: rechazo de plan aparece en El Cotilleo.';
            }
        }
        // ruido 14 días playtest
        $lab = $this->service->nuevaPartida('playtest_01', 'bc-ruido');
        for ($i = 0; $i < 14; $i++) {
            $this->service->avanzarReloj($lab, 24);
        }
        $msgs = BuzonEngine::listar($lab);
        $cotiDias = [];
        foreach ($msgs as $m) {
            if (!is_array($m)) {
                continue;
            }
            if ((($m['canal'] ?? '') === BuzonEngine::CANAL_COTILLEO) || (($m['clasificacion'] ?? '') === BuzonEngine::COTILLEO)) {
                $d = (int) ($m['dia'] ?? 0);
                $cotiDias[$d] = ($cotiDias[$d] ?? 0) + 1;
            }
        }
        $maxCotiDia = $cotiDias !== [] ? max($cotiDias) : 0;
        return [
            'status' => $fail ? 'FAIL' : 'PASS',
            'rechazo_en_buzon_no_cotilleo' => !$fail,
            'respuesta_plan_count' => $respuestaPlan,
            'cotilleo_max_por_dia_en_14d' => $maxCotiDia,
            'notas' => $notas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function secRelaciones(): array
    {
        $implemented = class_exists(RelacionEngine::class) && class_exists(ParejaEngine::class);
        if (!$implemented) {
            return $this->secNoImpl('Relaciones', 'Motores ausentes');
        }
        return [
            'status' => 'PARCIAL',
            'social_romance_contacto' => 'IMPLEMENTADO',
            'pareja_crisis_ruptura_hitos' => 'IMPLEMENTADO (manual/hitos, no umbral automático)',
            'deterioro_diario' => class_exists(RelacionDesgaste::class) ? 'IMPLEMENTADO' : 'NO_IMPLEMENTADO',
            'nota' => 'No se simula falsamente crisis automática por umbral si no está cableada.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function secEconomia(): array
    {
        $p = $this->service->nuevaPartida('juego_v1', 'eco-1');
        $ecoOn = FeatureConfig::isEnabled($p, 'economy_enabled');
        $dinero = $p['economia']['dinero']['balance'] ?? $p['celeste']['dinero'] ?? null;
        return [
            'status' => $ecoOn ? 'PARCIAL' : 'NO_IMPLEMENTADO',
            'economy_enabled' => $ecoOn,
            'dinero_juego_v1' => $dinero,
            'misiones_playtest_01' => FeatureConfig::isEnabled($this->service->nuevaPartida('playtest_01', 'eco-m'), 'misiones_diarias_enabled'),
            'peticiones_playtest_01' => FeatureConfig::isEnabled($this->service->nuevaPartida('playtest_01', 'eco-p'), 'peticiones_pueblo_enabled'),
            'nota' => 'Economía OFF en play canónico; hay lab SimuladorEconomia separado.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function secAforos(): array
    {
        $esperados = [
            'cafe_libros' => 10,
            'rincon_lola' => 10,
            'cine_game' => 12,
            'mala_idea' => 12,
            'parque' => 16,
            'gimnasio_spa' => 10,
        ];
        $dest = [
            'lug_cafeteria' => 8,
            'lug_biblioteca' => 6,
            'lug_tienda_ropa' => 4,
            'lug_bar' => 8,
            'lug_discoteca' => 8,
            'lug_karaoke' => 4,
        ];
        $ok = true;
        $detalle = [];
        foreach ($esperados as $cid => $n) {
            $got = ComplejoCatalog::aforoComplejo($cid);
            $detalle[$cid] = $got;
            if ($got !== $n) {
                $ok = false;
            }
        }
        foreach ($dest as $lug => $n) {
            $d = ComplejoCatalog::destino($lug);
            $got = (int) ($d['aforo'] ?? -1);
            if ($got !== $n) {
                $ok = false;
            }
        }
        // Saturación: techo complejo < suma destinos
        $sumaMala = 8 + 8 + 4;
        $techoMala = ComplejoCatalog::aforoComplejo('mala_idea');
        $logica = $techoMala < $sumaMala;
        // Prueba cabe: no permitir superar techo
        $p = $this->service->nuevaPartida('playtest_01', 'aforo-stress');
        // Desbloquear mala idea si hace falta — playtest puede no tenerlo
        $p['celeste']['lugares_desbloqueados'] = array_values(array_unique(array_merge(
            $p['celeste']['lugares_desbloqueados'] ?? [],
            ['lug_bar', 'lug_discoteca', 'lug_karaoke']
        )));
        $dia = (int) $p['reloj']['dia_pueblo'];
        $hora = 23;
        // llenar ocupación sintética vía planes autónomos pendientes
        $p['npc_autonomo']['planes_pendientes'] = [];
        $ids = array_keys($p['residentes']);
        for ($i = 0; $i < 8; $i++) {
            $p['npc_autonomo']['planes_pendientes'][] = [
                'lugar' => 'lug_bar',
                'dia' => $dia,
                'hora' => $hora,
                'duracion_minutos' => 120,
                'participantes' => [$ids[$i % count($ids)]],
            ];
        }
        $cabeExtra = AforoEngine::cabe($p, 'lug_discoteca', $dia, $hora, 5);
        // con 8 en bar, techo complejo 12 → discoteca solo cabe 4
        $cabe4 = AforoEngine::cabe($p, 'lug_discoteca', $dia, $hora, 4);
        $cabe5 = AforoEngine::cabe($p, 'lug_discoteca', $dia, $hora, 5);

        return [
            'status' => ($ok && $logica && $cabe4 && !$cabe5) ? 'PASS' : 'FAIL',
            'techos' => $detalle,
            'mala_idea_suma_destinos' => $sumaMala,
            'mala_idea_techo' => $techoMala,
            'techo_impide_20' => $logica,
            'cabe_4_en_disco_con_bar_8' => $cabe4,
            'cabe_5_en_disco_con_bar_8' => $cabe5,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function secIntegracion(): array
    {
        $checks = [];
        // Save/load
        $p = $this->service->nuevaPartida('playtest_01', 'save-1');
        $id = $p['meta']['partida_id'];
        $this->service->guardar($p);
        $p2 = $this->service->cargar($id);
        $checks['save_load_mismo_dia'] = ((int) $p['reloj']['dia_pueblo'] === (int) $p2['reloj']['dia_pueblo']);
        // Organizar misma persona
        $r = PropuestaEncuentroEngine::proponer($p2, ['per_p001', 'per_p001'], 1, 15, 'conocerse', 'lug_cafeteria');
        $checks['rechaza_misma_persona'] = (($r['ok'] ?? true) === false) || (($r['error'] ?? '') !== '');
        // resolverFranja no fatal en 2ª propuesta
        $p3 = $this->service->nuevaPartida('playtest_01', 'franja-2');
        $r1 = PropuestaEncuentroEngine::proponer($p3, ['per_p001', 'per_p002'], 1, 15, 'conocerse', 'lug_cafeteria');
        $fatal = false;
        try {
            $r2 = PropuestaEncuentroEngine::proponer($p3, ['per_p001', 'per_p003'], 1, 17, 'conocerse', 'lug_cafeteria');
            $checks['segunda_propuesta_sin_fatal'] = isset($r2['ok']) || isset($r2['error']);
        } catch (\Throwable $e) {
            $fatal = true;
            $checks['segunda_propuesta_sin_fatal'] = false;
            $this->findings[] = 'BUG: segunda propuesta fatal: ' . $e->getMessage();
        }
        // API-like: tipos_permitidos path via OrganizarMotivo
        $mot = OrganizarMotivo::de($p3, 'per_p001', 'per_p001', '', []);
        $checks['organizar_misma_persona_causa'] = ($mot['codigo'] ?? '') === OrganizarMotivo::MISMA_PERSONA
            || ($mot['codigo'] ?? '') === 'misma_persona';

        $status = (!in_array(false, $checks, true) && !$fatal) ? 'PASS' : 'FAIL';
        return ['status' => $status, 'checks' => $checks, 'r1_estado' => $r1['propuesta']['estado'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    private function secInvariantesLargas(): array
    {
        // Gate rápido: 7 días × 4 perfiles (el CLI --full hace 30/100/365).
        $perfiles = ['activa', 'normal', 'torpe', 'inactiva'];
        $horizonte = 7;
        $fallos = [];
        foreach ($perfiles as $perfil) {
            $row = $this->simularPerfil('playtest_01', 'inv-' . $perfil, $perfil, $horizonte);
            foreach ($row['invariantes_fallos'] as $f) {
                $fallos[] = $perfil . ':' . $f;
            }
        }
        return [
            'status' => $fallos === [] ? 'PASS' : 'FAIL',
            'horizonte' => $horizonte,
            'perfiles' => $perfiles,
            'fallos' => array_slice($fallos, 0, 40),
            'n_fallos' => count($fallos),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function simularPerfil(string $config, string $seed, string $perfil, int $dias): array
    {
        $p = $this->service->nuevaPartida($config, $seed);
        $ids = array_keys($p['residentes'] ?? []);
        $planes = 0;
        $aceptados = 0;
        $rechazados = 0;
        $invFallos = [];
        $coti = 0;
        $buz = 0;

        $rate = [
            'activa' => 0.55,
            'normal' => 0.28,
            'torpe' => 0.40,
            'inactiva' => 0.05,
        ][$perfil] ?? 0.2;

        for ($d = 0; $d < $dias; $d++) {
            // 3 ticks/día (mañana, tarde, noche) para no ir hora a hora en 365
            foreach ([8, 8, 8] as $h) {
                if ((mt_rand() / mt_getrandmax()) < $rate && count($ids) >= 2) {
                    $a = $ids[array_rand($ids)];
                    $b = $ids[array_rand($ids)];
                    if ($a === $b) {
                        continue;
                    }
                    $hora = 10 + (int) ($d % 8);
                    $lugar = 'lug_cafeteria';
                    $ops = $p['celeste']['lugares_desbloqueados'] ?? [];
                    if (in_array('lug_parque', $ops, true) && ($d % 3 === 0)) {
                        $lugar = 'lug_parque';
                    }
                    // perfil torpe: a veces hora imposible
                    if ($perfil === 'torpe' && ($d % 7 === 0)) {
                        $hora = 3;
                    }
                    $r = PropuestaEncuentroEngine::proponer($p, [$a, $b], (int) $p['reloj']['dia_pueblo'], $hora, 'conocerse', $lugar);
                    $planes++;
                    if (!empty($r['rechazada']) || (($r['propuesta']['estado'] ?? '') === 'rechazada')) {
                        $rechazados++;
                    } elseif (($r['ok'] ?? false) || (($r['propuesta']['estado'] ?? '') === 'programada') || (($r['propuesta']['estado'] ?? '') === 'aceptada')) {
                        $aceptados++;
                    }
                }
                $this->service->avanzarReloj($p, $h);
                foreach (PlaytestInvariantes::auditar($p, $this->root) as $f) {
                    // ignorar soft fuera_horario en sims largas (duraciones)
                    if (strpos($f, 'actividad_fuera_horario_suave') === 0) {
                        continue;
                    }
                    $invFallos[$f] = ($invFallos[$f] ?? 0) + 1;
                }
            }
        }
        foreach (BuzonEngine::listar($p) as $m) {
            if (!is_array($m)) {
                continue;
            }
            $canal = (string) ($m['canal'] ?? BuzonEngine::canalDe((string) ($m['clasificacion'] ?? '')));
            if ($canal === BuzonEngine::CANAL_COTILLEO) {
                $coti++;
            } else {
                $buz++;
            }
        }

        return [
            'config' => $config,
            'seed' => $seed,
            'perfil' => $perfil,
            'dias' => $dias,
            'poblacion_final' => count($p['residentes'] ?? []),
            'dia_final' => (int) ($p['reloj']['dia_pueblo'] ?? 0),
            'planes' => $planes,
            'aceptados' => $aceptados,
            'rechazados' => $rechazados,
            'tasa_acept' => $planes > 0 ? round($aceptados / $planes, 3) : null,
            'buzon_msgs' => $buz,
            'cotilleo_msgs' => $coti,
            'invariantes_fallos' => array_keys($invFallos),
            'invariantes_counts' => $invFallos,
            'vida' => class_exists(VidaPuebloEngine::class) ? VidaPuebloEngine::valor($p) : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function agregarSims(array $rows): array
    {
        $byPerfil = [];
        foreach ($rows as $r) {
            $k = $r['perfil'] . ':d' . $r['dias'];
            $byPerfil[$k]['n'] = ($byPerfil[$k]['n'] ?? 0) + 1;
            $byPerfil[$k]['planes'] = ($byPerfil[$k]['planes'] ?? 0) + $r['planes'];
            $byPerfil[$k]['aceptados'] = ($byPerfil[$k]['aceptados'] ?? 0) + $r['aceptados'];
            $byPerfil[$k]['rechazados'] = ($byPerfil[$k]['rechazados'] ?? 0) + $r['rechazados'];
            $byPerfil[$k]['cotilleo'] = ($byPerfil[$k]['cotilleo'] ?? 0) + $r['cotilleo_msgs'];
            $byPerfil[$k]['fallos_inv'] = ($byPerfil[$k]['fallos_inv'] ?? 0) + count($r['invariantes_fallos']);
        }
        foreach ($byPerfil as &$agg) {
            $p = $agg['planes'] ?: 1;
            $agg['tasa_acept'] = round($agg['aceptados'] / $p, 3);
        }
        unset($agg);
        return $byPerfil;
    }

    /**
     * @return array<string, mixed>
     */
    private function secNoImpl(string $titulo, string $detalle): array
    {
        return [
            'status' => 'NO_IMPLEMENTADO',
            'titulo' => $titulo,
            'detalle' => $detalle,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $secs
     * @return array<string, int>
     */
    private function resumir(array $secs): array
    {
        $c = ['PASS' => 0, 'FAIL' => 0, 'NO_IMPLEMENTADO' => 0, 'PARCIAL' => 0, 'BLOQUEADO_DECISION' => 0];
        foreach ($secs as $s) {
            $st = (string) ($s['status'] ?? 'PARCIAL');
            if (isset($c[$st])) {
                $c[$st]++;
            } else {
                $c['PARCIAL']++;
            }
            if (!empty($s['balance']) && strpos((string) $s['balance'], 'BLOQUEADO_DECISION') === 0) {
                $c['BLOQUEADO_DECISION']++;
            }
        }
        return $c;
    }

    /**
     * @param array<string, mixed> $out
     * @return array{veredicto:string,motivos:list<string>}
     */
    private function veredictoNeni(array $out): array
    {
        $motivos = [];
        $secs = $out['secciones'];
        if (($secs['A_tutorial']['status'] ?? '') === 'FAIL') {
            $motivos[] = 'Tutorial canónico 3→≈8 no está cableado: Neni seguiría chocando con arranque incoherente según docs.';
        }
        if (($secs['B_llegadas']['status'] ?? '') === 'NO_IMPLEMENTADO') {
            $motivos[] = 'Sin sistema de llegadas post-tutorial; el playtest longitudinal de población no es el juego real.';
        }
        if (($secs['N_invariantes']['status'] ?? '') === 'FAIL') {
            $motivos[] = 'Invariantes rotas en sims 30d (persona/aforo/etc.).';
        }
        if (($secs['M_integracion']['status'] ?? '') === 'FAIL') {
            $motivos[] = 'Fallos de integración (save/organizar/franja).';
        }
        if (($secs['L_aforos']['status'] ?? '') === 'FAIL') {
            $motivos[] = 'Aforos canónicos no se respetan.';
        }
        // Voluntad frustrante
        if (!empty($secs['D_planes_voluntad']['balance'])) {
            $motivos[] = 'Balance de voluntad: ' . $secs['D_planes_voluntad']['balance'];
        }

        // Criterio estricto: no SÍ si tutorial crecimiento falta o invariantes fallan o integración falla
        $bloquear = false;
        foreach (['A_tutorial', 'N_invariantes', 'M_integracion', 'L_aforos'] as $k) {
            if (($secs[$k]['status'] ?? '') === 'FAIL') {
                $bloquear = true;
            }
        }
        if ($bloquear) {
            return ['veredicto' => 'NO', 'motivos' => $motivos];
        }
        // Aún con PASS técnico, llegadas NO_IMPL + tutorial gap ya marcados FAIL
        return ['veredicto' => 'NO', 'motivos' => $motivos !== [] ? $motivos : ['Gate incompleto: faltan piezas canónicas para experiencia longitudinal.']];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function renderMarkdown(array $data): string
    {
        $r = $data['resumen'] ?? [];
        $neni = $data['neni']['veredicto'] ?? '?';
        $md = "# REPORT PLAYTEST INTEGRAL\n\n";
        $md .= 'Generado: ' . ($data['meta']['ts'] ?? '') . "\n\n";
        $md .= "## Resumen ejecutivo\n\n";
        $md .= '- PASS: ' . ($r['PASS'] ?? 0) . "\n";
        $md .= '- FAIL: ' . ($r['FAIL'] ?? 0) . "\n";
        $md .= '- NO_IMPLEMENTADO: ' . ($r['NO_IMPLEMENTADO'] ?? 0) . "\n";
        $md .= '- PARCIAL: ' . ($r['PARCIAL'] ?? 0) . "\n";
        $md .= '- BLOQUEADO_DECISION: ' . ($r['BLOQUEADO_DECISION'] ?? 0) . "\n\n";
        $md .= "## ¿DEJARÍA YA ENTRAR A NENI AL PLAYTEST?\n\n";
        $md .= '**' . $neni . "**\n\n";
        foreach ($data['neni']['motivos'] ?? [] as $m) {
            $md .= '- ' . $m . "\n";
        }
        $md .= "\n### Mapa de secciones\n\n";
        $map = [
            'A_tutorial' => 'A. Tutorial 3→8',
            'B_llegadas' => 'B. Llegadas',
            'C_autonomia' => 'C. Vida autónoma',
            'D_planes_voluntad' => 'D. Planes/voluntad',
            'E_compatibilidad' => 'E. Compatibilidad/resultados',
            'F_emociones' => 'F. Emociones',
            'G_descubrimientos' => 'G. Descubrimientos',
            'H_buzon_cotilleo' => 'H. Buzón/Cotilleo',
            'I_relaciones' => 'I. Relaciones',
            'J_marchas' => 'J. Marchas',
            'K_economia' => 'K. Economía',
            'L_aforos' => 'L. Aforos',
            'M_integracion' => 'M. Save/load/API/integración',
            'N_invariantes' => 'N. Invariantes',
        ];
        foreach ($map as $k => $title) {
            $sec = $data['secciones'][$k] ?? null;
            if ($sec === null) {
                continue;
            }
            $md .= '- **' . $title . '**: ' . ($sec['status'] ?? '?') . "\n";
        }
        $md .= "\n";
        $vol = $data['secciones']['D_planes_voluntad'] ?? null;
        if (is_array($vol)) {
            $md .= "## Voluntad — tasas por banda\n\n";
            $md .= "```json\n" . json_encode($vol['tasas_por_banda_score'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```\n\n";
            if (!empty($vol['balance'])) {
                $md .= '> ' . $vol['balance'] . "\n\n";
            }
            $md .= "### Ejemplos humanos (voluntad)\n\n";
            foreach (array_slice($vol['ejemplos'] ?? [], 0, 5) as $ej) {
                $md .= '- **' . ($ej['a'] ?? '?') . ' → ' . ($ej['b'] ?? '?') . '** @' . ($ej['hora'] ?? '?')
                    . ': ' . (!empty($ej['rechazada']) ? 'RECHAZA' : 'ACEPTA')
                    . ' (' . ($ej['clase'] ?? '—') . ') — ' . ($ej['mensaje_ui'] ?? '') . "\n";
            }
            $md .= "\n";
        }
        $plan = $data['secciones']['E_compatibilidad'] ?? null;
        if (is_array($plan)) {
            $md .= "## Ejemplos de planes (INPUT → DECISIÓN → RESULTADO)\n\n";
            foreach ($plan['casos'] ?? [] as $c) {
                $in = $c['input'] ?? [];
                $dec = $c['decision'] ?? [];
                $res = $c['resultado'] ?? ($c['after'] ?? []);
                $md .= '### ' . ($c['label'] ?? '?') . "\n";
                $md .= '- INPUT: ' . ($in['a'] ?? '?') . ' + ' . ($in['b'] ?? '?')
                    . ' | ' . ($in['tipo'] ?? '') . ' @ ' . ($in['lugar'] ?? '') . ' h' . ($in['hora'] ?? '') . "\n";
                $md .= '- DECISIÓN: ' . (!empty($dec['rechazada']) ? 'rechaza' : ((isset($dec['ok']) && $dec['ok'] === false) ? 'error' : 'ok/acepta'))
                    . ' | ' . ($dec['clase'] ?? $dec['error'] ?? '—') . ' | ' . ($dec['mensaje_ui'] ?? '') . "\n";
                $md .= '- RESULTADO emo: ' . ($res['emo_a'] ?? '?') . ' / ' . ($res['emo_b'] ?? '?')
                    . ' | social A→B ' . ($res['social_ab'] ?? '?') . ' B→A ' . ($res['social_ba'] ?? '?') . "\n\n";
            }
        }
        foreach ($data['secciones'] ?? [] as $k => $sec) {
            $md .= '## ' . ($map[$k] ?? $k) . ' — detalle JSON — ' . ($sec['status'] ?? '?') . "\n\n";
            $md .= "```json\n" . json_encode($sec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```\n\n";
        }
        if (!empty($data['horizontes'])) {
            $md .= "## Horizontes\n\n";
            $md .= "```json\n" . json_encode($data['horizontes']['agregado'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```\n\n";
            $md .= "### Sims individuales (resumen)\n\n";
            foreach ($data['horizontes']['simulaciones'] ?? [] as $sim) {
                $md .= '- seed `' . ($sim['seed'] ?? '') . '` perfil **' . ($sim['perfil'] ?? '') . '** '
                    . ($sim['dias'] ?? '') . 'd | pop ' . ($sim['poblacion_final'] ?? '?')
                    . ' | planes ' . ($sim['planes'] ?? 0)
                    . ' (acept ' . ($sim['aceptados'] ?? 0) . '/rech ' . ($sim['rechazados'] ?? 0) . ')'
                    . ' | cotilleo ' . ($sim['cotilleo_msgs'] ?? 0)
                    . ' | inv_fallos ' . count($sim['invariantes_fallos'] ?? []) . "\n";
            }
            $md .= "\n";
        }
        if (!empty($data['bugs']) || !empty($data['fixes'])) {
            $md .= "## Bugs / fixes\n\n";
            $md .= '- Bugs: ' . json_encode($data['bugs'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
            $md .= '- Fixes: ' . json_encode($data['fixes'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        }
        return $md;
    }
}
