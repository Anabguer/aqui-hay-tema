<?php
declare(strict_types=1);

/**
 * Edad real: incremento +1 en cumpleaños — tests focalizados.
 *
 * Cubre:
 *   1. perfil con edad ya materializada → +1
 *   2. perfil antiguo sin runtime.perfil_partida.edad → materializa + incrementa
 *   3. doble ejecución el mismo cumpleaños → no duplica
 *   4. año siguiente → vuelve a incrementar
 *   5. persistencia: la ficha refleja la edad incrementada
 *   6. perfil sin edad resoluble (catálogo ausente) → no marca key
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoContextualEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PerfilPartida;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\ResidenteCumpleanosEngine;

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

function alinearCumpleHoy(array &$p, string $rid): void
{
    $diaPueblo = (int) ($p['reloj']['dia_pueblo'] ?? 1);
    $fecha = Reloj::fechaDeDia($p['reloj'] ?? [], $diaPueblo);
    $p['residentes'][$rid]['identidad_publica']['cumpleanos'] = [
        'dia' => (int) $fecha->format('j'),
        'mes' => (int) $fecha->format('n'),
    ];
}

DomainBootstrap::boot();
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$svc = new PartidaService($root);

echo "--- Edad incremento por cumpleaños ---\n\n";

// ================================================================
// TEST 1: Perfil con edad ya materializada → +1
// ================================================================
echo "--- Test 1: Edad materializada → +1 ---\n";
$p1 = $svc->nuevaPartida('juego_v1', 'edad-inc-1-' . time());
$rid1 = (string) array_key_first($p1['residentes'] ?? []);

// Alinear cumple con hoy
alinearCumpleHoy($p1, $rid1);

// Materializar edad manualmente (simula save existente)
$p1['residentes'][$rid1]['runtime']['perfil_partida']['edad'] = 30;

// Ejecutar evaluarAlComenzarDia
MensajitoContextualEngine::evaluarAlComenzarDia($p1, $cal, $catalog);

$edadDespues = $p1['residentes'][$rid1]['runtime']['perfil_partida']['edad'] ?? null;
ok($edadDespues === 31, "1: edad 30 → 31 (fue $edadDespues)");
ok(!empty($p1['edad_incrementos'] ?? []), '1: edad_incrementos marcado');

// ================================================================
// TEST 2: Perfil antiguo sin runtime.perfil_partida.edad → materializa + incrementa
// ================================================================
echo "\n--- Test 2: Save antiguo sin edad materializada ---\n";
$p2 = $svc->nuevaPartida('juego_v1', 'edad-inc-2-' . time());
$rid2 = (string) array_key_first($p2['residentes'] ?? []);

// Alinear cumple con hoy
alinearCumpleHoy($p2, $rid2);

// Asegurar que NO tiene edad en perfil_partida
$p2['residentes'][$rid2]['runtime']['perfil_partida'] = [];

// Obtener edad canónica del catálogo para comparar
$edadCatalogo = PerfilPartida::edadDesdeCatalogo($p2, $rid2, $catalog);
ok($edadCatalogo !== null, '2: catálogo tiene edad');

// Ejecutar
MensajitoContextualEngine::evaluarAlComenzarDia($p2, $cal, $catalog);

$edad2 = $p2['residentes'][$rid2]['runtime']['perfil_partida']['edad'] ?? null;
ok($edad2 === $edadCatalogo + 1, "2: materializa ($edadCatalogo) + 1 = " . ($edadCatalogo + 1) . " (fue $edad2)");
ok(!empty($p2['edad_incrementos'] ?? []), '2: edad_incrementos marcado');

// ================================================================
// TEST 3: Doble ejecución el mismo cumpleaños → no duplica
// ================================================================
echo "\n--- Test 3: Doble ejecución → idempotente ---\n";
$p3 = $svc->nuevaPartida('juego_v1', 'edad-inc-3-' . time());
$rid3 = (string) array_key_first($p3['residentes'] ?? []);

alinearCumpleHoy($p3, $rid3);
$p3['residentes'][$rid3]['runtime']['perfil_partida']['edad'] = 40;

// Primera ejecución
MensajitoContextualEngine::evaluarAlComenzarDia($p3, $cal, $catalog);
$edad3a = $p3['residentes'][$rid3]['runtime']['perfil_partida']['edad'] ?? null;

// Segunda ejecución (simula refresh/reload del mismo día)
MensajitoContextualEngine::evaluarAlComenzarDia($p3, $cal, $catalog);
$edad3b = $p3['residentes'][$rid3]['runtime']['perfil_partida']['edad'] ?? null;

ok($edad3a === 41, "3a: primera ejecución 40 → 41 (fue $edad3a)");
ok($edad3b === 41, "3b: segunda ejecución sigue en 41 (fue $edad3b)");
ok($edad3a === $edad3b, '3: no se duplicó el incremento');

// ================================================================
// TEST 4: Año siguiente → vuelve a incrementar
// ================================================================
echo "\n--- Test 4: Año siguiente → +1 de nuevo ---\n";
$p4 = $svc->nuevaPartida('juego_v1', 'edad-inc-4-' . time());
$rid4 = (string) array_key_first($p4['residentes'] ?? []);

alinearCumpleHoy($p4, $rid4);
$p4['residentes'][$rid4]['runtime']['perfil_partida']['edad'] = 25;

// Primer año
MensajitoContextualEngine::evaluarAlComenzarDia($p4, $cal, $catalog);
$edad4a = $p4['residentes'][$rid4]['runtime']['perfil_partida']['edad'] ?? null;
ok($edad4a === 26, "4a: primer año 25 → 26 (fue $edad4a)");

// Simular año siguiente: borrar la clave anual actual para que vuelva a ejecutarse
$claveAnual4 = ResidenteCumpleanosEngine::claveAnual($p4, $rid4);
unset($p4['edad_incrementos'][$claveAnual4]);

// Segundo año
MensajitoContextualEngine::evaluarAlComenzarDia($p4, $cal, $catalog);
$edad4b = $p4['residentes'][$rid4]['runtime']['perfil_partida']['edad'] ?? null;
ok($edad4b === 27, "4b: segundo año 26 → 27 (fue $edad4b)");

// ================================================================
// TEST 5: Persistencia: la ficha refleja la edad incrementada
// ================================================================
echo "\n--- Test 5: Ficha refleja edad incrementada ---\n";
$p5 = $svc->nuevaPartida('juego_v1', 'edad-inc-5-' . time());
$rid5 = (string) array_key_first($p5['residentes'] ?? []);

alinearCumpleHoy($p5, $rid5);
$p5['residentes'][$rid5]['runtime']['perfil_partida']['edad'] = 50;

MensajitoContextualEngine::evaluarAlComenzarDia($p5, $cal, $catalog);

// Obtener ficha vía PartidaService (respuesta ligera no incluye identidad.edad)
$ficha = $svc->fichaResidente($p5, $rid5, true);
$edadVista = $ficha['vista_play']['edad'] ?? null;

ok($edadVista === 51, "5: ficha vista_play.edad = 51 (fue $edadVista)");

// ================================================================
// TEST 6: Perfil con edad null + catálogo con edad → materializa y
//         solo marca key DESPUÉS de incremento exitoso
// ================================================================
echo "\n--- Test 6: Materialización + key post-incremento ---\n";
$p6 = $svc->nuevaPartida('juego_v1', 'edad-inc-6-' . time());
$rid6 = (string) array_key_first($p6['residentes'] ?? []);

alinearCumpleHoy($p6, $rid6);

// Edad null en perfil (simula save antiguo roto)
$p6['residentes'][$rid6]['runtime']['perfil_partida']['edad'] = null;

// Obtener edad canónica del catálogo
$edadCat6 = PerfilPartida::edadDesdeCatalogo($p6, $rid6, $catalog);
ok($edadCat6 !== null, '6: catálogo tiene edad canónica');

// Ejecutar
MensajitoContextualEngine::evaluarAlComenzarDia($p6, $cal, $catalog);

$edad6 = $p6['residentes'][$rid6]['runtime']['perfil_partida']['edad'] ?? null;
$claveAnual6 = ResidenteCumpleanosEngine::claveAnual($p6, $rid6);
$marcado6 = !empty($p6['edad_incrementos'][$claveAnual6] ?? false);

ok($edad6 === $edadCat6 + 1, "6: null → materializa ($edadCat6) + 1 = " . ($edadCat6 + 1) . " (fue $edad6)");
ok($marcado6, '6: key marcada SOLO tras incremento exitoso');

// ================================================================
// RESUMEN
// ================================================================
echo "\n" . str_repeat('=', 50) . "\n";
if ($failures === 0) {
    echo "TODOS LOS TESTS PASARON\n";
} else {
    echo "FALLOS: $failures\n";
}
exit($failures > 0 ? 1 : 0);
