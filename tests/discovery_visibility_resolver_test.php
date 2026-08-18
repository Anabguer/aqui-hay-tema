<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DiscoveryEngine;
use AquiHayTema\Engine\DiscoveryVisibilityPolicy;
use AquiHayTema\Engine\DiscoveryVisibilityResolver;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'disc-vis-test');

function ok(bool $c, string $m): void
{
    static $fail = 0;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
    if (isset($GLOBALS['__disc_fail']) === false) {
        $GLOBALS['__disc_fail'] = 0;
    }
    if (!$c) {
        $GLOBALS['__disc_fail']++;
    }
}

$configBase = [
    'default' => 'sin_politica',
    'por_categoria' => [],
];

// OCULTO: no visible hasta descubrir
$configO = $configBase;
$configO['por_categoria']['vida.hobby_principal'] = DiscoveryVisibilityPolicy::OCULTO;

$r1 = (new DiscoveryVisibilityResolver($configO, $partida))->sanitizarValor([
    'residente_id' => 'per_qa_valid',
    'campo' => 'vida.hobby_principal',
    'valor_real' => 'bingo',
]);
ok($r1['visible_jugador'] === false && $r1['valor'] === null, 'oculto sin descubrimiento → oculto');

DiscoveryEngine::registrar($partida, 'per_qa_valid', 'vida.hobby_principal', 'pasear', 'test');
$r2 = (new DiscoveryVisibilityResolver($configO, $partida))->sanitizarValor([
    'residente_id' => 'per_qa_valid',
    'campo' => 'vida.hobby_principal',
    'valor_real' => 'bingo',
]);
ok($r2['visible_jugador'] === true && $r2['valor'] === 'bingo', 'oculto + descubierto → visible');

// PARCIAL: muestra placeholder genérico hasta descubrimiento
$configP = $configBase;
$configP['por_categoria']['vida.oculta_parcial'] = DiscoveryVisibilityPolicy::PARCIAL;
$r3 = (new DiscoveryVisibilityResolver($configP, $partida))->sanitizarValor([
    'residente_id' => 'per_qa_valid',
    'campo' => 'vida.oculta_parcial',
    'valor_real' => 'SECRET',
]);
ok($r3['visible_jugador'] === false && $r3['valor'] === DiscoveryVisibilityResolver::PARCIAL_PLACEHOLDER, 'parcial sin descubrimiento → placeholder');

// POR_EVENTO: de momento se comporta como oculto hasta estar descubierto (eventos no puenteados aún)
$configE = $configBase;
$configE['por_categoria']['vida.por_evento'] = DiscoveryVisibilityPolicy::POR_EVENTO;
$r4 = (new DiscoveryVisibilityResolver($configE, $partida))->sanitizarValor([
    'residente_id' => 'per_qa_valid',
    'campo' => 'vida.por_evento',
    'valor_real' => 'SECRET_E',
    'eventos_alcanzados' => ['evt_x'],
]);
ok($r4['visible_jugador'] === false && $r4['valor'] === null, 'por_evento sin descubrimiento → oculto (skeleton)');

exit(($GLOBALS['__disc_fail'] ?? 0) > 0 ? 1 : 0);

