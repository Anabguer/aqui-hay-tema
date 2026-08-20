<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\DomainEventDispatcher;
use AquiHayTema\Engine\DomainEvents;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelojOperations;
use AquiHayTema\Engine\TutorialBucle;
use AquiHayTema\Engine\VistaCotilleoV3;

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

ok(is_file($root . '/playtest.php'), 'entrada playtest.php existe');
$redir = file_get_contents($root . '/playtest.php');
ok(strpos((string) $redir, 'lab') !== false && strpos((string) $redir, 'taller') !== false, 'playtest fuerza taller+lab');

DomainBootstrap::boot();
$service = new PartidaService($root);
$p = $service->nuevaPartida('playtest_01', 'playtest-01');

ok(count($p['residentes']) === 8, '8 habitantes P001–P008');
ok(empty(TutorialBucle::vista($p)['activo']), 'sin tutorial juego_v1');
ok(FeatureConfig::isEnabled($p, 'npc_autonomy_enabled'), 'autonomía ON');
ok(FeatureConfig::isEnabled($p, 'buzon_enabled'), 'buzón ON');
ok(FeatureConfig::isEnabled($p, 'discovery_enabled'), 'discovery ON');
ok(FeatureConfig::isEnabled($p, 'emotional_state_from_events_enabled'), 'emociones ON');
ok(FeatureConfig::isEnabled($p, 'vida_pueblo_enabled'), 'Vida ON');
ok(FeatureConfig::isEnabled($p, 'misiones_diarias_enabled'), 'misiones ON (estado API)');
ok(FeatureConfig::isEnabled($p, 'peticiones_pueblo_enabled'), 'peticiones ON (estado API)');
ok(!FeatureConfig::isEnabled($p, 'economy_enabled'), 'economía OFF');
ok(in_array('lug_cafeteria', $p['celeste']['lugares_desbloqueados'], true), 'cafetería');
ok(in_array('lug_parque', $p['celeste']['lugares_desbloqueados'], true), 'parque');
ok(in_array('lug_biblioteca', $p['celeste']['lugares_desbloqueados'], true), 'biblioteca');

$antesCoin = count($p['historial_coincidencias'] ?? []);
$reloj = new RelojOperations($root, $service->getLogger(), $service->emociones());
$adv = $reloj->avanzarPasoAPaso($p, 24);
ok(($adv['ok'] ?? true) !== false, 'avanzar +1 día');
ok((int) $p['reloj']['dia_pueblo'] >= 2, 'día avanza');
ok(isset($adv['resumen_avance']), 'resumen_avance disponible tras avanzar');

DomainEventDispatcher::emit($p, DomainEvents::ENCUENTRO_TERMINADO, [
    'encuentro' => [
        'id' => 'enc_neni',
        'participantes' => ['per_p001', 'per_p002'],
        'tipo' => 'conocerse',
        'lugar' => 'lug_cafeteria',
    ],
    'resultado' => ['delta_social' => ['intensidad' => 4, 'tipo' => 'reales'], 'delta_romance' => []],
    'actores' => ['per_p001', 'per_p002'],
], null, 'playtest-neni', ['per_p001', 'per_p002']);
$coti = VistaCotilleoV3::de($p);
$hayTexto = false;
foreach ($coti['hoy'] as $e) {
    if (strpos((string) ($e['texto'] ?? ''), 'llevado') !== false || strpos((string) ($e['texto'] ?? ''), 'roce') !== false) {
        $hayTexto = true;
    }
}
ok($hayTexto || count($coti['hoy']) > 0 || count(BuzonEngine::listar($p, null, BuzonEngine::COTILLEO)) > 0,
    'encuentro con cambio puede alimentar El Cotilleo vía buzón');

$est = $service->estadoResumido($p);
ok(isset($est['reloj_texto']) || isset($est['reloj_vista']), 'HUD puede mostrar día/hora');
ok(isset($est['misiones_hoy']), 'misiones en estado (UI aún no las pinta)');
ok(isset($est['peticiones_pueblo']) || isset($est['peticiones_abiertas']), 'peticiones en estado');

echo $failures === 0 ? "OK playtest_neni\n" : "FAIL playtest_neni ({$failures})\n";
exit($failures > 0 ? 1 : 0);
