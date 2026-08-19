<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AvanceResumen;
use AquiHayTema\Engine\DomainEvents;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelojOperations;
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

function setup(): array
{
    global $root;
    $service = new PartidaService($root);
    $partida = $service->nuevaPartida('test_fixtures_v0', 'resumen-play');
    $ph = $service->crearResidentePlaceholderDev($partida);
    return [$service, $partida, 'per_qa_valid', $ph['residente']['catalog_id']];
}

function tiposLineas(array $resumen): array
{
    return array_values(array_map(static fn($l) => $l['tipo'] ?? '', $resumen['lineas'] ?? []));
}

[$service, $partida, $ida, $idb] = setup();
$est = $service->estadoResumido($partida);
ok($est['proximo_encuentro'] === null, 'sin encuentros: proximo null');
ok(($est['encuentros_hoy'] ?? -1) === 0, 'sin encuentros: encuentros_hoy 0');
ok(ResumenDia::proximoEncuentro($partida, $service->getCatalog()) === null, 'ResumenDia sin encuentros');

$enc19 = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse');
$enc21 = $service->programarEncuentro($partida, [$ida, $idb], 1, 21, 'amistad');
ok(($enc19['ok'] ?? false) && ($enc21['ok'] ?? false), 'dos encuentros programados');

$est = $service->estadoResumido($partida);
$prox = $est['proximo_encuentro'] ?? null;
ok(is_array($prox), 'próximo encuentro presente en estado');
ok(($prox['hora'] ?? 0) === 19, 'varios: el próximo es el de las 19h');
ok(($prox['id'] ?? '') === ($enc19['encuentro']['id'] ?? ''), 'varios: id del más cercano');
ok(in_array('QA Valid', $prox['participantes_nombres'] ?? [], true), 'nombres públicos en próximo');
ok(($prox['lugar_nombre'] ?? '') !== ($prox['lugar'] ?? 'x') || ($prox['lugar_nombre'] ?? '') === 'Cafetería', 'lugar con nombre');
ok(($est['encuentros_hoy'] ?? 0) === 2, 'encuentros_hoy = 2');
ok(($est['encuentro_en_curso'] ?? null) === null, 'aún no hay en curso');

$vista = RelojOperations::proximoEncuentroProgramado($partida);
ok(($vista['hora'] ?? 0) === 19, 'helper de reloj coincide con resumen');

// Ir al próximo: aterriza en 19 en_curso; siguiente programado es 21.
$ir = $service->irAlProximoEncuentro($partida);
ok($ir['ok'] ?? false, 'ir al próximo ok');
$est2 = $service->estadoResumido($partida);
ok(($est2['encuentro_en_curso']['hora'] ?? 0) === 19, 'en curso ahora a las 19h');
ok(($est2['proximo_encuentro']['hora'] ?? 0) === 21, 'siguiente programado a las 21h');

$resIr = $ir['resumen_avance'] ?? [];
$tiposIr = tiposLineas($resIr);
ok(!in_array(DomainEvents::TIEMPO_AVANZADO, $tiposIr, true), 'ir al próximo: sin ruido TIEMPO_AVANZADO');
ok(in_array(DomainEvents::ENCUENTRO_INICIADO, $tiposIr, true), 'ir al próximo: incluye inicio');
ok(($resIr['total'] ?? 0) >= 1, 'ir al próximo: hay líneas de resumen');

// +8h desde 19: el de 19 termina; el de 21 comienza y termina (19+8=3 del día 2? 19+8=27 → día 2 hora 3)
[$service, $partida, $ida, $idb] = setup();
$partida['celeste']['lugares_desbloqueados'][] = 'lug_parque';
$service->programarEncuentro($partida, [$ida, $idb], 1, 9, 'conocerse', 'lug_parque');
$paso = $service->avanzarRelojPasoAPaso($partida, 8);
ok($paso['ok'] ?? false, '+8h ok');
$res8 = $paso['resumen_avance'] ?? [];
$tipos8 = tiposLineas($res8);
ok(!in_array(DomainEvents::TIEMPO_AVANZADO, $tipos8, true), '+8h: sin TIEMPO_AVANZADO');
ok(in_array(DomainEvents::ENCUENTRO_INICIADO, $tipos8, true), '+8h: encuentro comenzó');
ok(in_array(DomainEvents::ENCUENTRO_TERMINADO, $tipos8, true), '+8h: encuentro terminó');
foreach ($res8['lineas'] ?? [] as $l) {
    ok(is_string($l['texto'] ?? null) && $l['texto'] !== '', 'línea de avance tiene texto');
    ok(strpos((string) ($l['texto'] ?? ''), 'D1 ·') === false, 'copy de avance sin D1 técnico');
    ok(strpos((string) ($l['texto'] ?? ''), 'D2 ·') === false, 'copy de avance sin D2 técnico');
}

$snap = AvanceResumen::snapshot($partida);
$vacio = AvanceResumen::desdeSnapshot($partida, $snap);
ok(($vacio['total'] ?? -1) === 0, 'snapshot posterior sin eventos nuevos → vacío');

exit($failures > 0 ? 1 : 0);
