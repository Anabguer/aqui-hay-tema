<?php
declare(strict_types=1);

/* Regalos F1: API (rutas en ambos entrypoints, GameError, atomicidad). */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\GameError;
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

$root = dirname(__DIR__);

// rutas registradas en api/index.php. NOTA: el index.php RAIZ en HEAD canonico es la
// landing (redirect a play.php), NO un router API. La igualdad index.php==api/index.php
// observada en el arbol principal es WIP ajeno sin commitear: no debe copiarse.
$apiIdx = file_get_contents($root . '/api/index.php');
$raizIdx = file_get_contents($root . '/index.php');
ok(strpos($apiIdx, "'inventario.listar'") !== false, 'api/index.php registra inventario.listar');
ok(strpos($apiIdx, "'regalo.entregar'") !== false, 'api/index.php registra regalo.entregar');
ok(strpos($apiIdx, "'dev.regalo.otorgar'") !== false, 'api/index.php registra dev.regalo.otorgar');
ok(strpos($apiIdx, 'RegalosHandler') !== false, 'api/index.php importa RegalosHandler');
ok(strpos($raizIdx, 'Location: play.php') !== false, 'index.php raiz sigue siendo la landing canonica');
$bloque = substr($apiIdx, (int) strpos($apiIdx, "'dev.regalo.otorgar'"), 260);
ok(strpos($bloque, 'requireDev()') !== false, 'dev.regalo.otorgar protegido con requireDev');

// GameError coherente para los nuevos codigos
ok(GameError::mensajeUi(GameError::REGALO_OBJETO_DESCONOCIDO) !== 'Ha ocurrido un error.', 'GameError REGALO_OBJETO_DESCONOCIDO con mensaje');
ok(GameError::mensajeUi(GameError::REGALO_SIN_UNIDADES) !== 'Ha ocurrido un error.', 'GameError REGALO_SIN_UNIDADES con mensaje');
ok(GameError::mensajeUi(GameError::REGALO_COOLDOWN) !== 'Ha ocurrido un error.', 'GameError REGALO_COOLDOWN con mensaje');
$r = GameError::respuesta(GameError::REGALO_COOLDOWN, [], 409);
ok($r['ok'] === false && $r['error'] === GameError::REGALO_COOLDOWN && isset($r['mensaje_ui']) && $r['_http'] === 409, 'GameError::respuesta shape estable');

// atomicidad a nivel motor: fallo de validacion no consume ni muta
$cal = regalo_cal();
$catalogo = regalo_catalogo();
$p = regalo_fixture_partida([
    'per_a' => regalo_perfil(['preferencias' => array_merge(regalo_perfil()['preferencias'], ['hobbies_pos' => ['leer']])]),
]);
InventarioEngine::anadir($p, 'libro', 1, $catalogo);
$antes = $p['inventario'];
$r1 = RegaloEngine::entregar($p, 'per_a', 'objeto_inexistente', $cal, $catalogo);
ok($r1['ok'] === false && $p['inventario'] === $antes, 'objeto desconocido: inventario intacto');
$r2 = RegaloEngine::entregar($p, 'per_inexistente', 'libro', $cal, $catalogo);
ok($r2['ok'] === false && $p['inventario'] === $antes, 'residente inexistente: inventario intacto');
$r3 = RegaloEngine::entregar($p, 'per_a', 'vinilo', $cal, $catalogo);
ok($r3['ok'] === false && $r3['error'] === 'regalo_sin_unidades' && $p['inventario'] === $antes, 'sin unidades: inventario intacto');
ok(($p['bitacora_relaciones'] ?? []) === [], 'fallos no registran hitos');
ok(($p['memoria_eventos'] ?? []) === [], 'fallos no registran memoria');

// exito: resolucion + consumo + memoria juntos en la misma mutacion
$r4 = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
ok($r4['ok'] === true && $p['inventario'] === [], 'exito consume y muta atomicamente');
ok(count($p['bitacora_relaciones']) === 1 && count($p['memoria_eventos']) === 1, 'exito registra memoria y bitacora');

// persistencia a traves del HANDLER real (regresion: entregar/otorgar deben guardar)
require_once dirname(__DIR__) . '/api/bootstrap.php';
$ctx = new \AquiHayTema\Api\ApiContext($root);
$plab = $ctx->service->nuevaPartida('juego_v1', 'lab-regalos-api');
$pidLab = (string) $plab['meta']['partida_id'];
$ot = \AquiHayTema\Api\Handlers\RegalosHandler::otorgarDev($ctx, ['objeto_id' => 'libro', 'cantidad' => 2], $plab);
ok(($ot['ok'] ?? false) === true, 'handler otorgarDev ok');
$enDisco = \AquiHayTema\Engine\JsonFile::read($root . '/data/partidas/' . $pidLab . '.json');
ok(($enDisco['inventario']['libro'] ?? 0) === 2, 'otorgarDev persiste en disco');
$ent = \AquiHayTema\Engine\RegaloEngine::entregar($plab, array_key_first($plab['residentes']), 'libro', regalo_cal(), regalo_catalogo());
$hitos = array_filter($plab['bitacora_relaciones'] ?? [], static fn($h) => ($h['tipo'] ?? '') === 'regalo');
ok(($ent['ok'] ?? false) === true && count($hitos) === 1, 'entrega en partida real registra hito');
foreach ([$root . '/data/partidas/' . $pidLab . '.json', $root . '/data/partidas/' . $pidLab . '.json.bak'] as $f) {
    if (is_file($f)) {
        unlink($f);
    }
}

exit($failures > 0 ? 1 : 0);
