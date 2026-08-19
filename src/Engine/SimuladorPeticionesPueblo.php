<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Lab B3+B4 combinado. Compara esquemas de petición. No escribe partidas. No toca B1/B3.
 */
final class SimuladorPeticionesPueblo
{
    public const PERFILES = ['A', 'B', 'C', 'D'];
    public const TAMANOS = [8, 16, 32];
    public const HORIZONTES = [30, 100, 365];
    public const TICKS_NACER_DIA = 12;

    /**
     * @param list<string> $esquemas
     * @param list<int> $tamanos
     * @param list<int> $horizontes
     * @return array<string, mixed>
     */
    public static function ejecutarComparacion(
        string $projectRoot,
        array $esquemas = ['E1', 'E2', 'E3', 'E4', 'E5'],
        array $tamanos = [8],
        array $horizontes = [30, 100, 365],
        int $seeds = 2,
        string $seedBase = 'lab-peticiones-b4'
    ): array {
        $cal = CalibracionConfig::load($projectRoot);
        $out = [
            '_provisional' => true,
            '_nota' => 'LAB CANDIDATOS B4. B3 no recalibrado. B1 no tocado. No canonizar cifras.',
            'seeds' => $seeds,
            'tamanos' => $tamanos,
            'horizontes' => $horizontes,
            'ticks_nacer_dia' => self::TICKS_NACER_DIA,
            'p_cumplir' => SimuladorMisionesDiarias::pCumplirMapa(),
            'esquemas' => [],
            'recomendacion' => null,
        ];
        foreach ($esquemas as $eid) {
            $esq = PeticionEsquemas::de((string) $eid);
            $block = [
                'id' => $esq['id'],
                'label' => $esq['label'],
                'esquema' => $esq,
                'por_tamano' => [],
                'imposibles_total' => 0,
                'farming_detectado' => false,
            ];
            foreach ($tamanos as $n) {
                $n = (int) $n;
                $block['por_tamano'][(string) $n] = ['por_perfil' => []];
                foreach (self::PERFILES as $perfil) {
                    $runs = [];
                    for ($s = 0; $s < $seeds; $s++) {
                        $rng = new RngService($seedBase . '-' . $esq['id'] . '-' . $n . '-' . $perfil . '-' . $s);
                        $runs[] = self::correr($n, $perfil, $horizontes, $rng, $cal, (string) $esq['id']);
                    }
                    $block['por_tamano'][(string) $n]['por_perfil'][$perfil] = [
                        'nombre' => SimuladorMisionesDiarias::nombrePerfil($perfil),
                        'p_cumplir' => SimuladorMisionesDiarias::pCumplir($perfil),
                        'por_horizonte' => self::agregarRuns($runs, $horizontes),
                    ];
                }
            }
            $block['imposibles_total'] = self::sumImposibles($block);
            $block['farming_detectado'] = self::detectarFarming($block);
            $block['score'] = self::scoreEsquema($block);
            $out['esquemas'][$esq['id']] = $block;
        }
        $out['recomendacion'] = self::recomendar($out);
        Reloj::fijarAhora(null);
        return $out;
    }

