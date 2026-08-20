<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Lab de vida autónoma. No escribe partidas. No canoniza.
 */
final class SimuladorAutonomia
{
    /**
     * @param list<int> $tamanos
     * @return array<string, mixed>
     */
    public static function ejecutar(
        string $projectRoot,
        array $tamanos = [8, 16, 32, 48],
        int $dias = 7,
        int $seeds = 3,
        string $seedBase = 'lab-autonomia'
    ): array {
        DomainBootstrap::boot();
        $catalog = new Catalog($projectRoot);
        $cal = CalibracionConfig::load($projectRoot);
        $out = [
            '_provisional' => true,
            '_canon' => false,
            '_nota' => 'Lab autonomía. Movimiento del pueblo ≠ Cotilleo. No toca Relacional V1 / B1 / B3 / E3.',
            'params' => [
                'p_intentar_hora' => CalibracionConfig::get($cal, 'autonomia.p_intentar_hora', 0.22),
                'p_franjas' => CalibracionConfig::get($cal, 'autonomia.p_franjas', null),
                'lineal' => CalibracionConfig::get($cal, 'autonomia.salidas_individuales_lineal', 0.48),
                'sqrt' => CalibracionConfig::get($cal, 'autonomia.salidas_individuales_sqrt', 0.0),
                'offset' => CalibracionConfig::get($cal, 'autonomia.salidas_individuales_offset', 0.5),
                'max_por_persona' => CalibracionConfig::get($cal, 'autonomia.max_salidas_por_residente_dia', 1),
                'max_misma_hora' => CalibracionConfig::get($cal, 'autonomia.max_salidas_misma_hora', 4),
                'anti_aislamiento_umbral_dias' => CalibracionConfig::get($cal, 'autonomia.anti_aislamiento_umbral_dias', 2),
                'cupo_8' => AutonomiaSalidas::cupoDia(8, $cal),
                'cupo_16' => AutonomiaSalidas::cupoDia(16, $cal),
                'cupo_32' => AutonomiaSalidas::cupoDia(32, $cal),
                'cupo_48' => AutonomiaSalidas::cupoDia(48, $cal),
            ],
            'por_tamano' => [],
        ];
        foreach ($tamanos as $n) {
            $runs = [];
            for ($s = 0; $s < $seeds; $s++) {
                $rng = new RngService($seedBase . '-' . $n . '-' . $s);
                $runs[] = self::correr($projectRoot, $catalog, $cal, (int) $n, $dias, $rng);
            }
            $out['por_tamano'][(string) $n] = self::agregar($runs, (int) $n, $dias);
        }
        $out['lectura'] = self::lectura($out);
        return $out;
    }

    /**
     * @return list<string>
     */
    private static function lugaresLab(int $n): array
    {
        $base = [
            'lug_cafeteria', 'lug_parque', 'lug_biblioteca',
            'lug_bar', 'lug_gimnasio', 'lug_cine',
        ];
        $extra = [
            'lug_arcade', 'lug_picnic', 'lug_restaurante',
            'lug_karaoke', 'lug_spa', 'lug_bingo',
            'lug_discoteca', 'lug_mirador', 'lug_tienda_ropa',
        ];
        $k = 0;
        if ($n >= 16) {
            $k = 3;
        }
        if ($n >= 32) {
            $k = 6;
        }
        if ($n >= 48) {
            $k = 9;
        }
        return array_merge($base, array_slice($extra, 0, $k));
    }

