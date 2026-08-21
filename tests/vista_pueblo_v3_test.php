<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PresenciaEngine;
use AquiHayTema\Engine\VistaCotilleoV3;
use AquiHayTema\Engine\VistaPuebloV3;

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

function cx(array $pueblo, string $id): ?array
{
    foreach ($pueblo['complejos'] ?? [] as $c) {
        if (($c['id'] ?? '') === $id) {
            return $c;
        }
    }
    return null;
}

$vis = VistaPuebloV3::pickVisible([
    ['id' => 'a', 'destino_id' => 'x', 'hay_tema' => false],
    ['id' => 'b', 'destino_id' => 'y', 'hay_tema' => true],
    ['id' => 'c', 'destino_id' => 'x', 'hay_tema' => false],
    ['id' => 'd', 'destino_id' => 'z', 'hay_tema' => false],
    ['id' => 'e', 'destino_id' => 'z', 'hay_tema' => false],
    ['id' => 'f', 'destino_id' => 'z', 'hay_tema' => false],
    ['id' => 'g', 'destino_id' => 'z', 'hay_tema' => false],
]);
ok(count($vis) === 5, 'máximo 5 visibles');
ok($vis[0]['id'] === 'b', 'hay_tema tiene prioridad visual');

$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'vista-v3');
$ph = $service->crearResidentePlaceholderDev($partida);
$ida = 'per_qa_valid';
$idb = $ph['residente']['catalog_id'];
$enc = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
ok($enc['ok'] ?? false, 'programa cafetería');
$ir = $service->irAlProximoEncuentro($partida);
ok($ir['ok'] ?? false, 'ir al próximo');

$partida['residentes'][$ida]['runtime']['estado_emocional'] = EstadoEmocional::estructura('alegre');
$partida['residentes'][$idb]['runtime']['estado_emocional'] = EstadoEmocional::estructura('enfadado');
$partida['residentes'][$ida]['runtime']['estado_emocional']['id'] = 'alegre';
$mapa = PresenciaEngine::resolver($partida, $root);
$pueblo = VistaPuebloV3::de($partida, $mapa, $root);
$cafe = cx($pueblo, 'cafe_libros');
ok(is_array($cafe), 'complejo café existe');
ok(($cafe['total'] ?? 0) >= 2, 'presencia real en café');
$tema = array_values(array_filter($cafe['personas'] ?? [], static function ($p) {
    return !empty($p['hay_tema']);
}));
ok(count($tema) === 0, 'encuentro marcado no infiere hay_tema');
$emoA = null;
foreach ($cafe['personas'] as $p) {
    if ($p['id'] === $ida) {
        $emoA = $p['emocion'];
    }
}
ok($emoA === 'alegre', 'borde usa estado_emocional del motor');

$partida['residentes'][$ida]['runtime']['estado_emocional']['id'] = 'enamorado_inventado';
$pueblo2 = VistaPuebloV3::de($partida, PresenciaEngine::resolver($partida, $root), $root);
$emoFake = 'x';
foreach (cx($pueblo2, 'cafe_libros')['personas'] as $p) {
    if ($p['id'] === $ida) {
        $emoFake = $p['emocion'];
    }
}
ok($emoFake === 'neutro', 'id emocional desconocido no se inventa: cae a neutro');

$pt = $service->nuevaPartida('playtest_01', 'vista-v3-pt');
$mapaPt = PresenciaEngine::resolver($pt, $root);
$puebloPt = VistaPuebloV3::de($pt, $mapaPt, $root);
ok(cx($puebloPt, 'cafe_libros')['fase_motor'] === 'pleno', 'café+biblioteca operativos V3');
ok(cx($puebloPt, 'parque')['fase'] === 'temprano', 'parque sin anexos → inicial');
ok(count($puebloPt['complejos']) === 6, '6 complejos');
ok(isset($puebloPt['tokens']) && count($puebloPt['tokens']) >= 1, 'tokens de todos los residentes, no solo los del mapa');

$puebloCine = VistaPuebloV3::de($pt, PresenciaEngine::resolver($pt, $root), $root);
ok(count(array_filter(cx($puebloCine, 'cine_game')['destinos_operativos'] ?? [], static fn($d) => ($d['id'] ?? '') === 'lug_cine')) === 1, 'cine operativo V3 sin arcade legacy');

$ptBingo = $service->nuevaPartida('playtest_01', 'vista-v3-bingo');
$ptBingo['celeste']['lugares_desbloqueados'][] = 'lug_bingo';
$puebloBingo = VistaPuebloV3::de($ptBingo, PresenciaEngine::resolver($ptBingo, $root), $root);
ok(cx($puebloBingo, 'rincon_lola')['fase'] === 'temprano', 'Lola sin PNG evolucionado: se queda temprano visual');
ok(cx($puebloBingo, 'rincon_lola')['fase_motor'] === 'pleno', 'el motor sí tiene bingo; no se finge el ala');

$pt['diario'] = [
    ['id' => 'd1', 'dia' => 1, 'texto' => 'hoy'],
];
$coti = VistaCotilleoV3::de($pt);
ok(count($coti['hoy']) === 1, 'cotilleo hoy = diario del día');
ok($coti['ayer'] === [] && $coti['viejos'] === [], 'sin inventar ayer/viejos');

ok(is_file($root . '/assets/personajes/tokens-m/P138.png'), 'lote técnico P138 en PLAY');
ok(is_file($root . '/assets/personajes/tokens-m/P173.png'), 'lote técnico Fase 1: 14 cabezas (P173)');
ok(is_file($root . '/assets/play-v3/complejos/cafe_temprano.png'), 'fachada café C3');
ok(is_file($root . '/assets/play-v3/marcas/sello_hay_tema.png'), 'sello hay tema C3');
ok(is_file($root . '/play-provisional.php'), 'PLAY anterior comparable');

echo $failures === 0 ? "OK vista_pueblo_v3\n" : "FAIL vista_pueblo_v3 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
