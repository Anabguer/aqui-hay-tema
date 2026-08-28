<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Lab\SimuladorCoherencia;

$fail = 0;

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$root = dirname(__DIR__);

// --- 1) Lab corto: sin errores graves ---
$r1 = SimuladorCoherencia::labCorto($root, 'fase9-corto-1');
ok($r1['ok'], "labCorto: ok=true (errores={$r1['errores']}, ms={$r1['tiempo_ms']})");
ok($r1['dias'] === 20, "labCorto: 20 días (got={$r1['dias']})");
ok($r1['tiempo_ms'] < 10000, "labCorto: < 10s (got={$r1['tiempo_ms']}ms)");

// --- 2) Lab corto: reproducible con mismo seed ---
$r2 = SimuladorCoherencia::labCorto($root, 'fase9-corto-1');
ok($r1['errores'] === $r2['errores'], 'labCorto: reproducible con mismo seed');

// --- 3) Lab largo (reducido para test): 3 seeds × 50 días ---
$r3 = SimuladorCoherencia::labLargo($root, 3, 50);
ok($r3['ok'], "labLargo: ok=true (errores={$r3['errores_total']}, ms={$r3['tiempo_ms']})");
ok($r3['seeds'] === 3, "labLargo: 3 seeds (got={$r3['seeds']})");
ok($r3['dias_total'] === 150, "labLargo: 150 días total (got={$r3['dias_total']})");
ok($r3['tiempo_ms'] < 30000, "labLargo: < 30s (got={$r3['tiempo_ms']}ms)");

// --- 4) Lab largo: detalle por seed ---
ok(count($r3['detalle']) === 3, "labLargo: 3 detalles (got=" . count($r3['detalle']) . ')');

echo "\n";
echo $fail === 0 ? "OK fase9_test\n" : "FAIL fase9_test ({$fail})\n";
exit($fail > 0 ? 1 : 0);