    /**
     * @param list<int> $horizontes
     * @return array<string, mixed>
     */
    public static function correr(int $n, string $perfil, array $horizontes, RngService $rng, array $cal, string $esquemaId): array
    {
        $partida = self::partidaLab($n, $rng, $cal, $esquemaId);
        $p = SimuladorMisionesDiarias::pCumplir($perfil);
        $maxH = 0;
        $snap = [];
        foreach ($horizontes as $h) {
            $h = (int) $h;
            $snap[(string) $h] = null;
            if ($h > $maxH) {
                $maxH = $h;
            }
        }
        $acc = self::accNuevo();
        $t0 = new \DateTimeImmutable('2026-08-17 08:00:00', Reloj::zona());

        for ($dia = 1; $dia <= $maxH; $dia++) {
            Reloj::fijarAhora($t0->modify('+' . ($dia - 1) . ' days'));
            $partida['reloj']['dia_pueblo'] = $dia;
            $partida['reloj']['hora_actual'] = 10;

            $gen = MisionDiariaEngine::alComenzarDia($partida, $cal, $rng, null);
            $nGen = count($gen);
            $acc['dias']++;
            $acc['misiones_gen'] += $nGen;
            foreach ($gen as $m) {
                $enc = MisionDiariaEngine::encuentroSinteticoPara($m, $partida);
                if (!MisionDiariaEngine::encaja($m, $enc)) {
                    $acc['imposibles']++;
                }
            }

            for ($t = 0; $t < self::TICKS_NACER_DIA; $t++) {
                $pet = PeticionPuebloEngine::intentarNacer($partida, $cal, $rng, null);
                if ($pet !== null) {
                    $acc['pet_gen']++;
                    $encP = PeticionPuebloEngine::encuentroSinteticoPara($pet, $partida);
                    if (!PeticionPuebloEngine::encaja($pet, $encP)) {
                        $acc['imposibles']++;
                    }
                    $peso = (string) ($pet['peso'] ?? '');
                    $acc['pesos'][$peso] = ($acc['pesos'][$peso] ?? 0) + 1;
                    $pid = (string) ($pet['plantilla_id'] ?? '');
                    $acc['plantillas'][$pid] = ($acc['plantillas'][$pid] ?? 0) + 1;
                }
            }

            $abiertas = PeticionPuebloEngine::abiertas($partida);
            $nAb = count($abiertas);
            $acc['suma_abiertas'] += $nAb;
            if ($nAb > PeticionPuebloEngine::capSimultaneas($n, $cal)) {
                $acc['sobre_cap']++;
            }
            $porNpc = [];
            foreach ($abiertas as $ap) {
                $rid = (string) ($ap['residente_id'] ?? '');
                $porNpc[$rid] = ($porNpc[$rid] ?? 0) + 1;
            }
            foreach ($porNpc as $c) {
                if ($c > 1) {
                    $acc['doble_npc']++;
                }
            }

            foreach (MisionDiariaEngine::delDia($partida, $dia) as $m) {
                if (($m['estado'] ?? '') !== MisionDiariaEngine::EST_PENDIENTE) {
                    continue;
                }
                if ($rng->nextFloat() > $p) {
                    continue;
                }
                $enc = MisionDiariaEngine::encuentroSinteticoPara($m, $partida);
                $nDone = MisionDiariaEngine::onEncuentroCelestine($partida, $enc, $cal, null);
                if ($nDone >= 1) {
                    $acc['misiones_ok']++;
                    if (!empty($m['cuenta_latido'])) {
                        $acc['validos_mision']++;
                    }
                    SimuladorMisionesDiarias::sideEffects($partida, $m, $enc, $cal);
                }
            }

            foreach (PeticionPuebloEngine::abiertas($partida) as $pet) {
                if ($rng->nextFloat() > $p) {
                    continue;
                }
                $encP = PeticionPuebloEngine::encuentroSinteticoPara($pet, $partida);
                $nDone = PeticionPuebloEngine::onEncuentroCelestine($partida, $encP, $cal, null);
                if ($nDone < 1) {
                    continue;
                }
                $acc['pet_ok']++;
                $peso = (string) ($pet['peso'] ?? '');
                if ($peso === PeticionEsquemas::PESO_FACIL) {
                    $acc['pet_ok_facil']++;
                }
                foreach ($partida['vida_pueblo']['ledger'] ?? [] as $e) {
                    if ((string) ($e['fuente_id'] ?? '') !== (string) ($pet['id'] ?? '')) {
                        continue;
                    }
                    if (!empty($e['positivo_valido_latido'])) {
                        $acc['validos_pet']++;
                        if ($peso === PeticionEsquemas::PESO_FACIL) {
                            $acc['validos_facil']++;
                        }
                    }
                    break;
                }
                self::sideEffectsPeticion($partida, $pet, $encP, $cal);
            }

            $acc['misiones_fail'] += MisionDiariaEngine::alCerrarDia($partida, $dia, $cal, null);

            Reloj::fijarAhora($t0->modify('+' . $dia . ' days'));
            $cad = PeticionEngine::caducarVencidas($partida, null);
            $acc['pet_cad'] += $cad;
            PeticionPuebloEngine::aplicarFalloPendiente($partida, $cal, null);

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
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function partidaLab(int $n, RngService $rng, array $cal, string $esquemaId): array
    {
        $partida = SimuladorMisionesDiarias::partidaLab($n, $rng, $cal);
        $partida['_lab_peticiones_b4'] = true;
        $partida['_b4_esquema'] = $esquemaId;
        $partida['features'][PeticionPuebloEngine::FLAG] = true;
        $partida['celeste']['lugares_desbloqueados'] = [
            'lug_cafeteria',
            'lug_parque',
            'lug_biblioteca',
            'lug_bingo',
        ];
        PeticionPuebloEngine::ensure($partida);
        $rng->persistToPartida($partida);
        return $partida;
    }

    /**
     * @return array<string, mixed>
     */
    private static function accNuevo(): array
    {
        return [
            'dias' => 0,
            'misiones_gen' => 0,
            'misiones_ok' => 0,
            'misiones_fail' => 0,
            'pet_gen' => 0,
            'pet_ok' => 0,
            'pet_cad' => 0,
            'pet_ok_facil' => 0,
            'validos_mision' => 0,
            'validos_pet' => 0,
            'validos_facil' => 0,
            'imposibles' => 0,
            'sobre_cap' => 0,
            'doble_npc' => 0,
            'pesos' => [],
            'plantillas' => [],
            'suma_abiertas' => 0,
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
        $petRes = (int) $acc['pet_ok'] + (int) $acc['pet_cad'];
        return [
            'misiones_por_dia' => round(((int) $acc['misiones_gen']) / $dias, 3),
            'peticiones_por_dia' => round(((int) $acc['pet_gen']) / $dias, 3),
            'abiertas_media' => round(((int) $acc['suma_abiertas']) / $dias, 3),
            'pct_pet_cumplidas' => $petRes > 0 ? round(100 * $acc['pet_ok'] / $petRes, 2) : 0.0,
            'pct_pet_caducadas' => $petRes > 0 ? round(100 * $acc['pet_cad'] / $petRes, 2) : 0.0,
            'pet_ok' => (int) $acc['pet_ok'],
            'pet_cad' => (int) $acc['pet_cad'],
            'pet_ok_facil' => (int) $acc['pet_ok_facil'],
            'validos_mision' => (int) $acc['validos_mision'],
            'validos_pet' => (int) $acc['validos_pet'],
            'validos_pet_por_dia' => round(((int) $acc['validos_pet']) / $dias, 3),
            'validos_total' => (int) $acc['validos_mision'] + (int) $acc['validos_pet'],
            'validos_por_dia' => round((((int) $acc['validos_mision']) + ((int) $acc['validos_pet'])) / $dias, 3),
            'validos_facil' => (int) $acc['validos_facil'],
            'vida_media' => round($acc['suma_vida'] / $dias, 2),
            'min' => $acc['min_vida'],
            'max' => $acc['max_vida'],
            'latidos' => (int) $acc['latidos'],
            'primer_latido' => $acc['primer_latido'],
            'dias_critico' => (int) $acc['dias_critico'],
            'game_over_teorico' => (bool) $acc['go'],
            'imposibles' => (int) $acc['imposibles'],
            'sobre_cap' => (int) $acc['sobre_cap'],
            'doble_npc' => (int) $acc['doble_npc'],
            'extra_vida_no_b3b4' => (int) $acc['extra_vida'],
            'pesos' => $acc['pesos'],
            'plantillas' => $acc['plantillas'],
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
            'misiones_por_dia', 'peticiones_por_dia', 'abiertas_media', 'pct_pet_cumplidas', 'pct_pet_caducadas',
            'pet_ok', 'pet_cad', 'pet_ok_facil', 'validos_mision', 'validos_pet', 'validos_pet_por_dia',
            'validos_total', 'validos_por_dia', 'validos_facil', 'vida_media', 'latidos', 'dias_critico',
            'imposibles', 'sobre_cap', 'doble_npc', 'extra_vida_no_b3b4', 'valor_final',
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
        $plants = [];
        $pesos = [];
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
            foreach ($f['plantillas'] ?? [] as $pid => $c) {
                $plants[$pid] = ($plants[$pid] ?? 0) + (int) $c;
            }
            foreach ($f['pesos'] ?? [] as $pe => $c) {
                $pesos[$pe] = ($pesos[$pe] ?? 0) + (int) $c;
            }
        }
        arsort($plants);
        arsort($pesos);
        $agg['min'] = $mins === [] ? null : min($mins);
        $agg['max'] = $maxs === [] ? null : max($maxs);
        $agg['primer_latido_media'] = $pl === [] ? null : round(array_sum($pl) / count($pl), 2);
        $agg['pct_game_over'] = round(100 * $go / $n, 2);
        $agg['plantillas'] = $plants;
        $agg['pesos'] = $pesos;
        $agg['runs'] = $n;
        return $agg;
    }

    /**
     * @param array<string, mixed> $pet
     * @param array<string, mixed> $enc
     * @param array<string, mixed> $cal
     */
    private static function sideEffectsPeticion(array &$partida, array $pet, array $enc, array $cal): void
    {
        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $params = is_array($pet['params'] ?? null) ? $pet['params'] : [];
        $partes = $enc['participantes'] ?? [];
        foreach ($partes as $rid) {
            $rid = (string) $rid;
            if ($rid === '' || !isset($partida['residentes'][$rid])) {
                continue;
            }
            $partida['residentes'][$rid]['runtime']['ultimo_contacto_social_dia'] = $dia;
        }
        $a = (string) ($pet['residente_id'] ?? ($partes[0] ?? ''));
        $b = (string) ($params['otro'] ?? ($partes[1] ?? ''));
        if ($a !== '' && $b !== '' && $a !== $b) {
            RelacionEngine::registrarContacto($partida, $a, $b, ContactoCalidad::NORMAL, $cal);
            RelacionEngine::registrarContacto($partida, $b, $a, ContactoCalidad::NORMAL, $cal);
        }
        if ((string) ($pet['plantilla_id'] ?? '') === 'primera_cita_pet' && $a !== '' && $b !== '') {
            RelacionBitacora::registrar($partida, RelacionBitacora::PRIMERA_CITA, [$a, $b]);
        }
    }

    private static function contarExtraVida(array $partida, int $dia): int
    {
        $n = 0;
        $ok = [
            VidaPuebloEngine::CAUSA_MISION_CUMPLIDA,
            VidaPuebloEngine::CAUSA_MISION_FALLIDA,
            VidaPuebloEngine::CAUSA_PETICION_CUMPLIDA,
            VidaPuebloEngine::CAUSA_PETICION_CADUCADA,
            VidaPuebloEngine::CAUSA_PETICION_IGNORADA,
            VidaPuebloEngine::CAUSA_LATIDO_RESACA,
        ];
        foreach ($partida['vida_pueblo']['ledger'] ?? [] as $e) {
            if ((int) ($e['dia'] ?? 0) !== $dia) {
                continue;
            }
            if (in_array((string) ($e['causa'] ?? ''), $ok, true)) {
                continue;
            }
            $n++;
        }
        return $n;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function sumImposibles(array $block): int
    {
        $n = 0;
        foreach ($block['por_tamano'] ?? [] as $tb) {
            foreach ($tb['por_perfil'] ?? [] as $perfil) {
                foreach ($perfil['por_horizonte'] ?? [] as $h) {
                    $n += (int) round((float) ($h['imposibles'] ?? 0));
                }
            }
        }
        return $n;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function detectarFarming(array $block): bool
    {
        foreach ($block['por_tamano'] ?? [] as $tb) {
            foreach ($tb['por_perfil'] ?? [] as $perfil) {
                foreach ($perfil['por_horizonte'] ?? [] as $h) {
                    if ((float) ($h['extra_vida_no_b3b4'] ?? 0) > 0.01) {
                        return true;
                    }
                    if ((float) ($h['validos_facil'] ?? 0) > 0.01) {
                        return true;
                    }
                    if ((float) ($h['sobre_cap'] ?? 0) > 0.01) {
                        return true;
                    }
                    if ((float) ($h['doble_npc'] ?? 0) > 0.01) {
                        return true;
                    }
                    if ((float) ($h['validos_pet_por_dia'] ?? 0) > 1.05) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function scoreEsquema(array $block): array
    {
        $a30 = $block['por_tamano']['8']['por_perfil']['A']['por_horizonte']['30'] ?? [];
        $a100 = $block['por_tamano']['8']['por_perfil']['A']['por_horizonte']['100'] ?? [];
        $b100 = $block['por_tamano']['8']['por_perfil']['B']['por_horizonte']['100'] ?? [];
        $c30 = $block['por_tamano']['8']['por_perfil']['C']['por_horizonte']['30'] ?? [];
        $d30 = $block['por_tamano']['8']['por_perfil']['D']['por_horizonte']['30'] ?? [];
        $pts = 0;
        $notas = [];
        $primer = $a30['primer_latido_media'] ?? null;
        if ($primer !== null) {
            $pv = (float) $primer;
            if ($pv >= 10 && $pv <= 15) {
                $pts += 30;
                $notas[] = 'A primer Latido en banda 10–15';
            } elseif ($pv >= 8 && $pv <= 18) {
                $pts += 18;
                $notas[] = 'A primer Latido cerca de 10–15';
            } elseif ($pv < 8) {
                $pts -= 20;
                $notas[] = 'A primer Latido demasiado pronto';
            } else {
                $pts += 4;
                $notas[] = 'A primer Latido tarde';
            }
        }
        $lat30 = (float) ($a30['latidos'] ?? 0);
        if ($lat30 >= 1.2 && $lat30 <= 3.2) {
            $pts += 15;
        } elseif ($lat30 > 5) {
            $pts -= 20;
            $notas[] = 'A convierte Latidos en frecuentes a 30d';
        }
        $lat100 = (float) ($a100['latidos'] ?? 0);
        if ($lat100 > 16) {
            $pts -= 12;
            $notas[] = 'A Latidos baratos a 100d';
        }
        $goB = (float) ($b100['pct_game_over'] ?? 100);
        $vidaB = (float) ($b100['vida_media'] ?? 0);
        if ($goB < 25 && $vidaB >= 40) {
            $pts += 25;
            $notas[] = 'B se sostiene a 100d';
        } elseif ($goB < 50 && $vidaB >= 30) {
            $pts += 12;
            $notas[] = 'B aguanta regular a 100d';
        } else {
            $notas[] = 'B no se sostiene solo con B3+B4 a 100d';
        }
        $goC = (float) ($c30['pct_game_over'] ?? 0);
        $vidaC = (float) ($c30['vida_media'] ?? 100);
        if ($goC >= 50 || $vidaC <= 30) {
            $pts += 10;
            $notas[] = 'C tiene peligro real';
        } else {
            $pts -= 8;
            $notas[] = 'C demasiado a salvo';
        }
        $goD = (float) ($d30['pct_game_over'] ?? 0);
        $vidaD = (float) ($d30['vida_media'] ?? 100);
        if ($goD >= 70 || $vidaD <= 25) {
            $pts += 10;
            $notas[] = 'D tiene peligro real';
        } else {
            $pts -= 8;
            $notas[] = 'D demasiado a salvo';
        }
        if (!empty($block['farming_detectado'])) {
            $pts -= 80;
            $notas[] = 'farming detectado';
        }
        return ['puntos' => $pts, 'notas' => $notas];
    }

    /**
     * @param array<string, mixed> $out
     * @return array<string, mixed>
     */
    private static function recomendar(array $out): array
    {
        $bestId = null;
        $bestPts = -9999;
        $ranking = [];
        foreach ($out['esquemas'] ?? [] as $id => $block) {
            $pts = (int) ($block['score']['puntos'] ?? 0);
            $ranking[$id] = $pts;
            if ($pts > $bestPts) {
                $bestPts = $pts;
                $bestId = (string) $id;
            }
        }
        arsort($ranking);
        $best = $out['esquemas'][$bestId] ?? [];
        return [
            'esquema' => $bestId,
            'label' => $best['label'] ?? '',
            'puntos' => $bestPts,
            'ranking' => $ranking,
            'notas' => $best['score']['notas'] ?? [],
            'por_que' => 'Mejor encaje A (Latido ~10–15d, no barato) + B sostenible a 100d + C/D en peligro, sin farm de fáciles. Cifras NO canon. No tocar B1 ni B3.',
            'BLOQUEADO_DECISION' => 'esquema_recompensa_peticiones_pueblo',
        ];
    }
}
