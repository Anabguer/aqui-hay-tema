<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Laboratorio de pueblo vivo. Mide; no canoniza. No escribe partidas.
 */
final class SimuladorPuebloVivo
{
    /**
     * @param list<int> $tamanos
     * @param list<int> $horizontes
     * @return array<string, mixed>
     */
    public static function ejecutar(
        string $projectRoot,
        array $tamanos = [3, 6, 16, 32],
        array $horizontes = [30, 100],
        int $pueblosPorCelda = 2,
        string $seedBase = 'lab-vivo',
        ?int $largoDias = 200
    ): array {
        $catalog = new Catalog($projectRoot);
        $cal = CalibracionConfig::load($projectRoot);
        $out = [
            '_provisional' => true,
            '_nota' => 'Métricas para revisión. No hay una cifra correcta única.',
            'pueblos_por_celda' => $pueblosPorCelda,
            'por_tamano' => [],
        ];
        foreach ($tamanos as $n) {
            $out['por_tamano'][$n] = [];
            foreach ($horizontes as $dias) {
                $out['por_tamano'][$n][$dias] = self::celda($catalog, $cal, (int) $n, (int) $dias, $pueblosPorCelda, $seedBase);
            }
            if ($largoDias !== null && $n === 16) {
                $out['por_tamano'][$n]['largo_' . $largoDias] = self::celda($catalog, $cal, (int) $n, (int) $largoDias, 1, $seedBase . '-largo');
            }
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function celda(Catalog $catalog, array $cal, int $n, int $dias, int $pueblos, string $seedBase): array
    {
        $acc = [];
        for ($p = 0; $p < $pueblos; $p++) {
            $rng = new RngService($seedBase . '-' . $n . '-' . $dias . '-' . $p);
            $partida = self::pueblo($n, $rng, $cal, $catalog);
            $m = self::correr($partida, $catalog, $cal, $rng, $dias);
            $acc[] = $m;
        }
        return self::promediar($acc, $n, $dias, $pueblos);
    }

    /**
     * @return array<string, mixed>
     */
    public static function pueblo(int $n, RngService $rng, array $cal, Catalog $catalog): array
    {
        $store = $catalog->store();
        $hobbies = GeneradorResidente::idsGenerables($store, 'hobbies');
        $rasgos = GeneradorResidente::idsGenerables($store, 'rasgos');
        $partida = [
            'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 8],
            'rng' => ['seed' => 'lab', 'state' => $rng->getState()],
            'residentes' => [],
            'relaciones_sociales' => [],
            'relaciones_romanticas' => [],
            'relaciones_conflicto' => [],
            'parentesco' => [],
            'bitacora_relaciones' => [],
            'buzon' => [],
            'memoria_eventos' => [],
            'historial_relaciones' => [],
            'encuentros' => [],
            'celeste' => [
                'lugares_desbloqueados' => ['lug_cafeteria', 'lug_parque', 'lug_biblioteca', 'lug_bingo'],
            ],
            'lab_vida_activa' => true,
            'lab_deltas_reales' => true,
            'npc_autonomo' => ['planes_pendientes' => [], 'historial_eventos' => []],
        ];
        for ($i = 0; $i < $n; $i++) {
            $id = 'lab_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $hob = $rng->pickUnique($hobbies !== [] ? $hobbies : ['pasear'], 3);
            $ras = $rng->pickUnique($rasgos !== [] ? $rasgos : ['amable'], 3);
            $partida['residentes'][$id] = [
                'catalog_id' => $id,
                'presencia' => 'residente',
                'runtime' => [
                    'ocupacion' => $rng->nextInt(0, 4) > 0 ? 'empleado' : 'desempleado',
                    'ultimo_protagonismo_dia' => 0,
                    'perfil_partida' => [
                        'edad' => $rng->nextInt(22, 72),
                        'hobbies' => array_values($hob),
                        'rasgos' => array_values($ras),
                        'preferencias' => [
                            'personalidad_pos' => [],
                            'personalidad_neg' => [],
                            'visual_pos' => [],
                            'visual_neg' => [],
                            'hobbies_pos' => [],
                            'hobbies_neg' => $rng->nextInt(0, 4) === 0 && $hobbies !== []
                                ? [$hobbies[$rng->nextInt(0, count($hobbies) - 1)]]
                                : [],
                        ],
                    ],
                    'estado_emocional' => EstadoEmocional::estructura('neutro'),
                ],
            ];
            DiscoveryReveal::alIncorporar($partida, $id, $cal);
        }
        SchemaFields::ensure($partida);
        RelacionGrafo::asegurarTodos($partida, $cal);
        $ids = array_keys($partida['residentes']);
        if ($n >= 2 && $rng->nextInt(0, 11) === 0) {
            $partida['parentesco'][] = [
                'persona_a' => $ids[0],
                'persona_b' => $ids[1],
                'tipo' => 'hermano',
            ];
        }
        $rng->persistToPartida($partida);
        return $partida;
    }

    /**
     * @return array<string, mixed>
     */
    public static function correr(array &$partida, Catalog $catalog, array $cal, RngService $rng, int $dias): array
    {
        $m = self::metricasVacias();
        $parejaInicio = [];
        // Tracking por residente para métricas de aislamiento
        $idsRes = array_keys($partida['residentes']);
        $ultimaActividad = array_fill_keys($idsRes, 0);   // 0 = nunca
        $ultimoContacto  = array_fill_keys($idsRes, 0);   // 0 = nunca
        for ($d = 1; $d <= $dias; $d++) {
            $partida['reloj']['dia_pueblo'] = $d;
            $partida['reloj']['hora_actual'] = 8;
            MotorVidaDiaria::alComenzarDia($partida, $cal, $rng);
            $vidaHoy = 0;
            $autoHoy = 0;
            $msgHoy = count($partida['buzon'] ?? []);
            $emoCounts = ['neutro' => 0, 'alegre' => 0, 'triste' => 0, 'enfadado' => 0];
            for ($h = 9; $h <= 22; $h++) {
                $partida['reloj']['hora_actual'] = $h;
                $tick = MotorVidaDiaria::tickHora($partida, $catalog, $cal, $rng);
                if (is_array($tick['vida'] ?? null) && isset($tick['vida']['evento'])) {
                    $vidaHoy++;
                    $m['eventos_vida']++;
                    $fam = (string) ($tick['vida']['evento'] ?? '');
                    $m['familias'][$fam] = (int) ($m['familias'][$fam] ?? 0) + 1;
                    // Actualizar actividad del protagonista (via resultado si disponible)
                    $resVida = $tick['vida']['resultado'] ?? [];
                    $prot = (string) ($resVida['protagonista'] ?? $tick['vida']['protagonista'] ?? '');
                    if ($prot !== '' && isset($ultimaActividad[$prot])) {
                        $ultimaActividad[$prot] = $d;
                    }
                }
                if (is_array($tick['autonomo'] ?? null) && isset($tick['autonomo']['quien'])) {
                    $autoHoy++;
                    $m['salidas_individuales']++;
                    $lug = (string) ($tick['autonomo']['lugar'] ?? '');
                    $m['visitas_edificio'][$lug] = (int) ($m['visitas_edificio'][$lug] ?? 0) + 1;
                    // Salida autónoma = actividad del residente
                    $quien = (string) ($tick['autonomo']['quien'] ?? '');
                    if ($quien !== '' && isset($ultimaActividad[$quien])) {
                        $ultimaActividad[$quien] = $d;
                    }
                }
                $cas = is_array($tick['casuales'] ?? null) ? $tick['casuales'] : [];
                $m['interacciones_casuales'] += count($cas);
                if (count($cas) > 0) {
                    $m['coincidencias']++;
                }
                foreach ($cas as $c) {
                    if (!empty($c['flechazo']['ok'])) {
                        $m['flechazos']++;
                    }
                    // Actualizar último contacto de los participantes (interaccion casual usa 'a' y 'b')
                    foreach (['a', 'b'] as $side) {
                        $pid = (string) ($c[$side] ?? '');
                        if ($pid !== '' && isset($ultimoContacto[$pid])) {
                            $ultimoContacto[$pid] = $d;
                        }
                    }
                }
                EncuentroLifecycle::sincronizarConReloj($partida, null, $catalog);
            }
            $emoSvc = new EmotionalStateService(
                new VisualPackStore($catalog->getRoot()),
                $catalog->store(),
                null
            );
            $emoSvc->expirarVencidos($partida);
            RelacionDesgaste::alCerrarDia($partida, $cal);
            $m['eventos_vida_por_dia'][] = $vidaHoy;
            $m['autonomos_por_dia'][] = $autoHoy;
            $m['mensajes_por_dia'][] = count($partida['buzon'] ?? []) - $msgHoy;
            foreach ($partida['residentes'] as $res) {
                $eid = (string) ($res['runtime']['estado_emocional']['id'] ?? 'neutro');
                if (!isset($emoCounts[$eid])) {
                    $emoCounts[$eid] = 0;
                }
                $emoCounts[$eid]++;
            }
            $m['emo_neutro'] += $emoCounts['neutro'];
            $m['emo_total'] += array_sum($emoCounts);
            $olvidados = 0;
            foreach ($partida['residentes'] as $res) {
                $ult = (int) ($res['runtime']['ultimo_protagonismo_dia'] ?? 0);
                if ($ult === 0 || ($d - $ult) >= 7) {
                    $olvidados++;
                }
            }
            $m['olvidados_dia'][] = $olvidados;

            foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
                $est = (string) ($rel['estado_pareja'] ?? '');
                $key = (string) ($rel['id'] ?? '');
                if ($est === ParejaEngine::PAREJA || $est === ParejaEngine::CRISIS) {
                    if (!isset($parejaInicio[$key])) {
                        $parejaInicio[$key] = $d;
                        $m['parejas_nuevas']++;
                    }
                    if ($est === ParejaEngine::CRISIS) {
                        $m['crisis_obs']++;
                    }
                }
            }
        }

        $m['rechazos'] = count($partida['rechazos_propuesta'] ?? []);
        $m['hitos'] = [];
        foreach ($partida['bitacora_relaciones'] ?? [] as $h) {
            $t = (string) ($h['tipo'] ?? '');
            $m['hitos'][$t] = (int) ($m['hitos'][$t] ?? 0) + 1;
        }
        $m['flechazos'] = max($m['flechazos'], (int) ($m['hitos'][RelacionBitacora::FLECHAZO] ?? 0));
        $m['rupturas'] = (int) ($m['hitos'][RelacionBitacora::RUPTURA] ?? 0);
        $m['reconciliaciones'] = (int) ($m['hitos'][RelacionBitacora::VUELTA] ?? 0) + (int) ($m['hitos'][RelacionBitacora::RECONCILIACION] ?? 0);
        $m['amistades'] = 0;
        $m['mejores_amigos'] = 0;
        $m['social_evapora'] = 0;
        $m['social_clava'] = 0;
        $bandaCount = ['conocido' => 0, 'cae_bien' => 0, 'amigo' => 0, 'buen_amigo' => 0, 'mejor_amigo' => 0, 'cae_mal' => 0];
        foreach ($partida['relaciones_sociales'] ?? [] as $rel) {
            if (empty($rel['conocidos'])) {
                continue;
            }
            $va = (int) ($rel['a_hacia_b']['valor'] ?? 0);
            $vb = (int) ($rel['b_hacia_a']['valor'] ?? 0);
            $ba = RelacionBandas::social($va, true, $cal);
            $bb = RelacionBandas::social($vb, true, $cal);
            if ($ba === 'amigo' || $bb === 'amigo' || $ba === 'buen_amigo' || $bb === 'buen_amigo') {
                $m['amistades']++;
            }
            if ($ba === 'mejor_amigo' || $bb === 'mejor_amigo') {
                $m['mejores_amigos']++;
            }
            foreach ([$ba, $bb] as $banda) {
                if (isset($bandaCount[$banda])) {
                    $bandaCount[$banda]++;
                }
            }
        }
        $m['bandas_sociales'] = $bandaCount;
        $m['parejas_activas_final'] = 0;
        $durs = [];
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            $est = (string) ($rel['estado_pareja'] ?? '');
            if ($est === ParejaEngine::PAREJA || $est === ParejaEngine::CRISIS) {
                $m['parejas_activas_final']++;
                $ini = (int) ($rel['fecha_inicio']['dia'] ?? 1);
                $durs[] = max(1, $dias - $ini + 1);
            }
            foreach ($rel['historial_parejas'] ?? [] as $hp) {
                if (isset($hp['inicio']['dia'], $hp['fin']['dia'])) {
                    $durs[] = max(1, (int) $hp['fin']['dia'] - (int) $hp['inicio']['dia'] + 1);
                }
            }
        }
        $m['duracion_media_pareja'] = $durs === [] ? 0 : round(array_sum($durs) / count($durs), 2);
        $m['mensajes_total'] = count($partida['buzon'] ?? []);
        $m['buzon_cat'] = ['importante' => 0, 'oportunidad' => 0, 'peticion' => 0, 'cotilleo' => 0];
        foreach ($partida['buzon'] ?? [] as $msg) {
            $c = (string) ($msg['clasificacion'] ?? 'cotilleo');
            if (isset($m['buzon_cat'][$c])) {
                $m['buzon_cat'][$c]++;
            }
        }
        $m['pct_tiempo_neutro'] = $m['emo_total'] > 0 ? round(100 * $m['emo_neutro'] / $m['emo_total'], 1) : 0;
        $proy10 = RelacionDesgaste::proyectarValor(10, 12, $cal);
        $proy60 = RelacionDesgaste::proyectarValor(60, 30, $cal);
        $proy90 = RelacionDesgaste::proyectarValor(90, 30, $cal);
        $m['desgaste_lab'] = ['v10_12d' => $proy10, 'v60_30d' => $proy60, 'v90_30d' => $proy90];

        // Métricas de aislamiento
        $sinActividad7 = 0;
        $sinInteraccion14 = 0;
        $diasDesdeContacto = [];
        $nuncaContacto = 0;
        foreach ($idsRes as $rid) {
            $ultAct = $ultimaActividad[$rid] ?? 0;
            if ($ultAct === 0 || ($dias - $ultAct) >= 7) {
                $sinActividad7++;
            }
            $ultCon = $ultimoContacto[$rid] ?? 0;
            if ($ultCon === 0) {
                $nuncaContacto++;
                // No convertir en 0: representamos con null en distribución
            } else {
                $diasDesdeContacto[] = $dias - $ultCon;
            }
        }
        $m['sin_actividad_7dias'] = $sinActividad7;
        $m['sin_interaccion_14dias'] = count(array_filter($idsRes, static function ($rid) use ($ultimoContacto, $dias) {
            $u = $ultimoContacto[$rid] ?? 0;
            return $u === 0 || ($dias - $u) >= 14;
        }));
        sort($diasDesdeContacto);
        $cnt = count($diasDesdeContacto);
        $m['dias_desde_ultimo_contacto'] = [
            'nunca_tuvieron_contacto' => $nuncaContacto,
            'n_con_contacto' => $cnt,
            'media'  => $cnt > 0 ? round(array_sum($diasDesdeContacto) / $cnt, 1) : null,
            'mediana'=> $cnt > 0 ? (float) $diasDesdeContacto[(int) floor(($cnt - 1) / 2)] : null,
            'p90'    => $cnt > 0 ? (float) $diasDesdeContacto[(int) floor(0.9 * ($cnt - 1))] : null,
            'maximo' => $cnt > 0 ? (float) end($diasDesdeContacto) : null,
        ];
        // max_declaraciones_mismo_par: contar desde memoria_eventos (más fiable que el tick)
        $declPorPar = [];
        foreach ($partida['memoria_eventos'] ?? [] as $ev) {
            $evFam = (string) ($ev['familia'] ?? '');
            if ($evFam === 'romance_hito') {
                $partic = (array) ($ev['participantes'] ?? []);
                sort($partic);
                if (count($partic) >= 2) {
                    $pk = implode('|', $partic);
                    $declPorPar[$pk] = ($declPorPar[$pk] ?? 0) + 1;
                }
            }
        }
        $m['max_declaraciones_mismo_par'] = $declPorPar !== [] ? max($declPorPar) : 0;

        return $m;
    }

