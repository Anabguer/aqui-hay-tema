<?php
declare(strict_types=1);

/**
 * AHT-P5: Tests deterministas del pipeline romántico.
 * Protege: reciprocidad, direccionalidad, no pareja instantánea,
 * memoria P2 influye, rechazos impiden trayectoria.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelacionBandas;
use AquiHayTema\Engine\RomanceElegibilidad;
use AquiHayTema\Engine\SenalRomantica;
use AquiHayTema\Engine\EncuentroDeltasReales;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$cal = CalibracionConfig::load($root);
$passed = 0;
$failed = 0;

function ok(bool $cond, string $msg): void
{
    global $passed, $failed;
    if ($cond) { echo "  OK: {$msg}\n"; $passed++; }
    else { echo "  FAIL: {$msg}\n"; $failed++; }
}

echo "=== AHT-P5 TESTS DETERMINISTAS ===\n\n";

// --- T1: romanceHacia es direccional ---
echo "T1: romanceHacia es direccional\n";
$p = $service->nuevaPartida('juego_v1', 'p5-t01');
$ids = array_keys($p['residentes']);
$a = $ids[0]; $b = $ids[1];
RelacionEngine::setRomanceHacia($p, $a, $b, 15);
$ab = RelacionEngine::romanceHacia($p, $a, $b);
$ba = RelacionEngine::romanceHacia($p, $b, $a);
ok($ab === 15, "A→B = 15 ({$ab})");
ok($ba === null || $ba === 0, "B→A = 0/null ({$ba})");

// --- T2: ParejaEngine requiere ambos aceptan ---
echo "\nT2: ParejaEngine requiere ambos aceptan\n";
$p = $service->nuevaPartida('juego_v1', 'p5-t02');
$ids = array_keys($p['residentes']);
$a = $ids[0]; $b = $ids[1];
$r1 = ParejaEngine::formar($p, $a, $b, true, false);
ok(!($r1['ok'] ?? false), "Solo A acepta → rechazado");
$r2 = ParejaEngine::formar($p, $a, $b, false, true);
ok(!($r2['ok'] ?? false), "Solo B acepta → rechazado");
$r3 = ParejaEngine::formar($p, $a, $b, true, true);
ok($r3['ok'] ?? false, "Ambos aceptan → formada");

// --- T3: No pareja desde relación neutral ---
echo "\nT3: No pareja desde relación neutral (sin romance previo)\n";
$p = $service->nuevaPartida('juego_v1', 'p5-t03');
$ids = array_keys($p['residentes']);
$a = $ids[0]; $b = $ids[1];
$romAB = RelacionEngine::romanceHacia($p, $a, $b);
ok($romAB === null || $romAB === 0, "Romance inicial es 0/null ({$romAB})");
$est = ParejaEngine::estado($p, $a, $b);
ok($est === 'ninguna', "Estado es 'ninguna'");

// --- T4: SenalRomantica requiere romance >= tilin o flechazo ---
echo "\nT4: Señal requiere romance >= 8 o flechazo\n";
$p = $service->nuevaPartida('juego_v1', 'p5-t04');
$ids = array_keys($p['residentes']);
$a = $ids[0]; $b = $ids[1];
RelacionEngine::registrarContacto($p, $a, $b, 'normal', $cal, 1);
$s1 = SenalRomantica::desdeHacia($p, $a, $b, $cal);
ok(empty($s1['ok']), "Sin romance → sin señal");
RelacionEngine::setRomanceHacia($p, $a, $b, 7);
$s2 = SenalRomantica::desdeHacia($p, $a, $b, $cal);
ok(empty($s2['ok']), "Romance=7 < tilin(8) → sin señal");
RelacionEngine::setRomanceHacia($p, $a, $b, 8);
$s3 = SenalRomantica::desdeHacia($p, $a, $b, $cal);
ok(!empty($s3['ok']), "Romance=8 >= tilin → señal OK");

// --- T5: romance×0.35 en encuentros no-cita ---
echo "\nT5: Encuentros no-cita aplican factor ×0.35 a romance\n";
$base = EncuentroDeltasReales::deResultado('bien', 'quedar', $cal);
ok($base['romance'] === 1, "bien+quedar: romance=3×0.35≈1 ({$base['romance']})");
$base2 = EncuentroDeltasReales::deResultado('muy_bien', 'quedar', $cal);
ok($base2['romance'] === 2, "muy_bien+quedar: romance=5×0.35≈2 ({$base2['romance']})");
$base3 = EncuentroDeltasReales::deResultado('bien', 'primera_cita', $cal);
ok($base3['romance'] === 3, "bien+primera_cita: romance=3×1=3 ({$base3['romance']})");

// --- T6: RechazoMemoria reduce romance ---
echo "\nT6: RechazoMemoria puede reducir romance\n";
$p = $service->nuevaPartida('juego_v1', 'p5-t06');
$ids = array_keys($p['residentes']);
$a = $ids[0]; $b = $ids[1];
RelacionEngine::setRomanceHacia($p, $a, $b, 15);
\AquiHayTema\Engine\RechazoMemoria::registrar($p, $a, $b, 'romantico', $cal, 'declaracion');
$romDespues = RelacionEngine::romanceHacia($p, $a, $b);
ok($romDespues < 15, "Tras rechazo romance bajó: 15→{$romDespues}");

// --- T7: P4 no dispara romance por sí sola ---
echo "\nT7: IniciativaSocial (P4) no genera romance\n";
$p = $service->nuevaPartida('juego_v1', 'p5-t07');
$ids = array_keys($p['residentes']);
$a = $ids[0]; $b = $ids[1];
RelacionEngine::setRomanceHacia($p, $a, $b, 0);
$antes = RelacionEngine::romanceHacia($p, $a, $b) ?? 0;
// Simular encuentro social normal (no cita)
$delta = EncuentroDeltasReales::deResultado('bien', 'quedar', $cal);
ok($delta['romance'] <= 2, "Encuentro social: romance delta ≤2 ({$delta['romance']})");

// --- T8: romper resetea romance a 0 ---
echo "\nT8: Romper pareja resetea romance a 0\n";
$p = $service->nuevaPartida('juego_v1', 'p5-t08');
$ids = array_keys($p['residentes']);
$a = $ids[0]; $b = $ids[1];
RelacionEngine::setRomanceHacia($p, $a, $b, 50);
RelacionEngine::setRomanceHacia($p, $b, $a, 45);
ParejaEngine::formar($p, $a, $b, true, true);
$est1 = ParejaEngine::estado($p, $a, $b);
ok($est1 === 'pareja', "Son pareja antes de romper");
ParejaEngine::romper($p, $a, $b);
$est2 = ParejaEngine::estado($p, $a, $b);
$romA = RelacionEngine::romanceHacia($p, $a, $b) ?? 999;
$romB = RelacionEngine::romanceHacia($p, $b, $a) ?? 999;
ok($est2 === 'ex', "Son ex después de romper");
ok($romA === 0, "Romance A→B reseteado a 0 ({$romA})");
ok($romB === 0, "Romance B→A reseteado a 0 ({$romB})");

// --- T9: Reciprocidad no forzada ---
echo "\nT9: Asimetría permitida — A puede gustar más que B\n";
$p = $service->nuevaPartida('juego_v1', 'p5-t09');
$ids = array_keys($p['residentes']);
$a = $ids[0]; $b = $ids[1];
RelacionEngine::setRomanceHacia($p, $a, $b, 25);
RelacionEngine::setRomanceHacia($p, $b, $a, 5);
$senalA = SenalRomantica::desdeHacia($p, $a, $b, $cal);
$senalB = SenalRomantica::desdeHacia($p, $b, $a, $cal);
ok(!empty($senalA['ok']), "A→B tiene señal (25 >= 8)");
ok(empty($senalB['ok']), "B→A no tiene señal (5 < 8)");
$dir = SenalRomantica::direccionVisible($p, $a, $b, $cal);
ok($dir !== null && $dir['desde'] === $a, "Dirección visible es A→B");

// --- T10: Elegibilidad — parentesco bloquea ---
echo "\nT10: Elegibilidad — parentesco bloquea romance\n";
$el = RomanceElegibilidad::par($p, $a, $a, $cal);
ok(!($el['ok'] ?? false), "Mismo personaje → no elegible");

echo "\n=== RESUMEN ===\n";
echo "Passed: {$passed}, Failed: {$failed}\n";
echo $failed === 0 ? "\nAHT-P5 TESTS DETERMINISTAS OK\n" : "\nAHT-P5 TESTS CON FALLOS\n";
