<?php
require dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\DisponibilidadEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;

$s = new PartidaService(dirname(__DIR__));
$p = $s->nuevaPartida('test_fixtures_v0', 'dbg');
$ph = $s->crearResidentePlaceholderDev($p);
$slots = DisponibilidadEngine::slotsCompatibles($p, ['per_qa_valid', $ph['residente']['catalog_id']], 'conocerse');
echo "first slot: ";
var_export($slots['slots'][0] ?? null);
echo "\n";
$enc = $s->programarEncuentro($p, ['per_qa_valid', $ph['residente']['catalog_id']], (int) $slots['slots'][0]['dia'], (int) $slots['slots'][0]['hora'], 'conocerse', 'lug_cafeteria');
echo "programar: ";
var_export($enc);
echo "\ngapMin8=" . CandidatoLlegadaEngine::gapMin(8) . " gapMin23=" . CandidatoLlegadaEngine::gapMin(23) . "\n";
