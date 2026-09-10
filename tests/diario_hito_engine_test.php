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

/**
 * Busca entrada de diario_hito por tipo y actor (nuevo formato per-actor).
 */
function buscarHito(array $partida, string $tipo, string $actorId): ?array
{
    foreach ($partida['diario'] ?? [] as $e) {
        if (!is_array($e)) continue;
        if (($e['tipo'] ?? '') !== 'diario_hito') continue;
        if (($e['subtipo'] ?? '') !== $tipo) continue;
        if (in_array($actorId, $e['actores'] ?? [], true)) {
            return $e;
        }
    }
    return null;
}

DomainBootstrap::boot();
$service = new PartidaService($root);
$cal = CalibracionConfig::load($root);

// --- 1) Hito relacional → entrada propia por actor (primera persona) ---
$p1 = $service->nuevaPartida('juego_v1', 'diario-hito-rup');
$ids = array_keys($p1['residentes']);
$a = (string) $ids[0];
$b = (string) $ids[1];
RelacionBitacora::registrar($p1, RelacionBitacora::RUPTURA, [$a, $b]);
$propiaA = buscarHito($p1, RelacionBitacora::RUPTURA, $a);
$propiaB = buscarHito($p1, RelacionBitacora::RUPTURA, $b);
ok($propiaA !== null, '1. ruptura genera entrada para actor A');
ok($propiaB !== null, '1. ruptura genera entrada para actor B');
ok(($propiaA['tipo'] ?? '') === 'diario_hito', '1. tipo diario_hito');
ok(trim((string) ($propiaA['titulo'] ?? '')) !== '', '1. titulo no vacío');
ok(!isset($propiaA['cotilleo_meta']), '1. sin cotilleo_meta (memoria privada)');
ok(
    in_array($a, $propiaA['actores'] ?? [], true) && !in_array($b, $propiaA['actores'] ?? [], true),
    '1. entrada de A solo contiene a A'
);
ok(
    in_array($b, $propiaB['actores'] ?? [], true) && !in_array($a, $propiaB['actores'] ?? [], true),
    '1. entrada de B solo contiene a B'
);

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
ok(buscarHito($p3, RelacionBitacora::PLAN_SIGNIFICATIVO, (string) $ids3[0]) === null, '3. plan_significativo no genera diario_hito');

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
ok(buscarHito($p4, RelacionBitacora::REGALO, $ida) !== null, '4. regalo → diario_hito para actor');

// --- 5) Rechazo importante ---
$p5 = $service->nuevaPartida('juego_v1', 'diario-hito-rechazo');
RelacionBitacora::registrar($p5, RelacionBitacora::RECHAZO_IMPORTANTE, [$ida, $idb], $ida . '>' . $idb);
ok(buscarHito($p5, RelacionBitacora::RECHAZO_IMPORTANTE, $ida) !== null, '5. rechazo_importante → diario_hito');

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
ok($n >= 1 && buscarHito($p7, RelacionBitacora::PRIMERA_CITA, $xa) !== null, '7. backfill bitácora → diario_hito');

// --- 8) Entrada propia por actor (espejo cotilleo ya no se refleja al diario) ---
$p8 = $service->nuevaPartida('juego_v1', 'diario-hito-diff');
$p8['features']['buzon_enabled'] = true;
RelacionBitacora::registrar($p8, RelacionBitacora::SE_CONOCIERON, [$ida, $idb]);
$propia8 = buscarHito($p8, RelacionBitacora::SE_CONOCIERON, $ida);
ok($propia8 !== null, '8. entrada propia existe para actor');
ok(
    ($propia8['titulo'] ?? '') !== '',
    '8. entrada propia aporta titulo'
);

echo $failures === 0 ? "OK diario_hito_engine\n" : "FAIL diario_hito_engine ({$failures})\n";
exit($failures > 0 ? 1 : 0);
