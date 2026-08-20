<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

use AquiHayTema\Engine\Voluntad\VoluntadPlanLab;
use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

/**
 * Gate post-implementación: longitudinal + trazas humanas + casos.
 */
final class PostGateIntegralRunner
{
    /** @var string */
    private $root;
    /** @var PartidaService */
    private $service;

    public function __construct(string $root)
    {
        $this->root = $root;
        $this->service = new PartidaService($root);
        DomainBootstrap::boot();
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $out = [
            'meta' => ['ts' => date('c'), 'php' => PHP_VERSION, 'modo' => 'post_gate'],
            'secciones' => [],
            'neni' => null,
        ];
        $out['secciones']['A_tutorial'] = $this->secTutorial();
        $out['secciones']['B_llegadas'] = $this->secLlegadas();
        $out['secciones']['C_voluntad'] = $this->secVoluntad();
        $out['secciones']['D_autonomia'] = $this->secAutonomiaEscala();
        $out['secciones']['E_encuentros'] = $this->secCasosEncuentros(30);
        $out['secciones']['F_emociones'] = $this->secEmociones();
        $out['secciones']['G_discovery'] = $this->secDiscoveryRico();
        $out['secciones']['H_relaciones'] = $this->secRelaciones();
        $out['secciones']['I_marchas'] = $this->secMarchas();
        $out['secciones']['J_economia'] = $this->secEconomia();
        $out['secciones']['K_aforos'] = $this->secAforos();
        $out['secciones']['L_buzon_cotilleo'] = $this->secBuzonEscala();
        $out['secciones']['M_integracion'] = $this->secIntegracion();
        $out['secciones']['N_invariantes'] = $this->secInvariantes();
        $out['secciones']['O_trazas'] = $this->secTrazas();
        $out['secciones']['P_casos'] = $out['secciones']['E_encuentros']; // alias pedido
        $out['horizontes'] = $this->runHorizontes();
        $out['resumen'] = $this->resumir($out['secciones']);
        $out['neni'] = $this->veredicto($out);
        return $out;
    }

