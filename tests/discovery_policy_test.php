<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DiscoveryEngine;
use AquiHayTema\Engine\DiscoveryVisibilityPolicy;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'disc-pol');
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$config = DiscoveryVisibilityPolicy::load($root);
ok($config['default'] === 'sin_politica', 'default sin política (no aplica a Rocío)');
ok($config['por_categoria'] === [], 'ninguna categoría asignada aún');

$v = DiscoveryVisibilityPolicy::visibilidad($partida, 'per_qa_valid', 'vida.hobby_principal', $config);
ok($v['politica'] === DiscoveryVisibilityPolicy::SIN_POLITICA, 'hobby sin política');
ok($v['visible_jugador'] === null, 'sin política no fuerza oculto ni público');

$configTest = $config;
$configTest['por_categoria']['vida.hobby_principal'] = DiscoveryVisibilityPolicy::OCULTO;
$v2 = DiscoveryVisibilityPolicy::visibilidad($partida, 'per_qa_valid', 'vida.hobby_principal', $configTest);
ok($v2['visible_jugador'] === false, 'oculto y no descubierto → no visible');

DiscoveryEngine::registrar($partida, 'per_qa_valid', 'vida.hobby_principal', 'pasear', 'test');
$v3 = DiscoveryVisibilityPolicy::visibilidad($partida, 'per_qa_valid', 'vida.hobby_principal', $configTest);
ok($v3['visible_jugador'] === true, 'oculto + descubierto → visible');

$configPub = $config;
$configPub['por_categoria']['identidad.nombre'] = DiscoveryVisibilityPolicy::PUBLICO;
$v4 = DiscoveryVisibilityPolicy::visibilidad($partida, 'per_qa_valid', 'identidad.nombre', $configPub);
ok($v4['visible_jugador'] === true, 'público visible sin descubrir');

exit($failures > 0 ? 1 : 0);
