<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DebugParejasEngine;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;

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

$service = new PartidaService($root);
$cal = CalibracionConfig::load($root);
$partida = $service->nuevaPartida('playtest_01', 'debug-parejas-test');

$disponibles = DebugParejasEngine::vecinosDisponibles($partida);
ok(count($disponibles) >= 4, 'playtest_01 tiene al menos 4 vecinos disponibles');

$crear = DebugParejasEngine::crear($partida, $cal);
ok($crear['ok'] ?? false, 'crear parejas DEBUG');
ok(count($crear['parejas'] ?? []) === 2, 'crea exactamente 2 parejas');

$estados = array_column($crear['parejas'] ?? [], 'estado_pareja');
ok(in_array(ParejaEngine::PAREJA, $estados, true), 'una pareja en estado pareja');
ok(in_array(ParejaEngine::CRISIS, $estados, true), 'una pareja en estado crisis');

$idsUsados = [];
foreach ($crear['parejas'] ?? [] as $p) {
    $idsUsados[] = $p['persona_a'];
    $idsUsados[] = $p['persona_b'];
    ok(($p['estado_pareja'] ?? '') !== '', 'respuesta incluye estado_pareja real');
}
ok(count($idsUsados) === count(array_unique($idsUsados)), 'no duplica residentes entre parejas DEBUG');
ok(count($idsUsados) === 4, 'usa 4 vecinos distintos');

$debugCount = 0;
foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
    if (($rel['_origen_debug'] ?? '') === DebugParejasEngine::ORIGEN) {
        $debugCount++;
        ok(array_key_exists('_debug_pareja_snapshot', $rel), 'marca snapshot para retirada segura');
        ok(isset($rel['estado_pareja']), 'usa clave real estado_pareja');
        ok(isset($rel['persona_a'], $rel['persona_b']), 'usa IDs reales persona_a/persona_b');
    }
}
ok($debugCount === 2, '2 relaciones marcadas _origen_debug en partida');

$enCrisis = null;
foreach ($crear['parejas'] ?? [] as $p) {
    if (!empty($p['en_crisis'])) {
        $enCrisis = $p['etiqueta'];
    }
}
ok($enCrisis === 'B', 'pareja B marcada en crisis');

ok(isset($crear['debug_parejas']['claves_modificadas']), 'vista consola con claves modificadas');

// Pareja real de gameplay: no debe tocarse al quitar DEBUG
$libres = array_values(array_diff($disponibles, $idsUsados));
if (count($libres) >= 2) {
    $realA = $libres[0];
    $realB = $libres[1];
    ParejaEngine::formar($partida, $realA, $realB, true, true, RelacionBitacora::DECLARACION, $cal);
    ok(ParejaEngine::estado($partida, $realA, $realB) === ParejaEngine::PAREJA, 'pareja real gameplay creada');
    $quitarConReal = DebugParejasEngine::quitar($partida);
    ok($quitarConReal['ok'] ?? false, 'quitar DEBUG con pareja real coexistiendo');
    ok(!DebugParejasEngine::tieneDebugActivas($partida), 'sin debug tras quitar');
    ok(ParejaEngine::estado($partida, $realA, $realB) === ParejaEngine::PAREJA, 'pareja real intacta tras quitar DEBUG');
    ok(($quitarConReal['n'] ?? 0) === 2, 'quita solo las 2 parejas DEBUG');
} else {
    $quitar = DebugParejasEngine::quitar($partida);
    ok($quitar['ok'] ?? false, 'quitar parejas DEBUG');
    ok(!DebugParejasEngine::tieneDebugActivas($partida), 'no quedan parejas DEBUG');
    ok(($quitar['n'] ?? 0) === 2, 'elimina las 2 parejas DEBUG');
}

$recrear = DebugParejasEngine::crear($partida, $cal);
ok($recrear['ok'] ?? false, 'recrear tras quitar');
$dup = DebugParejasEngine::crear($partida, $cal);
ok(!($dup['ok'] ?? true), 'no duplica si ya hay DEBUG');
ok(($dup['error'] ?? '') === 'debug_parejas_ya_existen', 'error claro si ya existen');

// Insuficientes vecinos: simular partida con 3 residentes libres
$partidaPoca = $service->nuevaPartida('playtest_01', 'debug-parejas-pocos');
foreach (DebugParejasEngine::vecinosDisponibles($partidaPoca) as $i => $rid) {
    if ($i >= 3) {
        unset($partidaPoca['residentes'][$rid]);
    }
}
$fallo = DebugParejasEngine::crear($partidaPoca, $cal);
ok(!($fallo['ok'] ?? true), 'no crea con menos de 4 vecinos');
ok(($fallo['error'] ?? '') === 'vecinos_insuficientes', 'aviso vecinos insuficientes');

exit($failures > 0 ? 1 : 0);
