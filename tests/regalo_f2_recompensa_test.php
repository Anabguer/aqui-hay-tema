<?php
declare(strict_types=1);

/* Regalos F2: fuente organica via peticion cumplida (F, G, L).
   F - cumplir determinadas peticiones otorga un objeto al inventario.
   G - refresh/reintento no duplica la recompensa.
   L - romance y vida del pueblo NO reciben efectos accidentales. */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\InventarioEngine;
use AquiHayTema\Engine\PeticionFeedback;
use AquiHayTema\Engine\PeticionEsquemas;
use AquiHayTema\Engine\RegaloRecompensaEngine;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function partida_con_peticion(string $pid, string $peso): array
{
    $p = regalo_fixture_partida(['per_a' => regalo_perfil()]);
    $p['peticiones'] = [[
        'id' => $pid,
        'peso' => $peso,
        'residente_id' => 'per_a',
        'texto' => 'Arregla el banco de la plaza',
        'estado' => 'abierta',
        'schema_b4' => true,
    ]];
    $p['peticiones_pueblo'] = ['validos_dia' => 0];
    return $p;
}

// ---- F: recompensa organica ------------------------------------------------
// facil: prob 0 -> nunca (roll determinista)
$pf = partida_con_peticion('pet_facil_01', PeticionEsquemas::PESO_FACIL);
PeticionFeedback::alCumplir($pf, $pf['peticiones'][0]);
ok(($pf['peticiones'][0]['recompensa_regalo']['estado'] ?? '') === 'no_toca', 'F: peso facil nunca da regalo');
ok(InventarioEngine::totalUnidades($pf) === 0, 'F: facil no anade unidades');

// dificil: prob 1 -> siempre, con objeto del catalogo y marca persistente
$pd = partida_con_peticion('pet_dif_01', PeticionEsquemas::PESO_DIFICIL);
$romanceAntes = $pd['relaciones_romanticas'];
$vidaAntes = $pd['vida_pueblo'] ?? null;
$r = RegaloRecompensaEngine::porPeticionCumplida($pd, $pd['peticiones'][0]);
ok($r !== null && ($r['ok'] ?? false), 'F: peticion dificil cumplida otorga regalo');
$objeto = (string) ($r['objeto_id'] ?? '');
ok($objeto !== '' && regalo_catalogo()->item('regalos', $objeto) !== null, "F: objeto real del catalogo ($objeto)");
ok(InventarioEngine::cantidad($pd, $objeto) === 1, 'F: unidad en inventario');
ok(($pd['peticiones'][0]['recompensa_regalo']['estado'] ?? '') === 'entregada', 'F: peticion marcada entregada');
ok(($pd['peticiones_pueblo']['recompensas_dia']['otorgadas'] ?? 0) === 1, 'F: contador diario persistido');
// L: sin efectos colaterales
ok($pd['relaciones_romanticas'] === $romanceAntes, 'L: romance intacto');
ok(($pd['residentes']['per_a']['runtime']['aprecio_celeste'] ?? null) === null, 'L: aprecio no cambia por recompensa');
if ($vidaAntes !== null) {
    ok($pd['vida_pueblo'] === $vidaAntes, 'L: vida del pueblo intacta');
}

// determinismo: mismo pid en partidas distintas -> mismo objeto
$pd2 = partida_con_peticion('pet_dif_01', PeticionEsquemas::PESO_DIFICIL);
$r2 = RegaloRecompensaEngine::porPeticionCumplida($pd2, $pd2['peticiones'][0]);
ok(($r2['objeto_id'] ?? '') === $objeto, 'G/determinismo: mismo pid -> mismo objeto');

// relevante: roll determinista respeta la probabilidad calibrada
$pidsRelevante = ['pet_rel_a', 'pet_rel_b', 'pet_rel_c', 'pet_rel_d'];
$conRegalo = 0;
foreach ($pidsRelevante as $pid) {
    $pr = partida_con_peticion($pid, PeticionEsquemas::PESO_RELEVANTE);
    RegaloRecompensaEngine::porPeticionCumplida($pr, $pr['peticiones'][0]);
    if (($pr['peticiones'][0]['recompensa_regalo']['estado'] ?? '') === 'entregada') {
        $conRegalo++;
    }
}
ok($conRegalo > 0 && $conRegalo < count($pidsRelevante), "F: relevante calibrado (granted $conRegalo/4)");

// ---- G: idempotencia / anti-duplicacion ------------------------------------
$n1 = InventarioEngine::totalUnidades($pd);
PeticionFeedback::alCumplir($pd, $pd['peticiones'][0]); // reintento directo
RegaloRecompensaEngine::porPeticionCumplida($pd, $pd['peticiones'][0]); // doble hook
ok(InventarioEngine::totalUnidades($pd) === $n1, 'G: re-cumplir la misma peticion no duplica');

// tope diario: segunda dificil el MISMO dia no otorga otra unidad
$pd3 = partida_con_peticion('pet_dif_02', PeticionEsquemas::PESO_DIFICIL);
$pd3['peticiones_pueblo']['recompensas_dia'] = ['n' => 1, 'otorgadas' => 1]; // ya hubo una hoy
$r3 = RegaloRecompensaEngine::porPeticionCumplida($pd3, $pd3['peticiones'][0]);
ok($r3 === null && ($pd3['peticiones'][0]['recompensa_regalo']['estado'] ?? '') === 'tope_diario', 'G: max_por_dia respetado');

// inventario lleno: sin hueco, marcado, sin crash
$pd4 = partida_con_peticion('pet_dif_03', PeticionEsquemas::PESO_DIFICIL);
InventarioEngine::anadir($pd4, 'libro', 200, regalo_catalogo()); // llena el cap 200
$r4 = RegaloRecompensaEngine::porPeticionCumplida($pd4, $pd4['peticiones'][0]);
ok($r4 === null && ($pd4['peticiones'][0]['recompensa_regalo']['estado'] ?? '') === 'sin_hueco', 'G: inventario lleno -> sin_hueco');

exit($failures > 0 ? 1 : 0);