    /**
     * @return array<string, mixed>
     */
    private static function correr(string $projectRoot, Catalog $catalog, array $cal, int $n, int $dias, RngService $rng): array
    {
        $partida = SimuladorPuebloVivo::pueblo($n, $rng, $cal, $catalog);
        $partida['lab_vida_activa'] = false;
        $partida['features']['npc_autonomy_enabled'] = true;
        $partida['features']['buzon_enabled'] = true;
        $partida['bloque_a'] = [
            'capacidad' => max(16, $n),
            'viviendas' => [],
        ];
        $partida['celeste']['lugares_desbloqueados'] = self::lugaresLab($n);
        $m = [
            'salidas' => 0,
            'max_persona_dia' => 0,
            'personas_salen_dia' => [],
            'salen_semana' => [],
            'coincidencias' => 0,
            'casuales' => 0,
            'cotilleos' => 0,
            'mismo_lugar_ayer' => 0,
            'par_persona_lugar' => [],
            'saturaciones_ocupacion' => 0,
            'aforo_lleno' => 0,
            'ticks_destino' => 0,
            'ticks_casa' => 0,
            'ticks_destino_vigilia' => 0,
            'ticks_casa_vigilia' => 0,
            'sum_fuera' => 0,
            'max_fuera' => 0,
            'sum_complejo' => [],
            'max_complejo' => [],
            'max_ocupacion_destino' => 0,
            'por_lugar' => [],
            'cafeteria_noche' => 0,
        ];
        $prevLugar = [];
        for ($d = 1; $d <= $dias; $d++) {
            $partida['reloj']['dia_pueblo'] = $d;
            $salenHoy = [];
            $salidasPersona = [];
            for ($h = 0; $h < 24; $h++) {
                $partida['reloj']['hora_actual'] = $h;
                $tick = MotorVidaDiaria::tickHora($partida, $catalog, $cal, $rng, null);
                $lote = [];
                if (is_array($tick['autonomo']['salidas'] ?? null)) {
                    $lote = $tick['autonomo']['salidas'];
                } elseif (is_array($tick['autonomo'] ?? null) && isset($tick['autonomo']['quien'])) {
                    $lote = [$tick['autonomo']];
                }
                foreach ($lote as $s) {
                    if (!is_array($s) || !isset($s['quien'])) {
                        continue;
                    }
                    $m['salidas']++;
                    $quien = (string) $s['quien'];
                    $lug = (string) ($s['lugar'] ?? '');
                    $salenHoy[$quien] = true;
                    $m['salen_semana'][$quien] = true;
                    $salidasPersona[$quien] = (int) ($salidasPersona[$quien] ?? 0) + 1;
                    $m['por_lugar'][$lug] = (int) ($m['por_lugar'][$lug] ?? 0) + 1;
                    $par = $quien . '|' . $lug;
                    $m['par_persona_lugar'][$par] = (int) ($m['par_persona_lugar'][$par] ?? 0) + 1;
                    if (isset($prevLugar[$quien]) && $prevLugar[$quien] === $lug) {
                        $m['mismo_lugar_ayer']++;
                    }
                    $prevLugar[$quien] = $lug;
                    if ($lug === 'lug_cafeteria' && ($h < 8 || $h >= 20)) {
                        $m['cafeteria_noche']++;
                    }
                }
                if (is_array($tick['casuales'] ?? null)) {
                    $m['casuales'] += count($tick['casuales']);
                }
                $enDestino = 0;
                foreach (EncuentroEngine::list($partida) as $enc) {
                    if (!LugarAtributos::ocupaHora($enc, $d, $h)) {
                        continue;
                    }
                    $enDestino += count($enc['participantes'] ?? []);
                    $lug = (string) ($enc['lugar'] ?? '');
                    $ocLug = AforoEngine::ocupacion($partida, $lug, $d, $h);
                    if ($ocLug > $m['max_ocupacion_destino']) {
                        $m['max_ocupacion_destino'] = $ocLug;
                    }
                    $attr = LugarAtributos::de($lug);
                    if ($ocLug >= $attr['aforo']) {
                        $m['saturaciones_ocupacion']++;
                    }
                }
                $m['sum_fuera'] += $enDestino;
                if ($enDestino > $m['max_fuera']) {
                    $m['max_fuera'] = $enDestino;
                }
                foreach (array_keys(ComplejoCatalog::complejos()) as $cid) {
                    $oc = AforoEngine::ocupacionComplejo($partida, $cid, $d, $h);
                    $m['sum_complejo'][$cid] = (int) ($m['sum_complejo'][$cid] ?? 0) + $oc;
                    $prevMax = (int) ($m['max_complejo'][$cid] ?? 0);
                    if ($oc > $prevMax) {
                        $m['max_complejo'][$cid] = $oc;
                    }
                }
                $m['ticks_destino'] += $enDestino;
                $m['ticks_casa'] += max(0, $n - $enDestino);
                if ($h >= 10 && $h < 22) {
                    $m['ticks_destino_vigilia'] = (int) ($m['ticks_destino_vigilia'] ?? 0) + $enDestino;
                    $m['ticks_casa_vigilia'] = (int) ($m['ticks_casa_vigilia'] ?? 0) + max(0, $n - $enDestino);
                }
                CoincidenciasEngine::detectarYRegistrar($partida, $projectRoot, $d, $h, null);
            }
            foreach ($salidasPersona as $c) {
                if ($c > $m['max_persona_dia']) {
                    $m['max_persona_dia'] = $c;
                }
            }
            $m['personas_salen_dia'][] = count($salenHoy);
        }
        $m['coincidencias'] = count($partida['historial_coincidencias'] ?? []);
        $m['aforo_lleno'] = (int) ($partida['npc_autonomo']['stats']['aforo_lleno'] ?? 0);
        $repsPersonaLugar = 0;
        foreach ($m['par_persona_lugar'] as $c) {
            if ((int) $c >= 2) {
                $repsPersonaLugar++;
            }
        }
        $m['pares_persona_lugar_repetidos'] = $repsPersonaLugar;
        foreach ($partida['buzon'] ?? [] as $msg) {
            if (($msg['clasificacion'] ?? '') === BuzonEngine::COTILLEO) {
                $m['cotilleos']++;
            }
        }
        return $m;
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @return array<string, mixed>
     */
    private static function agregar(array $runs, int $n, int $dias): array
    {
        $nr = count($runs);
        $sal = 0.0;
        $maxP = 0;
        $salen = 0.0;
        $salenSem = 0.0;
        $coin = 0.0;
        $cas = 0.0;
        $cot = 0.0;
        $rep = 0.0;
        $repPar = 0.0;
        $sat = 0.0;
        $aforo = 0.0;
        $casa = 0.0;
        $dest = 0.0;
        $casaV = 0.0;
        $destV = 0.0;
        $sumFuera = 0.0;
        $maxFuera = 0;
        $maxOcc = 0;
        $noche = 0;
        $maxCompAny = 0;
        $sumCompMax = [];
        foreach ($runs as $r) {
            $sal += (float) $r['salidas'];
            $maxP = max($maxP, (int) $r['max_persona_dia']);
            $salen += array_sum($r['personas_salen_dia']);
            $salenSem += count($r['salen_semana'] ?? []);
            $coin += (float) $r['coincidencias'];
            $cas += (float) ($r['casuales'] ?? 0);
            $cot += (float) $r['cotilleos'];
            $rep += (float) $r['mismo_lugar_ayer'];
            $repPar += (float) ($r['pares_persona_lugar_repetidos'] ?? 0);
            $sat += (float) ($r['saturaciones_ocupacion'] ?? $r['saturaciones'] ?? 0);
            $aforo += (float) ($r['aforo_lleno'] ?? 0);
            $casa += (float) $r['ticks_casa'];
            $dest += (float) $r['ticks_destino'];
            $casaV += (float) ($r['ticks_casa_vigilia'] ?? 0);
            $destV += (float) ($r['ticks_destino_vigilia'] ?? 0);
            $sumFuera += (float) ($r['sum_fuera'] ?? 0);
            $maxFuera = max($maxFuera, (int) ($r['max_fuera'] ?? 0));
            $maxOcc = max($maxOcc, (int) ($r['max_ocupacion_destino'] ?? 0));
            $noche += (int) $r['cafeteria_noche'];
            foreach ($r['max_complejo'] ?? [] as $cid => $mx) {
                $sumCompMax[$cid] = max((int) ($sumCompMax[$cid] ?? 0), (int) $mx);
                $maxCompAny = max($maxCompAny, (int) $mx);
            }
        }
        $ticks = $nr * $dias * 24 * $n;
        $ticksV = $casaV + $destV;
        $horas = $nr * $dias * 24;
        $pctSemana = ($nr > 0 && $n > 0) ? round(100.0 * ($salenSem / $nr) / $n, 1) : 0.0;
        return [
            'n' => $n,
            'dias' => $dias,
            'salidas_por_dia' => $nr > 0 ? round($sal / ($nr * $dias), 2) : 0,
            'max_salidas_misma_persona_dia' => $maxP,
            'pct_personas_salen_dia' => $n > 0 ? round(100.0 * ($salen / ($nr * $dias)) / $n, 1) : 0,
            'pct_salen_al_menos_una_vez_7d' => $pctSemana,
            'pct_no_salen_nunca_7d' => round(100.0 - $pctSemana, 1),
            'media_simultaneos_fuera' => $horas > 0 ? round($sumFuera / $horas, 2) : 0,
            'media_simultaneos_fuera_10_22' => ($nr * $dias * 12) > 0 ? round($destV / ($nr * $dias * 12), 2) : 0,
            'max_simultaneos_fuera' => $maxFuera,
            'max_ocupacion_destino' => $maxOcc,
            'max_ocupacion_complejo' => $maxCompAny,
            'max_por_complejo' => $sumCompMax,
            'coincidencias_por_dia' => $nr > 0 ? round($coin / ($nr * $dias), 2) : 0,
            'interacciones_casuales_por_dia' => $nr > 0 ? round($cas / ($nr * $dias), 2) : 0,
            'cotilleos_por_dia' => $nr > 0 ? round($cot / ($nr * $dias), 2) : 0,
            'repeticion_lugar_consecutivo' => $nr > 0 ? round($rep / $nr, 2) : 0,
            'pares_persona_lugar_repetidos_7d' => $nr > 0 ? round($repPar / $nr, 1) : 0,
            'horas_en_aforo' => $nr > 0 ? round($sat / $nr, 1) : 0,
            'saturaciones_rechazadas_aforo' => $nr > 0 ? round($aforo / $nr, 1) : 0,
            'pct_persona_hora_en_casa' => $ticks > 0 ? round(100.0 * $casa / $ticks, 1) : 0,
            'pct_persona_hora_en_casa_10_22' => $ticksV > 0 ? round(100.0 * $casaV / $ticksV, 1) : 0,
            'cafeteria_fuera_horario' => $noche,
        ];
    }

    /**
     * @param array<string, mixed> $lab
     * @return array<string, mixed>
     */
    private static function lectura(array $lab): array
    {
        $p8 = $lab['por_tamano']['8'] ?? [];
        $p16 = $lab['por_tamano']['16'] ?? [];
        $p32 = $lab['por_tamano']['32'] ?? [];
        $p48 = $lab['por_tamano']['48'] ?? [];
        $s8 = (float) ($p8['salidas_por_dia'] ?? 0);
        $s48 = (float) ($p48['salidas_por_dia'] ?? 0);
        return [
            'n8_salidas_dia' => $p8['salidas_por_dia'] ?? null,
            'n16_salidas_dia' => $p16['salidas_por_dia'] ?? null,
            'n32_salidas_dia' => $p32['salidas_por_dia'] ?? null,
            'n48_salidas_dia' => $p48['salidas_por_dia'] ?? null,
            'n8_max_persona' => $p8['max_salidas_misma_persona_dia'] ?? null,
            'n8_pct_salen_7d' => $p8['pct_salen_al_menos_una_vez_7d'] ?? null,
            'n48_pct_salen_7d' => $p48['pct_salen_al_menos_una_vez_7d'] ?? null,
            'n8_nunca_7d' => $p8['pct_no_salen_nunca_7d'] ?? null,
            'n48_nunca_7d' => $p48['pct_no_salen_nunca_7d'] ?? null,
            'n8_cotilleo' => $p8['cotilleos_por_dia'] ?? null,
            'n48_cotilleo' => $p48['cotilleos_por_dia'] ?? null,
            'escala_8_a_48' => $s8 > 0 ? round($s48 / $s8, 2) : null,
            'crece_con_poblacion' => $s48 > $s8 * 2.5,
            'sin_cafeteria_noche' => ((int) ($p8['cafeteria_fuera_horario'] ?? 0) + (int) ($p48['cafeteria_fuera_horario'] ?? 0)) === 0,
            'nadie_sale_dos_veces' => ((int) ($p8['max_salidas_misma_persona_dia'] ?? 9) <= 1)
                && ((int) ($p48['max_salidas_misma_persona_dia'] ?? 9) <= 1),
        ];
    }
}
