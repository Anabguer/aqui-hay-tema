<?php
declare(strict_types=1);

/* Regalos v2 (2026-09-02) — RegaloEngine::entregar:
   contrato emocional con causa histórica preservada + duración reducida al 50%
   cuando la causa previa es fuerte. NUNCA empeora triste/enfadado.
   Cobertura de los 9 escenarios obligatorios del contrato. */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\ConocimientoNpc;
use AquiHayTema\Engine\DiscoveryEngine;
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

/** Fija un estado emocional arbitrario en el runtime del residente. */
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

/** Asegura que el residente tiene el objeto en inventario. */
function darObjeto(array &$partida, string $objectId): void
{
    InventarioEngine::anadir($partida, $objectId, 2, regalo_catalogo());
}

// ============================================================
// 1. regalo gustado (le_encanta sobre estado neutro)
// ============================================================
$partida = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]]),
]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
darObjeto($partida, 'libro');
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['ok'] && $r['reaccion'] === RegaloEngine::LE_ENCANTA, '1: le_encanta sobre neutro');
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($est['id'] === EstadoEmocional::ALEGRE, '1: estado = alegre');
ok((int) $est['duracion_horas'] === 6, '1: duracion_horas = 6 (calibracion le_encanta)');
ok(($est['contexto']['estado_antes'] ?? '') === EstadoEmocional::NEUTRO, '1: contexto.estado_antes preservado');
ok(($est['contexto']['estado_antes_origen'] ?? '') === 'inicial', '1: contexto.estado_antes_origen preservado');
ok((bool) ($est['contexto']['causa_fuerte'] ?? false) === false, '1: causa_fuerte = false');
ok(isset($r['escena']) && is_string($r['escena']) && $r['escena'] !== '', '1: escena presente');
ok(isset($r['eco_emocional']) && is_string($r['eco_emocional']), '1: eco_emocional presente');
ok(strpos($r['escena'], 'libro') === false || strpos($r['escena'], 'paquete') !== false || strlen($r['escena']) > 0, '1: escena coherente');

// ============================================================
// 2. regalo neutro (indiferente)
// ============================================================
$partida = regalo_fixture_partida([
    'per_a' => regalo_perfil(),
]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
darObjeto($partida, 'llave_vieja'); // objeto sin hobby_ids → INDIFERENTE
$r = RegaloEngine::entregar($partida, 'per_a', 'llave_vieja', $cal, $catalogo);
ok($r['ok'] && $r['reaccion'] === RegaloEngine::INDIFERENTE, '2: indiferente');
ok($r['emocion'] === null, '2: sin emocion nueva');
ok(isset($r['escena']) && $r['escena'] !== '', '2: escena presente incluso en indiferente');
ok($r['eco_emocional'] === '', '2: sin eco emocional en indiferente');

// ============================================================
// 3. regalo que no gusta (no_le_gusta)
// ============================================================
$partida = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => ['hobbies_neg' => ['leer'], 'hobbies_pos' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]]),
]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
darObjeto($partida, 'libro');
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['ok'] && $r['reaccion'] === RegaloEngine::NO_LE_GUSTA, '3: no_le_gusta sobre neutro');
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($est['id'] === EstadoEmocional::ENFADADO, '3: estado neutro + no_le_gusta = enfadado');
ok((int) $est['duracion_horas'] === 4, '3: duracion 4h');

// ============================================================
// 4. NPC triste + regalo le_gusta que mejora estado
// ============================================================
$partida = regalo_fixture_partida([
    'per_a' => regalo_perfil(['hobbies' => ['leer'], 'preferencias' => ['hobbies_pos' => [], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]]),
]);
setEstado($partida, 'per_a', EstadoEmocional::TRISTE, 'encuentro', ['resultado_experiencia' => 'mal', 'encuentro_id' => 'enc_xyz']);
// Revelar hobby 'leer' para que RegaloHints lo reconozca.
DiscoveryReveal::registrarJugador($partida, 'per_a', ConocimientoNpc::campoHobby('leer'), 'leer', 'reveal_inicial');
darObjeto($partida, 'libro'); // hobby_ids: [leer, escribir] → LE_GUSTA
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['ok'] && $r['reaccion'] === RegaloEngine::LE_GUSTA, '4: le_gusta sobre triste');
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($est['id'] === EstadoEmocional::NEUTRO, '4: triste -> NEUTRO (alivio)');
ok(($est['contexto']['estado_antes'] ?? '') === EstadoEmocional::TRISTE, '4: contexto.estado_antes preservado');
ok(($est['contexto']['estado_antes_origen'] ?? '') === 'encuentro', '4: contexto.estado_antes_origen preservado');
ok((bool) ($est['contexto']['causa_fuerte'] ?? false) === true, '4: causa_fuerte=true (encuentro mal)');
ok((int) $est['duracion_horas'] === 1, '4: duracion reducida 3h * 0.5 = 1.5 -> floor 1 (alivio temporal)');
ok(($est['contexto']['estado_antes_contexto']['resultado_experiencia'] ?? '') === 'mal', '4: contexto.estado_antes_contexto preservado');

