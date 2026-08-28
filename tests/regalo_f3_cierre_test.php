<?php
declare(strict_types=1);

/* Regalos F3 - cierre: cotilleo por primer regalo + convivencia total de inventario
   (cap, cooldown, degradacion, discovery, gracias, cotilleo, aprecio, recompensa,
   save/load, romance/vida intactos). Sin duplicados ni farming. */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\DiscoveryEngine;
use AquiHayTema\Engine\InventarioEngine;
use AquiHayTema\Engine\PeticionFeedback;
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

function conTipo(array $partida, string $tipo): array
{
    return array_values(array_filter($partida['buzon'] ?? [], static function ($m) use ($tipo) {
        return is_array($m) && ($m['tipo'] ?? '') === $tipo;
    }));
}

$cal = regalo_cal();
$catalogo = regalo_catalogo();
$perfil = regalo_perfil(['preferencias' => array_merge(regalo_perfil()['preferencias'], ['hobbies_pos' => ['leer']])]);

// ---- cotilleo por primer regalo --------------------------------------------
$p = regalo_fixture_partida(['per_a' => $perfil]);
$p['features']['buzon_enabled'] = true;
InventarioEngine::anadir($p, 'libro', 3, $catalogo);
$r1 = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
ok($r1['ok'], 'primer regalo ok');
$cotis = conTipo($p, 'cotilleo_hito');
ok(count($cotis) === 1, 'exactamente 1 cotilleo por primer regalo');
ok(($cotis[0]['hito_clave'] ?? '') === 'regalo:per_a', 'dedupe clave regalo:per_x');
ok(($cotis[0]['clasificacion'] ?? '') === 'cotilleo', 'va al canal cotilleo (pestaña Diario)');
ok(strpos((string) $cotis[0]['texto'], 'Ana') !== false, 'copy habla del vecino, sin IDs');

$p['reloj']['dia_pueblo'] = 4; // fuera de cooldown
$r2 = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
ok($r2['ok'], 'segundo regalo ok (degradado)');
ok(count(conTipo($p, 'cotilleo_hito')) === 1, 'sin spam: el segundo regalo NO repite cotilleo');

// buzon off: cero cotilleo
$p2 = regalo_fixture_partida(['per_b' => $perfil]);
InventarioEngine::anadir($p2, 'libro', 1, $catalogo);
RegaloEngine::entregar($p2, 'per_b', 'libro', $cal, $catalogo);
ok(($p2['buzon'] ?? []) === [], 'buzon_enabled off: ni gracias ni cotilleo');

// ---- convivencia total sobre save antiguo -----------------------------------
$partida = [
    'meta' => ['partida_id' => 'part_lab_f3'],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 8],
    'residentes' => [
        'per_x' => [
            'identidad_publica' => ['nombre' => 'Clio'],
            'runtime' => ['perfil_partida' => $perfil],
        ],
    ],
    'relaciones_sociales' => [],
    'relaciones_romanticas' => [],
];
$partida['features'] = ['buzon_enabled' => true, 'discovery_enabled' => true];
$partida['peticiones'] = [[
    'id' => 'pet_f3_dif',
    'peso' => 'dificil',
    'residente_id' => 'per_x',
    'texto' => 'Cuelga el cartel del mercadillo',
    'estado' => 'abierta',
    'schema_b4' => true,
]];

InventarioEngine::anadir($partida, 'libro', 2, $catalogo); // "dev.otorgar"
$partida['peticiones'][0]['estado'] = 'resuelta';
PeticionFeedback::alCumplir($partida, $partida['peticiones'][0]); // recompensa organica
ok(InventarioEngine::totalUnidades($partida) === 3, 'recompensa organica suma al inventario (2+1)');
ok(($partida['peticiones'][0]['recompensa_regalo']['estado'] ?? '') === 'entregada', 'marca idempotente');
$nUnidades = InventarioEngine::totalUnidades($partida);
PeticionFeedback::alCumplir($partida, $partida['peticiones'][0]);
ok(InventarioEngine::totalUnidades($partida) === $nUnidades, 're-cumplir no duplica recompensa');

// entrega completa
$rA = RegaloEngine::entregar($partida, 'per_x', 'libro', $cal, $catalogo);
ok($rA['ok'] && $rA['reaccion'] === RegaloEngine::LE_ENCANTA, 'entrega encanta');
ok(($rA['descubrimientos'][0]['campo'] ?? '') === 'gusto_hobby:leer', 'discovery en la entrega');
ok(DiscoveryEngine::estado($partida, 'per_x', 'gusto_hobby:leer') === DiscoveryEngine::DESCUBIERTO, 'discovery persistido');
ok((int) $partida['residentes']['per_x']['runtime']['aprecio_celeste'] === 2, 'aprecio +2');
ok(count(conTipo($partida, 'gracias_regalo')) === 1, '1 gracias');
ok(count(conTipo($partida, 'cotilleo_hito')) === 1, '1 cotilleo (primer regalo)');

// cooldown + degradacion
$rB = RegaloEngine::entregar($partida, 'per_x', 'libro', $cal, $catalogo);
ok(!$rB['ok'] && $rB['error'] === 'regalo_cooldown', 'cooldown bloquea sin consumir');
$partida['reloj']['dia_pueblo'] = 4;
$rC = RegaloEngine::entregar($partida, 'per_x', 'libro', $cal, $catalogo);
ok($rC['ok'] && $rC['reaccion'] === RegaloEngine::LE_GUSTA, 'degradacion encanta->gusta');
ok(count(conTipo($partida, 'gracias_regalo')) === 1, 'sin segunda gracias');
ok(count(conTipo($partida, 'cotilleo_hito')) === 1, 'sin segundo cotilleo');

// save/load
$recargada = json_decode(json_encode($partida), true);
InventarioEngine::ensure($recargada);
ok(InventarioEngine::listar($recargada) === InventarioEngine::listar($partida), 'inventario sobrevive');
ok(DiscoveryEngine::estado($recargada, 'per_x', 'gusto_hobby:leer') === DiscoveryEngine::DESCUBIERTO, 'discovery sobrevive');

// no romance / vida intactos
ok(($recargada['relaciones_romanticas'] ?? []) === [], 'NO romance: cero relaciones romanticas');
ok(!isset($recargada['residentes']['per_x']['runtime']['romance']), 'NO romance: sin runtime romance');

exit($failures > 0 ? 1 : 0);
