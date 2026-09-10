<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';

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

// --- Test 1: aht_dev_readonly_allowed whitelist ---
require_once $root . '/src/dev_gate.php';
ok(aht_dev_readonly_allowed('dev.diagnostico.export'), 'diagnostico.export is in readonly whitelist');
ok(!aht_dev_readonly_allowed('dev.snapshot.guardar'), 'snapshot.guardar is NOT in readonly whitelist');
ok(!aht_dev_readonly_allowed('dev.reset.encuentros'), 'reset.encuentros is NOT in readonly whitelist');
ok(!aht_dev_readonly_allowed('dev.partida.eliminar'), 'partida.eliminar is NOT in readonly whitelist');
ok(!aht_dev_readonly_allowed('dev.encuentro.forzar_resolver'), 'forzar_resolver is NOT in readonly whitelist');
ok(!aht_dev_readonly_allowed('dev.simular'), 'simular is NOT in readonly whitelist');
ok(!aht_dev_readonly_allowed(''), 'empty action is NOT in readonly whitelist');

// --- Test 2: requireDev() blocks everything when no allowlist ---
putenv('AHT_DEV=');
@unlink($root . '/src/dev.local.php');
require_once $root . '/src/dev_gate.php';
ok(!aht_dev_enabled(), 'aht_dev_enabled() returns false in production mode');

// --- Test 3: DiagnosticExport::export reads data correctly ---
use AquiHayTema\Engine\DiagnosticExport;
use AquiHayTema\Engine\PartidaService;

$svc = new PartidaService($root);
$p = $svc->nuevaPartida('playtest_01', 'diag-test');

$export = DiagnosticExport::export($p, $root);

ok($export['ok'] === true, 'export ok is true');
ok($export['_tipo'] === 'diagnostico_dev', 'export tipo is diagnostico_dev');
ok(is_array($export['reloj']), 'reloj is array');
ok(is_array($export['residentes']), 'residentes is array');
ok(is_array($export['corazon_del_pueblo']), 'corazon_del_pueblo is array');
ok(array_key_exists('heart', $export['corazon_del_pueblo']), 'corazon has heart field');
ok(array_key_exists('banda', $export['corazon_del_pueblo']), 'corazon has banda field');
ok(array_key_exists('estancamiento', $export['corazon_del_pueblo']), 'corazon has estancamiento field');
ok(is_array($export['relaciones_sociales']), 'relaciones_sociales is array');
ok(is_array($export['relaciones_romanticas']), 'relaciones_romanticas is array');
ok(is_array($export['relaciones_conflicto']), 'relaciones_conflicto is array');
ok(is_array($export['peticiones']), 'peticiones is array');
ok(is_array($export['npc_autonomo']), 'npc_autonomo is array');
ok(is_array($export['emotional_instrumentation']), 'emotional_instrumentation is array');
ok(is_array($export['misiones_diarias']), 'misiones_diarias is array');
ok(is_array($export['encuentros_recientes']), 'encuentros_recientes is array');

// --- Test 4: Residentes have detailed fields ---
$firstRes = reset($export['residentes']);
if ($firstRes) {
    ok(array_key_exists('perfil_partida', $firstRes), 'residente has perfil_partida');
    ok(array_key_exists('hobbies', $firstRes['perfil_partida']), 'perfil_partida has hobbies');
    ok(array_key_exists('rasgos', $firstRes['perfil_partida']), 'perfil_partida has rasgos');
    ok(array_key_exists('necesidades', $firstRes), 'residente has necesidades');
    ok(array_key_exists('estado_emocional', $firstRes), 'residente has estado_emocional');
    ok(is_array($firstRes['necesidades']), 'necesidades is array');
    ok(array_key_exists('social', $firstRes['necesidades']), 'necesidades has social');
    ok(array_key_exists('diversion', $firstRes['necesidades']), 'necesidades has diversion');
    ok(array_key_exists('actividad', $firstRes['necesidades']), 'necesidades has actividad');
    ok(array_key_exists('calma', $firstRes['necesidades']), 'necesidades has calma');
}

// --- Test 5: Corazon del pueblo has required fields ---
$heart = $export['corazon_del_pueblo'];
ok(is_int($heart['heart']) || $heart['heart'] === null, 'heart is int or null');
ok(is_int($heart['latidos']), 'latidos is int');
ok(is_bool($heart['game_over_pendiente']), 'game_over_pendiente is bool');
ok(is_bool($heart['llego_a_cero']), 'leggo_a_cero is bool');
ok(is_int($heart['dias_en_critico']), 'dias_en_critico is int');
ok(is_array($heart['ledger_reciente']), 'ledger_reciente is array');
ok(is_int($heart['ledger_count']), 'ledger_count is int');

// --- Test 6: Export is idempotent (calling twice produces same structure) ---
$export2 = DiagnosticExport::export($p, $root);
ok($export['partida_id'] === $export2['partida_id'], 'idempotent: same partida_id');
ok($export['reloj'] === $export2['reloj'], 'idempotent: same reloj');
ok(count($export['residentes']) === count($export2['residentes']), 'idempotent: same residentes count');
ok($export['corazon_del_pueblo']['heart'] === $export2['corazon_del_pueblo']['heart'], 'idempotent: same heart value');
ok(count($export['relaciones_sociales']) === count($export2['relaciones_sociales']), 'idempotent: same social relations count');
ok(count($export['relaciones_romanticas']) === count($export2['relaciones_romanticas']), 'idempotent: same romantic relations count');
ok(count($export['peticiones']) === count($export2['peticiones']), 'idempotent: same peticiones count');

// --- Test 7: Export does NOT mutate the partida ---
$relojBefore = $p['reloj'];
$heartBefore = $p['vida_pueblo']['valor'] ?? null;
$residenteCountBefore = count($p['residentes']);
$relSocCountBefore = count($p['relaciones_sociales'] ?? []);
DiagnosticExport::export($p, $root);
ok($p['reloj'] === $relojBefore, 'no mutation: reloj unchanged');
ok(($p['vida_pueblo']['valor'] ?? null) === $heartBefore, 'no mutation: heart unchanged');
ok(count($p['residentes']) === $residenteCountBefore, 'no mutation: residentes count unchanged');
ok(count($p['relaciones_sociales'] ?? []) === $relSocCountBefore, 'no mutation: relaciones_sociales unchanged');

// Restore AHT_DEV
putenv('AHT_DEV=');

echo $failures === 0 ? "OK diagnostic_export_test\n" : "FAIL diagnostic_export_test ({$failures})\n";
exit($failures > 0 ? 1 : 0);
