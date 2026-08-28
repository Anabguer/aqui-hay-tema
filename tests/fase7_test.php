<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\MemoriaEventos;
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

$root = dirname(__DIR__);

// --- 1) compactar no toca si <= cap ---
$p = ['memoria_eventos' => [], 'persistencia' => ['memoria_eventos_cap' => 5]];
for ($i = 0; $i < 3; $i++) {
    $p['memoria_eventos'][] = ['familia' => 'casual', 'participantes' => ['a', 'b'], 'dia' => $i, 'hora' => 0];
}
MemoriaEventos::compactar($p, 5);
ok(count($p['memoria_eventos']) === 3, 'compactar: no recorta bajo cap');

// --- 2) compactar preserva hitos (intensidad >= 3) ---
$p2 = ['memoria_eventos' => [], 'persistencia' => ['memoria_eventos_cap' => 10]];
for ($i = 0; $i < 5; $i++) {
    $p2['memoria_eventos'][] = ['familia' => 'casual', 'participantes' => ['a', 'b'], 'dia' => $i, 'hora' => 0, 'intensidad' => 1];
}
$p2['memoria_eventos'][] = ['familia' => 'encuentro', 'participantes' => ['a', 'b'], 'dia' => 5, 'hora' => 0, 'intensidad' => 4];
$p2['memoria_eventos'][] = ['familia' => 'casual', 'participantes' => ['a', 'b'], 'dia' => 6, 'hora' => 0, 'intensidad' => 1];
MemoriaEventos::compactar($p2, 5);
$tieneHito = false;
foreach ($p2['memoria_eventos'] as $ev) {
    if (($ev['intensidad'] ?? 0) >= 3) {
        $tieneHito = true;
        break;
    }
}
ok($tieneHito, 'compactar: preserva hitos con intensidad alta');

// --- 3) compactar preserva hitos por resultado_experiencia ---
$p3 = ['memoria_eventos' => [], 'persistencia' => ['memoria_eventos_cap' => 10]];
for ($i = 0; $i < 5; $i++) {
    $p3['memoria_eventos'][] = ['familia' => 'casual', 'participantes' => ['a', 'b'], 'dia' => $i, 'hora' => 0];
}
$p3['memoria_eventos'][] = ['familia' => 'casual', 'participantes' => ['a', 'b'], 'dia' => 5, 'hora' => 0, 'resultado_experiencia' => 'bien'];
$p3['memoria_eventos'][] = ['familia' => 'casual', 'participantes' => ['a', 'b'], 'dia' => 6, 'hora' => 0];
MemoriaEventos::compactar($p3, 5);
$tieneExp = false;
foreach ($p3['memoria_eventos'] as $ev) {
    if (($ev['resultado_experiencia'] ?? null) !== null) {
        $tieneExp = true;
        break;
    }
}
ok($tieneExp, 'compactar: preserva hitos con resultado_experiencia');

// --- 4) compactar elimina ruido (entries sin hito, alto volumen) ---
$p4 = ['memoria_eventos' => [], 'persistencia' => ['memoria_eventos_cap' => 10]];
for ($i = 0; $i < 20; $i++) {
    $p4['memoria_eventos'][] = ['familia' => 'casual', 'participantes' => ['a', 'b'], 'dia' => $i, 'hora' => 0, 'intensidad' => 1];
}
$p4['memoria_eventos'][] = ['familia' => 'encuentro', 'participantes' => ['a', 'b'], 'dia' => 20, 'hora' => 0, 'intensidad' => 5];
MemoriaEventos::compactar($p4, 10);
ok(count($p4['memoria_eventos']) <= 10, 'compactar: respeta cap (got=' . count($p4['memoria_eventos']) . ')');

// --- 5) PersistenciaCaps aplica memoria_eventos cap ---
$p5 = ['memoria_eventos' => [], 'persistencia' => []];
$p5['persistencia'] = PersistenciaCaps::defaults($root);
for ($i = 0; $i < 600; $i++) {
    $p5['memoria_eventos'][] = ['familia' => 'casual', 'participantes' => ['a', 'b'], 'dia' => $i, 'hora' => 0];
}
PersistenciaCaps::aplicar($p5);
$cap = PersistenciaCaps::cap($p5, 'memoria_eventos_cap', 500);
ok(count($p5['memoria_eventos']) <= $cap, "PersistenciaCaps: memoria_eventos respetado cap ($cap, got=" . count($p5['memoria_eventos']) . ')');

// --- 6) compactar no rompe memoria_eventos vacía ---
$p6 = ['memoria_eventos' => []];
MemoriaEventos::compactar($p6, 10);
ok(count($p6['memoria_eventos']) === 0, 'compactar: no rompe con array vacío');

// --- 7) hito por familia significativa ---
$p7 = ['memoria_eventos' => [], 'persistencia' => ['memoria_eventos_cap' => 10]];
for ($i = 0; $i < 5; $i++) {
    $p7['memoria_eventos'][] = ['familia' => 'casual', 'participantes' => ['a', 'b'], 'dia' => $i, 'hora' => 0, 'intensidad' => 1];
}
$p7['memoria_eventos'][] = ['familia' => 'declaracion', 'participantes' => ['a', 'b'], 'dia' => 5, 'hora' => 0, 'intensidad' => 1];
$p7['memoria_eventos'][] = ['familia' => 'casual', 'participantes' => ['a', 'b'], 'dia' => 6, 'hora' => 0];
MemoriaEventos::compactar($p7, 5);
$tieneDecl = false;
foreach ($p7['memoria_eventos'] as $ev) {
    if (($ev['familia'] ?? '') === 'declaracion') {
        $tieneDecl = true;
        break;
    }
}
ok($tieneDecl, 'compactar: preserva hitos por familia significativa');

echo "\n";
echo $fail === 0 ? "OK fase7_test\n" : "FAIL fase7_test ({$fail})\n";
exit($fail > 0 ? 1 : 0);