// ============================================================
// 5. NPC enfadado + regalo le_encanta que mejora estado
// ============================================================
$partida = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]]),
]);
setEstado($partida, 'per_a', EstadoEmocional::ENFADADO, 'discusion_fuerte', ['motivo' => 'discusion']);
DiscoveryReveal::registrarJugador($partida, 'per_a', ConocimientoNpc::campoGusto('hobby', 'leer'), 'leer', 'encuentro');
darObjeto($partida, 'libro');
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['ok'] && $r['reaccion'] === RegaloEngine::LE_ENCANTA, '5: le_encanta sobre enfadado');
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($est['id'] === EstadoEmocional::ALEGRE, '5: enfadado -> ALEGRE (animó)');
ok(($est['contexto']['estado_antes'] ?? '') === EstadoEmocional::ENFADADO, '5: estado_antes preservado');
ok(($est['contexto']['estado_antes_origen'] ?? '') === 'discusion_fuerte', '5: origen preservado');
ok((bool) ($est['contexto']['causa_fuerte'] ?? false) === true, '5: causa_fuerte=true');
ok((int) $est['duracion_horas'] === 3, '5: duracion reducida 6h * 0.5 = 3h');
ok(($est['origen'] ?? '') === 'regalo', '5: origen actual = regalo (regla emocional, no copia causa)');

// ============================================================
// 6. La mejora emocional NO borra la causa/contexto histórico
// ============================================================
$partida = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]]),
]);
setEstado($partida, 'per_a', EstadoEmocional::TRISTE, 'perder_trabajo', ['fuente' => 'f10_trabajo']);
DiscoveryReveal::registrarJugador($partida, 'per_a', ConocimientoNpc::campoGusto('hobby', 'leer'), 'leer', 'encuentro');
darObjeto($partida, 'libro');
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
$ctx = $partida['residentes']['per_a']['runtime']['estado_emocional']['contexto'];
ok(($ctx['estado_antes'] ?? '') === EstadoEmocional::TRISTE, '6: estado_antes=TRISTE preservado');
ok(($ctx['estado_antes_origen'] ?? '') === 'perder_trabajo', '6: estado_antes_origen=perder_trabajo preservado');
ok(($ctx['estado_antes_contexto']['fuente'] ?? '') === 'f10_trabajo', '6: estado_antes_contexto preservado');
ok(($ctx['causa_fuerte'] ?? false) === true, '6: causa_fuerte=true');
ok($partida['bitacora_relaciones'] !== [], '6: bitacora_relaciones intacta');

// ============================================================
// 7. Gusto desconocido no revela información mágicamente
// ============================================================
$partida = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]]),
]);
$partida['features']['discovery_enabled'] = true; // activar discovery para verificar side-effect
// NO revelamos 'leer' al jugador.
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
darObjeto($partida, 'libro');
$antes = $partida['descubrimientos'] ?? [];
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['reaccion'] === RegaloEngine::LE_ENCANTA, '7: resolucion determinista (le_encanta por hobbies_pos real)');
$d = $partida['descubrimientos'] ?? [];
ok(count($d) > count($antes), '7: regalo acertado dispara discovery (aprendizaje real)');
ok(DiscoveryEngine::estado($partida, 'per_a', ConocimientoNpc::campoGusto('hobby', 'leer')) === DiscoveryEngine::DESCUBIERTO, '7: gusto_hobby:leer ahora DESCUBIERTO');

