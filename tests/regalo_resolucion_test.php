<?php
declare(strict_types=1);

/* Regalos F1: resolucion de reaccion SOLO con datos reales del perfil. */

require_once __DIR__ . '/regalos_f1_fixture.php';

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

$cal = regalo_cal();
$catalogo = regalo_catalogo();
$libro = $catalogo->item('regalos', 'libro');

// hobbies_pos -> LE ENCANTA
$p = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => array_merge(regalo_perfil()['preferencias'], ['hobbies_pos' => ['leer']])]),
]);
ok(RegaloEngine::resolverReaccion($p, 'per_a', $libro, $cal) === RegaloEngine::LE_ENCANTA, 'hobbies_pos -> le_encanta');

// hobby propio -> LE GUSTA
$p = regalo_fixture_partida([
    'per_a' => regalo_perfil(['hobbies' => ['leer']]),
]);
ok(RegaloEngine::resolverReaccion($p, 'per_a', $libro, $cal) === RegaloEngine::LE_GUSTA, 'hobby propio -> le_gusta');

// hobbies_neg -> NO LE GUSTA
$p = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => array_merge(regalo_perfil()['preferencias'], ['hobbies_neg' => ['leer']])]),
]);
ok(RegaloEngine::resolverReaccion($p, 'per_a', $libro, $cal) === RegaloEngine::NO_LE_GUSTA, 'hobbies_neg -> no_le_gusta');

// negativo gana a positivo (precedencia canonica rechazo > gusto > propio)
$p = regalo_fixture_partida([
    'per_a' => regalo_perfil([
        'hobbies' => ['leer'],
        'preferencias' => array_merge(regalo_perfil()['preferencias'], ['hobbies_pos' => ['leer'], 'hobbies_neg' => ['escribir']]),
    ]),
]);
ok(RegaloEngine::resolverReaccion($p, 'per_a', $libro, $cal) === RegaloEngine::NO_LE_GUSTA, 'negativo gana a positivo');

// sin match -> INDIFERENTE
$p = regalo_fixture_partida([
    'per_a' => regalo_perfil(['hobbies' => ['bingo'], 'preferencias' => array_merge(regalo_perfil()['preferencias'], ['hobbies_pos' => ['copas']])]),
]);
ok(RegaloEngine::resolverReaccion($p, 'per_a', $libro, $cal) === RegaloEngine::INDIFERENTE, 'sin match -> indiferente');

// objeto sin hobby_ids -> siempre INDIFERENTE
$llave = $catalogo->item('regalos', 'llave_vieja');
$p = regalo_fixture_partida([
    'per_a' => regalo_perfil(['hobbies' => ['leer', 'escribir']]),
]);
ok(RegaloEngine::resolverReaccion($p, 'per_a', $llave, $cal) === RegaloEngine::INDIFERENTE, 'hobby_ids vacio -> indiferente');

// degradacion determinista
ok(RegaloEngine::degradar(RegaloEngine::LE_ENCANTA) === RegaloEngine::LE_GUSTA, 'degradar encanta -> gusta');
ok(RegaloEngine::degradar(RegaloEngine::LE_GUSTA) === RegaloEngine::INDIFERENTE, 'degradar gusta -> indiferente');
ok(RegaloEngine::degradar(RegaloEngine::INDIFERENTE) === RegaloEngine::INDIFERENTE, 'degradar indiferente -> indiferente');
ok(RegaloEngine::degradar(RegaloEngine::NO_LE_GUSTA) === RegaloEngine::NO_LE_GUSTA, 'degradar no_gusta -> no_gusta');

// catalogo: 25 canonicos, hobby_ids validos, assets reales, duplicado excluido
$items = $catalogo->items('regalos');
ok(count($items) === 25, 'catalogo con 25 regalos canonicos');
$ids = array_map(static fn($r) => $r['id'], $items);
ok(count($ids) === count(array_unique($ids)), 'ids de regalo unicos');
ok(!in_array('mermelada_casera', $ids, true), 'mermelada_casera excluido (duplicado byte-identico de tarro_mermelada)');
ok(in_array('tarro_mermelada', $ids, true), 'tarro_mermelada canonico presente');
$hobbyIds = $catalogo->ids('hobbies');
$mal = [];
$root = dirname(__DIR__);
foreach ($items as $r) {
    foreach ($r['hobby_ids'] ?? [] as $h) {
        if (!in_array($h, $hobbyIds, true)) {
            $mal[] = $r['id'] . ':' . $h;
        }
    }
    if (!is_file($root . '/assets/play-v3/' . $r['asset'])) {
        $mal[] = $r['id'] . ':asset_falta(' . $r['asset'] . ')';
    }
}
ok($mal === [], 'hobby_ids contra aficiones.json y assets existentes' . ($mal === [] ? '' : ': ' . implode(',', $mal)));

exit($failures > 0 ? 1 : 0);
