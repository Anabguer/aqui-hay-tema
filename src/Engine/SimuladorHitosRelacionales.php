<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Simulaciones largas de hitos relacionales. Mide; no canoniza.
 */
final class SimuladorHitosRelacionales
{
    /** @var list<string> */
    private const RASGOS_POOL = [
        'directo', 'timido', 'leal', 'cabezota', 'empatico', 'vanidoso',
        'ansioso', 'bromista', 'reservado', 'sociable', 'tranquilo', 'nervioso',
    ];

    /**
     * @param list<int> $poblaciones
     * @param list<int> $horizontes
     * @param list<string> $perfiles
     * @return array<string, mixed>
     */
    public static function ejecutar(
        string $projectRoot,
        array $poblaciones = [8, 16, 32, 48],
        array $horizontes = [30, 100, 365, 700],
        int $seeds = 3,
        array $perfiles = ['activa', 'normal', 'torpe', 'inactiva'],
        string $seedBase = 'hitos-rel'
    ): array {
        $cal = CalibracionConfig::load($projectRoot);
        $out = [
            '_provisional' => true,
            '_generado' => date('c'),
            'seeds' => $seeds,
            'matriz' => [],
            'escenarios_dirigidos' => [],
            'historias' => [],
            'anomalias' => [],
            'recomendacion' => [],
        ];

        foreach ($poblaciones as $n) {
            foreach ($horizontes as $dias) {
                foreach ($perfiles as $perfil) {
                    $agg = self::aggVacio();
                    for ($s = 0; $s < $seeds; $s++) {
                        $rng = new RngService($seedBase . "-{$n}-{$dias}-{$perfil}-{$s}");
                        $partida = self::pueblo($n, $rng, $cal, $perfil);
                        $m = self::correr($partida, $cal, $rng, $dias, $perfil);
                        self::sumar($agg, $m);
                        if ($s === 0 && $n === 16 && $dias === 365 && $perfil === 'normal') {
                            $out['historias'] = array_merge($out['historias'], self::extraerHistorias($partida, 4));
                        }
                    }
                    self::promediar($agg, $seeds);
                    $out['matriz']["n{$n}_d{$dias}_{$perfil}"] = $agg;
                    self::detectarAnomalias($agg, $n, $dias, $perfil, $out['anomalias']);
                }
            }
        }

        $out['escenarios_dirigidos'] = self::correrEscenarios($cal, $seedBase);
        $out['recomendacion'] = self::recomendar($out);
        return $out;
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function correr(array &$partida, array $cal, RngService $rng, int $dias, string $perfil): array
    {
        $m = self::aggVacio();
        $m['pop_ini'] = count($partida['residentes'] ?? []);
        for ($d = 1; $d <= $dias; $d++) {
            $partida['reloj']['dia_pueblo'] = $d;
            $partida['reloj']['hora_actual'] = 20;
            self::driftLab($partida, $cal, $rng, $perfil);
            RelacionDesgaste::alCerrarDia($partida, $cal);
            HitoRelacionalEngine::alCerrarDia($partida, $cal, $rng);
            if ($perfil === 'activa' && $d % 3 === 0) {
                self::impulsoJugadora($partida, $cal, $rng, 0.85);
            } elseif ($perfil === 'normal' && $d % 5 === 0) {
                self::impulsoJugadora($partida, $cal, $rng, 0.55);
            } elseif ($perfil === 'torpe' && $d % 7 === 0) {
                self::impulsoJugadora($partida, $cal, $rng, 0.35);
            }
        }
        return self::metricas($partida, $m);
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function driftLab(array &$partida, array $cal, RngService $rng, string $perfil): void
    {
        if (!(bool) CalibracionConfig::get($cal, 'hitos_relacionales.drift_lab.activo_en_sim', true)) {
            return;
        }
        $ids = array_keys($partida['residentes'] ?? []);
        $n = count($ids);
        if ($n < 2) {
            return;
        }
        $factor = (float) CalibracionConfig::get($cal, 'hitos_relacionales.drift_lab.pares_contacto_por_dia_factor', 0.35);
        if ($perfil === 'inactiva') {
            $factor *= 0.25;
        } elseif ($perfil === 'activa') {
            $factor *= 1.2;
        }
        $pares = max(1, (int) round($n * $factor / 2));
        $pBump = (float) CalibracionConfig::get($cal, 'hitos_relacionales.drift_lab.p_bump_romance', 0.22);
        for ($i = 0; $i < $pares; $i++) {
            $a = (string) $ids[$rng->nextInt(0, $n - 1)];
            $b = (string) $ids[$rng->nextInt(0, $n - 1)];
            if ($a === $b) {
                continue;
            }
            RelacionEngine::registrarContacto($partida, $a, $b, ContactoCalidad::NORMAL, $cal);
            $ds = HitoRelacionalContexto::randRango(
                $rng,
                (array) CalibracionConfig::get($cal, 'hitos_relacionales.drift_lab.delta_social', [1, 4])
            );
            RelacionEngine::ajustarSocialHacia($partida, $a, $b, $ds, $cal);
            if ($rng->nextFloat() <= $pBump && !ParentescoVeto::bloqueaRomance($partida, $a, $b, $cal)) {
                $dr = HitoRelacionalContexto::randRango(
                    $rng,
                    (array) CalibracionConfig::get($cal, 'hitos_relacionales.drift_lab.delta_romance_si_afinidad', [0, 3])
                );
                if ($dr > 0) {
                    HitoRelacionalContexto::bumpRomance($partida, $a, $b, $dr);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $cal
     */
    private static function impulsoJugadora(array &$partida, array $cal, RngService $rng, float $p): void
    {
        if ($rng->nextFloat() > $p) {
            return;
        }
        $ids = array_keys($partida['residentes'] ?? []);
        if (count($ids) < 2) {
            return;
        }
        $a = (string) $ids[$rng->nextInt(0, count($ids) - 1)];
        $b = (string) $ids[$rng->nextInt(0, count($ids) - 1)];
        if ($a === $b) {
            return;
        }
        RelacionEngine::registrarContacto($partida, $a, $b, ContactoCalidad::SIGNIFICATIVO, $cal);
        RelacionEngine::ajustarSocialHacia($partida, $a, $b, 3, $cal);
        RelacionEngine::ajustarSocialHacia($partida, $b, $a, 2, $cal);
        if (!ParentescoVeto::bloqueaRomance($partida, $a, $b, $cal) && $rng->nextFloat() < 0.4) {
            HitoRelacionalContexto::bumpRomance($partida, $a, $b, $rng->nextInt(1, 4));
            HitoRelacionalContexto::bumpRomance($partida, $b, $a, $rng->nextInt(0, 3));
        }
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function pueblo(int $n, RngService $rng, array $cal, string $perfil): array
    {
        $partida = [
            'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 8],
            'residentes' => [],
            'relaciones_sociales' => [],
            'relaciones_romanticas' => [],
            'relaciones_conflicto' => [],
            'parentesco' => [],
            'bitacora_relaciones' => [],
            'historial_relaciones' => [],
            'lab_vida_activa' => true,
            'lab_perfil' => $perfil,
        ];
        for ($i = 0; $i < $n; $i++) {
            $id = 'h' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $ras = [];
            $k = $rng->nextInt(1, 3);
            for ($j = 0; $j < $k; $j++) {
                $ras[] = self::RASGOS_POOL[$rng->nextInt(0, count(self::RASGOS_POOL) - 1)];
            }
            $partida['residentes'][$id] = [
                'catalog_id' => $id,
                'presencia' => 'residente',
                'runtime' => [
                    'perfil_partida' => [
                        'edad' => $rng->nextInt(20, 65),
                        'nombre' => 'Res' . $i,
                        'rasgos' => array_values(array_unique($ras)),
                    ],
                    'estado_emocional' => ['id' => 'neutro'],
                ],
            ];
        }
        SchemaFields::ensure($partida);
        $ids = array_keys($partida['residentes']);
        // densificar conocidos iniciales
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($rng->nextFloat() < 0.45) {
                    RelacionEngine::upsertSocial($partida, (string) $ids[$i], (string) $ids[$j], 'conocido', 1);
                    RelacionEngine::registrarContacto(
                        $partida,
                        (string) $ids[$i],
                        (string) $ids[$j],
                        ContactoCalidad::LEVE,
                        $cal
                    );
                }
            }
        }
        if ($n >= 2 && $rng->nextFloat() < 0.08) {
            $partida['parentesco'][] = [
                'persona_a' => (string) $ids[0],
                'persona_b' => (string) $ids[1],
                'tipo' => 'hermano',
            ];
        }
        return $partida;
    }

    /**
     * @return array<string, float|int>
     */
    private static function aggVacio(): array
    {
        return [
            'amistades' => 0,
            'hitos_romanticos' => 0,
            'confesiones' => 0,
            'besos' => 0,
            'parejas' => 0,
            'dias_media_primera_pareja' => 0,
            'duracion_media_parejas' => 0,
            'crisis' => 0,
            'rupturas' => 0,
            'reconciliaciones' => 0,
            'crushes_tercero' => 0,
            'triangulos' => 0,
            'infidelidades' => 0,
            'parejas_estables_pct' => 0,
            'unilaterales' => 0,
            'sin_relacion' => 0,
            'encadenadores' => 0,
            'eventos_repetidos' => 0,
        ];
    }

    /**
     * @param array<string, float|int> $m
     * @return array<string, float|int>
     */
    private static function metricas(array $partida, array $m): array
    {
        $bit = $partida['bitacora_relaciones'] ?? [];
        $tipos = [];
        foreach ($bit as $h) {
            if (!is_array($h)) {
                continue;
            }
            $t = (string) ($h['tipo'] ?? '');
            $tipos[$t] = ($tipos[$t] ?? 0) + 1;
        }
        $m['confesiones'] = (int) ($tipos[RelacionBitacora::CONFESION] ?? 0);
        $m['besos'] = (int) ($tipos[RelacionBitacora::BESO] ?? 0);
        $m['parejas'] = (int) (($tipos[RelacionBitacora::INICIO_PAREJA] ?? 0) + ($tipos[RelacionBitacora::VUELTA] ?? 0));
        $m['crisis'] = (int) ($tipos[RelacionBitacora::CRISIS] ?? 0);
        $m['rupturas'] = (int) ($tipos[RelacionBitacora::RUPTURA] ?? 0);
        $m['reconciliaciones'] = (int) ($tipos[RelacionBitacora::RECONCILIACION] ?? 0);
        $m['infidelidades'] = (int) ($tipos[RelacionBitacora::INFIDELIDAD] ?? 0);
        $m['hitos_romanticos'] = (int) (
            ($tipos[RelacionBitacora::TENSION_ROMANTICA] ?? 0)
            + ($tipos[RelacionBitacora::COQUETEO] ?? 0)
            + $m['confesiones']
            + $m['besos']
            + $m['parejas']
        );
        $m['crushes_tercero'] = 0;
        $m['triangulos'] = 0;

        $amistades = 0;
        foreach ($partida['relaciones_sociales'] ?? [] as $rel) {
            if (!is_array($rel) || empty($rel['conocidos'])) {
                continue;
            }
            $va = (int) ($rel['a_hacia_b']['valor'] ?? 0);
            $vb = (int) ($rel['b_hacia_a']['valor'] ?? 0);
            if ($va >= 40 && $vb >= 40) {
                $amistades++;
            }
        }
        $m['amistades'] = $amistades;

        $unil = 0;
        $conRel = [];
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            $ra = (int) ($rel['romance_a_hacia_b'] ?? 0);
            $rb = (int) ($rel['romance_b_hacia_a'] ?? 0);
            if (($ra >= 22 && $rb < 8) || ($rb >= 22 && $ra < 8)) {
                $unil++;
            }
            if ($ra >= 8 || $rb >= 8) {
                $conRel[$a] = true;
                $conRel[$b] = true;
            }
            $parejaDeA = TerceroRomantico::parejaDe($partida, $a);
            if ($parejaDeA !== null && $parejaDeA !== $b && max($ra, $rb) >= 22) {
                $m['crushes_tercero']++;
                if (min($ra, $rb) >= 18) {
                    $m['triangulos']++;
                }
            }
        }
        $m['unilaterales'] = $unil;
        $m['sin_relacion'] = count($partida['residentes'] ?? []) - count($conRel);

        $diasPrimera = [];
        $duraciones = [];
        $estables = 0;
        $parejasActivas = 0;
        $encaden = [];
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $est = (string) ($rel['estado_pareja'] ?? '');
            if ($est === ParejaEngine::PAREJA || $est === ParejaEngine::CRISIS) {
                $parejasActivas++;
                $estab = $rel['estabilidad_pareja']['valor'] ?? null;
                if (is_numeric($estab) && (int) $estab >= 50 && $est === ParejaEngine::PAREJA) {
                    $estables++;
                }
            }
            foreach ($rel['historial_parejas'] ?? [] as $hp) {
                if (!is_array($hp)) {
                    continue;
                }
                $ini = (int) ($hp['inicio']['dia'] ?? 0);
                if ($ini > 0) {
                    $diasPrimera[] = $ini;
                }
                $fin = $hp['fin']['dia'] ?? null;
                if ($fin !== null) {
                    $duraciones[] = max(0, (int) $fin - $ini);
                } elseif ($ini > 0) {
                    $duraciones[] = max(0, (int) ($partida['reloj']['dia_pueblo'] ?? 1) - $ini);
                }
            }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            $nHist = count($rel['historial_parejas'] ?? []);
            if ($nHist >= 3) {
                $encaden[$a] = true;
                $encaden[$b] = true;
            }
        }
        $m['dias_media_primera_pareja'] = $diasPrimera === [] ? 0 : array_sum($diasPrimera) / count($diasPrimera);
        $m['duracion_media_parejas'] = $duraciones === [] ? 0 : array_sum($duraciones) / count($duraciones);
        $m['parejas_estables_pct'] = $parejasActivas > 0 ? (100.0 * $estables / $parejasActivas) : 0.0;
        $m['encadenadores'] = count($encaden);

        $rep = 0;
        foreach ($tipos as $t => $c) {
            if ($c >= 8 && in_array($t, [RelacionBitacora::COQUETEO, RelacionBitacora::TENSION_ROMANTICA], true)) {
                $rep++;
            }
        }
        $m['eventos_repetidos'] = $rep;
        return $m;
    }

    /** @param array<string, float|int> $agg @param array<string, float|int> $m */
    private static function sumar(array &$agg, array $m): void
    {
        foreach ($m as $k => $v) {
            if ($k === 'pop_ini') {
                $agg[$k] = $v;
                continue;
            }
            $agg[$k] = ($agg[$k] ?? 0) + $v;
        }
    }

    /** @param array<string, float|int> $agg */
    private static function promediar(array &$agg, int $seeds): void
    {
        foreach ($agg as $k => $v) {
            if ($k === 'pop_ini') {
                continue;
            }
            $agg[$k] = round(((float) $v) / max(1, $seeds), 2);
        }
    }

    /**
     * @param array<string, float|int> $agg
     * @param list<string> $anomalias
     */
    private static function detectarAnomalias(array $agg, int $n, int $dias, string $perfil, array &$anomalias): void
    {
        if ($agg['infidelidades'] > max(1.5, $agg['parejas'] * 0.4)) {
            $anomalias[] = "infidelidad_alta n{$n} d{$dias} {$perfil}: {$agg['infidelidades']} vs parejas {$agg['parejas']}";
        }
        if ($dias >= 365 && $agg['parejas'] < 0.2 && $perfil !== 'inactiva') {
            $anomalias[] = "casi_cero_parejas n{$n} d{$dias} {$perfil}";
        }
        if ($agg['encadenadores'] > max(1, $n * 0.15)) {
            $anomalias[] = "encadenadores n{$n} d{$dias} {$perfil}: {$agg['encadenadores']}";
        }
        if ($agg['crisis'] > $agg['parejas'] * 3 && $agg['parejas'] > 0) {
            $anomalias[] = "crisis_excesivas n{$n} d{$dias} {$perfil}";
        }
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function correrEscenarios(array $cal, string $seedBase): array
    {
        $lista = [
            'amistad_sin_romance', 'romance_unilateral', 'romance_mutuo_alto', 'beso_sin_pareja',
            'pareja_rapida', 'pareja_lenta', 'pareja_estable', 'pareja_deteriorada',
            'crisis_recuperada', 'crisis_a_ruptura', 'tercero_compatible', 'crush_no_actuado',
            'triangulo', 'infidelidad_rara', 'ruptura_antes_otra', 'reconciliacion',
        ];
        $out = [];
        foreach ($lista as $esc) {
            $rng = new RngService($seedBase . '-esc-' . $esc);
            $partida = self::pueblo(8, $rng, $cal, 'normal');
            $ids = array_keys($partida['residentes']);
            $setup = ['a' => (string) $ids[0], 'b' => (string) $ids[1], 'c' => (string) ($ids[2] ?? '')];
            $r = HitoRelacionalEngine::escenarioDirigido($partida, $esc, $setup, $cal, $rng);
            $out[$esc] = [
                'ok' => !empty($r['ok']),
                'estado' => $r['estado_ab'] ?? null,
                'romance_ab' => $r['romance_ab'] ?? null,
                'romance_ba' => $r['romance_ba'] ?? null,
                'bitacora_tipos' => self::tiposBitacora($partida),
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function extraerHistorias(array $partida, int $max): array
    {
        $porPar = [];
        foreach ($partida['bitacora_relaciones'] ?? [] as $h) {
            if (!is_array($h)) {
                continue;
            }
            $par = $h['par'] ?? null;
            if (!is_array($par) || count($par) < 2) {
                continue;
            }
            $k = implode('|', $par);
            $porPar[$k][] = $h;
        }
        uasort($porPar, static function ($a, $b) {
            return count($b) <=> count($a);
        });
        $historias = [];
        $i = 0;
        foreach ($porPar as $k => $hits) {
            if ($i >= $max) {
                break;
            }
            if (count($hits) < 2) {
                continue;
            }
            $ids = explode('|', $k);
            $nomA = (string) ($partida['residentes'][$ids[0]]['runtime']['perfil_partida']['nombre'] ?? $ids[0]);
            $nomB = (string) ($partida['residentes'][$ids[1]]['runtime']['perfil_partida']['nombre'] ?? $ids[1]);
            $lineas = [];
            foreach ($hits as $h) {
                $dia = (int) ($h['fecha']['dia'] ?? 0);
                $tipo = (string) ($h['tipo'] ?? '');
                $res = $h['resultado'];
                $extra = is_string($res) ? " ({$res})" : '';
                $lineas[] = "día {$dia}: {$tipo}{$extra}";
            }
            $historias[] = [
                'titulo' => "{$nomA} / {$nomB}",
                'lineas' => $lineas,
            ];
            $i++;
        }
        return $historias;
    }

    /**
     * @return array<string, int>
     */
    private static function tiposBitacora(array $partida): array
    {
        $out = [];
        foreach ($partida['bitacora_relaciones'] ?? [] as $h) {
            if (!is_array($h)) {
                continue;
            }
            $t = (string) ($h['tipo'] ?? '');
            $out[$t] = ($out[$t] ?? 0) + 1;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $out
     * @return list<string>
     */
    private static function recomendar(array $out): array
    {
        $recs = [];
        $inf = 0.0;
        $par = 0.0;
        $n = 0;
        foreach ($out['matriz'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $inf += (float) ($row['infidelidades'] ?? 0);
            $par += (float) ($row['parejas'] ?? 0);
            $n++;
        }
        $infMed = $n > 0 ? $inf / $n : 0;
        $parMed = $n > 0 ? $par / $n : 0;
        if ($infMed > 0.8) {
            $recs[] = 'BALANCE: bajar hitos_relacionales.infidelidad.p_base o endurecer estabilidad_pareja_max.';
        } elseif ($infMed < 0.02 && $parMed > 1) {
            $recs[] = 'BALANCE: infidelidad casi ausente; OK si se busca rareza. No subir sin revisión.';
        }
        if ($parMed < 0.3) {
            $recs[] = 'BALANCE: pocas parejas — revisar inicio_pareja.p_base / romance_min_ambos / trayectoria.';
        } elseif ($parMed > 6) {
            $recs[] = 'BALANCE: muchas parejas — subir umbrales o bajar p_base inicio_pareja.';
        }
        $recs[] = 'PROVISIONAL: no canonizar cifras finas hasta revisión Neni+ChatGPT con estas métricas.';
        $recs[] = 'MARCHAS siguen BLOQUEADO_DECISION (fuera de este bloque).';
        return $recs;
    }
}
