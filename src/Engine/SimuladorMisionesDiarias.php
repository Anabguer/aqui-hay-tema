<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Lab B3: generador REAL de misiones diarias. Peticiones desactivadas.
 * No escribe partidas. No toca cifras de B1.
 */
final class SimuladorMisionesDiarias
{
    public const PERFILES = ['A', 'B', 'C', 'D'];
    public const TAMANOS = [8, 16, 32];
    public const HORIZONTES = [7, 30, 100, 365];

    /**
     * @param list<int> $tamanos
     * @param list<int> $horizontes
     * @return array<string, mixed>
     */
    public static function ejecutar(
        string $projectRoot,
        array $tamanos = [8, 16, 32],
        array $horizontes = [7, 30, 100, 365],
        int $seeds = 2,
        string $seedBase = 'lab-misiones-b3'
    ): array {
        $cal = CalibracionConfig::load($projectRoot);
        $maxH = 0;
        foreach ($horizontes as $h) {
            $h = (int) $h;
            if ($h > $maxH) {
                $maxH = $h;
            }
        }
        $out = [
            '_provisional' => true,
            '_nota' => 'Generador REAL B3. Peticiones OFF. Cifras B1 no tocadas. No canonizar.',
            'seeds' => $seeds,
            'tamanos' => $tamanos,
            'horizontes' => $horizontes,
            'p_cumplir' => self::pCumplirMapa(),
            'por_tamano' => [],
            'imposibles_total' => 0,
            'farming_detectado' => false,
            'recomendacion_b4' => null,
        ];

        foreach ($tamanos as $n) {
            $n = (int) $n;
            $out['por_tamano'][(string) $n] = ['por_perfil' => []];
            foreach (self::PERFILES as $perfil) {
                $runs = [];
                for ($s = 0; $s < $seeds; $s++) {
                    $rng = new RngService($seedBase . '-' . $n . '-' . $perfil . '-' . $s);
                    $runs[] = self::correr($n, $perfil, $maxH, $horizontes, $rng, $cal);
                }
                $out['por_tamano'][(string) $n]['por_perfil'][$perfil] = [
                    'nombre' => self::nombrePerfil($perfil),
                    'p_cumplir' => self::pCumplir($perfil),
                    'por_horizonte' => self::agregarRuns($runs, $horizontes),
                ];
            }
        }

        $out['imposibles_total'] = self::sumImposibles($out);
        $out['farming_detectado'] = self::detectarFarming($out);
        $out['recomendacion_b4'] = self::recomendar($out);
        return $out;
    }

    public static function nombrePerfil(string $perfil): string
    {
        $n = [
            'A' => 'Jugador excelente',
            'B' => 'Jugador normal',
            'C' => 'Jugador malo',
            'D' => 'Jugador casi inactivo',
        ];
        return $n[$perfil] ?? $perfil;
    }

    /**
     * @return array<string, float>
     */
    public static function pCumplirMapa(): array
    {
        return ['A' => 1.0, 'B' => 0.55, 'C' => 0.22, 'D' => 0.05];
    }

    public static function pCumplir(string $perfil): float
    {
        $m = self::pCumplirMapa();
        return $m[$perfil] ?? 0.0;
    }

