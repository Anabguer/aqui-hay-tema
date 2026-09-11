<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\ActividadPresupuesto;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\MotorVidaDiaria;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RngService;

$root = dirname(__DIR__);
DomainBootstrap::boot();

$N_SEEDS = 5;
$CONFIGS = [
    ['n' => 3, 'days' => 14, 'label' => '3r-14d'],
    ['n' => 5, 'days' => 14, 'label' => '5r-14d'],
    ['n' => 8, 'days' => 14, 'label' => '8r-14d'],
];

function simulateOnce(int $nTarget, int $maxDays, string $seed, array $cal, Catalog $catalog): array {
    global $root;
    $service = new PartidaService($root);
    $partida = $service->nuevaPartida('playtest_01', "sim-$seed");

    $allIds = array_keys($partida['residentes']);
    foreach ($allIds as $i => $id) {
        if ($i >= $nTarget) unset($partida['residentes'][$id]);
    }
    $ids = array_keys($partida['residentes']);
    $n = count($ids);

    foreach (['npc_autonomy_enabled','discovery_enabled','vida_pueblo_enabled','misiones_diarias_enabled','peticiones_pueblo_enabled'] as $f) {
        $partida['features'][$f] = true;
    }

    $npcActs = array_fill_keys($ids, 0);
    $npcDisc = array_fill_keys($ids, 0);
    $npcEncIn = array_fill_keys($ids, 0);
    $daily = [];
    $totalCands = 0;
    $totalRevs = 0;

    for ($dia = 1; $dia <= $maxDays; $dia++) {
        $partida['reloj']['dia_pueblo'] = $dia;
        $partida['reloj']['hora_actual'] = 8;
        $rng = new RngService("$seed-d$dia");
        MotorVidaDiaria::alComenzarDia($partida, $cal, $rng);

        $dEnc = 0; $dHue = 0; $dSal = 0; $dSoc = 0; $dDisc = 0;

        for ($hora = 8; $hora <= 23; $hora++) {
            $partida['reloj']['hora_actual'] = $hora;
            $result = MotorVidaDiaria::tickHora($partida, $catalog, $cal, $rng);

            if (isset($result['vida']['evento']) || isset($result['vida']['ok'])) {
                $dHue++; $dEnc++;
            }
            if (isset($result['autonomo']['quien']) && !isset($result['autonomo']['error'])) {
                $dSal++; $dEnc++;
                $w = $result['autonomo']['quien'];
                if (isset($npcActs[$w])) $npcActs[$w]++;
            }
            if (isset($result['iniciativa_social']['ok']) && $result['iniciativa_social']['ok']) {
                $dSoc++; $dEnc++;
            }

            EncuentroLifecycle::sincronizarConReloj($partida, null, $catalog);
        }

        // Count candidates from encounter resolution
        foreach ($partida['encuentros'] ?? [] as $enc) {
            $encDia = (int) ($enc['dia'] ?? 0);
            if ($encDia === $dia) {
                $cands = AquiHayTema\Engine\DiscoveryReveal::candidatosEncuentro(
                    $partida,
                    $enc['participantes'][0] ?? '',
                    $enc['participantes'][1] ?? '',
                    $enc,
                    $catalog
                );
                $totalCands += count($cands);
                $rev = $enc['resultado']['descubrimientos'] ?? [];
                $totalRevs += count($rev);
            }
        }

        // Track encounter participation
        foreach ($partida['encuentros'] ?? [] as $enc) {
            $encDia = (int) ($enc['dia'] ?? 0);
            if ($encDia === $dia && ($enc['estado'] ?? '') === 'terminado') {
                foreach ($enc['participantes'] ?? [] as $pid) {
                    if (isset($npcEncIn[$pid])) $npcEncIn[$pid]++;
                }
            }
        }

        // Track discoveries
        $dDisc = $partida['discovery_dia']['count'] ?? 0;
        foreach ($partida['discovery_dia']['por_residente'] ?? [] as $rid => $info) {
            if (($info['ultimo_dia'] ?? 0) === $dia && isset($npcDisc[$rid])) {
                $npcDisc[$rid]++;
            }
        }

        $daily[$dia] = compact('dEnc','dHue','dSal','dSoc','dDisc');
    }

    $totEnc = array_sum(array_column($daily, 'dEnc'));
    $totHue = array_sum(array_column($daily, 'dHue'));
    $totSal = array_sum(array_column($daily, 'dSal'));
    $totSoc = array_sum(array_column($daily, 'dSoc'));
    $totDisc = array_sum(array_column($daily, 'dDisc'));
    $emptyDays = count(array_filter(array_column($daily, 'dDisc'), fn($d) => $d === 0));
    $maxStreak = 0; $curStreak = 0;
    for ($dia = 1; $dia <= $maxDays; $dia++) {
        if ($daily[$dia]['dDisc'] === 0) { $curStreak++; $maxStreak = max($maxStreak, $curStreak); }
        else { $curStreak = 0; }
    }

    return [
        'totEnc' => $totEnc, 'totHue' => $totHue, 'totSal' => $totSal, 'totSoc' => $totSoc,
        'totDisc' => $totDisc, 'emptyDays' => $emptyDays, 'maxStreak' => $maxStreak,
        'npcActs' => $npcActs, 'npcDisc' => $npcDisc, 'npcEncIn' => $npcEncIn,
        'daily' => $daily, 'n' => $n, 'maxDays' => $maxDays,
        'totalCands' => $totalCands, 'totalRevs' => $totalRevs,
    ];
}

