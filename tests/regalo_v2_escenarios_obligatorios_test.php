<?php
declare(strict_types=1);

/* Regalos v2 (2026-09-02) — Escenarios obligatorios del contrato.
   Cobertura explicita de:
     1. regalo gustado
     2. regalo neutro
     3. regalo que no gusta
     4. NPC triste + regalo que mejora estado
     5. NPC enfadado + regalo que mejora estado
     6. la mejora emocional no borra la causa/contexto histórico
     7. gusto desconocido no revela información mágicamente
     8. no se duplica inventario ni se crea un segundo flujo de regalos
     9. comportamiento previo que deba conservarse no regresa
       (regalo_emocion_relacion_test.php debe seguir pasando tal cual)
*/

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\ConocimientoNpc;
use AquiHayTema\Engine\DiscoveryReveal;
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

function setEstado(array &$partida, string $rid, string $id, string $origen, array $ctx = []): void
{
    $partida['residentes'][$rid]['runtime']['estado_emocional'] = EstadoEmocional::estructura(
        $id,
        null,
        $origen,
        EstadoEmocional::marcaReloj($partida['reloj'] ?? null),
        null,
        $ctx,
        null
    );
}

// ============================================================
// 1. regalo gustado
// ============================================================
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 1, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['reaccion'] === RegaloEngine::LE_ENCANTA, '1: regalo gustado = LE_ENCANTA');
ok($r['reaccion'] !== RegaloEngine::INDIFERENTE, '1: NO indiferente');

// ============================================================
// 2. regalo neutro
// ============================================================
$partida = regalo_fixture_partida(['per_a' => regalo_perfil()]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'llave_vieja', 1, $catalogo); // sin hobby_ids
$r = RegaloEngine::entregar($partida, 'per_a', 'llave_vieja', $cal, $catalogo);
ok($r['reaccion'] === RegaloEngine::INDIFERENTE, '2: regalo neutro = INDIFERENTE');

// ============================================================
// 3. regalo que no gusta
// ============================================================
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_neg' => ['leer'], 'hobbies_pos' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 1, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['reaccion'] === RegaloEngine::NO_LE_GUSTA, '3: regalo que no gusta = NO_LE_GUSTA');

