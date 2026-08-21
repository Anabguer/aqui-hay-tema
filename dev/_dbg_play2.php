<?php
require dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\DisponibilidadEngine;

$s = new PartidaService(dirname(__DIR__));
$p = $s->nuevaPartida('test_fixtures_v0', 'play-flujo-dbg');
$p['reloj']['hora_actual'] = 8;
$p['reloj']['minuto_actual'] = 0;
$ph = $s->crearResidentePlaceholderDev($p);
$ida = 'per_qa_valid';
$idb = $ph['residente']['catalog_id'];
$slots = DisponibilidadEngine::slotsCompatibles($p, [$ida, $idb], 'conocerse', null, null, 7, 24, null, 'lug_cafeteria');
echo 'slots count=' . count($slots['slots'] ?? []) . ' first=' . json_encode($slots['slots'][0] ?? null) . "\n";
$primer = $slots['slots'][0];
$enc = $s->programarEncuentro($p, [$ida, $idb], (int) $primer['dia'], (int) $primer['hora'], 'conocerse', 'lug_cafeteria');
echo json_encode($enc) . "\n";
