<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PresenciaEngine;
use AquiHayTema\Engine\ResumenDia;

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

function marcaDe(array $mapa, string $id): ?string
{
    foreach ($mapa['lugares'] ?? [] as $l) {
        if (($l['id'] ?? '') === $id) {
            return $l['encuentro_marca'] ?? null;
        }
    }
    return 'missing';
}

function setup(): array
{
    global $root;
    $service = new PartidaService($root);
    $partida = $service->nuevaPartida('test_fixtures_v0', 'mapa-enc');
    $ph = $service->crearResidentePlaceholderDev($partida);
    return [$service, $partida, 'per_qa_valid', $ph['residente']['catalog_id']];
}

[$service, $partida, $ida, $idb] = setup();
$mapa0 = PresenciaEngine::resolver($partida, $root);
$marcas0 = array_filter(array_map(static fn($l) => $l['encuentro_marca'] ?? null, $mapa0['lugares'] ?? []));
ok($marcas0 === [], 'sin encuentros → mapa sin marcas');
ok(ResumenDia::marcasPorLugar($partida, $service->getCatalog()) === [], 'marcasPorLugar vacío');

$encCafe = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
ok($encCafe['ok'] ?? false, 'programa cafetería 19h');
$mapa1 = PresenciaEngine::resolver($partida, $root);
ok(marcaDe($mapa1, 'lug_cafeteria') === 'proximo', 'próximo → cafetería marcada');
ok(marcaDe($mapa1, 'lug_parque') === null, 'parque sin encuentro no marcado');
$cafe = null;
foreach ($mapa1['lugares'] as $l) {
    if ($l['id'] === 'lug_cafeteria') {
        $cafe = $l;
    }
}
ok(($cafe['encuentro']['id'] ?? '') === ($encCafe['encuentro']['id'] ?? ''), 'dato del mapa coherente con encuentro');
ok(ResumenDia::residenteEnVista($cafe['encuentro'] ?? null, $ida), 'residente seleccionado participa');
ok(!ResumenDia::residenteEnVista($cafe['encuentro'] ?? null, 'per_inexistente'), 'no participante no refuerza');

$bloq = $service->programarEncuentro($partida, [$ida, $idb], 1, 21, 'amistad', 'lug_parque');
ok(!($bloq['ok'] ?? true), 'lugar bloqueado no acepta encuentro');
ok(marcaDe(PresenciaEngine::resolver($partida, $root), 'lug_parque') === null, 'bloqueado sin encuentro válido → sin marca');

$partida['celeste']['lugares_desbloqueados'][] = 'lug_parque';
$encPar = $service->programarEncuentro($partida, [$ida, $idb], 1, 21, 'amistad', 'lug_parque');
ok($encPar['ok'] ?? false, 'parque desbloqueado acepta encuentro');
$mapa2 = PresenciaEngine::resolver($partida, $root);
ok(marcaDe($mapa2, 'lug_cafeteria') === 'proximo', 'varios: el próximo (19h) marca cafetería');
ok(marcaDe($mapa2, 'lug_parque') === null, 'varios: el de 21h no es el próximo, parque sin marca de próximo');

$ir = $service->irAlProximoEncuentro($partida);
ok($ir['ok'] ?? false, 'ir al próximo → en curso en cafetería');
$mapa3 = PresenciaEngine::resolver($partida, $root);
ok(marcaDe($mapa3, 'lug_cafeteria') === 'en_curso', 'en curso marca cafetería');
ok(marcaDe($mapa3, 'lug_parque') === 'proximo', 'parque pasa a próximo');
$marcas = ResumenDia::marcasPorLugar($partida, $service->getCatalog());
ok(($marcas['lug_cafeteria']['marca'] ?? '') === 'en_curso', 'prioridad en_curso en marcasPorLugar');
ok(($marcas['lug_parque']['marca'] ?? '') === 'proximo', 'otro lugar conserva próximo');

// Mismo lugar: en curso pisa un próximo posterior en la misma cafetería.
[$service, $partida, $ida, $idb] = setup();
$service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
$service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'amistad', 'lug_cafeteria');
$service->irAlProximoEncuentro($partida);
$mismo = ResumenDia::marcasPorLugar($partida, $service->getCatalog());
ok(($mismo['lug_cafeteria']['marca'] ?? '') === 'en_curso', 'mismo lugar: en curso pisa próximo');
ok(count($mismo) === 1, 'un solo sitio marcado si ambos encuentros son ahí');

exit($failures > 0 ? 1 : 0);
