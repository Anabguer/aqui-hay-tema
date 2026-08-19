<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Laboratorio de Vida del Pueblo.
 * Alimenta el ledger con INPUT DE LAB (no son misiones/peticiones reales).
 * No escribe partidas. No canoniza cifras.
 */
final class SimuladorVidaPueblo
{
    public const PERFILES = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'G_valid'];
    public const HORIZONTES = [7, 30, 100, 365];

    /**
     * @param list<int> $horizontes
     * @return array<string, mixed>
     */
    public static function ejecutar(
        string $projectRoot,
        array $horizontes = [7, 30, 100, 365],
        int $seeds = 5,
        string $seedBase = 'lab-vida-pueblo'
    ): array {
        $cal = CalibracionConfig::load($projectRoot);
        $labCfg = self::labCfg($cal);
        $out = [
            '_provisional' => true,
            '_nota' => 'INPUT DE LAB. No hay misiones/peticiones/economía jugables. No canonizar.',
            'deltas_lab' => $labCfg,
            'seeds' => $seeds,
            'por_perfil' => [],
            'offline' => self::escenariosOffline($cal, $labCfg),
            'preocupantes' => [],
            'recomendacion_b3_b4' => null,
        ];

        foreach (self::PERFILES as $perfil) {
            $out['por_perfil'][$perfil] = [
                'nombre' => self::nombrePerfil($perfil),
                'por_horizonte' => [],
            ];
            foreach ($horizontes as $dias) {
                $runs = [];
                for ($s = 0; $s < $seeds; $s++) {
                    $rng = new RngService($seedBase . '-' . $perfil . '-' . $dias . '-' . $s);
                    $runs[] = self::correr($perfil, (int) $dias, $rng, $cal, $labCfg);
                }
                $out['por_perfil'][$perfil]['por_horizonte'][(string) $dias] = self::agregar($runs);
            }
        }

        $out['preocupantes'] = self::detectarPreocupantes($out);
        $out['recomendacion_b3_b4'] = self::recomendar($out, $labCfg);
        return $out;
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function labCfg(array $cal): array
    {
        return [
            '_etiqueta' => 'INPUT_DE_LAB',
            'misiones_dia' => (int) CalibracionConfig::get($cal, 'vida_pueblo.lab.misiones_dia', 3),
            'peticiones_dia' => (int) CalibracionConfig::get($cal, 'vida_pueblo.lab.peticiones_dia', 2),
            'mision_ok' => (int) CalibracionConfig::get($cal, 'vida_pueblo.lab.mision_ok', 2),
            'mision_fail' => (int) CalibracionConfig::get($cal, 'vida_pueblo.lab.mision_fail', -2),
            'peticion_ok' => (int) CalibracionConfig::get($cal, 'vida_pueblo.lab.peticion_ok', 2),
            'peticion_fail' => (int) CalibracionConfig::get($cal, 'vida_pueblo.lab.peticion_fail', -2),
            'hito' => (int) CalibracionConfig::get($cal, 'vida_pueblo.lab.hito', 4),
            'farming_tick' => (int) CalibracionConfig::get($cal, 'vida_pueblo.lab.farming_tick', 1),
            'farming_por_dia' => (int) CalibracionConfig::get($cal, 'vida_pueblo.lab.farming_por_dia', 12),
            'offline_teorico_por_dia' => (int) CalibracionConfig::get($cal, 'vida_pueblo.lab.offline_teorico_por_dia', 4),
        ];
    }

    public static function nombrePerfil(string $perfil): string
    {
        $n = [
            'A' => 'Jugador excelente',
            'B' => 'Jugador normal',
            'C' => 'Jugador malo',
            'D' => 'Jugador casi inactivo',
            'E' => 'Rachas malas',
            'F' => 'Recuperación desde crítico',
            'G' => 'Farming acciones pequeñas no válidas',
            'G_valid' => 'Farming +1 válido (control de exploit)',
        ];
        return $n[$perfil] ?? $perfil;
    }

    /**
     * @param array<string, mixed> $cal
     * @param array<string, mixed> $labCfg
     * @return array<string, mixed>
     */
    public static function correr(string $perfil, int $dias, RngService $rng, array $cal, array $labCfg): array
    {
        $partida = [
            'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 12, 'ultima_sesion_iso' => null],
            'meta' => ['seed' => 'lab'],
            'features' => [VidaPuebloEngine::FLAG => false],
        ];
        VidaPuebloEngine::ensure($partida, $cal);
        $inicial = VidaPuebloEngine::valor($partida);

        if ($perfil === 'F') {
            $objetivo = 12;
            $delta = $objetivo - $inicial;
            VidaPuebloEngine::aplicar($partida, $delta, [
                'causa' => VidaPuebloEngine::CAUSA_LAB_SETUP,
                'origen' => VidaPuebloEngine::ORIGEN_LAB,
                'atribuible_celestine' => true,
                'positivo_valido_latido' => false,
                'lab' => true,
                'fuente_id' => 'lab_setup_critico',
            ], $cal);
        }

        $min = VidaPuebloEngine::valor($partida);
        $max = $min;
        $diasCritico = 0;
        $go = 0;
        $diaGo = null;
        $salioDeCritico = false;
        $estuvoCritico = VidaPuebloEngine::valor($partida) <= 19;

        for ($d = 1; $d <= $dias; $d++) {
            $partida['reloj']['dia_pueblo'] = $d;
            $eventos = self::eventosDelDia($perfil, $d, $rng, $labCfg);
            foreach ($eventos as $ev) {
                VidaPuebloEngine::aplicar($partida, (int) $ev['delta'], $ev['meta'], $cal);
                $val = VidaPuebloEngine::valor($partida);
                if ($val < $min) {
                    $min = $val;
                }
                if ($val > $max) {
                    $max = $val;
                }
                if ($val === 0) {
                    $go++;
                    if ($diaGo === null) {
                        $diaGo = $d;
                    }
                    break 2;
                }
            }
            $val = VidaPuebloEngine::valor($partida);
            if ($val <= 19) {
                $diasCritico++;
                $estuvoCritico = true;
            } elseif ($estuvoCritico) {
                $salioDeCritico = true;
            }
        }

        $final = VidaPuebloEngine::valor($partida);
        $vp = $partida['vida_pueblo'];
        $latidos = (int) ($vp['latidos'] ?? 0);
        $espiral = $go > 0 && !$salioDeCritico;
        $farming = ($perfil === 'G' && $latidos > 0) || ($perfil === 'G_valid' && $latidos > max(1, (int) floor($dias / 8)));

        return [
            'inicial' => $perfil === 'F' ? 12 : $inicial,
            'min' => $min,
            'max' => $max,
            'final' => $final,
            'latidos' => $latidos,
            'primer_latido_dia' => $vp['primer_latido_dia'],
            'dias_en_critico' => $diasCritico,
            'game_over' => $go,
            'dia_game_over' => $diaGo,
            'salio_de_critico' => $salioDeCritico,
            'recuperable' => $estuvoCritico ? ($salioDeCritico || $final > 19) : null,
            'positivos_validos' => (int) ($vp['positivos_validos_total'] ?? 0),
            'negativos' => (int) ($vp['negativos_total'] ?? 0),
            'espiral_imposible' => $espiral,
            'farming_evidente' => $farming,
            'banda_final' => VidaPuebloEngine::banda($final, $cal)['id'],
        ];
    }

    /**
     * @param array<string, mixed> $labCfg
     * @return list<array{delta:int,meta:array}>
     */
    private static function eventosDelDia(string $perfil, int $dia, RngService $rng, array $labCfg): array
    {
        if ($perfil === 'G' || $perfil === 'G_valid') {
            $n = (int) $labCfg['farming_por_dia'];
            $tick = (int) $labCfg['farming_tick'];
            $valid = $perfil === 'G_valid';
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $out[] = self::evLab(
                    $tick,
                    VidaPuebloEngine::CAUSA_LAB,
                    $valid
                );
            }
            return $out;
        }

        $pMis = 0.55;
        $pPet = 0.50;
        $pHito = 0.0;
        $mis = (int) $labCfg['misiones_dia'];
        $pet = (int) $labCfg['peticiones_dia'];

        if ($perfil === 'A') {
            $pMis = 0.90;
            $pPet = 0.85;
            $pHito = 0.03;
        } elseif ($perfil === 'C') {
            $pMis = 0.22;
            $pPet = 0.18;
        } elseif ($perfil === 'D') {
            $pMis = 0.05;
            $pPet = 0.05;
        } elseif ($perfil === 'E') {
            $bloque = (int) floor(($dia - 1) / 5);
            $mala = ($bloque % 2) === 0;
            $pMis = $mala ? 0.08 : 0.70;
            $pPet = $mala ? 0.08 : 0.60;
        } elseif ($perfil === 'F') {
            $pMis = 0.90;
            $pPet = 0.85;
            $pHito = 0.02;
        }

        $out = [];
        for ($i = 0; $i < $mis; $i++) {
            $ok = $rng->nextFloat() < $pMis;
            $out[] = self::evLab(
                $ok ? (int) $labCfg['mision_ok'] : (int) $labCfg['mision_fail'],
                $ok ? VidaPuebloEngine::CAUSA_MISION_CUMPLIDA : VidaPuebloEngine::CAUSA_MISION_FALLIDA,
                $ok
            );
        }
        for ($i = 0; $i < $pet; $i++) {
            $ok = $rng->nextFloat() < $pPet;
            $out[] = self::evLab(
                $ok ? (int) $labCfg['peticion_ok'] : (int) $labCfg['peticion_fail'],
                $ok ? VidaPuebloEngine::CAUSA_PETICION_CUMPLIDA : VidaPuebloEngine::CAUSA_PETICION_CADUCADA,
                $ok
            );
        }
        if ($pHito > 0 && $rng->nextFloat() < $pHito) {
            $out[] = self::evLab((int) $labCfg['hito'], VidaPuebloEngine::CAUSA_HITO, true);
        }
        return $out;
    }

    /**
     * @return array{delta:int,meta:array<string,mixed>}
     */
    private static function evLab(int $delta, string $causa, bool $positivoValido): array
    {
        return [
            'delta' => $delta,
            'meta' => [
                'causa' => $causa,
                'origen' => VidaPuebloEngine::ORIGEN_LAB,
                'atribuible_celestine' => true,
                'positivo_valido_latido' => $positivoValido && $delta > 0,
                'lab' => true,
                'fuente_id' => 'INPUT_DE_LAB',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @return array<string, mixed>
     */
    private static function agregar(array $runs): array
    {
        $n = count($runs);
        if ($n === 0) {
            return [];
        }
        $keysNum = [
            'inicial', 'min', 'max', 'final', 'latidos', 'dias_en_critico',
            'game_over', 'positivos_validos', 'negativos',
        ];
        $agg = ['_runs' => $n, '_provisional' => true];
        foreach ($keysNum as $k) {
            $vals = [];
            foreach ($runs as $r) {
                $vals[] = (float) ($r[$k] ?? 0);
            }
            $agg[$k . '_media'] = round(array_sum($vals) / $n, 2);
            $agg[$k . '_min'] = min($vals);
            $agg[$k . '_max'] = max($vals);
        }
        $primers = [];
        foreach ($runs as $r) {
            if ($r['primer_latido_dia'] !== null) {
                $primers[] = (int) $r['primer_latido_dia'];
            }
        }
        $agg['primer_latido_dia_media'] = $primers === [] ? null : round(array_sum($primers) / count($primers), 1);
        $agg['pct_con_latido'] = round(100.0 * count($primers) / $n, 1);
        $go = 0;
        $espiral = 0;
        $farm = 0;
        $recup = 0;
        $recupN = 0;
        foreach ($runs as $r) {
            if ((int) $r['game_over'] > 0) {
                $go++;
            }
            if (!empty($r['espiral_imposible'])) {
                $espiral++;
            }
            if (!empty($r['farming_evidente'])) {
                $farm++;
            }
            if ($r['recuperable'] !== null) {
                $recupN++;
                if ($r['recuperable']) {
                    $recup++;
                }
            }
        }
        $agg['pct_game_over'] = round(100.0 * $go / $n, 1);
        $agg['pct_espiral'] = round(100.0 * $espiral / $n, 1);
        $agg['pct_farming'] = round(100.0 * $farm / $n, 1);
        $agg['pct_recuperacion'] = $recupN === 0 ? null : round(100.0 * $recup / $recupN, 1);
        $agg['dia_go_media'] = self::mediaCampo($runs, 'dia_game_over');
        return $agg;
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @return float|null
     */
    private static function mediaCampo(array $runs, string $k)
    {
        $vals = [];
        foreach ($runs as $r) {
            if ($r[$k] !== null) {
                $vals[] = (float) $r[$k];
            }
        }
        if ($vals === []) {
            return null;
        }
        return round(array_sum($vals) / count($vals), 1);
    }

    /**
     * @param array<string, mixed> $cal
     * @param array<string, mixed> $labCfg
     * @return array<string, mixed>
     */
    private static function escenariosOffline(array $cal, array $labCfg): array
    {
        $porDia = (int) $labCfg['offline_teorico_por_dia'];
        $casos = [
            '1d' => 1,
            '3d' => 3,
            '7d' => 7,
            '30d' => 30,
        ];
        $out = ['_nota' => 'Una ausencia. Cap −15. Suelo 5. Nunca GO.'];
        foreach ($casos as $k => $dias) {
            $teorico = -($porDia * $dias);
            $out[$k] = self::offlineDesde(65, $teorico, $cal);
        }
        $out['critico_12_30d'] = self::offlineDesde(12, -($porDia * 30), $cal);
        $out['critico_18_7d'] = self::offlineDesde(18, -($porDia * 7), $cal);
        return $out;
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    private static function offlineDesde(int $desde, int $teorico, array $cal): array
    {
        $partida = [
            'reloj' => ['dia_pueblo' => 3, 'hora_actual' => 10],
            'features' => [VidaPuebloEngine::FLAG => false],
        ];
        VidaPuebloEngine::ensure($partida, $cal);
        $ahora = VidaPuebloEngine::valor($partida);
        if ($desde !== $ahora) {
            VidaPuebloEngine::aplicar($partida, $desde - $ahora, [
                'causa' => VidaPuebloEngine::CAUSA_LAB_SETUP,
                'origen' => VidaPuebloEngine::ORIGEN_LAB,
                'atribuible_celestine' => true,
                'positivo_valido_latido' => false,
                'lab' => true,
            ], $cal);
        }
        $r = VidaPuebloEngine::aplicarAusencia($partida, $teorico, ['lab' => true], $cal);
        return [
            'desde' => $desde,
            'teorico' => $teorico,
            'aplicado' => $r['delta_capeado'] ?? $r['delta_aplicado'],
            'final' => VidaPuebloEngine::valor($partida),
            'game_over_pendiente' => (bool) ($partida['vida_pueblo']['game_over_pendiente'] ?? false),
            'game_over_activo' => (bool) ($partida['vida_pueblo']['game_over_activo'] ?? false),
            'suelo_aplicado' => (bool) ($r['suelo_aplicado'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $out
     * @return list<string>
     */
    private static function detectarPreocupantes(array $out): array
    {
        $p = [];
        $a30 = $out['por_perfil']['A']['por_horizonte']['30'] ?? [];
        if (($a30['primer_latido_dia_media'] ?? 99) !== null && (float) ($a30['primer_latido_dia_media'] ?? 99) < 8) {
            $p[] = 'A: primer Latido demasiado pronto en 30d (<8 días). B3/B4 quizá +2 y no +3, o menos aciertos diarios.';
        }
        $b30 = $out['por_perfil']['B']['por_horizonte']['30'] ?? [];
        if ((float) ($b30['pct_game_over'] ?? 0) > 40) {
            $p[] = 'B: demasiado GO en 30d para un jugador normal.';
        }
        $d7 = $out['por_perfil']['D']['por_horizonte']['7'] ?? [];
        if ((float) ($d7['pct_game_over'] ?? 0) > 90) {
            $p[] = 'D: GO casi seguro en 7d si se ignoran 3 misiones + 2 peticiones a −2. Quizá crítico avisado en B5.';
        }
        $f30 = $out['por_perfil']['F']['por_horizonte']['30'] ?? [];
        if (($f30['pct_recuperacion'] ?? 100) !== null && (float) $f30['pct_recuperacion'] < 80) {
            $p[] = 'F: recuperación desde crítico poco fiable con juego excelente. Subir deltas de éxito o bajar fallos.';
        }
        $g30 = $out['por_perfil']['G']['por_horizonte']['30'] ?? [];
        if ((float) ($g30['latidos_media'] ?? 0) > 0) {
            $p[] = 'G: farming no válido produce Latidos. El umbral 25 o el clamp 99 no está cortando.';
        }
        $gv30 = $out['por_perfil']['G_valid']['por_horizonte']['30'] ?? [];
        if ((float) ($gv30['latidos_media'] ?? 0) > 6) {
            $p[] = 'G_valid: +1 válidos fabrican Latidos de más. B3 no debe marcar microacciones como positivo_valido.';
        }
        $off = $out['offline']['30d'] ?? [];
        if (!empty($off['game_over_pendiente']) || !empty($off['game_over_activo'])) {
            $p[] = 'Offline 30d produce GO. Rompe la regla dura.';
        }
        $offC = $out['offline']['critico_12_30d'] ?? [];
        if ((int) ($offC['final'] ?? 5) === 0) {
            $p[] = 'Offline desde crítico llega a 0.';
        }
        return $p;
    }

    /**
     * @param array<string, mixed> $out
     * @param array<string, mixed> $labCfg
     * @return array<string, mixed>
     */
    private static function recomendar(array $out, array $labCfg): array
    {
        return [
            '_provisional' => true,
            'mision_ok' => (int) $labCfg['mision_ok'],
            'mision_fail' => (int) $labCfg['mision_fail'],
            'peticion_ok' => (int) $labCfg['peticion_ok'],
            'peticion_fail' => (int) $labCfg['peticion_fail'],
            'nota' => 'Partir de ±2. Si A latea antes de 10 días, no subir a +3 en B3. Microacciones nunca positivo_valido. Offline −15 ya aplasta 3d=30d en Vida; el cotilleo de B11 no debe sumar más Vida negativa.',
        ];
    }
}
