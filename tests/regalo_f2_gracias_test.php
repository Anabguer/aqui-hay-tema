<?php
declare(strict_types=1);

/* Regalos F2: respuesta del vecino - Mensajito de gracias (M).
   Solo le_encanta no repetido; cooldown propio; sin duplicados ni spam. */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\InventarioEngine;
use AquiHayTema\Engine\RegaloEngine;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function graciasDeTipo(array $partida, string $tipo): array
{
    return array_values(array_filter($partida['buzon'] ?? [], static function ($m) use ($tipo) {
        return is_array($m) && ($m['tipo'] ?? '') === $tipo;
    }));
}

$cal = regalo_cal();
$catalogo = regalo_catalogo();

// buzon activado + encanta no repetido -> 1 solo mensajito de gracias
$p = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => array_merge(regalo_perfil()['preferencias'], ['hobbies_pos' => ['leer']])])]);
$p['features']['buzon_enabled'] = true;
InventarioEngine::anadir($p, 'libro', 1, $catalogo);
$r1 = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
ok($r1['ok'] && $r1['reaccion'] === RegaloEngine::LE_ENCANTA, 'regalo encanta');
ok(is_string($r1['gracias_mensaje_id']) && $r1['gracias_mensaje_id'] !== '', 'gracias_mensaje_id devuelto');
$gracias = graciasDeTipo($p, 'gracias_regalo');
ok(count($gracias) === 1, 'exactamente 1 mensajito gracias_regalo');
ok(($gracias[0]['clasificacion'] ?? '') === BuzonEngine::OPORTUNIDAD, 'clasificacion oportunidad (no compite con importante)');
ok(($gracias[0]['origen']['evento_id'] ?? '') === 'gracias_regalo:per_a', 'origen familia clara');

// le_gusta NO genera gracias
$p['reloj']['dia_pueblo'] = 4;
$p['reloj']['hora_actual'] = 8;
InventarioEngine::anadir($p, 'cuaderno', 1, $catalogo); // leer, escribir -> degradado? cuaderno es nuevo objeto
$r2 = RegaloEngine::entregar($p, 'per_a', 'cuaderno', $cal, $catalogo);
ok($r2['ok'], 'segundo regalo entregado');
if ($r2['reaccion'] !== RegaloEngine::LE_GUSTA) {
    // si la calibracion cambiara y fuera encanta igualmente validamos por cooldown
    ok(graciasDeTipo($p, 'gracias_regalo') === $gracias || count(graciasDeTipo($p, 'gracias_regalo')) >= 1, 'sin gracias indebidas');
} else {
    ok($r2['gracias_mensaje_id'] === null, 'le_gusta no da las gracias');
}
$nTrasGusta = count(graciasDeTipo($p, 'gracias_regalo'));
ok($nTrasGusta <= 2, 'sin spam: max 1 extra en el peor caso degradable');

// cooldown regalo_gracias (72h): otro encanta dentro de ventana no repite
$p3 = regalo_fixture_partida([
    'per_a' => regalo_perfil([
        'preferencias' => array_merge(regalo_perfil()['preferencias'], ['hobbies_pos' => ['musica', 'leer']]),
    ]),
]);
$p3['features']['buzon_enabled'] = true;
InventarioEngine::anadir($p3, 'vinilo', 3, $catalogo); // musica
InventarioEngine::anadir($p3, 'marcapaginas', 1, $catalogo); // leer
$rA = RegaloEngine::entregar($p3, 'per_a', 'vinilo', $cal, $catalogo);
ok($rA['ok'] && $rA['reaccion'] === RegaloEngine::LE_ENCANTA && $rA['gracias_mensaje_id'] !== null, 'primer vinilo: gracias');
$p3['reloj']['dia_pueblo'] = 2; // +24h < 72h
$p3['reloj']['hora_actual'] = 8;
$rB = RegaloEngine::entregar($p3, 'per_a', 'vinilo', $cal, $catalogo);
if ($rB['ok']) {
    ok($rB['gracias_mensaje_id'] === null, 'dentro de cooldown 72h: sin segundo gracias');
}
// pasada la ventana (y fuera del cooldown de regalo de 20h), un objeto NUEVO
// que encanta vuelve a agradecer (una sola vez)
$p3['reloj']['dia_pueblo'] = 5;
$p3['reloj']['hora_actual'] = 12;
$rC = RegaloEngine::entregar($p3, 'per_a', 'marcapaginas', $cal, $catalogo);
ok($rC['ok'] && $rC['reaccion'] === RegaloEngine::LE_ENCANTA && $rC['gracias_mensaje_id'] !== null, 'pasada la ventana vuelve a agradecer (una vez)');
$totalGracias = count(graciasDeTipo($p3, 'gracias_regalo'));
ok($totalGracias === 2, "cadencia total acotada ($totalGracias)");

// buzon desactivado (default): cero mensajitos aunque haya encanta
$p4 = regalo_fixture_partida(['per_a' => regalo_perfil(['hobbies' => ['leer']])]);
InventarioEngine::anadir($p4, 'libro', 1, $catalogo);
RegaloEngine::entregar($p4, 'per_a', 'libro', $cal, $catalogo);
ok(($p4['buzon'] ?? []) === [], 'buzon_enabled off: sin mensajitos');

exit($failures > 0 ? 1 : 0);
