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

function simulate(int $nTarget, int $maxDays, string $label): void {
    global $root;
    $service = new PartidaService($root);
    $catalog = new Catalog($root);
    $cal = CalibracionConfig::load($root);
    $partida = $service->nuevaPartida('playtest_01', "sim-$label");

    $allIds = array_keys($partida['residentes']);
    foreach ($allIds as $i => $id) {
        if ($i >= $nTarget) unset($partida['residentes'][$id]);
    }
    $ids = array_keys($partida['residentes']);
    $n = count($ids);

    foreach (['npc_autonomy_enabled','discovery_enabled','vida_pueblo_enabled','misiones_diarias_enabled','peticiones_pueblo_enabled'] as $f) {
        $partida['features'][$f] = true;
    }

    $huecoPresupuesto = MotorVidaDiaria::presupuesto($n, $cal, new RngService('hc'));
    $globalBudgetD1 = ActividadPresupuesto::calcularPresupuesto($n, 1, $cal);
    $globalBudgetN = ActividadPresupuesto::calcularPresupuesto($n, 2, $cal);
    $maxHuecoRatio = (float) CalibracionConfig::get($cal, 'presupuesto_actividad.max_hueco_ratio', 0.6);
    $huecoMax = (int) ceil($globalBudgetN * $maxHuecoRatio);
    $capNPC = (int) CalibracionConfig::get($cal, 'autonomia.cap_acciones_por_npc_dia', 6);
    $maxDisc = (int) CalibracionConfig::get($cal, 'discovery.max_por_dia', 3);
    $probEnc = (float) CalibracionConfig::get($cal, 'discovery.prob_por_encuentro', 0.4);
    $cooldown = (int) CalibracionConfig::get($cal, 'discovery.cooldown_dias_por_residente', 2);
    $nHobInit = (int) CalibracionConfig::get($cal, 'discovery.hobbies_iniciales', 1);
    $nRasInit = (int) CalibracionConfig::get($cal, 'discovery.rasgos_iniciales', 0);

    echo "=== SIM: $label ($n residents, $maxDays days) ===\n";
    echo "HuecoSchedule=$huecoPresupuesto | GlobalBudget: d1=$globalBudgetD1 d2+=$globalBudgetN | HuecoMax=$huecoMax (ratio=$maxHuecoRatio)\n";
    echo "capNPC=$capNPC | disc: init=${nHobInit}+${nRasInit} max_dia=$maxDisc prob=$probEnc cooldown=${cooldown}d\n\n";

    $daily = [];
    $npcActs = array_fill_keys($ids, 0);
    $npcDisc = array_fill_keys($ids, 0);

    for ($dia = 1; $dia <= $maxDays; $dia++) {
        $partida['reloj']['dia_pueblo'] = $dia;
        $partida['reloj']['hora_actual'] = 8;
        $rng = new RngService("sim-$label-d$dia");
        MotorVidaDiaria::alComenzarDia($partida, $cal, $rng);

        $bTotal = $partida['presupuesto_actividad']['total'] ?? 0;
        $huecosHoras = $partida['huecos_vida']['horas'] ?? [];
        $huecoCount = count($huecosHoras);

        $dEnc = 0; $dHue = 0; $dSal = 0; $dSoc = 0; $dDisc = 0; $dBloq = 0; $dHueBloq = 0;

        for ($hora = 8; $hora <= 23; $hora++) {
            $partida['reloj']['hora_actual'] = $hora;
            $result = MotorVidaDiaria::tickHora($partida, $catalog, $cal, $rng);

            if (isset($result['vida']['evento']) || isset($result['vida']['ok'])) {
                $dHue++; $dEnc++;
            }
            if (!empty($result['hueco_bloqueado_presupuesto'])) {
                $dHueBloq++;
            }
            if (isset($result['autonomo']['quien']) && !isset($result['autonomo']['error'])) {
                $dSal++; $dEnc++;
                $w = $result['autonomo']['quien'];
                if (isset($npcActs[$w])) $npcActs[$w]++;
            }
            if (isset($result['iniciativa_social']['ok']) && $result['iniciativa_social']['ok']) {
                $dSoc++; $dEnc++;
            }
            if (!empty($result['presupuesto_agotado_salida'])) $dBloq++;
            if (!empty($result['presupuesto_agotado_social'])) $dBloq++;

            // Resolve encounters at their scheduled time (triggers discovery)
            EncuentroLifecycle::sincronizarConReloj($partida, null, $catalog);
        }

        $dDisc = $partida['discovery_dia']['count'] ?? 0;
        foreach ($partida['discovery_dia']['por_residente'] ?? [] as $rid => $info) {
            if (($info['ultimo_dia'] ?? 0) === $dia && isset($npcDisc[$rid])) {
                $npcDisc[$rid]++;
            }
        }

        $bCons = $partida['presupuesto_actividad']['consumido'] ?? 0;
        $daily[$dia] = compact('dEnc','dHue','dSal','dSoc','dDisc','dBloq','bTotal','bCons','huecoCount','dHueBloq');
    }

    echo "Día | HxcSched | Budget | Used | Enc | Hue | HxBloq | Sal | Soc | Disc | Bloq\n";
    echo str_repeat('-', 80) . "\n";
    $emptyDays = 0;
    for ($dia = 1; $dia <= $maxDays; $dia++) {
        $d = $daily[$dia];
        printf("%3d | %8d | %6d | %4d | %3d | %3d | %6d | %3d | %3d | %4d | %4d\n",
            $dia, $d['huecoCount'], $d['bTotal'], $d['bCons'],
            $d['dEnc'], $d['dHue'], $d['dHueBloq'], $d['dSal'], $d['dSoc'], $d['dDisc'], $d['dBloq']);
        if ($d['dDisc'] === 0) $emptyDays++;
    }
    echo str_repeat('-', 80) . "\n";

    $totEnc = array_sum(array_column($daily, 'dEnc'));
    $totHue = array_sum(array_column($daily, 'dHue'));
    $totHueBloq = array_sum(array_column($daily, 'dHueBloq'));
    $totSal = array_sum(array_column($daily, 'dSal'));
    $totSoc = array_sum(array_column($daily, 'dSoc'));
    $totDisc = array_sum(array_column($daily, 'dDisc'));
    $totBloq = array_sum(array_column($daily, 'dBloq'));

    echo "TOTAL: enc=$totEnc hue=$totHue(hxBlock=$totHueBloq) sal=$totSal soc=$totSoc disc=$totDisc bloq=$totBloq\n";
    echo "AVG: enc/día=" . round($totEnc/$maxDays,2) . " disc/día=" . round($totDisc/$maxDays,2) . "\n";
    echo "Días sin discovery: $emptyDays/$maxDays\n";

    echo "NPC acts: ";
    foreach ($npcActs as $id => $a) echo "$id=$a ";
    echo "\nNPC disc: ";
    foreach ($npcDisc as $id => $d) echo "$id=$d ";
    echo "\n";

    $actVals = array_values($npcActs);
    $actSum = array_sum($actVals);
    if ($actSum > 0) {
        $pcts = array_map(fn($a) => $a > 0 ? round($a/$actSum*100,1).'%' : '0%', $actVals);
        echo "Distribution: " . implode(', ', $pcts) . " | max=" . round(max($actVals)/$actSum*100,1) . "%\n";
    }
    $capHits = 0;
    foreach ($actVals as $a) { if ($a >= $capNPC) $capHits++; }
    echo "NPCs at cap($capNPC): $capHits/$n\n\n";
}

simulate(3, 3, '3r-3d');
simulate(3, 14, '3r-14d');
simulate(5, 14, '5r-14d');
simulate(8, 14, '8r-14d');
