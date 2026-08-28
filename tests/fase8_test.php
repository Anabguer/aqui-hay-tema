<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\NarrativeCoherenceEngine;
use AquiHayTema\Engine\PersistenciaCaps;

$fail = 0;

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

// --- 1) Partida vacía: sin contradicciones graves ---
$p1 = ['residentes' => [], 'buzon' => [], 'memoria_eventos' => [], 'propuestas_encuentro' => [], 'encuentros' => [], 'diario' => [], 'calibracion' => []];
$h1 = NarrativeCoherenceEngine::verificar($p1);
$graves = array_filter($h1, fn($x) => ($x['severidad'] ?? '') === 'ALTA');
ok(count($graves) === 0, 'partida vacía: sin contradicciones graves');

// --- 2) Resumen retorna conteo correcto ---
$r1 = NarrativeCoherenceEngine::resumen($p1);
ok($r1['total'] >= 0, 'resumen: total >= 0');
ok($r1['alta'] === 0, 'resumen: alta = 0 para partida limpia');

// --- 3) C12 detecta memoria_eventos sin cap ---
$p2 = $p1;
$p2['memoria_eventos'] = array_fill(0, 600, ['familia' => 'casual', 'participantes' => ['a'], 'dia' => 1, 'hora' => 0]);
$h2 = NarrativeCoherenceEngine::verificar($p2);
$findOne = false;
foreach ($h2 as $h) {
    if ($h['id'] === 'C12') { $findOne = true; break; }
}
ok($findOne, 'C12: detecta memoria_eventos sin cap (600 > 500)');

// --- 4) C12 NO dispara si debajo del cap ---
$p3 = $p1;
$p3['memoria_eventos'] = array_fill(0, 100, ['familia' => 'casual', 'participantes' => ['a'], 'dia' => 1, 'hora' => 0]);
$h3 = NarrativeCoherenceEngine::verificar($p3);
$findOne3 = false;
foreach ($h3 as $h) {
    if ($h['id'] === 'C12') { $findOne3 = true; break; }
}
ok(!$findOne3, 'C12: NO dispara si debajo del cap');

// --- 5) C8 detecta cotilleo sin asimetría ---
$p4 = $p1;
$p4['buzon'] = array_fill(0, 6, [
    'id' => 'msg_test', 'tipo' => 'cotilleo_patron', 'texto' => 'Todo va bien en el pueblo.',
    'de_persona' => 'per_p001', 'clasificacion' => 'informativo', 'canal' => 'buzon',
]);
$h4 = NarrativeCoherenceEngine::verificar($p4);
$findOne4 = false;
foreach ($h4 as $h) {
    if ($h['id'] === 'C8') { $findOne4 = true; break; }
}
ok($findOne4, 'C8: detecta cotilleo sin asimetría');

// --- 6) C8 NO dispara si hay señales de asimetría ---
$p5 = $p1;
$p5['buzon'] = array_fill(0, 6, [
    'id' => 'msg_test2', 'tipo' => 'cotilleo_patron', 'texto' => 'Hugo lo pasó bien, pero Tamara no conectó.',
    'de_persona' => 'per_p001', 'clasificacion' => 'informativo', 'canal' => 'buzon',
]);
$h5 = NarrativeCoherenceEngine::verificar($p5);
$findOne5 = false;
foreach ($h5 as $h) {
    if ($h['id'] === 'C8') { $findOne5 = true; break; }
}
ok(!$findOne5, 'C8: NO dispara si hay asimetría');

// --- 7) C9 detecta diario sin hitos ---
$p6 = $p1;
$p6['calibracion'] = ['diario_enabled' => true];
$p6['diario'] = array_fill(0, 15, ['fuente' => 'cotilleo', 'tipo' => 'eco']);
$h6 = NarrativeCoherenceEngine::verificar($p6);
$findOne6 = false;
foreach ($h6 as $h) {
    if ($h['id'] === 'C9') { $findOne6 = true; break; }
}
ok($findOne6, 'C9: detecta diario sin hitos');

// --- 8) C9 NO dispara si tiene hitos ---
$p7 = $p1;
$p7['calibracion'] = ['diario_enabled' => true];
$p7['diario'] = array_fill(0, 10, ['fuente' => 'cotilleo', 'tipo' => 'eco']);
$p7['diario'][] = ['fuente' => 'hito', 'tipo' => 'hito', 'es_hito' => true];
$h7 = NarrativeCoherenceEngine::verificar($p7);
$findOne7 = false;
foreach ($h7 as $h) {
    if ($h['id'] === 'C9') { $findOne7 = true; break; }
}
ok(!$findOne7, 'C9: NO dispara si tiene hitos');

// --- 9) Detección C4: mensajitos sin historia ---
$p8 = $p1;
$p8['buzon'] = array_fill(0, 10, [
    'id' => 'msg_f1', 'tipo' => 'espontaneo_f_opinion', 'de_persona' => 'per_p001',
    'datos_familia' => ['otro_id' => 'per_p002', 'historial' => ''],
    'clasificacion' => 'oportunidad', 'canal' => 'buzon',
]);
$h8 = NarrativeCoherenceEngine::verificar($p8);
$findOne8 = false;
foreach ($h8 as $h) {
    if ($h['id'] === 'C4') { $findOne8 = true; break; }
}
ok($findOne8, 'C4: detecta mensajitos sin historia previa');

// --- 10) resumen con problemas ---
$p9 = $p1;
$p9['memoria_eventos'] = array_fill(0, 600, ['familia' => 'casual', 'participantes' => ['a'], 'dia' => 1, 'hora' => 0]);
$r9 = NarrativeCoherenceEngine::resumen($p9);
ok($r9['total'] >= 1, 'resumen: detecta al menos 1 problema');
ok($r9['alta'] >= 1, 'resumen: al menos 1 alta');

echo "\n";
echo $fail === 0 ? "OK fase8_test\n" : "FAIL fase8_test ({$fail})\n";
exit($fail > 0 ? 1 : 0);
