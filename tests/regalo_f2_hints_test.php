<?php
declare(strict_types=1);

/* Regalos F2: Hints al regalar (E).
   E - los hints usan EXCLUSIVAMENTE conocimiento ya descubierto por el jugador.
   Sin descubrimientos previos: cero pistas, aunque el perfil oculto tenga prefs. */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\ConocimientoNpc;
use AquiHayTema\Engine\DiscoveryReveal;
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

function perfil_f2h(): array
{
    return regalo_perfil([
        'hobbies' => ['videojuegos'],
        'preferencias' => array_merge(regalo_perfil()['preferencias'], [
            'hobbies_pos' => ['leer', 'correr'],
            'hobbies_neg' => ['deporte'],
        ]),
    ]);
}

$catalogo = regalo_catalogo();

// sin nada descubierto: NINGUN hint (aunque perfil tenga pos/neg reales)
$p = regalo_fixture_partida(['per_a' => perfil_f2h()]);
ok(RegaloHints::paraObjeto($p, 'per_a', ['leer', 'escribir']) === null, 'sin discovery: libro sin hint');
ok(RegaloHints::paraObjeto($p, 'per_a', ['deporte', 'correr']) === null, 'sin discovery: cantimplora sin hint');
ok(RegaloHints::paraObjeto($p, 'per_a', ['videojuegos']) === null, 'sin discovery: hobby propio sin hint');
ok(RegaloHints::paraObjeto($p, 'per_a', []) === null, 'objeto neutro sin hobby_ids: sin hint');

// gusto descubierto -> encanta
DiscoveryReveal::registrarJugador($p, 'per_a', ConocimientoNpc::campoGusto('hobby', 'leer'), 'leer', 'test');
$h = RegaloHints::paraObjeto($p, 'per_a', ['leer', 'escribir']);
ok($h !== null && $h['nivel'] === 'le_encanta' && $h['valor'] === 'leer', 'gusto descubierto -> hint le_encanta');
$txt = RegaloHints::textoDe($h, 'Ana', $catalogo);
ok($txt === 'Sabes que a Ana le encanta Leer.', "copy encanta correcto ($txt)");

// hobby conocido (reveal/encuentros) -> gusta, pero SOLO si no hay gusto/rechazo descubierto para ese objeto
DiscoveryReveal::registrarJugador($p, 'per_a', ConocimientoNpc::campoHobby('videojuegos'), 'videojuegos', 'test');
$h2 = RegaloHints::paraObjeto($p, 'per_a', ['videojuegos']);
ok($h2 !== null && $h2['nivel'] === 'le_gusta', 'hobby conocido -> hint le_gusta');
$txt2 = RegaloHints::textoDe($h2, 'Ana', $catalogo);
ok($txt2 === 'Sabes que a Ana le gusta Videojuegos.', "copy gusta correcto ($txt2)");
$otro = RegaloHints::paraObjeto($p, 'per_a', ['bingo']);
ok($otro === null, 'objeto sin relacion con lo conocido: sin hint');

// rechazo gana sobre gusto en el MISMO objeto (precedencia canonica)
DiscoveryReveal::registrarJugador($p, 'per_a', ConocimientoNpc::campoGusto('hobby', 'correr'), 'correr', 'test');
DiscoveryReveal::registrarJugador($p, 'per_a', ConocimientoNpc::campoRechazo('hobby', 'deporte'), 'deporte', 'test');
$h3 = RegaloHints::paraObjeto($p, 'per_a', ['deporte', 'correr']);
ok($h3 !== null && $h3['nivel'] === 'no_le_gusta' && $h3['valor'] === 'deporte', 'rechazo descubierto gana al gusto');
$txt3 = RegaloHints::textoDe($h3, 'Ana', $catalogo);
ok($txt3 === 'Has descubierto que Ana no soporta deporte.', "copy rechazo correcto ($txt3)");

// el hint nunca filtra valores NO conocidos del mismo campo
$p2 = regalo_fixture_partida(['per_b' => perfil_f2h()]);
DiscoveryReveal::registrarJugador($p2, 'per_b', ConocimientoNpc::campoGusto('hobby', 'leer'), 'leer', 'test');
$h4 = RegaloHints::paraObjeto($p2, 'per_b', ['deporte', 'correr']);
ok($h4 === null, 'solo porque correr/deporte esten en prefs ocultos NO hay hint');

exit($failures > 0 ? 1 : 0);
