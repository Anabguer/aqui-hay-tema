<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\NombresReservadosPartida;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ResidenteOperations;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$cat = new Catalog($root);
$svc = new PartidaService($root);

// ============================================================
// A. nombre libre → se usa
// ============================================================
$p = $svc->nuevaPartida('test_fixtures_v0', 'dup-name-free');
$ops = new ResidenteOperations($cat);
$r = $ops->incorporarCatalogo($p, 'per_p019', 'residente');
ok($r['ok'] ?? false, 'A: incorpora per_p019 (Diego 30)');
ok(($p['residentes']['per_p019']['identidad_publica']['nombre'] ?? '') === 'Diego', 'A: nombre visible es Diego');

// ============================================================
// B. nombre ya usado → no se repite
// ============================================================
$r2 = $ops->incorporarCatalogo($p, 'per_p109', 'residente');
ok(!($r2['ok'] ?? false), 'B: per_p109 (Diego 24) rechazado — nombre duplicado');
ok(($r2['error'] ?? '') === 'nombre_duplicado', 'B: error es nombre_duplicado');

// ============================================================
// C. varios nombres ocupados → busca otro (poolDisponible)
// ============================================================
$p2 = $svc->nuevaPartida('test_fixtures_v0', 'dup-name-pool');
$ops2 = new ResidenteOperations($cat);
$ops2->incorporarCatalogo($p2, 'per_p019', 'residente');
use AquiHayTema\Engine\CandidatoLlegadaEngine;
$pool = CandidatoLlegadaEngine::poolDisponible($p2, $root);
ok(!in_array('per_p109', $pool, true), 'C: per_p109 (Diego 24) no está en pool cuando Diego 30 está activo');

// ============================================================
// D. comparación case-insensitive
// ============================================================
$p3 = $svc->nuevaPartida('test_fixtures_v0', 'dup-name-case');
$ops3 = new ResidenteOperations($cat);
$ops3->incorporarCatalogo($p3, 'per_p019', 'residente');
$nombreLower = strtolower($p3['residentes']['per_p019']['identidad_publica']['nombre'] ?? '');
ok($nombreLower === 'diego', 'D: nombre normalizado es "diego"');
$usados = NombresReservadosPartida::usados($p3, $root);
ok(isset($usados['diego']), 'D: "diego" en mapa de usados');
ok(NombresReservadosPartida::idBloqueado($usados, $root, 'per_p109'), 'D: per_p109 bloqueado por nombre');

// ============================================================
// E. llegada inicial + llegada posterior → no duplica
// ============================================================
$p4 = $svc->nuevaPartida('juego_v1', 'dup-name-init');
$tieneDiego = false;
foreach ($p4['residentes'] as $rid => $rdata) {
    $n = strtolower($rdata['identidad_publica']['nombre'] ?? '');
    if ($n === 'diego') {
        $tieneDiego = true;
        break;
    }
}
// Si el config inicial ya tiene Diego, intentar incorporar el otro
if ($tieneDiego) {
    $otro = ($p4['residentes']['per_p019'] ?? null) !== null ? 'per_p109' : 'per_p019';
    $rE = $ops->incorporarCatalogo($p4, $otro, 'residente');
    ok(!($rE['ok'] ?? false), 'E: segundo Diego rechazado tras llegada inicial');
} else {
    ok(true, 'E: config sin Diego, skip (caso cubierto por B)');
}

// ============================================================
// F. varias llegadas seguidas → no duplica
// ============================================================
$p5 = $svc->nuevaPartida('test_fixtures_v0', 'dup-name-seq');
$opsF = new ResidenteOperations($cat);
$opsF->incorporarCatalogo($p5, 'per_p019', 'residente');
$rF1 = $opsF->incorporarCatalogo($p5, 'per_p109', 'residente');
ok(!($rF1['ok'] ?? false), 'F1: segundo Diego rechazado');
$rF2 = $opsF->incorporarCatalogo($p5, 'per_p109', 'residente');
ok(!($rF2['ok'] ?? false), 'F2: tercer intento Diego sigue rechazado');

// ============================================================
// G. máximo de residentes si aplica → no rompe lógica de 16
// ============================================================
use AquiHayTema\Engine\CapacidadViviendas;
$p6 = $svc->nuevaPartida('test_fixtures_v0', 'dup-name-cap16');
$opsG = new ResidenteOperations($cat);
$added = 1;
while (CapacidadViviendas::huecos($p6) > 0) {
    $disp = CandidatoLlegadaEngine::poolDisponible($p6, $root);
    if ($disp === []) {
        break;
    }
    $rG = $opsG->incorporarCatalogo($p6, $disp[0], 'residente');
    if (!($rG['ok'] ?? false)) {
        break;
    }
    $added++;
}
ok(CapacidadViviendas::huecos($p6) >= 0, 'G: huecos >= 0 al llenar');
ok($added <= 17, 'G: no se excede de 17 incorporaciones');

echo $failures === 0 ? "OK nombres_duplicados_residentes_test\n" : "FAIL nombres_duplicados_residentes_test ({$failures})\n";
exit($failures > 0 ? 1 : 0);
