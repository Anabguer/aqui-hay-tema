<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainEvents;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelojOperations;

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

function countEvento(array $partida, string $tipo): int
{
    return count(array_filter(
        $partida['domain_events'] ?? [],
        static fn($e) => ($e['evento'] ?? '') === $tipo
    ));
}

function setup(): array
{
    global $root;
    $service = new PartidaService($root);
    $partida = $service->nuevaPartida('test_fixtures_v0', 'reloj-proximo');
    $ph = $service->crearResidentePlaceholderDev($partida);
    return [$service, $partida, 'per_qa_valid', $ph['residente']['catalog_id']];
}

// Sin encuentros: no mueve el reloj.
[$service, $partida, $ida, $idb] = setup();
$hora0 = (int) $partida['reloj']['hora_actual'];
$dia0 = (int) $partida['reloj']['dia_pueblo'];
$rVacio = $service->irAlProximoEncuentro($partida);
ok(!($rVacio['ok'] ?? true), 'sin encuentros → no avanza');
ok(($rVacio['error'] ?? '') === 'SIN_PROXIMO_ENCUENTRO', 'error SIN_PROXIMO_ENCUENTRO');
ok((int) $partida['reloj']['hora_actual'] === $hora0 && (int) $partida['reloj']['dia_pueblo'] === $dia0, 'reloj intacto sin próximo');

// Próximo encuentro hoy.
[$service, $partida, $ida, $idb] = setup();
$encHoy = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse');
ok($encHoy['ok'] ?? false, 'programa encuentro hoy 19h');
$antesTiempo = countEvento($partida, DomainEvents::TIEMPO_AVANZADO);
$rHoy = $service->irAlProximoEncuentro($partida);
ok($rHoy['ok'] ?? false, 'ir al próximo hoy ok');
ok((int) $partida['reloj']['dia_pueblo'] === 1, 'sigue en día 1');
ok((int) $partida['reloj']['hora_actual'] === 19, 'reloj en 19h');
ok(($rHoy['encuentro']['estado'] ?? '') === 'en_curso', 'encuentro pasa a en_curso');
ok(($rHoy['horas_avanzadas'] ?? 0) === 11, 'avanzó 11 horas (8→19)');
ok(countEvento($partida, DomainEvents::TIEMPO_AVANZADO) - $antesTiempo === 11, 'eventos intermedios TIEMPO_AVANZADO por hora');
ok(countEvento($partida, DomainEvents::ENCUENTRO_INICIADO) >= 1, 'ENCUENTRO_INICIADO emitido');

// Varios encuentros: aterriza en el más cercano, no en el último.
[$service, $partida, $ida, $idb] = setup();
$enc19 = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse');
$partida['celeste']['lugares_desbloqueados'][] = 'lug_parque';
$enc21 = $service->programarEncuentro($partida, [$ida, $idb], 1, 21, 'amistad', 'lug_parque');
ok(($enc19['ok'] ?? false) && ($enc21['ok'] ?? false), 'dos encuentros programados');
$rVar = $service->irAlProximoEncuentro($partida);
ok((int) $partida['reloj']['hora_actual'] === 19, 'varios: aterriza en el primero (19h)');
ok(($rVar['encuentro']['id'] ?? '') === ($enc19['encuentro']['id'] ?? ''), 'varios: el próximo es el de 19h');
$est21 = null;
foreach ($partida['encuentros'] as $e) {
    if ($e['id'] === ($enc21['encuentro']['id'] ?? '')) {
        $est21 = $e['estado'];
    }
}
ok($est21 === 'programado', 'el encuentro posterior sigue programado');

// Próximo mañana.
[$service, $partida, $ida, $idb] = setup();
$encMan = $service->programarEncuentro($partida, [$ida, $idb], 2, 19, 'conocerse');
ok($encMan['ok'] ?? false, 'programa encuentro mañana 19h');
$rMan = $service->irAlProximoEncuentro($partida);
ok($rMan['ok'] ?? false, 'ir al próximo mañana ok');
ok((int) $partida['reloj']['dia_pueblo'] === 2, 'reloj en día 2');
ok((int) $partida['reloj']['hora_actual'] === 19, 'reloj mañana a las 19h');
ok(($rMan['encuentro']['estado'] ?? '') === 'en_curso', 'encuentro de mañana en_curso');

// Eventos intermedios con +8h paso a paso: 9h se inicia y termina.
[$service, $partida, $ida, $idb] = setup();
$partida['celeste']['lugares_desbloqueados'][] = 'lug_parque';
$enc9 = $service->programarEncuentro($partida, [$ida, $idb], 1, 9, 'conocerse', 'lug_parque');
ok($enc9['ok'] ?? false, 'programa encuentro 9h para +8h');
$paso = $service->avanzarRelojPasoAPaso($partida, 8);
ok($paso['ok'] ?? false, '+8h paso a paso ok');
ok((int) $partida['reloj']['hora_actual'] === 16, 'reloj en 16h tras +8h');
$est9 = null;
foreach ($partida['encuentros'] as $e) {
    if ($e['id'] === ($enc9['encuentro']['id'] ?? '')) {
        $est9 = $e['estado'];
    }
}
ok($est9 === 'terminado', 'encuentro de 9h terminado tras +8h');
ok(countEvento($partida, DomainEvents::ENCUENTRO_INICIADO) >= 1, '+8h emitió ENCUENTRO_INICIADO intermedio');
ok(countEvento($partida, DomainEvents::ENCUENTRO_TERMINADO) >= 1, '+8h emitió ENCUENTRO_TERMINADO intermedio');

// No salto hacia atrás.
[$service, $partida, $ida, $idb] = setup();
$horaAntes = (int) $partida['reloj']['hora_actual'];
$neg = $service->avanzarRelojPasoAPaso($partida, -3);
ok(!($neg['ok'] ?? true), 'horas negativas rechazadas');
ok(($neg['error'] ?? '') === 'RELOJ_NO_REWIND', 'error RELOJ_NO_REWIND');
ok((int) $partida['reloj']['hora_actual'] === $horaAntes, 'reloj no retrocede');
$neg2 = $service->avanzarReloj($partida, -1);
ok(!($neg2['ok'] ?? true), 'avanzar bloque negativo rechazado');

ok(RelojOperations::proximoEncuentroProgramado($partida) === null, 'helper sin próximo devuelve null');

exit($failures > 0 ? 1 : 0);
