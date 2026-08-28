<?php
declare(strict_types=1);

/* Regalos F1: efectos emocionales y de aprecio. Romance y Vida del Pueblo intactos. */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\EstadoEmocional;
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

$cal = regalo_cal();
$catalogo = regalo_catalogo();

function partida_con(string $hobbyPos = '', string $hobbyNeg = '', string $propio = ''): array
{
    $prefs = regalo_perfil()['preferencias'];
    if ($hobbyPos !== '') {
        $prefs['hobbies_pos'] = [$hobbyPos];
    }
    if ($hobbyNeg !== '') {
        $prefs['hobbies_neg'] = [$hobbyNeg];
    }
    $p = regalo_fixture_partida([
        'per_a' => regalo_perfil(['hobbies' => $propio !== '' ? [$propio] : [], 'preferencias' => $prefs]),
    ]);
    InventarioEngine::anadir($p, 'libro', 4, regalo_catalogo());
    return $p;
}

// LE ENCANTA -> alegre + aprecio +2
$p = partida_con(hobbyPos: 'leer');
$vidaAntes = $p['vida_pueblo']['valor'] ?? null;
$r = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
$estado = $p['residentes']['per_a']['runtime']['estado_emocional'] ?? null;
ok($r['ok'] && ($estado['id'] ?? '') === EstadoEmocional::ALEGRE, 'le_encanta -> alegre');
ok(($estado['origen'] ?? '') === 'regalo', 'origen de emocion = regalo');
ok(($estado['duracion_horas'] ?? 0) === 6, 'le_encanta dura 6h (calibracion)');
ok($r['delta_aprecio'] === 2 && $r['aprecio_celeste'] === 2, 'le_encanta aprecio +2');

// LE GUSTA -> alegre mas breve + aprecio +1
$p = partida_con(propio: 'leer');
$r = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
$estado = $p['residentes']['per_a']['runtime']['estado_emocional'] ?? null;
ok($r['ok'] && ($estado['id'] ?? '') === EstadoEmocional::ALEGRE, 'le_gusta -> alegre');
ok(($estado['duracion_horas'] ?? 0) === 3, 'le_gusta dura 3h (menor que encanta)');
ok($r['delta_aprecio'] === 1, 'le_gusta aprecio +1');

// INDIFERENTE -> sin emocion nueva, aprecio 0
$p = partida_con();
$antes = $p['residentes']['per_a']['runtime']['estado_emocional'] ?? null;
$r = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
$despues = $p['residentes']['per_a']['runtime']['estado_emocional'] ?? null;
ok($r['ok'] && $r['reaccion'] === RegaloEngine::INDIFERENTE, 'indiferente resuelto');
ok($antes === $despues, 'indiferente no cambia emocion');
ok($r['delta_aprecio'] === 0 && $r['aprecio_celeste'] === 0, 'indiferente aprecio 0');

// NO LE GUSTA -> enfadado breve + aprecio -1
$p = partida_con(hobbyNeg: 'leer');
$r = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
$estado = $p['residentes']['per_a']['runtime']['estado_emocional'] ?? null;
ok($r['ok'] && ($estado['id'] ?? '') === EstadoEmocional::ENFADADO, 'no_le_gusta -> enfadado');
ok(($estado['duracion_horas'] ?? 0) === 4, 'no_le_gusta dura 4h (breve)');
ok($r['delta_aprecio'] === -1 && $r['aprecio_celeste'] === -1, 'no_le_gusta aprecio -1');

// aprecio con clamp -100..100
$p = partida_con(hobbyPos: 'leer');
$p['residentes']['per_a']['runtime']['aprecio_celeste'] = 99;
$r = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
ok($r['aprecio_celeste'] === 100, 'aprecio clamp superior a 100');

// ROMANCE intacto
ok(($p['relaciones_romanticas'] ?? []) === [], 'regalo no crea romance');
ok(!isset($r['romance']), 'respuesta no toca romance');

// VIDA DEL PUEBLO intacta
$vidaDespues = $p['vida_pueblo']['valor'] ?? null;
ok($vidaAntes === $vidaDespues, 'vida del pueblo sin cambios');

// memoria en bitacora con meta minima
$hito = end($p['bitacora_relaciones']);
ok(($hito['tipo'] ?? '') === 'regalo', 'hito tipo regalo en bitacora');
ok(($hito['meta']['objeto_id'] ?? '') === 'libro', 'hito meta objeto_id');
ok(($hito['meta']['reaccion'] ?? '') === RegaloEngine::LE_ENCANTA, 'hito meta reaccion');
ok(in_array('per_a', $hito['participantes'] ?? [], true), 'hito participa el vecino');

exit($failures > 0 ? 1 : 0);
