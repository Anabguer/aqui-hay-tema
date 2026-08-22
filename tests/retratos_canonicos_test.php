<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PresenciaEngine;
use AquiHayTema\Engine\ResidenteRuntime;
use AquiHayTema\Engine\RetratoResolver;
use AquiHayTema\Engine\VistaPuebloV3;
use AquiHayTema\Engine\VisualPackStore;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$cat = new Catalog($root);
$packs = new VisualPackStore($root);
$pool = $cat->listPersonajeIdsJugables();
ok($pool !== [], 'pool jugable no vacío');

$sinRetrato = [];
$urlPorCatalog = [];
$packPorCatalog = [];
$lotePorCatalog = [];

foreach ($pool as $catalogId) {
    $personaje = $cat->loadPersonaje($catalogId);
    $runtime = ResidenteRuntime::crearDesdeCatalogo($personaje);
    $tok = RetratoResolver::resolver($runtime, $catalogId, $packs, $root);

  if ($tok['lote']) {
        $lotePorCatalog[] = $catalogId;
    }
    if ($tok['url'] === null) {
        $sinRetrato[] = $catalogId;
        continue;
    }
    if (isset($urlPorCatalog[$tok['url']]) && $urlPorCatalog[$tok['url']] !== $catalogId) {
        $prev = $urlPorCatalog[$tok['url']];
        ok(false, "colisión accidental de retrato: $catalogId y $prev comparten {$tok['url']}");
    } else {
        $urlPorCatalog[$tok['url']] = $catalogId;
    }
    if (isset($packPorCatalog[$tok['pack_id'] ?? '']) && $packPorCatalog[$tok['pack_id']] !== $catalogId) {
        $prev = $packPorCatalog[$tok['pack_id']];
        ok(false, "colisión de pack: $catalogId y $prev comparten pack {$tok['pack_id']}");
    } elseif (is_string($tok['pack_id'] ?? null) && $tok['pack_id'] !== '') {
        $packPorCatalog[$tok['pack_id']] = $catalogId;
    }
}

ok($lotePorCatalog === [], 'ningún personaje jugable usa fallback lote');
ok($sinRetrato === [], 'todos en pool jugable tienen retrato válido');
ok(count($pool) === 200, 'pool jugable canónico tiene 200 per_p* con pack');

// Caso reportado: per_p004 y per_p007 no deben compartir retrato
$raul = RetratoResolver::resolver(
    ResidenteRuntime::crearDesdeCatalogo($cat->loadPersonaje('per_p004')),
    'per_p004',
    $packs,
    $root
);
$alex = RetratoResolver::resolver(
    ResidenteRuntime::crearDesdeCatalogo($cat->loadPersonaje('per_p007')),
    'per_p007',
    $packs,
    $root
);
$dani = RetratoResolver::resolver(
    ResidenteRuntime::crearDesdeCatalogo($cat->loadPersonaje('per_p006')),
    'per_p006',
    $packs,
    $root
);
ok($raul['url'] !== null && $alex['url'] !== null && $dani['url'] !== null, 'Raúl, Álex y Dani tienen retrato canónico');
ok($raul['url'] !== $alex['url'], 'Raúl y Álex no comparten retrato');
ok($dani['url'] !== $raul['url'] && $dani['url'] !== $alex['url'], 'Dani no comparte retrato con Raúl ni Álex');
ok(str_contains((string) $raul['url'], 'P004_'), 'Raúl usa pack P004');
ok(str_contains((string) $alex['url'], 'P007_'), 'Álex usa pack P007');
ok(str_contains((string) $dani['url'], 'P006_'), 'Dani usa pack P006');

// Expresión según estado emocional V1 (misma fuente que ficha y mapa)
$service = new PartidaService($root);
$partidaEmo = $service->nuevaPartida('playtest_01', 'retratos-emo');
$ridDani = 'per_p006';
if (isset($partidaEmo['residentes'][$ridDani])) {
    $partidaEmo['residentes'][$ridDani]['runtime']['estado_emocional'] = EstadoEmocional::estructura('alegre');
    $alegre = RetratoResolver::resolver($partidaEmo['residentes'][$ridDani], $ridDani, $packs, $root);
    ok(str_contains((string) $alegre['url'], 'P006_alegre'), 'estado alegre → P006_alegre.png');
    ok($alegre['expression_id'] === 'alegre', 'expression_id alegre');
    $partidaEmo['residentes'][$ridDani]['runtime']['estado_emocional'] = EstadoEmocional::estructura('triste');
    $triste = RetratoResolver::resolver($partidaEmo['residentes'][$ridDani], $ridDani, $packs, $root);
    ok(str_contains((string) $triste['url'], 'P006_triste'), 'estado triste → P006_triste.png');
    $partidaEmo['residentes'][$ridDani]['runtime']['estado_emocional'] = EstadoEmocional::estructura('enfadado');
    $enfad = RetratoResolver::resolver($partidaEmo['residentes'][$ridDani], $ridDani, $packs, $root);
    ok(str_contains((string) $enfad['url'], 'P006_enfadado'), 'estado enfadado → P006_enfadado.png');
}

// Misma resolución en VistaPuebloV3 (mapa) y RetratoResolver
$partida = $service->nuevaPartida('playtest_01', 'retratos-canonicos');
$mapa = PresenciaEngine::resolver($partida, $root);
$pueblo = VistaPuebloV3::de($partida, $mapa, $root);
foreach (['per_p004', 'per_p006', 'per_p007'] as $rid) {
    if (!isset($partida['residentes'][$rid])) {
        continue;
    }
    $canon = RetratoResolver::resolver($partida['residentes'][$rid], $rid, $packs, $root);
    $vista = $pueblo['tokens'][$rid] ?? null;
    ok(
        is_array($vista) && ($vista['url'] ?? null) === $canon['url'],
        "VistaPuebloV3 y RetratoResolver coinciden para $rid"
    );
    ok(empty($vista['lote']), "sin flag lote en tokens de $rid");
}

// Estabilidad: misma URL en dos llamadas
$t1 = RetratoResolver::resolver($partida['residentes']['per_p007'], 'per_p007', $packs, $root);
$t2 = RetratoResolver::resolver($partida['residentes']['per_p007'], 'per_p007', $packs, $root);
ok($t1['url'] === $t2['url'], 'asociación estable personaje → retrato');

// Demostrar colisión del antiguo fallback CRC32 (regresión documentada)
$lote = [
    'P001.png', 'P008.png', 'P009.png', 'P010.png', 'P016.png',
    'P018.png', 'P028.png', 'P031.png', 'P082.png', 'P109.png',
    'P117.png', 'P121.png', 'P138.png', 'P173.png',
];
$n = count($lote);
$idxRaul = (int) (sprintf('%u', crc32('per_p004')) % $n);
$idxAlex = (int) (sprintf('%u', crc32('per_p007')) % $n);
ok($idxRaul === $idxAlex && $lote[$idxRaul] === 'P008.png', 'regresión: antiguo lote CRC32 colisionaba per_p004/per_p007 en P008');

echo $failures === 0 ? "OK retratos_canonicos_test\n" : "FAIL retratos_canonicos_test ({$failures})\n";
exit($failures > 0 ? 1 : 0);
