<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'org-reset-test');
$ids = array_keys($p['residentes']);
assert(count($ids) >= 2, 'necesita 2 residentes');
$a = $ids[0];
$b = $ids[1];
$solo = $ids[2] ?? $ids[0];

$r1 = PropuestaEncuentroEngine::proponer($p, [$a, $b], 1, 18, 'conocerse', 'lug_cafeteria');
assert(!empty($r1['ok']), 'plan pareja ok');

$r2 = PropuestaEncuentroEngine::proponer($p, [$solo], 1, 19, 'individual', 'lug_cine');
assert(!empty($r2['ok']), 'plan solo ok');
$prop = $r2['propuesta'] ?? [];
$parts = $prop['participantes'] ?? [];
assert(count($parts) === 1, 'plan solo un participante');
assert((string) ($parts[0] ?? '') === $solo, 'plan solo participante correcto');
assert(($prop['tipo'] ?? '') === 'individual', 'tipo individual');

echo "plan_individual_test OK\n";
