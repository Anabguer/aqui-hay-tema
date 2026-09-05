<?php
declare(strict_types=1);

/* Detallito sorpresa por misión individual — DESACTIVADO (producto 2026-09).

   Verifica que alCumplirMision ya no concede regalos aleatorios.
   Los regalos canónicos son solo 3/3 misiones e Historia del Pueblo.
*/

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\DetallitoEngine;
use AquiHayTema\Engine\InventarioEngine;
use AquiHayTema\Engine\MisionDiariaEngine;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function detallito_fixture_partida(): array
{
    $p = regalo_fixture_partida(['per_a' => regalo_perfil()]);
    $p['features'] = [
        'misiones_diarias_enabled' => true,
        'buzon_enabled' => true,
    ];
    MisionDiariaEngine::ensure($p);
    return $p;
}

function make_mision(string $id): array
{
    return [
        'id' => $id,
        'plantilla_id' => 'test_plantilla',
        'familia' => 'test',
        'dia' => 1,
        'estado' => 'pendiente',
        'texto' => 'Test mission',
        'hecho' => 'test',
        'params' => [],
        'exigencia' => 50,
        'cuenta_latido' => false,
    ];
}

// Ningún intento debe generar detallito
$conDetallito = 0;
for ($i = 0; $i < 30; $i++) {
    $px = detallito_fixture_partida();
    $m = make_mision('mis_off_' . $i);
    $px['misiones_diarias']['items'][] = $m;
    $r = DetallitoEngine::alCumplirMision($px, $m);
    if ($r !== null && ($r['ok'] ?? false)) {
        $conDetallito++;
    }
}
ok($conDetallito === 0, 'Detallito desactivado: 0 regalos en 30 intentos');

// Misión cumplida vía motor tampoco debe regalar por detallito
$p = detallito_fixture_partida();
$p['misiones_diarias']['items'][] = make_mision('mis_cumplir_1');
$antes = InventarioEngine::totalUnidades($p);
MisionDiariaEngine::cumplir($p, 'mis_cumplir_1', [], null);
$despues = InventarioEngine::totalUnidades($p);
ok($despues === $antes, '1 misión cumplida no altera inventario (sin 3/3)');

echo "\n" . ($failures === 0 ? 'ALL OK' : "FAILURES: $failures") . "\n";
exit($failures > 0 ? 1 : 0);
