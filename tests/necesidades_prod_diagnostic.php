<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\NecesidadEstado;
use AquiHayTema\Engine\CalibracionConfig;

// Replicate the production flow EXACTLY
$root = dirname(__DIR__);
$partidaPath = $root . '/data/partidas/part_77efa3bcfc7f5606.json';

// 1. Load save (like repo->cargar)
$raw = file_get_contents($partidaPath);
$partida = json_decode($raw, true);

echo "=== BEFORE mergeIntoPartida ===\n";
echo "features.necesidades_enabled: " . var_export($partida['features']['necesidades_enabled'] ?? 'MISSING', true) . "\n";
echo "features.encuentros_enabled: " . var_export($partida['features']['encuentros_enabled'] ?? 'MISSING', true) . "\n";
echo "hora: " . ($partida['reloj']['hora_actual'] ?? '?') . "\n";

// 2. Apply FeatureConfig::mergeIntoPartida (like requirePartida does)
FeatureConfig::mergeIntoPartida($partida, $root);

echo "\n=== AFTER mergeIntoPartida ===\n";
echo "features.necesidades_enabled: " . var_export($partida['features']['necesidades_enabled'] ?? 'MISSING', true) . "\n";
echo "features.encuentros_enabled: " . var_export($partida['features']['encuentros_enabled'] ?? 'MISSING', true) . "\n";

// 3. Check all features
echo "\nAll features:\n";
foreach ($partida['features'] as $k => $v) {
    echo "  $k = " . var_export($v, true) . "\n";
}

// 4. Check isEnabled results
echo "\n=== isEnabled checks ===\n";
echo "necesidades_enabled: " . var_export(FeatureConfig::isEnabled($partida, 'necesidades_enabled'), true) . "\n";
echo "encuentros_enabled: " . var_export(FeatureConfig::isEnabled($partida, 'encuentros_enabled'), true) . "\n";
echo "npc_autonomy_enabled: " . var_export(FeatureConfig::isEnabled($partida, 'npc_autonomy_enabled'), true) . "\n";

// 5. Simulate one tickNecesidades
echo "\n=== BEFORE tickNecesidades ===\n";
foreach ($partida['residentes'] as $rid => &$res) {
    echo "$rid: runtime.necesidades = " . var_export($res['runtime']['necesidades'] ?? 'MISSING', true) . "\n";
}

// Apply tickNecesidades (replicate MotorVidaDiaria::tickNecesidades)
$cal = CalibracionConfig::load($root);
if (FeatureConfig::isEnabled($partida, 'necesidades_enabled')) {
    echo "\nnecesidades_enabled is TRUE → applying decay\n";
    foreach ($partida['residentes'] as &$res) {
        NecesidadEstado::ensureResidente($res);
        NecesidadEstado::aplicarDecay($res, $cal);
    }
    unset($res);
} else {
    echo "\nnecesidades_enabled is FALSE → SKIPPING decay\n";
}

echo "\n=== AFTER tickNecesidades ===\n";
foreach ($partida['residentes'] as $rid => &$res) {
    $rt = $res['runtime']['necesidades'] ?? null;
    if ($rt) {
        foreach ($rt as $nec => $data) {
            echo "$rid.$nec: valor=" . $data['valor'] . " banda=" . $data['banda'] . "\n";
        }
    } else {
        echo "$rid: NO NECESIDADES\n";
    }
}

// 6. Also check the $runVidaHoraria condition
echo "\n=== runVidaHoraria check ===\n";
$labVida = !empty($partida['lab_vida_activa']);
$calTick = CalibracionConfig::load($root);
$activoPlay = (bool) CalibracionConfig::get($calTick, 'acontecimientos_dia.activo_en_play', false);
$npcAuto = FeatureConfig::isEnabled($partida, 'npc_autonomy_enabled');
$encuentros = FeatureConfig::isEnabled($partida, 'encuentros_enabled');
echo "lab_vida_activa: " . var_export($labVida, true) . "\n";
echo "activo_en_play: " . var_export($activoPlay, true) . "\n";
echo "npc_autonomy_enabled: " . var_export($npcAuto, true) . "\n";
echo "encuentros_enabled: " . var_export($encuentros, true) . "\n";
echo "runVidaHoraria (OR): " . var_export($labVida || $activoPlay || $npcAuto || $encuentros, true) . "\n";
