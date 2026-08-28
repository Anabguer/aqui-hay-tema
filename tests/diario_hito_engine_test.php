<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\DiarioHitoEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;

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

DomainBootstrap::boot();
$service = new PartidaService($root);
$cal = CalibracionConfig::load($root);

// --- 1) Hito relacional → entrada propia (no espejo) ---
$p1 = $service->nuevaPartida('juego_v1', 'diario-hito-rup');
$ids = array_keys($p1['residentes']);
$a = (string) $ids[0];
$b = (string) $ids[1];
RelacionBitacora::registrar($p1, RelacionBitacora::RUPTURA, [$a, $b]);
$clave = 'diario_hito:' . RelacionBitacora::RUPTURA . ':' . implode('|', $a < $b ? [$a, $b] : [$b, $a]);
$propia = DiarioEngine::entradaPorEvento($p1, $clave);
ok($propia !== null, '1. ruptura genera entrada diario_hito propia');
ok(($propia['tipo'] ?? '') === 'diario_hito', '1. tipo diario_hito');
ok(trim((string) ($propia['titulo'] ?? '')) !== '', '1. titulo no vacío');
ok(!isset($propia['cotilleo_meta']), '1. sin cotilleo_meta (memoria privada)');

// --- 2) Idempotencia ---
$nAntes = count($p1['diario'] ?? []);
DiarioHitoEngine::alHito($p1, [
    'tipo' => RelacionBitacora::RUPTURA,
    'participantes' => [$a, $b],
    'id' => 'hito_test',
]);
ok(count($p1['diario'] ?? []) === $nAntes, '2. segundo alHito no duplica');

// --- 3) Micro-hito excluido: plan_significativo ---
$p3 = $service->nuevaPartida('juego_v1', 'diario-hito-plan');
$ids3 = array_keys($p3['residentes']);
RelacionBitacora::registrar($p3, RelacionBitacora::PLAN_SIGNIFICATIVO, [(string) $ids3[0], (string) $ids3[1]]);
$clavePlan = 'diario_hito:' . RelacionBitacora::PLAN_SIGNIFICATIVO . ':'
    . implode('|', $ids3[0] < $ids3[1] ? [(string) $ids3[0], (string) $ids3[1]] : [(string) $ids3[1], (string) $ids3[0]]);
ok(DiarioEngine::entradaPorEvento($p3, $clavePlan) === null, '3. plan_significativo no genera diario_hito');

// --- 4) Regalo / flores ---
$p4 = $service->nuevaPartida('juego_v1', 'diario-hito-regalo');
$ida = (string) array_key_first($p4['residentes']);
$idb = null;
foreach (array_keys($p4['residentes']) as $rid) {
    if ($rid !== $ida) {
        $idb = (string) $rid;
        break;
    }
}
RelacionBitacora::registrar($p4, RelacionBitacora::REGALO, [$ida, $idb]);
$claveRegalo = 'diario_hito:' . RelacionBitacora::REGALO . ':' . implode('|', $ida < $idb ? [$ida, $idb] : [$idb, $ida]);
ok(DiarioEngine::entradaPorEvento($p4, $claveRegalo) !== null, '4. regalo → diario_hito');

// --- 5) Rechazo importante ---
$p5 = $service->nuevaPartida('juego_v1', 'diario-hito-rechazo');
RelacionBitacora::registrar($p5, RelacionBitacora::RECHAZO_IMPORTANTE, [$ida, $idb], $ida . '>' . $idb);
$claveRech = 'diario_hito:' . RelacionBitacora::RECHAZO_IMPORTANTE . ':' . implode('|', $ida < $idb ? [$ida, $idb] : [$idb, $ida]);
ok(DiarioEngine::entradaPorEvento($p5, $claveRech) !== null, '5. rechazo_importante → diario_hito');

// --- 6) listarPorResidente incluye hitos propios ---
$porA = DiarioEngine::listarPorResidente($p1, $a);
ok(count($porA) >= 1, '6. residente implicado ve su hito');

// --- 7) Backfill desde bitácora (save antiguo) ---
$p7 = $service->nuevaPartida('juego_v1', 'diario-hito-backfill');
$xa = (string) array_key_first($p7['residentes']);
$xb = null;
foreach (array_keys($p7['residentes']) as $rid) {
    if ($rid !== $xa) {
        $xb = (string) $rid;
        break;
    }
}
$p7['bitacora_relaciones'][] = [
    'id' => 'hito_legacy_1',
    'tipo' => RelacionBitacora::PRIMERA_CITA,
    'fecha' => ['dia' => 2, 'hora' => 10],
    'participantes' => [$xa, $xb],
    'par' => $xa < $xb ? [$xa, $xb] : [$xb, $xa],
    'direccion' => null,
    'resultado' => null,
    'intensidad' => null,
    'meta' => [],
];
$p7['diario'] = [];
$p7['diario_hitos_registrados'] = [];
$n = DiarioHitoEngine::sincronizarDesdeBitacora($p7);
$clavePc = 'diario_hito:' . RelacionBitacora::PRIMERA_CITA . ':' . implode('|', $xa < $xb ? [$xa, $xb] : [$xb, $xa]);
ok($n >= 1 && DiarioEngine::entradaPorEvento($p7, $clavePc) !== null, '7. backfill bitácora → diario_hito');

// --- 8) Contenido propio distinto del espejo cotilleo ---
$p8 = $service->nuevaPartida('juego_v1', 'diario-hito-diff');
$p8['features']['buzon_enabled'] = true;
RelacionBitacora::registrar($p8, RelacionBitacora::SE_CONOCIERON, [$ida, $idb]);
$claveCot = RelacionBitacora::SE_CONOCIERON . ':' . implode('|', $ida < $idb ? [$ida, $idb] : [$idb, $ida]);
$espejo = DiarioEngine::entradaPorEvento($p8, $claveCot);
$propia8 = DiarioEngine::entradaPorEvento($p8, 'diario_hito:' . $claveCot);
ok($espejo !== null && $propia8 !== null, '8. coexisten espejo cotilleo y entrada propia');
ok(
    trim((string) ($espejo['texto'] ?? '')) !== trim((string) ($propia8['texto'] ?? ''))
    || ($propia8['titulo'] ?? '') !== '',
    '8. entrada propia aporta titulo/memoria distinta del espejo'
);

echo $failures === 0 ? "OK diario_hito_engine\n" : "FAIL diario_hito_engine ({$failures})\n";
exit($failures > 0 ? 1 : 0);