// ============================================================
// 4. NPC triste + regalo que mejora estado
// ============================================================
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['hobbies' => ['leer'], 'preferencias' => ['hobbies_pos' => [], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::TRISTE, 'encuentro', ['resultado_experiencia' => 'mal']);
DiscoveryReveal::registrarJugador($partida, 'per_a', ConocimientoNpc::campoHobby('leer'), 'leer', 'reveal_inicial');
InventarioEngine::anadir($partida, 'libro', 1, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($r['reaccion'] === RegaloEngine::LE_GUSTA, '4: regalo le_gusta');
ok($est['id'] !== EstadoEmocional::TRISTE, '4: estado MEJORA (sale de TRISTE)');
ok($est['id'] === EstadoEmocional::NEUTRO, '4: estado = NEUTRO (alivio)');

// ============================================================
// 5. NPC enfadado + regalo que mejora estado
// ============================================================
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::ENFADADO, 'discusion_fuerte', ['motivo' => 'disc']);
DiscoveryReveal::registrarJugador($partida, 'per_a', ConocimientoNpc::campoGusto('hobby', 'leer'), 'leer', 'encuentro');
InventarioEngine::anadir($partida, 'libro', 1, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($r['reaccion'] === RegaloEngine::LE_ENCANTA, '5: regalo le_encanta');
ok($est['id'] !== EstadoEmocional::ENFADADO, '5: estado MEJORA (sale de ENFADADO)');
ok($est['id'] === EstadoEmocional::ALEGRE, '5: estado = ALEGRE');

// ============================================================
// 6. La mejora emocional NO borra la causa/contexto histórico
// ============================================================
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::TRISTE, 'perder_trabajo', ['fuente' => 'f10_trabajo', 'causa_especifica' => 'despido_planta_3']);
DiscoveryReveal::registrarJugador($partida, 'per_a', ConocimientoNpc::campoGusto('hobby', 'leer'), 'leer', 'encuentro');
$bitacoraAntes = count($partida['bitacora_relaciones'] ?? []);
InventarioEngine::anadir($partida, 'libro', 1, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
$ctx = $partida['residentes']['per_a']['runtime']['estado_emocional']['contexto'];
ok(($ctx['estado_antes'] ?? '') === EstadoEmocional::TRISTE, '6: estado_antes=TRISTE preservado');
ok(($ctx['estado_antes_origen'] ?? '') === 'perder_trabajo', '6: origen=perder_trabajo preservado');
ok(($ctx['estado_antes_contexto']['fuente'] ?? '') === 'f10_trabajo', '6: contexto.causa específico preservado');
ok(($ctx['estado_antes_contexto']['causa_especifica'] ?? '') === 'despido_planta_3', '6: contexto.causa_especifica preservado');
ok(count($partida['bitacora_relaciones'] ?? []) > $bitacoraAntes, '6: bitacora_relaciones con REGISTRO del regalo');

// ============================================================
// 7. Gusto desconocido no revela información mágicamente
// ============================================================
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
$partida['features']['discovery_enabled'] = true; // activar discovery para verificar side-effect
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
// NO revelamos 'leer' al jugador.
InventarioEngine::anadir($partida, 'libro', 1, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['reaccion'] === RegaloEngine::LE_ENCANTA, '7: resolucion determinista usa perfil real');
ok(!isset($r['preferencias']), '7: payload sin preferencias internas');
ok(!isset($r['hobbies']), '7: payload sin hobbies internos');
ok(!isset($r['hobby_match']), '7: payload sin hobby_match interno');
ok(!isset($r['compatibilidad']) && !isset($r['probabilidad']) && !isset($r['score']),
   '7: payload sin scores');
ok(count($r['descubrimientos']) > 0, '7: side-effect: discovery_leer registrado tras LE_ENCANTA');
ok(is_string($r['descubrimientos'][0]['texto']) && $r['descubrimientos'][0]['texto'] !== '',
   '7: copy del descubrimiento no vacío');
ok($r['descubrimientos'][0]['campo'] === ConocimientoNpc::campoGusto('hobby', 'leer'),
   '7: campo del descubrimiento = gusto_hobby:leer');

// ============================================================
// 8. No se duplica inventario ni se crea un segundo flujo de regalos
// ============================================================
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 3, $catalogo);
$antesTotal = InventarioEngine::totalUnidades($partida);
$estructura = $partida['inventario'];
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok(InventarioEngine::totalUnidades($partida) === $antesTotal - 1, '8: inventario exactamente -1');
ok(is_array($partida['inventario']), '8: inventario sigue siendo array indexado por id');
ok(!array_key_exists('regalos_dos', $partida), '8: no aparece segundo flujo');
ok(!array_key_exists('regalos_log', $partida), '8: no aparece log paralelo');
ok(!array_key_exists('objetos', $partida), '8: no aparece sección objetos paralela');

// ============================================================
// 9. Comportamiento previo conservado (mismas aserciones que regalo_emocion_relacion_test.php)
// ============================================================

// LE ENCANTA -> alegre + aprecio +2
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 4, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($r['ok'] && $est['id'] === EstadoEmocional::ALEGRE, '9: le_encanta -> alegre (legacy)');
ok(($est['origen'] ?? '') === 'regalo', '9: origen=regalo');
ok((int) $est['duracion_horas'] === 6, '9: duracion_horas = 6 (sin causa fuerte previa)');
ok($r['delta_aprecio'] === 2 && $r['aprecio_celeste'] === 2, '9: aprecio +2');

// LE GUSTA -> alegre 3h + aprecio +1
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['hobbies' => ['leer'], 'preferencias' => ['hobbies_pos' => [], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 4, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($r['reaccion'] === RegaloEngine::LE_GUSTA, '9: le_gusta -> le_gusta');
ok($est['id'] === EstadoEmocional::ALEGRE, '9: le_gusta -> alegre');
ok((int) $est['duracion_horas'] === 3, '9: le_gusta duracion 3h');
ok($r['delta_aprecio'] === 1, '9: aprecio +1');

// NO LE GUSTA -> enfadado 4h + aprecio -1
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_neg' => ['leer'], 'hobbies_pos' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 4, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($est['id'] === EstadoEmocional::ENFADADO, '9: no_le_gusta -> enfadado');
ok((int) $est['duracion_horas'] === 4, '9: no_le_gusta duracion 4h');
ok($r['delta_aprecio'] === -1, '9: aprecio -1');

// INDIFERENTE -> sin emoción, aprecio 0
$partida = regalo_fixture_partida(['per_a' => regalo_perfil()]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'llave_vieja', 4, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'llave_vieja', $cal, $catalogo);
ok($r['reaccion'] === RegaloEngine::INDIFERENTE, '9: indiferente -> indiferente');
ok($r['emocion'] === null, '9: indiferente no aplica emoción');
ok($r['delta_aprecio'] === 0, '9: aprecio 0');

// Romance intacto
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 4, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok(($partida['relaciones_romanticas'] ?? []) === [], '9: romance intacto');
ok(!isset($r['romance']), '9: respuesta sin campo romance');

// ============================================================
// Coherencia con aprecio_celeste clamp -100..100
// ============================================================
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
$partida['residentes']['per_a']['runtime']['aprecio_celeste'] = 99;
InventarioEngine::anadir($partida, 'libro', 4, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['aprecio_celeste'] === 100, '9: aprecio clamp 99+2 -> 100 (NO 101)');

exit($failures > 0 ? 1 : 0);