    /**
     * @param list<int> $horizontes
     * @return array<string, mixed>
     */
    public static function correr(int $n, string $perfil, int $maxH, array $horizontes, RngService $rng, array $cal): array
    {
        $partida = self::partidaLab($n, $rng, $cal);
        $p = self::pCumplir($perfil);
        $snap = [];
        $acc = self::accNuevo();

        foreach ($horizontes as $h) {
            $h = (int) $h;
            $snap[(string) $h] = null;
        }

        for ($dia = 1; $dia <= $maxH; $dia++) {
            $partida['reloj']['dia_pueblo'] = $dia;
            $partida['reloj']['hora_actual'] = 10;
            $gen = MisionDiariaEngine::alComenzarDia($partida, $cal, $rng, null);
            $nGen = count($gen);
            $acc['dias']++;
            $acc['generadas'] += $nGen;
            if ($nGen >= 3) {
                $acc['dias_3']++;
            } elseif ($nGen === 2) {
                $acc['dias_2']++;
            } elseif ($nGen === 1) {
                $acc['dias_1']++;
            } else {
                $acc['dias_0']++;
            }

            $famsDia = [];
            foreach ($gen as $m) {
                $fam = (string) ($m['familia'] ?? '');
                $pid = (string) ($m['plantilla_id'] ?? '');
                $acc['familias'][$fam] = ($acc['familias'][$fam] ?? 0) + 1;
                $acc['plantillas'][$pid] = ($acc['plantillas'][$pid] ?? 0) + 1;
                if (isset($famsDia[$fam])) {
                    $acc['familias_duplicadas_dia']++;
                }
                $famsDia[$fam] = true;
                $enc = MisionDiariaEngine::encuentroSinteticoPara($m, $partida);
                if (!MisionDiariaEngine::encaja($m, $enc)) {
                    $acc['imposibles']++;
                }
            }
            if ($nGen > 3) {
                $acc['mas_de_tres']++;
            }

            $pend = MisionDiariaEngine::delDia($partida, $dia);
            foreach ($pend as $m) {
                if (($m['estado'] ?? '') !== MisionDiariaEngine::EST_PENDIENTE) {
                    continue;
                }
                if ($rng->nextFloat() > $p) {
                    continue;
                }
                $enc = MisionDiariaEngine::encuentroSinteticoPara($m, $partida);
                $nDone = MisionDiariaEngine::onEncuentroCelestine($partida, $enc, $cal, null);
                if ($nDone > 1) {
                    $acc['encuentro_multi_mision']++;
                }
                if ($nDone >= 1) {
                    $acc['cumplidas']++;
                    self::sideEffects($partida, $m, $enc, $cal);
                }
            }

            $cad = MisionDiariaEngine::alCerrarDia($partida, $dia, $cal, null);
            $acc['fallidas'] += $cad;

            $valor = VidaPuebloEngine::valor($partida);
            $acc['suma_vida'] += $valor;
            if ($acc['min_vida'] === null || $valor < $acc['min_vida']) {
                $acc['min_vida'] = $valor;
            }
            if ($acc['max_vida'] === null || $valor > $acc['max_vida']) {
                $acc['max_vida'] = $valor;
            }
            if ($valor <= 19) {
                $acc['dias_critico']++;
            }
            $vp = $partida['vida_pueblo'] ?? [];
            $acc['latidos'] = (int) ($vp['latidos'] ?? 0);
            $acc['primer_latido'] = $vp['primer_latido_dia'] ?? null;
            $acc['go'] = !empty($vp['game_over_pendiente']) || !empty($vp['llego_a_cero']);
            $acc['extra_vida'] += self::contarExtraVida($partida, $dia);

            if (array_key_exists((string) $dia, $snap)) {
                $snap[(string) $dia] = self::foto($acc, $partida);
            }
        }

        return ['snaps' => $snap, 'final' => self::foto($acc, $partida)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function accNuevo(): array
    {
        return [
            'dias' => 0,
            'generadas' => 0,
            'dias_3' => 0,
            'dias_2' => 0,
            'dias_1' => 0,
            'dias_0' => 0,
            'cumplidas' => 0,
            'fallidas' => 0,
            'imposibles' => 0,
            'familias_duplicadas_dia' => 0,
            'mas_de_tres' => 0,
            'encuentro_multi_mision' => 0,
            'familias' => [],
            'plantillas' => [],
            'suma_vida' => 0,
            'min_vida' => null,
            'max_vida' => null,
            'dias_critico' => 0,
            'latidos' => 0,
            'primer_latido' => null,
            'go' => false,
            'extra_vida' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $acc
     * @return array<string, mixed>
     */
    private static function foto(array $acc, array $partida): array
    {
        $dias = max(1, (int) $acc['dias']);
        $gen = (int) $acc['generadas'];
        $resueltas = (int) $acc['cumplidas'] + (int) $acc['fallidas'];
        return [
            'misiones_generadas' => $gen,
            'misiones_por_dia' => round($gen / $dias, 3),
            'pct_dias_3' => round(100 * $acc['dias_3'] / $dias, 2),
            'pct_dias_2' => round(100 * $acc['dias_2'] / $dias, 2),
            'pct_dias_1' => round(100 * $acc['dias_1'] / $dias, 2),
            'pct_dias_0' => round(100 * $acc['dias_0'] / $dias, 2),
            'pct_cumplidas' => $resueltas > 0 ? round(100 * $acc['cumplidas'] / $resueltas, 2) : 0.0,
            'pct_fallidas' => $resueltas > 0 ? round(100 * $acc['fallidas'] / $resueltas, 2) : 0.0,
            'cumplidas' => (int) $acc['cumplidas'],
            'fallidas' => (int) $acc['fallidas'],
            'vida_media' => round($acc['suma_vida'] / $dias, 2),
            'min' => $acc['min_vida'],
            'max' => $acc['max_vida'],
            'latidos' => (int) $acc['latidos'],
            'primer_latido' => $acc['primer_latido'],
            'dias_critico' => (int) $acc['dias_critico'],
            'game_over_teorico' => (bool) $acc['go'],
            'familias' => $acc['familias'],
            'plantillas' => $acc['plantillas'],
            'imposibles' => (int) $acc['imposibles'],
            'familias_duplicadas_dia' => (int) $acc['familias_duplicadas_dia'],
            'mas_de_tres' => (int) $acc['mas_de_tres'],
            'encuentro_multi_mision' => (int) $acc['encuentro_multi_mision'],
            'extra_vida_no_mision' => (int) $acc['extra_vida'],
            'valor_final' => VidaPuebloEngine::valor($partida),
        ];
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @param list<int> $horizontes
     * @return array<string, mixed>
     */
    private static function agregarRuns(array $runs, array $horizontes): array
    {
        $out = [];
        foreach ($horizontes as $h) {
            $h = (int) $h;
            $fotos = [];
            foreach ($runs as $run) {
                if (isset($run['snaps'][(string) $h]) && is_array($run['snaps'][(string) $h])) {
                    $fotos[] = $run['snaps'][(string) $h];
                }
            }
            $out[(string) $h] = self::mediaFotos($fotos);
        }
        return $out;
    }

    /**
     * @param list<array<string, mixed>> $fotos
     * @return array<string, mixed>
     */
    private static function mediaFotos(array $fotos): array
    {
        if ($fotos === []) {
            return [];
        }
        $n = count($fotos);
        $numKeys = [
            'misiones_generadas', 'misiones_por_dia', 'pct_dias_3', 'pct_dias_2', 'pct_dias_1', 'pct_dias_0',
            'pct_cumplidas', 'pct_fallidas', 'cumplidas', 'fallidas', 'vida_media', 'latidos', 'dias_critico',
            'imposibles', 'familias_duplicadas_dia', 'mas_de_tres', 'encuentro_multi_mision', 'extra_vida_no_mision',
            'valor_final',
        ];
        $agg = [];
        foreach ($numKeys as $k) {
            $s = 0.0;
            foreach ($fotos as $f) {
                $s += (float) ($f[$k] ?? 0);
            }
            $agg[$k] = round($s / $n, 3);
        }
        $mins = [];
        $maxs = [];
        $pl = [];
        $go = 0;
        $fams = [];
        $plants = [];
        foreach ($fotos as $f) {
            if ($f['min'] !== null) {
                $mins[] = (int) $f['min'];
            }
            if ($f['max'] !== null) {
                $maxs[] = (int) $f['max'];
            }
            if ($f['primer_latido'] !== null) {
                $pl[] = (int) $f['primer_latido'];
            }
            if (!empty($f['game_over_teorico'])) {
                $go++;
            }
            foreach ($f['familias'] ?? [] as $fam => $c) {
                $fams[$fam] = ($fams[$fam] ?? 0) + (int) $c;
            }
            foreach ($f['plantillas'] ?? [] as $pid => $c) {
                $plants[$pid] = ($plants[$pid] ?? 0) + (int) $c;
            }
        }
        arsort($fams);
        arsort($plants);
        $catalogo = [];
        foreach (MisionPlantillas::catalogo() as $plItem) {
            $catalogo[] = (string) $plItem['id'];
        }
        $nunca = [];
        foreach ($catalogo as $pid) {
            if ((int) ($plants[$pid] ?? 0) === 0) {
                $nunca[] = $pid;
            }
        }
        $agg['min'] = $mins === [] ? null : min($mins);
        $agg['max'] = $maxs === [] ? null : max($maxs);
        $agg['primer_latido_media'] = $pl === [] ? null : round(array_sum($pl) / count($pl), 2);
        $agg['pct_game_over'] = round(100 * $go / $n, 2);
        $agg['familias'] = $fams;
        $agg['plantillas'] = $plants;
        $agg['misiones_que_casi_nunca'] = $nunca;
        $agg['runs'] = $n;
        return $agg;
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function partidaLab(int $n, RngService $rng, array $cal): array
    {
        $partida = [
            '_lab_misiones_b3' => true,
            'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10],
            'meta' => ['seed' => 'lab-b3', 'config_id' => 'lab_misiones_b3'],
            'rng' => ['seed' => 'lab-b3', 'state' => $rng->getState()],
            'features' => [
                VidaPuebloEngine::FLAG => true,
                MisionDiariaEngine::FLAG => true,
            ],
            'residentes' => [],
            'celeste' => [
                'lugares_desbloqueados' => ['lug_cafeteria', 'lug_parque', 'lug_biblioteca'],
            ],
            'relaciones_sociales' => [],
            'relaciones_romanticas' => [],
            'descubrimientos' => [],
            'bitacora_relaciones' => [],
        ];
        for ($i = 1; $i <= $n; $i++) {
            $id = sprintf('lab_r%02d', $i);
            $partida['residentes'][$id] = [
                'presencia' => 'residente',
                'identidad_publica' => ['nombre' => 'Vecino ' . $i],
                'runtime' => [
                    'ultimo_contacto_social_dia' => 0,
                    'perfil_partida' => [
                        'hobbies' => ['hobby_a_' . ($i % 5), 'hobby_b_' . ($i % 3)],
                        'rasgos' => ['rasgo_' . ($i % 4)],
                    ],
                ],
            ];
        }
        RelacionGrafo::asegurarTodos($partida, $cal);
        VidaPuebloEngine::ensure($partida, $cal);
        MisionDiariaEngine::ensure($partida);
        $rng->persistToPartida($partida);
        return $partida;
    }

    /**
     * @param array<string, mixed> $m
     * @param array<string, mixed> $enc
     * @param array<string, mixed> $cal
     */
    private static function sideEffects(array &$partida, array $m, array $enc, array $cal): void
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $pid = (string) ($m['plantilla_id'] ?? '');
        $params = is_array($m['params'] ?? null) ? $m['params'] : [];
        $partes = $enc['participantes'] ?? [];
        foreach ($partes as $rid) {
            $rid = (string) $rid;
            if ($rid === '' || !isset($partida['residentes'][$rid])) {
                continue;
            }
            $partida['residentes'][$rid]['runtime']['ultimo_contacto_social_dia'] = $dia;
        }
        $a = (string) ($params['a'] ?? ($partes[0] ?? ''));
        $b = (string) ($params['b'] ?? ($partes[1] ?? ''));
        if ($a !== '' && $b !== '' && $a !== $b) {
            RelacionEngine::registrarContacto($partida, $a, $b, ContactoCalidad::NORMAL, $cal);
            RelacionEngine::registrarContacto($partida, $b, $a, ContactoCalidad::NORMAL, $cal);
        }
        if ($pid === 'primera_cita_hoy' && $a !== '' && $b !== '') {
            RelacionBitacora::registrar($partida, RelacionBitacora::PRIMERA_CITA, [$a, $b]);
        }
        if ($pid === 'por_descubrir') {
            $rid = (string) ($params['residente_id'] ?? '');
            $perfil = PerfilPartida::de($partida, $rid);
            if (is_array($perfil)) {
                foreach ($perfil['hobbies'] ?? [] as $h) {
                    $campo = ConocimientoNpc::campoHobby((string) $h);
                    if (DiscoveryEngine::estado($partida, $rid, $campo) !== DiscoveryEngine::DESCUBIERTO) {
                        $partida['descubrimientos'][] = [
                            'residente_id' => $rid,
                            'campo' => $campo,
                            'estado' => DiscoveryEngine::DESCUBIERTO,
                            'origen' => 'lab_mision',
                        ];
                        break;
                    }
                }
            }
        }
    }

    private static function contarExtraVida(array $partida, int $dia): int
    {
        $n = 0;
        foreach ($partida['vida_pueblo']['ledger'] ?? [] as $e) {
            if ((int) ($e['dia'] ?? 0) !== $dia) {
                continue;
            }
            $causa = (string) ($e['causa'] ?? '');
            if ($causa === VidaPuebloEngine::CAUSA_MISION_CUMPLIDA
                || $causa === VidaPuebloEngine::CAUSA_MISION_FALLIDA
                || $causa === VidaPuebloEngine::CAUSA_LATIDO_RESACA
            ) {
                continue;
            }
            $n++;
        }
        return $n;
    }

    /**
     * @param array<string, mixed> $out
     */
    private static function sumImposibles(array $out): int
    {
        $n = 0;
        foreach ($out['por_tamano'] ?? [] as $block) {
            foreach ($block['por_perfil'] ?? [] as $perfil) {
                foreach ($perfil['por_horizonte'] ?? [] as $h) {
                    $n += (int) round((float) ($h['imposibles'] ?? 0));
                }
            }
        }
        return $n;
    }

    /**
     * @param array<string, mixed> $out
     */
    private static function detectarFarming(array $out): bool
    {
        foreach ($out['por_tamano'] ?? [] as $block) {
            foreach ($block['por_perfil'] ?? [] as $perfil) {
                foreach ($perfil['por_horizonte'] ?? [] as $h) {
                    if ((float) ($h['extra_vida_no_mision'] ?? 0) > 0.01) {
                        return true;
                    }
                    if ((float) ($h['mas_de_tres'] ?? 0) > 0.01) {
                        return true;
                    }
                    if ((float) ($h['encuentro_multi_mision'] ?? 0) > 0.01) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $out
     * @return array<string, mixed>
     */
    private static function recomendar(array $out): array
    {
        $a8_30 = $out['por_tamano']['8']['por_perfil']['A']['por_horizonte']['30'] ?? [];
        $b8_30 = $out['por_tamano']['8']['por_perfil']['B']['por_horizonte']['30'] ?? [];
        $c8_30 = $out['por_tamano']['8']['por_perfil']['C']['por_horizonte']['30'] ?? [];
        $d8_30 = $out['por_tamano']['8']['por_perfil']['D']['por_horizonte']['30'] ?? [];
        $latA = (float) ($a8_30['latidos'] ?? 0);
        $primer = $a8_30['primer_latido_media'] ?? null;
        $combustible = 'ok';
        if ($primer !== null && (float) $primer < 7) {
            $combustible = 'demasiado';
        } elseif ($latA >= 4) {
            $combustible = 'demasiado';
        } elseif ($latA <= 0.2 && (float) ($a8_30['vida_media'] ?? 65) < 70) {
            $combustible = 'poco';
        }
        return [
            'combustible_b3_solo' => $combustible,
            'latidos_A_8_30d' => $latA,
            'primer_latido_A_8_30d' => $primer,
            'vida_media_B_8_30d' => $b8_30['vida_media'] ?? null,
            'vida_media_C_8_30d' => $c8_30['vida_media'] ?? null,
            'vida_media_D_8_30d' => $d8_30['vida_media'] ?? null,
            'b4' => 'No sumar otro +6/día de peticiones fáciles si A ya latea con solo misiones. Peticiones deben ser más raras o no todas +2 válido. No tocar B1 todavía.',
        ];
    }
}
