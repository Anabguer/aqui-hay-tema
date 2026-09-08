<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

$root = dirname(__DIR__);
require_once $root . '/src/autoload.php';

use AquiHayTema\Engine\{Catalog, PartidaService, ResidenteCumpleanosEngine, FichaPlayVista, SchemaFields};

header('Content-Type: text/plain; charset=utf-8');

$catalog = new Catalog($root);
$svc = new PartidaService($root);

$partidas = glob($root . '/saves/partida_*.json');
if (empty($partidas)) {
    echo "NO_SAVES\n";
    exit(0);
}

sort($partidas);
$savePath = end($partidas);
$partida = json_decode(file_get_contents($savePath), true);

if (!is_array($partida)) {
    echo "INVALID_SAVE\n";
    exit(0);
}

echo "SAVE: " . basename($savePath) . "\n";
echo "RESIDENTES: " . count($partida['residentes'] ?? []) . "\n\n";

SchemaFields::ensure($partida);

$rids = array_keys($partida['residentes'] ?? []);
$rid = $rids[0] ?? null;
if (!$rid) {
    echo "NO_RESIDENTS\n";
    exit(0);
}

echo "=== STEP 1: ResidenteCumpleanosEngine::obtener() ===\n";
$cp = ResidenteCumpleanosEngine::obtener($partida, $rid, $catalog);
echo "obtener() = " . json_encode($cp) . "\n";
echo "type: " . gettype($cp) . "\n";
if (is_array($cp)) {
    echo "dia: " . ($cp['dia'] ?? 'MISSING') . " (type: " . gettype($cp['dia']) . ")\n";
    echo "mes: " . ($cp['mes'] ?? 'MISSING') . " (type: " . gettype($cp['mes']) . ")\n";
    echo "dia truthy: " . ($cp['dia'] ? 'YES' : 'NO') . "\n";
    echo "mes truthy: " . ($cp['mes'] ? 'YES' : 'NO') . "\n";
}

echo "\n=== STEP 2: identidad_publica cumpleanos (raw save) ===\n";
$raw = $partida['residentes'][$rid]['identidad_publica']['cumpleanos'] ?? 'NOT_SET';
echo "raw = " . json_encode($raw) . "\n";

echo "\n=== STEP 3: fichaResidente(respuestaLigera=true) ===\n";
$ficha = $svc->fichaResidente($partida, $rid, true);
echo "ficha keys: " . implode(', ', array_keys($ficha)) . "\n";
echo "ficha.vista_play = " . (isset($ficha['vista_play']) ? 'EXISTS' : 'MISSING') . "\n";
echo "ficha.vista_play.cumpleanos = " . json_encode($ficha['vista_play']['cumpleanos'] ?? 'MISSING') . "\n";
echo "ficha.identidad = " . json_encode($ficha['identidad'] ?? []) . "\n";
echo "ficha.identidad.cumpleanos = " . json_encode($ficha['identidad']['cumpleanos'] ?? 'NOT_IN_LIGHT_RESPONSE') . "\n";

echo "\n=== STEP 4: vista_play keys ===\n";
$vista = $ficha['vista_play'] ?? [];
echo "keys: " . implode(', ', array_keys($vista)) . "\n";

echo "\n=== STEP 5: Simula JS exacto ===\n";
$jsonStr = json_encode($ficha, JSON_UNESCAPED_UNICODE);
$fe = json_decode($jsonStr, true);
$fJs = $fe;
$vistaJs = $fJs['vista_play'] ?? $fJs;
echo "vista = f.vista_play || f -> " . ($vistaJs === ($fJs['vista_play'] ?? null) ? 'f.vista_play' : 'f') . "\n";
echo "vista.cumpleanos = " . json_encode($vistaJs['cumpleanos'] ?? 'UNDEF') . "\n";
$cpJs = $vistaJs['cumpleanos'] ?? ($fJs['identidad']['cumpleanos'] ?? null);
echo "cp = vista.cumpleanos || (f.identidad && f.identidad.cumpleanos) = " . json_encode($cpJs) . "\n";
$pass = !empty($cpJs) && !empty($cpJs['dia']) && !empty($cpJs['mes']);
echo "cp && cp.dia && cp.mes = " . ($pass ? 'TRUE' : 'FALSE') . "\n";

if ($pass) {
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    echo "textContent = 'Cumplea\u00f1os: ' + cp.dia + ' de ' + meses[cp.mes-1] = \"Cumpleaños: " . $cpJs['dia'] . " de " . ($meses[($cpJs['mes'] | 0) - 1] ?? '???') . "\"\n";
}

echo "\n=== STEP 6: Todos los residentes ===\n";
foreach ($rids as $r) {
    $cpR = ResidenteCumpleanosEngine::obtener($partida, $r, $catalog);
    $nom = $partida['residentes'][$r]['identidad_publica']['nombre'] ?? $r;
    echo "$r ($nom): " . json_encode($cpR) . "\n";
}
