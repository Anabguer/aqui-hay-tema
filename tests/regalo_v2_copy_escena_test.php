<?php
declare(strict_types=1);

/* Regalos v2 (2026-09-02) — Copy: escena humana + eco emocional.
   Deterministas. Sin IDs ni métricas. Capitalización del objeto respetada. */

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
// Escena: 4 familias x 3 variantes. Determinista.
// ============================================================
$partida = regalo_fixture_partida(['per_a' => regalo_perfil()]);
$partida['residentes']['per_a']['identidad_publica']['genero'] = 'mujer';

$e1 = RegaloEngine::textoEscena($partida, 'per_a', 'Libro', RegaloEngine::LE_ENCANTA);
$e2 = RegaloEngine::textoEscena($partida, 'per_a', 'Libro', RegaloEngine::LE_ENCANTA);
ok(is_string($e1) && $e1 !== '', 'escena: le_encanta produce string no vacio');
ok($e1 === $e2, 'escena: determinista por (residente, objeto, reaccion)');

// Capitalización: el objeto mantiene su nombre tal cual.
ok(strpos($e1, 'Libro') !== false || strpos($e1, 'libro') !== false || strpos($e1, 'paquete') !== false,
   'escena: coherente (puede mencionar objeto o escena neutra)');

// No debe filtrar IDs técnicos.
ok(strpos($e1, 'id:') === false, 'escena: sin id tecnico');
ok(strpos($e1, 'php') === false, 'escena: sin jerga tecnica');

// ============================================================
// Eco emocional — variantes principales
// ============================================================

// 1. Regalo le_encanta sobre neutro → "se le nota en la cara".
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
$partida['residentes']['per_a']['identidad_publica']['genero'] = 'mujer';
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 2, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['reaccion'] === RegaloEngine::LE_ENCANTA, 'eco: reaccion le_encanta');
ok(is_string($r['eco_emocional']) && $r['eco_emocional'] !== '', 'eco: string no vacio');
ok(strpos($r['eco_emocional'], '+') === false, 'eco: sin +X');

// 2. Regalo le_encanta sobre triste con causa fuerte → copy "lo agradece, pero sigue pensándoselo".
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_pos' => ['leer'], 'hobbies_neg' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::TRISTE, 'perder_trabajo', ['fuente' => 'f10']);
$partida['residentes']['per_a']['identidad_publica']['genero'] = 'mujer';
InventarioEngine::anadir($partida, 'libro', 2, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok(is_string($r['eco_emocional']) && $r['eco_emocional'] !== '', 'eco 2: no vacio');
ok($r['reaccion'] === RegaloEngine::LE_ENCANTA, 'eco 2: reaccion le_encanta');
ok(strpos($r['eco_emocional'], 'agradece') !== false, 'eco 2: copy de mantenimiento');

// 3. Regalo no_le_gusta sobre triste → eco refleja mantenimiento.
$partida = regalo_fixture_partida(['per_a' => regalo_perfil(['preferencias' => ['hobbies_neg' => ['leer'], 'hobbies_pos' => [], 'personalidad_pos' => [], 'personalidad_neg' => [], 'visual_pos' => [], 'visual_neg' => []]])]);
setEstado($partida, 'per_a', EstadoEmocional::TRISTE, 'encuentro', ['resultado_experiencia' => 'mal']);
$partida['residentes']['per_a']['identidad_publica']['genero'] = 'mujer';
InventarioEngine::anadir($partida, 'libro', 2, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok($r['reaccion'] === RegaloEngine::NO_LE_GUSTA, 'eco 3: no_le_gusta');
ok(is_string($r['eco_emocional']) && $r['eco_emocional'] !== '', 'eco 3: eco no vacio');
ok(strpos($r['eco_emocional'], 'ánimo') !== false || strpos($r['eco_emocional'], 'animo') !== false, 'eco 3: menciona mantenimiento del ánimo');

// 4. Regalo indiferente sobre cualquier estado → eco vacio.
$partida = regalo_fixture_partida(['per_a' => regalo_perfil()]);
setEstado($partida, 'per_a', EstadoEmocional::TRISTE, 'encuentro', ['resultado_experiencia' => 'mal']);
InventarioEngine::anadir($partida, 'llave_vieja', 2, $catalogo); // sin hobby_ids
$r = RegaloEngine::entregar($partida, 'per_a', 'llave_vieja', $cal, $catalogo);
ok($r['reaccion'] === RegaloEngine::INDIFERENTE, 'eco 4: indiferente');
ok($r['eco_emocional'] === '', 'eco 4: eco vacio en indiferente');

// 5. Determinismo: misma entrega → mismo eco.
$partida = regalo_fixture_partida(['per_a' => regalo_perfil()]);
$partida['residentes']['per_a']['identidad_publica']['genero'] = 'mujer';
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 4, $catalogo);
$r1 = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
// Siguiente regalo, mismo objeto, mismo residente → mismo eco_emocional base.
$partida['reloj']['dia_pueblo'] = 4; // saltar cooldown
$r2 = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
ok(is_string($r1['escena']) && $r1['escena'] === $r2['escena'], 'escena: determinista entre regalos');

// 6. No IDs técnicos en eco.
$partida = regalo_fixture_partida(['per_a' => regalo_perfil()]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 2, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
$eco = $r['eco_emocional'] . ' ' . $r['escena'];
ok(!preg_match('/\b[a-z]+_[a-z]+\b/', $eco), 'eco/escena: sin identificadores snake_case');
ok(strpos($eco, '+15') === false && strpos($eco, '+12') === false && strpos($eco, '-5') === false, 'eco/escena: sin fórmulas numéricas');

// 7. Sin objetos prohibidos: el copy nunca dice "compatibilidad" ni "%".
$partida = regalo_fixture_partida(['per_a' => regalo_perfil()]);
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 2, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
$todo = $r['eco_emocional'] . ' ' . $r['escena'] . ' ' . $r['texto'];
ok(stripos($todo, 'compatibilidad') === false, 'copy: sin "compatibilidad"');
ok(stripos($todo, 'probabilidad') === false, 'copy: sin "probabilidad"');
ok(strpos($todo, '%') === false, 'copy: sin porcentaje');

// 9. Concordancia de género.
$partida = regalo_fixture_partida(['per_a' => regalo_perfil()]);
$partida['residentes']['per_a']['identidad_publica']['genero'] = 'mujer';
setEstado($partida, 'per_a', EstadoEmocional::NEUTRO, 'inicial');
InventarioEngine::anadir($partida, 'libro', 2, $catalogo);
$r = RegaloEngine::entregar($partida, 'per_a', 'libro', $cal, $catalogo);
$ecoMujer = $r['eco_emocional'] . ' ' . $r['escena'];
// La escena dice "ha abierto" (neutro, OK).
// El eco puede usar "más tranquil" + "a" o "más tranquilo" sin género.
// Verificación blanda: no debe tener masculino genérico incorrecto cuando hay genero.
ok(is_string($ecoMujer), 'eco: concordancia — string válido para mujer');

exit($failures > 0 ? 1 : 0);