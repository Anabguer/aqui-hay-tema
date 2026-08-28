<?php
declare(strict_types=1);

/* Regalos F2: persistencia (H, N).
   H - save/load conserva inventario, descubrimientos y aprecio.
   N - saves antiguos sin inventario/descubrimientos/aprecio siguen cargando. */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\ConocimientoNpc;
use AquiHayTema\Engine\DiscoveryEngine;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\InventarioEngine;
use AquiHayTema\Engine\RegaloEngine;
use AquiHayTema\Engine\RegaloHints;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$cal = regalo_cal();
$catalogo = regalo_catalogo();

// ---- H: save/load roundtrip -------------------------------------------------
$p = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => array_merge(regalo_perfil()['preferencias'], ['hobbies_pos' => ['leer']])]),
]);
$p['features']['discovery_enabled'] = true;
InventarioEngine::anadir($p, 'libro', 2, $catalogo);
InventarioEngine::anadir($p, 'taza', 1, $catalogo);
RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo); // inventario-1, discovery + aprecio +2
$aprecioEsperado = $p['residentes']['per_a']['runtime']['aprecio_celeste'];
$invAntes = InventarioEngine::listar($p);
$discAntes = $p['descubrimientos'];

$serial = json_encode($p);
$p2 = json_decode($serial, true);
ok(is_array($p2), 'json roundtrip valido');
InventarioEngine::ensure($p2);
ok(InventarioEngine::listar($p2) === $invAntes, 'H: inventario identico tras save/load');
ok(DiscoveryEngine::estado($p2, 'per_a', ConocimientoNpc::campoGusto('hobby', 'leer')) === DiscoveryEngine::DESCUBIERTO, 'H: descubrimiento sobrevive');
ok((int) $p2['residentes']['per_a']['runtime']['aprecio_celeste'] === (int) $aprecioEsperado, 'H: aprecio sobrevive');
// hints siguen resolviendo tras recargar
$h = RegaloHints::paraObjeto($p2, 'per_a', ['leer']);
ok($h !== null && $h['nivel'] === 'le_encanta', 'H: hints funcionan tras load');
// y el motor sigue resolviendo coherente tras load
$p2['reloj']['dia_pueblo'] = 4;
InventarioEngine::anadir($p2, 'marcapaginas', 1, $catalogo);
$r = RegaloEngine::entregar($p2, 'per_a', 'marcapaginas', $cal, $catalogo);
ok($r['ok'] && $r['reaccion'] === RegaloEngine::LE_ENCANTA, 'H: regalo post-load resuelve normal');

// ---- N: save antiguo ---------------------------------------------------------
$viejo = [
    'meta' => ['partida_id' => 'part_legacy'],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 8],
    'residentes' => [
        'per_a' => [
            'identidad_publica' => ['nombre' => 'Ana'],
            'runtime' => ['perfil_partida' => regalo_perfil(['preferencias' => array_merge(regalo_perfil()['preferencias'], ['hobbies_pos' => ['leer']])])],
        ],
    ],
    // SIN inventario, SIN descubrimientos, SIN memoria_eventos, SIN bitacora_relaciones
];
InventarioEngine::ensure($viejo);
ok(isset($viejo['inventario']) && $viejo['inventario'] === [], 'N: ensure crea inventario en save antiguo');
ok(InventarioEngine::totalUnidades($viejo) === 0, 'N: total 0 en save antiguo');
ok(RegaloHints::paraObjeto($viejo, 'per_a', ['leer']) === null, 'N: hints vacios sin discoveries');
$viejo['features']['discovery_enabled'] = true;
InventarioEngine::anadir($viejo, 'libro', 1, $catalogo);
$rv = RegaloEngine::entregar($viejo, 'per_a', 'libro', $cal, $catalogo);
ok($rv['ok'], 'N: entregar funciona sobre save antiguo');
ok(count($viejo['descubrimientos']) === 1, 'N: discovery crea seccion nueva sin romper nada');
ok(($viejo['bitacora_relaciones'][0]['tipo'] ?? '') === 'regalo', 'N: bitacora se crea al vuelo');

exit($failures > 0 ? 1 : 0);
