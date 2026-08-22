<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\MapaHandler;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\LugarAtributos;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PerfilPartida;
use AquiHayTema\Engine\PresenciaEngine;
use AquiHayTema\Engine\ResumenDia;
use AquiHayTema\Engine\VistaPuebloV3;

DomainBootstrap::boot();
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

function idsEnLugar(array $mapa, string $lugarId): array
{
    foreach ($mapa['lugares'] ?? [] as $l) {
        if (($l['id'] ?? '') === $lugarId) {
            return array_values(array_map(static fn($p) => (string) ($p['id'] ?? ''), $l['residentes_presentes'] ?? []));
        }
    }
    return [];
}

function idsVisiblesCafe(array $pueblo): array
{
    foreach ($pueblo['complejos'] ?? [] as $cx) {
        if (($cx['id'] ?? '') !== 'cafe_libros') {
            continue;
        }
        return array_values(array_map(static fn($p) => (string) ($p['id'] ?? ''), $cx['visibles'] ?? []));
    }
    return [];
}

function setupPareja(): array
{
    global $root;
    $service = new PartidaService($root);
    $partida = $service->nuevaPartida('test_fixtures_v0', 'presencia-enc');
    $ph = $service->crearResidentePlaceholderDev($partida);
    return [$service, $partida, 'per_qa_valid', $ph['residente']['catalog_id']];
}

[$service, $partida, $ida, $idb] = setupPareja();
$enc = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
ok($enc['ok'] ?? false, 'programa cafetería 19h');

$antes = PresenciaEngine::resolver($partida, $root);
ok(!in_array($ida, idsEnLugar($antes, 'lug_cafeteria'), true), 'antes de empezar: no presente por la cita');
ok(!in_array($idb, idsEnLugar($antes, 'lug_cafeteria'), true), 'antes de empezar: el otro tampoco');

while ((int) $partida['reloj']['hora_actual'] < 19) {
    $service->avanzarReloj($partida, 1);
}
EncuentroLifecycle::sincronizarConReloj($partida);
ok(($partida['encuentros'][0]['estado'] ?? '') === 'en_curso', 'al llegar la hora: programado pasa a en_curso');

$durante = PresenciaEngine::resolver($partida, $root);
$pueblo = VistaPuebloV3::de($partida, $durante, $root);
$presentes = idsEnLugar($durante, 'lug_cafeteria');
ok(count(array_intersect([$ida, $idb], $presentes)) === 2, 'en curso: 2 presentes en cafetería');
ok(count(array_intersect([$ida, $idb], idsVisiblesCafe($pueblo))) === 2, 'vista pueblo: 2 visibles en café');

$encRow = $partida['encuentros'][0];
$encRow['duracion_horas'] = 2;
$encRow['duracion_minutos'] = 120;
$partida['encuentros'][0] = $encRow;
$partida['encuentros'][0]['hora'] = 18;
$partida['encuentros'][0]['estado'] = 'en_curso';
$partida['reloj']['hora_actual'] = 19;
ok(LugarAtributos::ocupaHora($partida['encuentros'][0], 1, 19), 'duración 2h: ocupa la segunda hora');
ok(ResumenDia::encuentroEnCurso($partida) !== null, 'duración 2h: encuentro_en_curso en hora intermedia');
ok(count(array_intersect([$ida, $idb], idsEnLugar(PresenciaEngine::resolver($partida, $root), 'lug_cafeteria'))) === 2, 'duración 2h: siguen presentes en hora 19');

foreach ([$ida, $idb] as $rid) {
    $perfil = PerfilPartida::de($partida, $rid);
    if (is_array($perfil)) {
        $partida['residentes'][$rid]['runtime']['perfil_partida']['lugares_preferentes'] = ['lug_parque'];
    }
}
$service->avanzarReloj($partida, 1);
EncuentroLifecycle::sincronizarConReloj($partida);
$tras = PresenciaEngine::resolver($partida, $root);
ok(($partida['encuentros'][0]['estado'] ?? '') === 'terminado', 'al terminar: estado terminado');
ok(!in_array($ida, idsEnLugar($tras, 'lug_cafeteria'), true), 'al terminar: deja de aparecer por la cita');
ok(!in_array($idb, idsEnLugar($tras, 'lug_cafeteria'), true), 'al terminar: el otro también');

[$service, $partida, $ida, $idb] = setupPareja();
$ph = $service->crearResidentePlaceholderDev($partida);
$solo = $ph['residente']['catalog_id'];
$attrCine = LugarAtributos::de('lug_cine');
$partida['encuentros'][] = [
    'id' => 'enc_ind_cine',
    'tipo' => 'individual',
    'participantes' => [$solo],
    'lugar' => 'lug_cine',
    'dia' => 1,
    'hora' => 17,
    'duracion_horas' => $attrCine['horas'],
    'duracion_minutos' => $attrCine['duracion_minutos'],
    'estado' => 'programado',
    'reserva_agenda' => ['tipo' => 'encuentro', 'origen' => 'celeste'],
];
$partida['reloj']['hora_actual'] = 17;
EncuentroLifecycle::sincronizarConReloj($partida);
$mapaCine = PresenciaEngine::resolver($partida, $root);
$cineIds = idsEnLugar($mapaCine, 'lug_cine');
ok(in_array($solo, $cineIds, true), 'plan individual: 1 presente en cine');
ok(count($cineIds) === 1, 'plan individual: solo un token');

[$service, $partida, $ida, $idb] = setupPareja();
$perfil = PerfilPartida::de($partida, $ida);
if (is_array($perfil)) {
    $partida['residentes'][$ida]['runtime']['perfil_partida']['lugares_preferentes'] = ['lug_parque'];
}
$service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
while ((int) $partida['reloj']['hora_actual'] < 19) {
    $service->avanzarReloj($partida, 1);
}
EncuentroLifecycle::sincronizarConReloj($partida);
$mapaRut = PresenciaEngine::resolver($partida, $root);
$presentesRut = idsEnLugar($mapaRut, 'lug_cafeteria');
ok(in_array($ida, $presentesRut, true) && in_array($idb, $presentesRut, true), 'encuentro activo prevalece frente a rutina');
ok(!in_array($ida, idsEnLugar($mapaRut, 'lug_parque'), true), 'encuentro activo: no lo manda al parque rutinario');

$ctx = new ApiContext($root);
[$service, $partida, $ida, $idb] = setupPareja();
$service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
while ((int) $partida['reloj']['hora_actual'] < 19) {
    $service->avanzarReloj($partida, 1);
}
$api = MapaHandler::presencia($ctx, [], $partida);
$apiIds = idsVisiblesCafe($api['pueblo'] ?? []);
ok(count(array_intersect([$ida, $idb], $apiIds)) === 2, 'mapa.presencia API: 2 visibles tras sync en handler');

echo $failures === 0 ? "presencia_encuentros_test OK\n" : "presencia_encuentros_test FAIL ({$failures})\n";
exit($failures > 0 ? 1 : 0);
