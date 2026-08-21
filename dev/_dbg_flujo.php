<?php
require dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\DisponibilidadEngine;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\SimulationRunner;

$s = new PartidaService(dirname(__DIR__));
$p = $s->nuevaPartida('test_fixtures_v0', 'dbg-flujo');
$ph = $s->crearResidentePlaceholderDev($p);
$a = 'per_qa_valid';
$b = $ph['residente']['catalog_id'];
RelacionEngine::setRomanceHacia($p, $a, $b, 80);
RelacionEngine::setRomanceHacia($p, $b, $a, 20);
echo "romance B->A: " . RelacionEngine::romanceHacia($p, $b, $a) . "\n";

$slots = DisponibilidadEngine::slotsCompatibles($p, [$a, $b], 'conocerse', 1, 8, 2, 8);
echo "slot0: " . json_encode($slots['slots'][0] ?? null) . "\n";
$enc = $s->programarEncuentro($p, [$a, $b], (int) $slots['slots'][0]['dia'], (int) $slots['slots'][0]['hora'], 'conocerse');
echo "enc1: " . json_encode($enc) . "\n";

$r = SimulationRunner::runFlujoLargoPlay(dirname(__DIR__), 30, 'flujo-play-30-dbg');
echo "errors: " . json_encode($r['errores'] ?? []) . "\n";
echo "programados: " . ($r['encuentros_programados'] ?? 0) . "\n";
