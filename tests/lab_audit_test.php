<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\LabAudit;
use AquiHayTema\Engine\PartidaService;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$svc = new PartidaService($root);
$partida = $svc->nuevaPartida('juego_v1', 'lab_audit_test_seed');

LabAudit::reset();
LabAudit::eventoNuevaPartida($partida, new Catalog($root));
$eventos = LabAudit::flush();

assert(count($eventos) >= 7, 'debe haber PARTIDA + NPC + REL por vecino');
$tags = array_column($eventos, 'tag');
assert(in_array('NPC', $tags, true), 'debe haber eventos NPC');
assert(in_array('REL', $tags, true), 'debe haber eventos REL');
assert($eventos[0]['tag'] === 'PARTIDA', 'primer evento PARTIDA');
assert(isset($eventos[0]['datos']['vecinos']), 'debe incluir vecinos');
assert(isset($eventos[0]['datos']['matriz_relacional']), 'debe incluir matriz');

$ids = LabAudit::residentesActivos($partida);
assert(count($ids) >= 3, 'juego_v1 debe tener varios iniciales');

echo "lab_audit_test OK (" . count($eventos[0]['datos']['vecinos']) . " vecinos, "
    . count($eventos[0]['datos']['matriz_relacional']) . " pares)\n";