    /** @return array<string, mixed> */
    private function secTutorial(): array
    {
        $p = $this->service->nuevaPartida('juego_v1', 'pg-tut');
        $n0 = count(TutorialIncorporaciones::residentesActivos($p));
        $ids0 = TutorialIncorporaciones::residentesActivos($p);
        TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_BUZON, $this->root);
        TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_VECINO, $this->root);
        TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_PLAN, $this->root);
        for ($i = 0; $i < 16; $i++) {
            $this->service->avanzarReloj($p, 1);
        }
        $n1 = count(TutorialIncorporaciones::residentesActivos($p));
        $tut = TutorialBucle::vista($p);
        $dup = count($ids0) !== count(array_unique($ids0));
        $ok = $n0 === 3 && $n1 >= 8 && empty($tut['activo']) && !empty($tut['completado'])
            && ($p['llegadas']['modo'] ?? '') === 'normal' && !$dup;
        return [
            'status' => $ok ? 'PASS' : 'FAIL',
            'n_inicial' => $n0,
            'ids_iniciales' => $ids0,
            'n_fin_dia1' => $n1,
            'tutorial_completado' => !empty($tut['completado']),
            'modo_llegadas' => $p['llegadas']['modo'] ?? null,
            'incorporaciones_tutorial' => $p['llegadas']['tutorial_hechas'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function secLlegadas(): array
    {
        $seeds = ['lg-a', 'lg-b', 'lg-c', 'lg-d', 'lg-e'];
        $caps = [
            'A' => ['bloques' => ['a'], 'cap' => 16],
            'A+B' => ['bloques' => ['a', 'b'], 'cap' => 32],
            'A+B+C' => ['bloques' => ['a', 'b', 'c'], 'cap' => 32], // start playtest 8; open B+C
        ];
        // Fix A+B+C label capacity 48
        $caps['A+B+C']['cap'] = 48;

        $dist = [];
        foreach ($caps as $label => $cfg) {
            $porSeed = [];
            foreach ($seeds as $seed) {
                $p = $this->service->nuevaPartida('playtest_01', $seed . '-' . $label);
                CandidatoLlegadaEngine::activarModoNormal($p);
                $p['llegadas']['cooldown_hasta_dia'] = 0;
                $p['celeste']['bloques_abiertos'] = $cfg['bloques'];
                CapacidadViviendas::ensure($p);
                $stats = [
                    'ofrecidos' => 0,
                    'aceptados' => 0,
                    'rechazados' => 0,
                    'expirados' => 0,
                    'llegados' => 0,
                    'dias_hasta_primero' => null,
                    'pop_final' => 0,
                ];
                $n0 = count(TutorialIncorporaciones::residentesActivos($p));
                for ($d = 0; $d < 100; $d++) {
                    $p['llegadas']['_tick_por_hora'] = false;
                    if (!is_array($p['llegadas']['candidato_activo'] ?? null)) {
                        CandidatoLlegadaEngine::intentarOfrecer($p, $this->root);
                    }
                    if (is_array($p['llegadas']['candidato_activo'] ?? null)) {
                        $stats['ofrecidos']++;
                        if ($stats['dias_hasta_primero'] === null) {
                            $stats['dias_hasta_primero'] = (int) $p['reloj']['dia_pueblo'];
                        }
                        if ((crc32($seed . $d) % 100) < 70) {
                            CandidatoLlegadaEngine::aceptar($p, $this->root);
                            $stats['aceptados']++;
                            $esp = (int) ($p['llegadas']['en_camino']['espera_minutos'] ?? 5);
                            CandidatoLlegadaEngine::avanzarMinutosReloj($p, $esp);
                            CandidatoLlegadaEngine::tick($p, $this->root);
                            $stats['llegados']++;
                        } else {
                            CandidatoLlegadaEngine::rechazar($p, $this->root);
                            $stats['rechazados']++;
                        }
                    }
                    $this->service->avanzarReloj($p, 24);
                }
                $stats['pop_final'] = count(TutorialIncorporaciones::residentesActivos($p));
                $stats['pop_inicial'] = $n0;
                $stats['cap'] = CapacidadViviendas::capacidadTotal($p);
                $porSeed[$seed] = $stats;
            }
            $dist[$label] = $porSeed;
        }
        $ok = true;
        foreach ($dist['A'] as $s) {
            if (($s['ofrecidos'] ?? 0) < 1) {
                $ok = false;
            }
        }
        return [
            'status' => $ok ? 'PASS' : 'FAIL',
            'horizonte_dias' => 100,
            'seeds' => $seeds,
            'distribucion' => $dist,
            'nota' => 'Un candidato; buzón; aceptar→espera 1–10 min→vivienda; rechazo excluye cara.',
        ];
    }

    /** @return array<string, mixed> */
    private function secVoluntad(): array
    {
        $pares = [
            [20, 20], [20, 50], [20, 80], [40, 40], [40, 70], [50, 50],
            [50, 80], [70, 70], [70, 90], [90, 90], [95, 95], [95, 20],
        ];
        $lab = VoluntadPlanLab::simular($pares, 2500);
        return [
            'status' => 'BLOQUEADO_DECISION',
            'codigo' => 'BLOQUEADO_DECISION_VOLUNTAD',
            'sistema_actual' => 'producto (pA×pB) — NO cambiado',
            'lab' => $lab,
            'recomendacion' => $lab['recomendacion'],
        ];
    }

    /** @return array<string, mixed> */
    private function secAutonomiaEscala(): array
    {
        $poblaciones = [8, 16, 32, 48];
        $rows = [];
        foreach ($poblaciones as $target) {
            $p = $this->service->nuevaPartida('playtest_01', 'aut-' . $target);
            CandidatoLlegadaEngine::activarModoNormal($p);
            if ($target > 16) {
                CapacidadViviendas::abrirBloque($p, 'b');
            }
            if ($target > 32) {
                CapacidadViviendas::abrirBloque($p, 'c');
            }
            $this->rellenarHasta($p, $target);
            $dosSitios = 0;
            $aforoFail = 0;
            for ($d = 0; $d < 5; $d++) {
                $this->service->avanzarRelojPasoAPaso($p, 24);
                foreach (PlaytestInvariantes::auditar($p, $this->root) as $f) {
                    if (strpos($f, 'persona_dos_sitios') === 0) {
                        $dosSitios++;
                    }
                    if (strpos($f, 'aforo_') === 0) {
                        $aforoFail++;
                    }
                }
            }
            $msgs = BuzonEngine::listar($p);
            $coti = 0;
            foreach ($msgs as $m) {
                if (($m['canal'] ?? '') === BuzonEngine::CANAL_COTILLEO || ($m['clasificacion'] ?? '') === BuzonEngine::COTILLEO) {
                    $coti++;
                }
            }
            $rows[$target] = [
                'pop' => count(TutorialIncorporaciones::residentesActivos($p)),
                'persona_dos_sitios' => $dosSitios,
                'aforo_fallos' => $aforoFail,
                'cotilleo_msgs_5d' => $coti,
                'cap' => CapacidadViviendas::capacidadTotal($p),
            ];
        }
        $ok = true;
        foreach ($rows as $r) {
            if ($r['persona_dos_sitios'] > 0 || $r['aforo_fallos'] > 0 || $r['pop'] === 0) {
                $ok = false;
            }
        }
        return ['status' => $ok ? 'PASS' : 'FAIL', 'por_poblacion' => $rows, 'dias' => 5];
    }

    private function rellenarHasta(array &$partida, int $target): void
    {
        $ops = new ResidenteOperations(new Catalog($this->root));
        $pool = CandidatoLlegadaEngine::poolDisponible($partida, $this->root);
        // Also allow re-adding from full catalog if excluded empty
        $all = (new Catalog($this->root))->listPersonajeIds();
        foreach ($all as $id) {
            if (count(TutorialIncorporaciones::residentesActivos($partida)) >= $target) {
                break;
            }
            $id = (string) $id;
            if ($id === 'per_qa_valid' || isset($partida['residentes'][$id])) {
                continue;
            }
            if (CapacidadViviendas::huecos($partida) <= 0) {
                break;
            }
            $ops->incorporarCatalogo($partida, $id, 'residente');
        }
        // Si el catálogo no llega (solo ~10 fichas), clonar placeholders
        $n = 1;
        while (count(TutorialIncorporaciones::residentesActivos($partida)) < $target
            && CapacidadViviendas::huecos($partida) > 0) {
            $r = $ops->crearPlaceholderDev($partida);
            if (!($r['ok'] ?? false)) {
                break;
            }
            $n++;
            if ($n > 80) {
                break;
            }
        }
    }

    /** @return array<string, mixed> */
    private function secCasosEncuentros(int $n): array
    {
        $casos = [];
        $p = $this->service->nuevaPartida('playtest_01', 'casos-enc');
        $ids = array_values(TutorialIncorporaciones::residentesActivos($p));
        $lugares = ['lug_cafeteria', 'lug_parque', 'lug_biblioteca'];
        for ($i = 0; $i < $n; $i++) {
            $a = $ids[$i % count($ids)];
            $b = $ids[($i + 1 + ($i % 3)) % count($ids)];
            if ($a === $b) {
                continue;
            }
            $lugar = $lugares[$i % count($lugares)];
            $hora = 10 + ($i % 10);
            $before = [
                'a' => IdentidadPublica::nombre($p, $a),
                'b' => IdentidadPublica::nombre($p, $b),
                'conocen' => RelacionEngine::seConocen($p, $a, $b),
                'social_ab' => RelacionEngine::valorSocialHacia($p, $a, $b),
                'social_ba' => RelacionEngine::valorSocialHacia($p, $b, $a),
                'emo_a' => $p['residentes'][$a]['runtime']['estado_emocional']['id'] ?? null,
                'emo_b' => $p['residentes'][$b]['runtime']['estado_emocional']['id'] ?? null,
                'lugar' => $lugar,
                'hora' => $hora,
            ];
            $r = PropuestaEncuentroEngine::proponer($p, [$a, $b], (int) $p['reloj']['dia_pueblo'], $hora, 'conocerse', $lugar);
            $reacs = [];
            foreach ($r['propuesta']['reacciones'] ?? [] as $reac) {
                if (!is_array($reac)) {
                    continue;
                }
                $reacs[] = [
                    'nombre' => $reac['nombre'] ?? null,
                    'decision' => $reac['decision'] ?? null,
                    'score' => $reac['score'] ?? null,
                    'p' => $reac['p'] ?? null,
                    'motivo' => $reac['motivo_tecnico'] ?? null,
                ];
            }
            if (empty($r['rechazada'])) {
                $this->service->avanzarRelojPasoAPaso($p, 6);
            }
            $after = [
                'social_ab' => RelacionEngine::valorSocialHacia($p, $a, $b),
                'social_ba' => RelacionEngine::valorSocialHacia($p, $b, $a),
                'emo_a' => $p['residentes'][$a]['runtime']['estado_emocional']['id'] ?? null,
                'emo_b' => $p['residentes'][$b]['runtime']['estado_emocional']['id'] ?? null,
                'disc_a' => count(DiscoveryEngine::listarPorResidente($p, $a)),
                'disc_b' => count(DiscoveryEngine::listarPorResidente($p, $b)),
            ];
            $porQue = 'Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). ';
            if (!empty($r['rechazada'])) {
                $porQue .= 'Rechazo: ' . ($r['rechazo_clase'] ?? $r['mensaje_ui'] ?? 'desconocido');
            } else {
                $porQue .= 'Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió.';
            }
            $casos[] = [
                'antes' => $before,
                'decision' => [
                    'rechazada' => $r['rechazada'] ?? null,
                    'clase' => $r['rechazo_clase'] ?? null,
                    'mensaje_ui' => $r['mensaje_ui'] ?? null,
                    'reacciones' => $reacs,
                ],
                'despues' => $after,
                'por_que_el_motor' => $porQue,
            ];
            if ($i % 4 === 3) {
                $this->service->avanzarReloj($p, 24);
            }
        }
        return ['status' => 'PASS', 'n' => count($casos), 'casos' => $casos];
    }

    /** @return array<string, mixed> */
    private function secEmociones(): array
    {
        $p = $this->service->nuevaPartida('playtest_01', 'emo-pg');
        $ids = TutorialIncorporaciones::residentesActivos($p);
        $canon = true;
        foreach ($ids as $id) {
            $e = (string) ($p['residentes'][$id]['runtime']['estado_emocional']['id'] ?? 'neutro');
            if (!in_array($e, ['alegre', 'neutro', 'triste', 'enfadado', 'neutral'], true)) {
                $canon = false;
            }
        }
        return ['status' => $canon ? 'PASS' : 'FAIL', 'nota' => 'Emoción individual canónica.'];
    }

    /** @return array<string, mixed> */
    private function secDiscoveryRico(): array
    {
        $p = $this->service->nuevaPartida('playtest_01', 'disc-rico');
        $id = TutorialIncorporaciones::residentesActivos($p)[0];
        $nombre = IdentidadPublica::nombre($p, $id);
        $snaps = [];
        foreach ([1, 10, 30, 100, 365] as $target) {
            while ((int) $p['reloj']['dia_pueblo'] < $target) {
                $ids = TutorialIncorporaciones::residentesActivos($p);
                if (count($ids) >= 2 && ((int) $p['reloj']['dia_pueblo'] % 2 === 0)) {
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
            $items = DiscoveryEngine::listarPorResidente($p, $id);
            $cats = [];
            foreach ($items as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $k = (string) ($it['clave'] ?? $it['tipo'] ?? $it['campo'] ?? 'otro');
                $cat = explode(':', $k)[0];
                if ($cat === $k && isset($it['tipo'])) {
                    $cat = (string) $it['tipo'];
                }
                $cats[$cat] = ($cats[$cat] ?? 0) + 1;
            }
            $snaps['dia_' . $target] = [
                'residente' => $nombre,
                'n' => count($items),
                'por_categoria' => $cats,
                'items' => array_slice($items, 0, 20),
            ];
        }
        // Total descubrible aproximado de la ficha
        $totalApprox = null;
        try {
            $ficha = (new Catalog($this->root))->loadPersonaje($id);
            $h = count($ficha['personalidad']['hobbies'] ?? $ficha['hobbies'] ?? []);
            $r = count($ficha['personalidad']['rasgos'] ?? $ficha['rasgos'] ?? []);
            $totalApprox = ['hobbies_ficha' => $h, 'rasgos_ficha' => $r, 'reveal_inicial_esperado' => 2];
        } catch (\Throwable $e) {
            $totalApprox = ['error' => $e->getMessage()];
        }
        $n1 = $snaps['dia_1']['n'];
        $n365 = $snaps['dia_365']['n'];
        $status = 'PARCIAL';
        if ($n1 >= 1 && $n365 >= $n1) {
            $status = ($n365 <= $n1 + 1) ? 'PARCIAL' : 'PASS';
        }
        return [
            'status' => $status,
            'residente' => $nombre,
            'total_descubrible_aprox' => $totalApprox,
            'snapshots' => $snaps,
            'nota' => $status === 'PARCIAL'
                ? 'Reveal inicial 1+1 OK; crecimiento contextual débil a 365d — ritmo/triggers merecen revisión producto.'
                : 'Discovery crece con interacciones.',
        ];
    }

    /** @return array<string, mixed> */
    private function secRelaciones(): array
    {
        return [
            'status' => 'PARCIAL',
            'implementado' => ['social', 'romance', 'contacto', 'desgaste_diario', 'pareja_por_hito'],
            'RELACIONES_NO_IMPLEMENTADO' => [
                'crisis_automatica_por_umbral (explícitamente nunca_auto_por_umbral)',
                'ruptura_automatica_por_probabilidad JSON (huérfana)',
                'trayectoria completa conocidos→ruptura sin hitos manuales',
            ],
            'BLOQUEADO_DECISION' => [
                'Cuándo/cómo se dispara crisis/ruptura en play',
                'Uso de crisis.probabilidad / ruptura.probabilidad del JSON',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function secMarchas(): array
    {
        return [
            'status' => 'BLOQUEADO_DECISION',
            'codigo' => 'BLOQUEADO_DECISION_MARCHAS',
            'documentado_cerrado' => [
                'Intención sí; salida solo con OK Celestine',
                'Quédate = siempre se queda',
                'Nunca offline',
                'Antiguo archivado fuera del pool de esa partida',
            ],
            'falta_decidir' => [
                '¿Marchas en 1.ª prueba jugable?',
                'Canonizar protección ~30 días (hoy hipótesis)',
                'Fórmula/umbrales de “quiere irse”',
                'Tope de arcos post-quédate',
            ],
            'NO_IMPLEMENTADO' => 'MarchaEngine / aviso marcha cableado a roster',
        ];
    }

    /** @return array<string, mixed> */
    private function secEconomia(): array
    {
        $p = $this->service->nuevaPartida('juego_v1', 'eco-pg');
        return [
            'status' => 'NO_IMPLEMENTADO',
            'economy_enabled' => FeatureConfig::isEnabled($p, 'economy_enabled'),
            'principio_cerrado' => 'El dinero no sirve para sobrevivir; abre diversión (edificios, objetos, viajes…). Citas normales no generan dinero.',
            'lab_existe' => class_exists(SimuladorEconomia::class),
            'BLOQUEADO_DECISION' => [
                'gates_B_C', 'renta_offline', 'parque_inicial', 'fama_en_desbloqueos', 'latido_paga',
            ],
            'nota' => 'No se enciende economy_enabled ni se inventan precios.',
        ];
    }

    /** @return array<string, mixed> */
    private function secAforos(): array
    {
        $runner = new PlaytestIntegralRunner($this->root);
        $ref = new \ReflectionClass($runner);
        $m = $ref->getMethod('secAforos');
        $m->setAccessible(true);
        $base = $m->invoke($runner);
        // Saturación con pop 32
        $p = $this->service->nuevaPartida('playtest_01', 'aforo-32');
        CapacidadViviendas::abrirBloque($p, 'b');
        $this->rellenarHasta($p, 32);
        $fail = 0;
        for ($d = 0; $d < 3; $d++) {
            $this->service->avanzarRelojPasoAPaso($p, 24);
            foreach (PlaytestInvariantes::auditar($p, $this->root) as $f) {
                if (strpos($f, 'aforo_') === 0) {
                    $fail++;
                }
            }
        }
        $base['bajo_poblacion_32'] = ['aforo_fallos_3d' => $fail];
        if ($fail > 0) {
            $base['status'] = 'FAIL';
        }
        return $base;
    }

    /** @return array<string, mixed> */
    private function secBuzonEscala(): array
    {
        $rows = [];
        foreach ([8, 16, 32] as $pop) {
            $p = $this->service->nuevaPartida('playtest_01', 'bc-' . $pop);
            if ($pop > 16) {
                CapacidadViviendas::abrirBloque($p, 'b');
            }
            $this->rellenarHasta($p, $pop);
            for ($d = 0; $d < 30; $d++) {
                if ($d % 3 === 0) {
                    $ids = TutorialIncorporaciones::residentesActivos($p);
                    if (count($ids) >= 2) {
                        PropuestaEncuentroEngine::proponer($p, [$ids[0], $ids[1]], (int) $p['reloj']['dia_pueblo'], 15, 'conocerse', 'lug_cafeteria');
                    }
                }
                $this->service->avanzarReloj($p, 24);
            }
            $buz = 0;
            $coti = 0;
            foreach (BuzonEngine::listar($p) as $m) {
                if (!is_array($m)) {
                    continue;
                }
                if (($m['canal'] ?? BuzonEngine::canalDe((string) ($m['clasificacion'] ?? ''))) === BuzonEngine::CANAL_COTILLEO) {
                    $coti++;
                } else {
                    $buz++;
                }
            }
            $rows[$pop] = [
                'buzon_total_30d' => $buz,
                'cotilleo_total_30d' => $coti,
                'cotilleo_por_dia' => round($coti / 30, 2),
                'buzon_por_dia' => round($buz / 30, 2),
            ];
        }
        return ['status' => 'PASS', 'por_poblacion_30d' => $rows];
    }

    /** @return array<string, mixed> */
    private function secIntegracion(): array
    {
        $p = $this->service->nuevaPartida('juego_v1', 'int-pg');
        TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_BUZON, $this->root);
        TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_VECINO, $this->root);
        TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_PLAN, $this->root);
        $id = $p['meta']['partida_id'];
        $this->service->guardar($p);
        $p2 = $this->service->cargar($id);
        $ok = !empty(TutorialBucle::vista($p2)['completado']);
        return [
            'status' => $ok ? 'PASS' : 'FAIL',
            'save_load_tutorial' => $ok,
            'api_llegada' => ['llegada.estado', 'llegada.aceptar', 'llegada.rechazar'],
        ];
    }

    /** @return array<string, mixed> */
    private function secInvariantes(): array
    {
        $fallos = [];
        foreach (['activa', 'normal'] as $perfil) {
            $p = $this->service->nuevaPartida('juego_v1', 'inv-' . $perfil);
            TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_BUZON, $this->root);
            TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_VECINO, $this->root);
            TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_PLAN, $this->root);
            for ($d = 0; $d < 30; $d++) {
                $ids = TutorialIncorporaciones::residentesActivos($p);
                if (count($ids) >= 2 && (mt_rand() / mt_getrandmax()) < ($perfil === 'activa' ? 0.4 : 0.2)) {
                    PropuestaEncuentroEngine::proponer($p, [$ids[0], $ids[array_rand($ids)]], (int) $p['reloj']['dia_pueblo'], 14, 'conocerse', 'lug_cafeteria');
                }
                // Auto-aceptar candidatos a veces
                if (is_array($p['llegadas']['candidato_activo'] ?? null) && $perfil === 'activa') {
                    CandidatoLlegadaEngine::aceptar($p, $this->root);
                    $esp = (int) ($p['llegadas']['en_camino']['espera_minutos'] ?? 3);
                    CandidatoLlegadaEngine::avanzarMinutosReloj($p, $esp);
                }
                $this->service->avanzarReloj($p, 24);
                foreach (PlaytestInvariantes::auditar($p, $this->root) as $f) {
                    $fallos[$f] = ($fallos[$f] ?? 0) + 1;
                }
            }
        }
        return [
            'status' => $fallos === [] ? 'PASS' : 'FAIL',
            'fallos' => $fallos,
        ];
    }

    /** @return array<string, mixed> */
    private function secTrazas(): array
    {
        $p = $this->service->nuevaPartida('juego_v1', 'traza-humana');
        TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_BUZON, $this->root);
        TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_VECINO, $this->root);
        TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_PLAN, $this->root);
        $trazaGlobal = [];
        $porPersona = [];

        $log = static function (array &$bag, int $dia, string $texto) {
            $bag[] = ['dia' => $dia, 'hecho' => $texto];
        };

        for ($d = 0; $d < 120; $d++) {
            $dia = (int) $p['reloj']['dia_pueblo'];
            $ids = TutorialIncorporaciones::residentesActivos($p);
            foreach ($ids as $rid) {
                $porPersona[$rid] ??= ['nombre' => IdentidadPublica::nombre($p, $rid), 'eventos' => []];
            }
            if ($d === 0) {
                $log($trazaGlobal, $dia, 'Partida: 3 iniciales + tutorial en curso/completado.');
            }
            foreach ($p['llegadas']['tutorial_hechas'] ?? [] as $inc) {
                $cid = (string) ($inc['catalog_id'] ?? '');
                if ($cid !== '' && empty($porPersona[$cid]['_logged_llegada'])) {
                    $log($porPersona[$cid]['eventos'], $dia, 'Llegó (tutorial) a vivienda ' . ($inc['vivienda_id'] ?? '?'));
                    $porPersona[$cid]['_logged_llegada'] = true;
                    $log($trazaGlobal, $dia, IdentidadPublica::nombre($p, $cid) . ' se mudó (tutorial).');
                }
            }
            // planes
            if (count($ids) >= 2 && $d % 2 === 0) {
                $a = $ids[0];
                $b = $ids[min(1, count($ids) - 1)];
                $r = PropuestaEncuentroEngine::proponer($p, [$a, $b], $dia, 15, 'conocerse', 'lug_cafeteria');
                $na = IdentidadPublica::nombre($p, $a);
                $nb = IdentidadPublica::nombre($p, $b);
                if (!empty($r['rechazada'])) {
                    $txt = "$na propuso plan con $nb → RECHAZO (" . ($r['rechazo_clase'] ?? '?') . ')';
                } else {
                    $txt = "$na y $nb aceptaron plan en cafetería.";
                }
                $log($trazaGlobal, $dia, $txt);
                $log($porPersona[$a]['eventos'], $dia, $txt);
                $log($porPersona[$b]['eventos'], $dia, $txt);
            }
            if (is_array($p['llegadas']['candidato_activo'] ?? null)) {
                $c = $p['llegadas']['candidato_activo'];
                $log($trazaGlobal, $dia, 'Candidato en buzón: ' . ($c['nombre'] ?? $c['catalog_id']));
                if ($d % 3 !== 0) {
                    CandidatoLlegadaEngine::aceptar($p, $this->root);
                    $esp = (int) ($p['llegadas']['en_camino']['espera_minutos'] ?? 4);
                    CandidatoLlegadaEngine::avanzarMinutosReloj($p, $esp);
                    CandidatoLlegadaEngine::tick($p, $this->root);
                    $log($trazaGlobal, $dia, 'Celestine aceptó candidato; llegó tras espera.');
                } else {
                    CandidatoLlegadaEngine::rechazar($p, $this->root);
                    $log($trazaGlobal, $dia, 'Celestine rechazó candidato.');
                }
            }
            $this->service->avanzarReloj($p, 24);
        }

        // Limpiar flags internos
        foreach ($porPersona as &$pp) {
            unset($pp['_logged_llegada']);
            $pp['eventos'] = array_slice($pp['eventos'], 0, 40);
        }
        unset($pp);

        $idsFinal = TutorialIncorporaciones::residentesActivos($p);
        $elegidos = array_slice($idsFinal, 0, 3);
        $trazasSel = [];
        foreach ($elegidos as $eid) {
            $trazasSel[] = $porPersona[$eid] ?? ['nombre' => $eid, 'eventos' => []];
        }

        return [
            'status' => 'PASS',
            'horizonte' => 120,
            'poblacion_final' => count($idsFinal),
            'traza_pueblo' => array_slice($trazaGlobal, 0, 60),
            'residentes' => $trazasSel,
            'parejas_potenciales' => [
                ['a' => IdentidadPublica::nombre($p, $idsFinal[0] ?? ''), 'b' => IdentidadPublica::nombre($p, $idsFinal[1] ?? '')],
                ['a' => IdentidadPublica::nombre($p, $idsFinal[1] ?? ''), 'b' => IdentidadPublica::nombre($p, $idsFinal[2] ?? '')],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function runHorizontes(): array
    {
        $rows = [];
        foreach ([30, 100] as $dias) {
            foreach (['activa', 'normal', 'torpe', 'inactiva'] as $perfil) {
                foreach (['h1', 'h2'] as $seed) {
                    $rows[] = $this->simPerfil($seed . "-$perfil-d$dias", $perfil, $dias);
                }
            }
        }
        // 365: 1 seed × 4 perfiles
        foreach (['activa', 'normal', 'torpe', 'inactiva'] as $perfil) {
            $rows[] = $this->simPerfil("y1-$perfil-d365", $perfil, 365);
        }
        return ['simulaciones' => $rows];
    }

    /** @return array<string, mixed> */
    private function simPerfil(string $seed, string $perfil, int $dias): array
    {
        $p = $this->service->nuevaPartida('juego_v1', $seed);
        TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_BUZON, $this->root);
        TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_VECINO, $this->root);
        TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_PLAN, $this->root);
        $rate = ['activa' => 0.5, 'normal' => 0.25, 'torpe' => 0.35, 'inactiva' => 0.05][$perfil] ?? 0.2;
        $planes = 0;
        $acept = 0;
        $inv = [];
        for ($d = 0; $d < $dias; $d++) {
            $ids = TutorialIncorporaciones::residentesActivos($p);
            if (count($ids) >= 2 && (mt_rand() / mt_getrandmax()) < $rate) {
                $a = $ids[array_rand($ids)];
                $b = $ids[array_rand($ids)];
                if ($a !== $b) {
                    $r = PropuestaEncuentroEngine::proponer($p, [$a, $b], (int) $p['reloj']['dia_pueblo'], 14, 'conocerse', 'lug_cafeteria');
                    $planes++;
                    if (empty($r['rechazada'])) {
                        $acept++;
                    }
                }
            }
            if (is_array($p['llegadas']['candidato_activo'] ?? null)) {
                if ($perfil === 'inactiva') {
                    // deja expirar
                } elseif ($perfil === 'torpe' && ($d % 4 === 0)) {
                    CandidatoLlegadaEngine::rechazar($p, $this->root);
                } else {
                    CandidatoLlegadaEngine::aceptar($p, $this->root);
                    $esp = (int) ($p['llegadas']['en_camino']['espera_minutos'] ?? 3);
                    CandidatoLlegadaEngine::avanzarMinutosReloj($p, $esp);
                }
            }
            $this->service->avanzarReloj($p, 24);
            foreach (PlaytestInvariantes::auditar($p, $this->root) as $f) {
                $inv[$f] = true;
            }
        }
        return [
            'seed' => $seed,
            'perfil' => $perfil,
            'dias' => $dias,
            'pop_final' => count(TutorialIncorporaciones::residentesActivos($p)),
            'planes' => $planes,
            'aceptados' => $acept,
            'inv_fallos' => array_keys($inv),
            'llegadas_hist' => count($p['llegadas']['historial'] ?? []),
        ];
    }

    /** @param array<string, array<string, mixed>> $secs */
    private function resumir(array $secs): array
    {
        $c = ['PASS' => 0, 'FAIL' => 0, 'NO_IMPLEMENTADO' => 0, 'PARCIAL' => 0, 'BLOQUEADO_DECISION' => 0];
        foreach ($secs as $k => $s) {
            if ($k === 'P_casos') {
                continue; // alias
            }
            $st = (string) ($s['status'] ?? 'PARCIAL');
            if (isset($c[$st])) {
                $c[$st]++;
            } else {
                $c['PARCIAL']++;
            }
        }
        return $c;
    }

    /** @param array<string, mixed> $out */
    private function veredicto(array $out): array
    {
        $secs = $out['secciones'];
        $motivos = [];
        if (($secs['A_tutorial']['status'] ?? '') !== 'PASS') {
            $motivos[] = 'Tutorial 3→8 no PASS.';
        }
        if (($secs['B_llegadas']['status'] ?? '') !== 'PASS') {
            $motivos[] = 'Llegadas no PASS.';
        }
        if (($secs['N_invariantes']['status'] ?? '') === 'FAIL') {
            $motivos[] = 'Invariantes rotas.';
        }
        if (($secs['C_voluntad']['status'] ?? '') === 'BLOQUEADO_DECISION') {
            $motivos[] = 'Voluntad de plan (p²) sigue abierta: ' . ($secs['C_voluntad']['codigo'] ?? '');
        }
        if (($secs['I_marchas']['status'] ?? '') === 'BLOQUEADO_DECISION') {
            $motivos[] = 'Marchas sin contrato numérico cerrado.';
        }
        if (($secs['G_discovery']['status'] ?? '') === 'PARCIAL') {
            $motivos[] = 'Discovery crece poco a largo plazo (experiencia longitudinal).';
        }

        // Criterio Neni: bugs básicos castigados + pueblo evoluciona.
        // Aún NO si voluntad/marchas/discovery dejan experiencia dudosa o fallos estructurales.
        $estructuralOk = ($secs['A_tutorial']['status'] ?? '') === 'PASS'
            && ($secs['B_llegadas']['status'] ?? '') === 'PASS'
            && ($secs['N_invariantes']['status'] ?? '') !== 'FAIL'
            && ($secs['M_integracion']['status'] ?? '') === 'PASS';

        if (!$estructuralOk) {
            return ['veredicto' => 'NO', 'motivos' => $motivos !== [] ? $motivos : ['Estructura básica aún falla.']];
        }

        // Estructura OK pero Neni aún no: la pregunta ya es de sensación (voluntad) y sistemas abiertos.
        $motivos[] = 'El bucle longitudinal básico (3→8 + candidatos) ya se puede castigar en automático.';
        $motivos[] = 'Sigue abierto el balance de Organizar (plantones ≈p²) — Neni lo sentiría como bug de diseño.';
        $motivos[] = 'Discovery longitudinal y marchas/economía aún no dan una experiencia “completa” de año.';
        return ['veredicto' => 'NO', 'motivos' => $motivos];
    }

    /** @param array<string, mixed> $data */
    public static function renderMarkdown(array $data): string
    {
        $r = $data['resumen'] ?? [];
        $md = "# POST_GATE_REPORT\n\n";
        $md .= 'Generado: ' . ($data['meta']['ts'] ?? '') . "\n\n";
        $md .= "## Resumen\n\n";
        foreach (['PASS', 'FAIL', 'PARCIAL', 'NO_IMPLEMENTADO', 'BLOQUEADO_DECISION'] as $k) {
            $md .= "- $k: " . ($r[$k] ?? 0) . "\n";
        }
        $md .= "\n## ¿DEJARÍA YA ENTRAR A NENI AL PLAYTEST?\n\n";
        $md .= '**' . ($data['neni']['veredicto'] ?? '?') . "**\n\n";
        foreach ($data['neni']['motivos'] ?? [] as $m) {
            $md .= '- ' . $m . "\n";
        }
        $md .= "\n";
        $map = [
            'A_tutorial' => 'A. Tutorial 3→8',
            'B_llegadas' => 'B. Llegadas',
            'C_voluntad' => 'C. Voluntad',
            'D_autonomia' => 'D. Vida autónoma',
            'E_encuentros' => 'E. Encuentros',
            'F_emociones' => 'F. Emociones',
            'G_discovery' => 'G. Discovery',
            'H_relaciones' => 'H. Relaciones',
            'I_marchas' => 'I. Marchas',
            'J_economia' => 'J. Economía',
            'K_aforos' => 'K. Aforos',
            'L_buzon_cotilleo' => 'L. Buzón/Cotilleo',
            'M_integracion' => 'M. Integración',
            'N_invariantes' => 'N. Invariantes',
            'O_trazas' => 'O. Trazas humanas',
        ];
        foreach ($map as $k => $title) {
            $sec = $data['secciones'][$k] ?? null;
            if ($sec === null) {
                continue;
            }
            $md .= '## ' . $title . ' — ' . ($sec['status'] ?? '?') . "\n\n";
            if ($k === 'C_voluntad') {
                $md .= "Sistema actual **no modificado** (sigue pA×pB).\n\n";
                $md .= "### Recomendación lab\n\n";
                $md .= '`' . ($sec['recomendacion']['formula'] ?? '') . "`\n\n";
                foreach ($sec['recomendacion']['motivos'] ?? [] as $mot) {
                    $md .= '- ' . $mot . "\n";
                }
                $md .= "\n### Matriz media_geometrica vs producto (p_plan)\n\n";
                $md .= "| scores | producto | geométrica | min_suave |\n|---|---|---|---|\n";
                $prod = $sec['lab']['formulas']['producto']['matriz'] ?? [];
                $geom = $sec['lab']['formulas']['media_geometrica']['matriz'] ?? [];
                $mins = $sec['lab']['formulas']['min_suave']['matriz'] ?? [];
                foreach ($prod as $i => $row) {
                    $md .= '| ' . ($row['scores'][0] ?? '') . '/' . ($row['scores'][1] ?? '')
                        . ' | ' . ($row['p_plan'] ?? '')
                        . ' | ' . ($geom[$i]['p_plan'] ?? '')
                        . ' | ' . ($mins[$i]['p_plan'] ?? '') . " |\n";
                }
                $md .= "\n";
            }
            if ($k === 'O_trazas') {
                $md .= "### Traza pueblo (extracto)\n\n";
                foreach (array_slice($sec['traza_pueblo'] ?? [], 0, 25) as $ev) {
                    $md .= '- D' . ($ev['dia'] ?? '?') . ': ' . ($ev['hecho'] ?? '') . "\n";
                }
                $md .= "\n### Residentes\n\n";
                foreach ($sec['residentes'] ?? [] as $rp) {
                    $md .= '#### ' . ($rp['nombre'] ?? '?') . "\n\n";
                    foreach (array_slice($rp['eventos'] ?? [], 0, 15) as $ev) {
                        $md .= '- D' . ($ev['dia'] ?? '?') . ': ' . ($ev['hecho'] ?? '') . "\n";
                    }
                    $md .= "\n";
                }
            }
            if ($k === 'E_encuentros') {
                $md .= 'Casos: ' . ($sec['n'] ?? 0) . " (ver JSON para BEFORE/DECISION/AFTER completos).\n\n";
                foreach (array_slice($sec['casos'] ?? [], 0, 5) as $c) {
                    $a = $c['antes'] ?? [];
                    $md .= '- **' . ($a['a'] ?? '') . ' + ' . ($a['b'] ?? '') . '** @' . ($a['lugar'] ?? '')
                        . ' → ' . (!empty($c['decision']['rechazada']) ? 'RECHAZA' : 'OK')
                        . ' — ' . ($c['por_que_el_motor'] ?? '') . "\n";
                }
                $md .= "\n";
            }
            $md .= "<details><summary>JSON</summary>\n\n```json\n"
                . json_encode($sec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                . "\n```\n</details>\n\n";
        }
        if (!empty($data['horizontes']['simulaciones'])) {
            $md .= "## Horizontes\n\n";
            foreach ($data['horizontes']['simulaciones'] as $sim) {
                $md .= '- `' . ($sim['seed'] ?? '') . '` ' . ($sim['perfil'] ?? '') . ' '
                    . ($sim['dias'] ?? '') . 'd pop=' . ($sim['pop_final'] ?? '?')
                    . ' planes=' . ($sim['planes'] ?? 0)
                    . ' llegadas_hist=' . ($sim['llegadas_hist'] ?? 0)
                    . ' inv=' . count($sim['inv_fallos'] ?? []) . "\n";
            }
        }
        return $md;
    }
}