    /**
     * @return array<string, mixed>
     */
    private static function metricasVacias(): array
    {
        return [
            'eventos_vida' => 0,
            'salidas_individuales' => 0,
            'interacciones_casuales' => 0,
            'coincidencias' => 0,
            'flechazos' => 0,
            'eventos_vida_por_dia' => [],
            'autonomos_por_dia' => [],
            'mensajes_por_dia' => [],
            'olvidados_dia' => [],
            'familias' => [],
            'visitas_edificio' => [],
            'emo_neutro' => 0,
            'emo_total' => 0,
            'parejas_nuevas' => 0,
            'crisis_obs' => 0,
            'sin_actividad_7dias' => 0,
            'sin_interaccion_14dias' => 0,
            'dias_desde_ultimo_contacto' => [],
            'max_declaraciones_mismo_par' => 0,
        ];
    }

    /**
     * @param list<array<string, mixed>> $acc
     * @return array<string, mixed>
     */
    private static function promediar(array $acc, int $n, int $dias, int $pueblos): array
    {
        $avg = static function (array $vals): float {
            return $vals === [] ? 0.0 : round(array_sum($vals) / count($vals), 2);
        };
        $vida = [];
        $auto = [];
        $msg = [];
        $olv = [];
        $flech = [];
        $cas = [];
        $par = [];
        $rup = [];
        $ami = [];
        $neut = [];
        foreach ($acc as $m) {
            $vida[] = $avg($m['eventos_vida_por_dia'] ?? []);
            $auto[] = $avg($m['autonomos_por_dia'] ?? []);
            $msg[] = $avg($m['mensajes_por_dia'] ?? []);
            $olv[] = $avg($m['olvidados_dia'] ?? []);
            $flech[] = (float) ($m['flechazos'] ?? 0);
            $cas[] = (float) ($m['interacciones_casuales'] ?? 0) / max(1, $dias);
            $par[] = (float) ($m['parejas_nuevas'] ?? 0);
            $rup[] = (float) ($m['rupturas'] ?? 0);
            $ami[] = (float) ($m['amistades'] ?? 0);
            $neut[] = (float) ($m['pct_tiempo_neutro'] ?? 0);
        }
        $last = $acc[0] ?? [];
        return [
            'residentes' => $n,
            'dias' => $dias,
            'pueblos' => $pueblos,
            'eventos_vida_por_dia' => $avg($vida),
            'acciones_autonomas_por_dia' => $avg($auto),
            'mensajes_por_dia' => $avg($msg),
            'olvidados_media' => $avg($olv),
            'casuales_por_dia' => $avg($cas),
            'flechazos_totales_media' => $avg($flech),
            'parejas_nuevas_media' => $avg($par),
            'rupturas_media' => $avg($rup),
            'amistades_final_media' => $avg($ami),
            'pct_tiempo_neutro' => $avg($neut),
            'parejas_activas_final_media' => $avg(array_map(static function ($m) {
                return (float) ($m['parejas_activas_final'] ?? 0);
            }, $acc)),
            'duracion_media_pareja' => $avg(array_map(static function ($m) {
                return (float) ($m['duracion_media_pareja'] ?? 0);
            }, $acc)),
            'rechazos_media' => $avg(array_map(static function ($m) {
                return (float) ($m['rechazos'] ?? 0);
            }, $acc)),
            'mejores_amigos_media' => $avg(array_map(static function ($m) {
                return (float) ($m['mejores_amigos'] ?? 0);
            }, $acc)),
            'sin_actividad_7dias_media' => $avg(array_map(static function ($m) {
                return (float) ($m['sin_actividad_7dias'] ?? 0);
            }, $acc)),
            'sin_interaccion_14dias_media' => $avg(array_map(static function ($m) {
                return (float) ($m['sin_interaccion_14dias'] ?? 0);
            }, $acc)),
            'max_declaraciones_mismo_par_media' => $avg(array_map(static function ($m) {
                return (float) ($m['max_declaraciones_mismo_par'] ?? 0);
            }, $acc)),
            'dias_desde_contacto_ejemplo' => $last['dias_desde_ultimo_contacto'] ?? [],
            'declaraciones_totales_ejemplo' => array_sum(array_values($last['familias'] ?? [])) > 0
                ? ($last['familias']['declaracion'] ?? 0)
                : 0,
            'buzon_cat_ejemplo' => $last['buzon_cat'] ?? [],
            'visitas_edificio_ejemplo' => $last['visitas_edificio'] ?? [],
            'familias_ejemplo' => $last['familias'] ?? [],
            'desgaste_proyeccion' => $last['desgaste_lab'] ?? [],
            'hitos_ejemplo' => $last['hitos'] ?? [],
            'bandas_sociales_ejemplo' => $last['bandas_sociales'] ?? [],
        ];
    }
}
