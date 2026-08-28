<?php
declare(strict_types=1);

/* Regalos F1: inventario (ensure, anadir, consumir, cap, save antiguo). */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\InventarioEngine;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$catalogo = regalo_catalogo();

// save antiguo sin clave inventario
$p = regalo_fixture_partida(['per_a' => regalo_perfil()]);
InventarioEngine::ensure($p);
ok(isset($p['inventario']) && $p['inventario'] === [], 'ensure crea inventario vacio en save antiguo');
ok(InventarioEngine::listar($p) === [], 'listar en save antiguo = vacio');
ok(InventarioEngine::cantidad($p, 'libro') === 0, 'cantidad 0 en save antiguo');

// anadir + listar ordenado + acumular
$r1 = InventarioEngine::anadir($p, 'taza', 2, $catalogo);
$r2 = InventarioEngine::anadir($p, 'libro', 1, $catalogo);
$r3 = InventarioEngine::anadir($p, 'libro', 1, $catalogo);
ok($r1['ok'] && $r2['ok'] && $r3['ok'], 'anadir ok');
ok(InventarioEngine::cantidad($p, 'libro') === 2, 'anadir acumula');
ok(array_keys(InventarioEngine::listar($p)) === ['libro', 'taza'], 'listar ordenado por id');

// objeto desconocido (validado contra catalogo)
$rmal = InventarioEngine::anadir($p, 'mermelada_casera', 1, $catalogo);
ok(!$rmal['ok'] && $rmal['error'] === 'regalo_objeto_desconocido', 'anadir objeto desconocido rechazado');
ok(InventarioEngine::cantidad($p, 'mermelada_casera') === 0, 'desconocido no entra en inventario');

// consumir parcial y total
$rc = InventarioEngine::consumir($p, 'taza', 1);
ok($rc['ok'] && $rc['restante'] === 1, 'consumir parcial deja 1');
$rc2 = InventarioEngine::consumir($p, 'taza', 1);
ok($rc2['ok'] && $rc2['restante'] === 0, 'consumir total deja 0');
ok(!isset($p['inventario']['taza']), 'cantidad 0 se elimina del mapa');

// consumir sin unidades: error y nunca negativo
$rx = InventarioEngine::consumir($p, 'taza', 1);
ok(!$rx['ok'] && $rx['error'] === 'regalo_sin_unidades', 'consumir sin unidades rechazado');
ok(InventarioEngine::cantidad($p, 'taza') === 0, 'nunca cantidades negativas');

// cap de inventario (200 por defecto)
$p2 = regalo_fixture_partida(['per_a' => regalo_perfil()]);
$p2['persistencia'] = ['inventario_cap' => 10];
$ra = InventarioEngine::anadir($p2, 'libro', 7, $catalogo);
$rb = InventarioEngine::anadir($p2, 'taza', 5, $catalogo);
ok($ra['ok'] && $ra['anadido'] === 7, 'anadir bajo cap completo');
ok($rb['ok'] && $rb['anadido'] === 3, 'anadir se recorta al hueco del cap');
ok(InventarioEngine::totalUnidades($p2) === 10, 'cap respetado (total 10)');
$rc3 = InventarioEngine::anadir($p2, 'vinilo', 1, $catalogo);
ok(!$rc3['ok'] && $rc3['error'] === 'inventario_lleno', 'inventario lleno rechaza');
ok(InventarioEngine::cantidad($p2, 'vinilo') === 0, 'lleno: nada entra');

// determinismo: misma operacion, mismo resultado
$p3 = regalo_fixture_partida(['per_a' => regalo_perfil()]);
InventarioEngine::anadir($p3, 'libro', 2, $catalogo);
$p4 = regalo_fixture_partida(['per_a' => regalo_perfil()]);
InventarioEngine::anadir($p4, 'libro', 2, $catalogo);
ok($p3['inventario'] === $p4['inventario'], 'inventario determinista');

exit($failures > 0 ? 1 : 0);