// Load cal once
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);

// Print config header
$nHob = (int) CalibracionConfig::get($cal, 'discovery.hobbies_iniciales', 0);
$nRas = (int) CalibracionConfig::get($cal, 'discovery.rasgos_iniciales', 0);
$maxDisc = (int) CalibracionConfig::get($cal, 'discovery.max_por_dia', 1);
$maxExp = (int) CalibracionConfig::get($cal, 'discovery.max_por_experiencia', 2);
$probEnc = (float) CalibracionConfig::get($cal, 'discovery.prob_por_encuentro', 0.5);
$cooldown = (int) CalibracionConfig::get($cal, 'discovery.cooldown_dias_por_residente', 2);

echo "=== MULTI-SEED PACING ANALYSIS ===\n";
echo "Config: init=${nHob}+${nRas} max_dia=$maxDisc max_exp=$maxExp prob=$probEnc cooldown=${cooldown}d seeds=$N_SEEDS\n\n";

foreach ($CONFIGS as $cfg) {
    $n = $cfg['n']; $days = $cfg['days']; $label = $cfg['label'];
    $results = [];
    for ($s = 0; $s < $N_SEEDS; $s++) {
        $results[] = simulateOnce($n, $days, "seed-$label-s$s", $cal, $catalog);
    }

    echo "--- $label (" . $N_SEEDS . " seeds) ---\n";

    // Aggregate
    $allDisc = array_column($results, 'totDisc');
    $allEmpty = array_column($results, 'emptyDays');
    $allMaxStreak = array_column($results, 'maxStreak');
    $avgDisc = array_sum($allDisc) / count($allDisc);
    $avgEmpty = array_sum($allEmpty) / count($allEmpty);
    $avgMaxStreak = array_sum($allMaxStreak) / count($allMaxStreak);
    $minDisc = min($allDisc); $maxDiscR = max($allDisc);

    echo "  Disc total: min=$minDisc max=$maxDiscR avg=" . round($avgDisc, 1) . " (all gameplay — sim doesn't apply initial hobbies)\n";
    echo "  Empty days: avg=" . round($avgEmpty, 1) . " max_streak: avg=" . round($avgMaxStreak, 1) . "\n";

    // NPC analysis (from seed 0 for simplicity)
    $r0 = $results[0];
    $zeroSolo = 0; $zeroEnc = 0;
    foreach ($r0['npcActs'] as $id => $a) {
        if ($a === 0) $zeroSolo++;
    }
    foreach ($r0['npcEncIn'] as $id => $e) {
        if ($e === 0) $zeroEnc++;
    }
    echo "  NPC 0 solo outings: $zeroSolo/" . $r0['n'] . " | NPC 0 encounters: $zeroEnc/" . $r0['n'] . "\n";
    echo "  Candidates generated: " . $r0['totalCands'] . " | Reveals recorded: " . $r0['totalRevs'] . "\n";

    // NPC disc distribution (seed 0)
    echo "  NPC disc: ";
    foreach ($r0['npcDisc'] as $id => $d) echo "$id=$d ";
    echo "\n\n";
}

echo "=== INTERACCIONCASUAL TRACE ===\n";
echo "Entry point 1: MotorVidaDiaria::casualesDeHora() → tickHora()\n";
echo "  Consequence of encounters at same place → BUDGETED activity\n";
echo "Entry point 2: CoincidenciasInteraccionBridge → RelojOperations/RelojDev\n";
echo "  Detects coincidences when NPCs at same place → BUDGETED activity\n";
echo "Both paths: consequence of budgeted encounters. No autonomous path.\n";
echo "Decision: PATH A — no additional budget integration needed.\n";