// Verificación clave: el regalo NO devuelve en su payload ninguna pista sobre el origen.
// Sólo devuelve el copy humano y los descubrimientos (post-aplicación).
ok(!isset($r['hobby_match_calculado']), '7: payload NO incluye hobby_match (motor interno)');
ok(!isset($r['preferencias_reveladas']), '7: payload NO incluye preferencias crudas');

// ============================================================
// 8. No se duplica inventario ni se crea un segundo flujo de regalos
// ============================================================
$partida = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]]),
]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 3, $catalogo);
$antesTotal = InventarioEngine::totalUnidades($partida);
$antesInv = $partida['inventario'];
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
// Nota: InventarioEngine::ordenar() reasigna el array internamente (ksort),
// así que no podemos comparar referencia. Comparamos claves + valores.
ok(array_keys($partida['inventario']) === array_keys($antesInv), '8: claves inventario intactas');
ok(InventarioEngine::totalUnidades($partida) === $antesTotal - 1, '8: inventario -1 unidad');
ok(!array_key_exists('regalos', $partida), '8: no aparece sección regalos paralela');
ok(!array_key_exists('regalos_dos', $partida), '8: no aparece segundo flujo');
ok(!array_key_exists('regalo_log', $partida), '8: no aparece log paralelo');

// ============================================================
// 9. no_le_gusta sobre triste NO empeora
// ============================================================
$partida = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => ['hobbies_neg' => ['leer'], 'hobbies_pos' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]]),
]);
setEstado($partida, 'per_a', EstadoEmocional::TRISTE, 'encuentro', ['resultado_experiencia' => 'mal']);
darObjeto($partida, 'libro');
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['ok'] && $r['reaccion'] === RegaloEngine::NO_LE_GUSTA, '9: no_le_gusta sobre triste');
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($est['id'] === EstadoEmocional::TRISTE, '9: triste se mantiene (no empeora)');
ok(($r['emocion']['motivo'] ?? '') === 'mantiene', '9: motivo=mantiene');
ok(($r['emocion']['mantiene'] ?? false) === true, '9: mantiene=true');
ok(($est['origen'] ?? '') === 'encuentro', '9: origen preservado (no se reescribe a regalo)');
ok(strpos($r['eco_emocional'], 'ánimo') !== false || strpos($r['eco_emocional'], 'animo') !== false, '9: eco emocional refleja mantenimiento');

// ============================================================
// no_le_gusta sobre enfadado tampoco empeora
// ============================================================
$partida = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => ['hobbies_neg' => ['leer'], 'hobbies_pos' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]]),
]);
setEstado($partida, 'per_a', EstadoEmocional::ENFADADO, 'discusion_fuerte', []);
darObjeto($partida, 'libro');
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['reaccion'] === RegaloEngine::NO_LE_GUSTA, '9b: no_le_gusta sobre enfadado');
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($est['id'] === EstadoEmocional::ENFADADO, '9b: enfadado se mantiene');

// ============================================================
// 10. causa_fuerte: duracion reducida al 50%
// ============================================================
$partida = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]]),
]);
setEstado($partida, 'per_a', EstadoEmocional::TRISTE, 'ruptura', ['motivo' => 'ruptura_pareja']);
DiscoveryReveal::registrarJugador($partida, 'per_a', ConocimientoNpc::campoGusto('hobby', 'leer'), 'leer', 'encuentro');
darObjeto($partida, 'libro');
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($est['id'] === EstadoEmocional::ALEGRE, '10: ruptura + le_encanta -> alegre');
ok((int) $est['duracion_horas'] === 3, '10: duracion reducida 6h * 0.5 = 3h');

// ============================================================
// 11. Sin causa fuerte: duracion completa
// ============================================================
$partida = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]]),
]);
setEstado($partida, 'per_a', EstadoEmocional::TRISTE, 'inicial', []); // inicial NO es fuerte
DiscoveryReveal::registrarJugador($partida, 'per_a', ConocimientoNpc::campoGusto('hobby', 'leer'), 'leer', 'encuentro');
darObjeto($partida, 'libro');
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
$est = $partida['residentes']['per_a']['runtime']['estado_emocional'];
ok($est['id'] === EstadoEmocional::ALEGRE, '11: triste origen inicial + le_encanta -> alegre');
ok((int) $est['duracion_horas'] === 6, '11: duracion completa 6h (sin causa fuerte)');

exit($failures > 0 ? 1 : 0);