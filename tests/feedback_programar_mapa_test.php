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

function lugarDe(array $mapa, string $id): ?array
{
    foreach ($mapa['lugares'] ?? [] as $l) {
        if (($l['id'] ?? '') === $id) {
            return $l;
        }
    }
    return null;
}

function idsPresentes(?array $lugar): array
{
    return array_values(array_map(static fn($p) => $p['id'] ?? '', $lugar['residentes_presentes'] ?? []));
}

function setup(): array
{
    global $root;
    $service = new PartidaService($root);
    $partida = $service->nuevaPartida('test_fixtures_v0', 'feedback-mapa');
    $ph = $service->crearResidentePlaceholderDev($partida);
    return [$service, $partida, 'per_qa_valid', $ph['residente']['catalog_id']];
}

[$service, $partida, $ida, $idb] = setup();
$diaAhora = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
$horaAhora = (int) ($partida['reloj']['hora_actual'] ?? 0);

$encHoy = $service->programarEncuentro($partida, [$ida, $idb], $diaAhora, 19, 'conocerse', 'lug_cafeteria');
ok($encHoy['ok'] ?? false, 'programar hoy en cafetería');
ok(($encHoy['encuentro']['lugar'] ?? '') === 'lug_cafeteria', 'lugar real del backend = cafetería');
ok(($encHoy['vista']['lugar'] ?? '') === 'lug_cafeteria', 'vista.lugar coincide con el encuentro');
ok(($encHoy['vista']['es_hoy'] ?? false) === true, 'encuentro hoy → es_hoy');
ok(!empty($encHoy['vista']['participantes_nombres']), 'vista trae participantes');
ok(($encHoy['vista']['hora'] ?? 0) === 19, 'vista trae hora');

$mapaHoy = PresenciaEngine::resolver($partida, $root);
$cafeHoy = lugarDe($mapaHoy, 'lug_cafeteria');
ok(marcaDe($mapaHoy, 'lug_cafeteria') === 'proximo', 'hoy: cafetería marcada próximo');
ok(($cafeHoy['encuentro']['id'] ?? '') === ($encHoy['encuentro']['id'] ?? ''), 'mapa coherente con encuentro programado');
ok(($cafeHoy['encuentro']['hora'] ?? 0) === 19, 'detalle de mapa incluye hora');
ok(in_array($ida, $cafeHoy['encuentro']['participantes'] ?? [], true), 'detalle de mapa incluye participantes');
if ($horaAhora !== 19) {
    ok(!in_array($ida, idsPresentes($cafeHoy), true), 'hoy antes de la hora: no simula presencia en el lugar');
}

$parqueBloq = $service->programarEncuentro($partida, [$ida, $idb], $diaAhora, 21, 'amistad', 'lug_parque');
ok(!($parqueBloq['ok'] ?? true), 'lugar bloqueado rechazado');
ok(!in_array('lug_parque', $partida['celeste']['lugares_desbloqueados'] ?? [], true), 'parque no entra en operativos');
ok(marcaDe(PresenciaEngine::resolver($partida, $root), 'lug_parque') === null, 'bloqueado nunca marcado');

$est1 = $service->estadoResumido($partida);
$mapa1 = PresenciaEngine::resolver($partida, $root);
$est2 = $service->estadoResumido($partida);
$mapa2 = PresenciaEngine::resolver($partida, $root);
ok(($est1['proximo_encuentro']['id'] ?? '') === ($encHoy['encuentro']['id'] ?? ''), 'estado: próximo = programado');
ok(($est2['proximo_encuentro']['id'] ?? '') === ($est1['proximo_encuentro']['id'] ?? ''), 'refresh: mismo id');
ok(($est2['proximo_encuentro']['lugar'] ?? '') === 'lug_cafeteria', 'refresh: mismo lugar');
ok(marcaDe($mapa2, 'lug_cafeteria') === marcaDe($mapa1, 'lug_cafeteria'), 'refresh: misma marca');

[$service, $partida, $ida, $idb] = setup();
$diaAhora = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
$encFut = $service->programarEncuentro($partida, [$ida, $idb], $diaAhora + 1, 19, 'conocerse', 'lug_cafeteria');
ok($encFut['ok'] ?? false, 'programar encuentro futuro');
ok(($encFut['vista']['es_hoy'] ?? true) === false, 'futuro → no es_hoy');
ok(($encFut['vista']['dia'] ?? 0) === $diaAhora + 1, 'vista conserva fecha futura');
ok(($encFut['encuentro']['lugar'] ?? '') === 'lug_cafeteria', 'futuro: lugar real cafetería');

$mapaFut = PresenciaEngine::resolver($partida, $root);
$cafeFut = lugarDe($mapaFut, 'lug_cafeteria');
ok(marcaDe($mapaFut, 'lug_cafeteria') === 'proximo', 'futuro: lugar marcado como próximo (el siguiente)');
ok(($cafeFut['encuentro']['id'] ?? '') === ($encFut['encuentro']['id'] ?? ''), 'futuro: dato del mapa = encuentro');
ok(($cafeFut['encuentro']['es_hoy'] ?? true) === false, 'futuro: mapa no lo trata como hoy');
ok(!in_array($ida, idsPresentes($cafeFut), true), 'futuro: no simula presencia ahora');
ok(!in_array($idb, idsPresentes($cafeFut), true), 'futuro: el otro participante tampoco está presente');

$estFut = $service->estadoResumido($partida);
ok(($estFut['proximo_encuentro']['lugar'] ?? '') === ($encFut['encuentro']['lugar'] ?? ''), 'estado futuro coherente con backend');

exit($failures > 0 ? 1 : 0);
