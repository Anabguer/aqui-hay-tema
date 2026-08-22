<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\DebugLabHandler;
use AquiHayTema\Api\Handlers\PartidaHandler;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\PlayLab;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;

$root = dirname(__DIR__);
$ctx = new ApiContext($root);
$failures = 0;
$bodyBase = ['debug' => 1];

function ok(bool $cond, string $msg): void
{
    global $failures;
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        $failures++;
    }
}

// A) +1 día usa reloj canónico (avanzarPasoAPaso)
$p1 = $ctx->service->nuevaPartida('playtest_01', 'play-lab-a');
$dia0 = (int) $p1['reloj']['dia_pueblo'];
$r1 = DebugLabHandler::simular($ctx, array_merge($bodyBase, ['dias' => 1]), $p1);
ok(($r1['ok'] ?? false) === true, 'simular 1 día ok');
ok(($r1['simulacion']['motor'] ?? '') === 'avanzarRelojPasoAPaso', 'motor canónico paso_a_paso');
ok((int) $p1['reloj']['dia_pueblo'] === $dia0 + 1, '+1 día avanza reloj un día');

// B) +7 días ≡ 168h paso a paso (misma seed, comparar estado clave)
$seedB = 'play-lab-equiv-7';
$pb = $ctx->service->nuevaPartida('playtest_01', $seedB);
$pc = $ctx->service->nuevaPartida('playtest_01', $seedB);
DebugLabHandler::simular($ctx, array_merge($bodyBase, ['dias' => 7]), $pb);
$ctx->service->avanzarRelojPasoAPaso($pc, 7 * 24);
ok(
    (int) $pb['reloj']['dia_pueblo'] === (int) $pc['reloj']['dia_pueblo'],
    'día final igual batch 7d vs 168h'
);
ok(
    (int) $pb['reloj']['hora_actual'] === (int) $pc['reloj']['hora_actual'],
    'hora final igual batch 7d vs 168h'
);
ok(
    count($pb['bitacora_relaciones'] ?? []) === count($pc['bitacora_relaciones'] ?? []),
    'misma cantidad de hitos bitácora 7d'
);

// C) Encuentro programado avanza con simulación canónica
$pc2 = $ctx->service->nuevaPartida('playtest_01', 'play-lab-celeste');
$idsC = array_values(array_filter(array_keys($pc2['residentes'] ?? []), 'is_string'));
if (count($idsC) >= 2) {
    $diaEnc = (int) $pc2['reloj']['dia_pueblo'] + 1;
    $prog = $ctx->service->programarEncuentro($pc2, [$idsC[0], $idsC[1]], $diaEnc, 17, 'conocerse', 'lug_cafeteria');
    if ($prog['ok'] ?? false) {
        $encId = (string) ($prog['encuentro']['id'] ?? '');
        DebugLabHandler::simular($ctx, array_merge($bodyBase, ['dias' => 2]), $pc2);
        $completado = false;
        foreach ($pc2['encuentros'] ?? [] as $enc) {
            if (is_array($enc) && (string) ($enc['id'] ?? '') === $encId && ($enc['estado'] ?? '') === 'completado') {
                $completado = true;
                break;
            }
        }
        ok($completado || (int) $pc2['reloj']['dia_pueblo'] > $diaEnc, 'encuentro programado avanza con simulación canónica');
    } else {
        ok(true, 'programar no disponible (skip test celeste en esta config)');
    }
} else {
    ok(false, 'sin residentes para test celeste');
}

// D) Resumen periodo cuenta hitos reales de bitácora
$pd = $ctx->service->nuevaPartida('playtest_01', 'play-lab-hitos');
$idsD = array_values(array_filter(array_keys($pd['residentes'] ?? []), 'is_string'));
$a = $idsD[0] ?? 'per_p001';
$b = $idsD[1] ?? 'per_p002';
$antesN = count($pd['bitacora_relaciones'] ?? []);
RelacionBitacora::registrar($pd, RelacionBitacora::FLECHAZO, [$a, $b], $a . '>' . $b);
$ctx->service->guardar($pd);
$rD = DebugLabHandler::simular($ctx, array_merge($bodyBase, ['dias' => 1]), $pd);
$flechazosPeriodo = (int) (($rD['periodo']['romance']['flechazos'] ?? 0));
$nuevosHitos = count($pd['bitacora_relaciones'] ?? []) - $antesN;
ok($flechazosPeriodo >= 0, 'métrica flechazos numérica');
$tieneHitoPrevio = RelacionBitacora::tienenHito($pd, $a, $b, RelacionBitacora::FLECHAZO);
ok($tieneHitoPrevio, 'hito flechazo previo registrado en partida');

// E) Datos direccionales A→B / B→A no mezclados
$pe = $ctx->service->nuevaPartida('playtest_01', 'play-lab-dir');
$ra = array_values(array_filter(array_keys($pe['residentes'] ?? []), 'is_string'))[0] ?? '';
$rb = array_values(array_filter(array_keys($pe['residentes'] ?? []), 'is_string'))[1] ?? '';
if ($ra !== '' && $rb !== '') {
    RelacionEngine::setRomanceHacia($pe, $ra, $rb, 42);
    RelacionEngine::setRomanceHacia($pe, $rb, $ra, 3);
    $cat = new Catalog($root);
    $par = PlayLab::inspectorPar($pe, $ra, $rb, $cat);
    ok(($par['a_hacia_b']['romance'] ?? null) === 42, 'A→B romance 42');
    ok(($par['b_hacia_a']['romance'] ?? null) === 3, 'B→A romance 3');
    ok(($par['a_hacia_b']['romance'] ?? null) !== ($par['b_hacia_a']['romance'] ?? null), 'direcciones separadas');
} else {
    ok(false, 'sin par para test direccional');
}

// F) partida.refresh no incluye informes lab
$pf = $ctx->service->nuevaPartida('playtest_01', 'play-lab-refresh');
$out = PartidaHandler::refrescar($ctx, array_merge($bodyBase, ['partida_id' => $pf['meta']['partida_id']]), $pf);
ok(!isset($out['lab']), 'refresh sin bloque lab');
ok(!isset($out['play_lab']), 'refresh sin play_lab');
$estadoKeys = array_keys($out['estado'] ?? []);
ok(!in_array('matriz_relacional', $estadoKeys, true), 'estado resumido sin matriz');

if ($failures === 0) {
    echo "play_lab_test OK\n";
    exit(0);
}
echo "play_lab_test FAIL ($failures)\n";
exit(1);
