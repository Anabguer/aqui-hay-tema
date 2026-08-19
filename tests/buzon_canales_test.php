<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\DomainEventDispatcher;
use AquiHayTema\Engine\DomainEvents;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PeticionEsquemas;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\PropuestaNivel;

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

DomainBootstrap::boot();
$service = new PartidaService($root);
$partida = $service->nuevaPartida('playtest_01', 'buzon-canales');

ok(FeatureConfig::isEnabled($partida, 'peticiones_pueblo_enabled'), 'B4 flag on en playtest');
ok((string) PeticionEsquemas::CANON === 'E3', 'canon E3');

$antes = count($partida['buzon'] ?? []);
DomainEventDispatcher::emit($partida, DomainEvents::ENCUENTRO_TERMINADO, [
    'encuentro' => ['id' => 'enc_vacio', 'participantes' => ['per_p005'], 'tipo' => 'individual'],
    'resultado' => ['delta_social' => ['intensidad' => 0], 'delta_romance' => []],
    'actores' => ['per_p005'],
], null, 'test-vacio', ['per_p005']);
ok(count($partida['buzon'] ?? []) === $antes, 'encuentro vacío no fabrica aviso');

DomainEventDispatcher::emit($partida, DomainEvents::ENCUENTRO_TERMINADO, [
    'encuentro' => ['id' => 'enc_social', 'participantes' => ['per_p001', 'per_p002'], 'tipo' => 'conocerse'],
    'resultado' => ['delta_social' => ['intensidad' => 5, 'tipo' => 'reales'], 'delta_romance' => []],
    'actores' => ['per_p001', 'per_p002'],
], null, 'test-social', ['per_p001', 'per_p002']);
$msgs = $partida['buzon'] ?? [];
$ultimo = $msgs[count($msgs) - 1] ?? [];
ok(($ultimo['canal'] ?? '') === BuzonEngine::CANAL_COTILLEO, 'encuentro con cambio → canal cotilleo');
ok(($ultimo['clasificacion'] ?? '') === BuzonEngine::COTILLEO, 'clasificación cotilleo');
ok(strpos((string) ($ultimo['texto'] ?? ''), 'Ha ocurrido algo') === false, 'sin copy vacío “ha ocurrido algo”');
ok(strpos((string) ($ultimo['texto'] ?? ''), 'reales') === false, 'cotilleo sin jerga reales');
ok(!empty($ultimo['fecha_corta']), 'mensaje con fecha humana');

$partida['_b4_forzar_nacer'] = true;
$pet = PeticionPuebloEngine::intentarNacer($partida, [], null, null);
ok($pet !== null, 'petición B4 nace si se fuerza');
$pets = BuzonEngine::listar($partida, null, BuzonEngine::PETICION);
ok($pets !== [], 'hay mensaje clasificación petición');
ok(($pets[0]['canal'] ?? '') === BuzonEngine::CANAL_BUZON, 'petición va al canal buzón');
ok(strpos((string) ($pets[0]['texto'] ?? ''), ':') !== false, 'copy de residente reconocible');

$est = $service->estadoResumido($partida);
ok(isset($est['misiones_hoy']['misiones']), 'estado expone misiones_hoy');
ok(count($est['misiones_hoy']['misiones']) >= 1, 'día 1 tiene misiones en API');
ok(isset($est['planes_organizar'][0]['cupo']), 'estado expone cupo de plan');
ok((int) PropuestaNivel::cupoUi('conocerse') === 1, 'contrato UI: conocerse cupo 1');
ok((int) PropuestaNivel::cupoUi('quedar') === 2, 'contrato UI: quedar cupo 2');

exit($failures > 0 ? 1 : 0);
