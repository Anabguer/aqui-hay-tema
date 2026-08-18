<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DiscoveryEngine;
use AquiHayTema\Engine\DiscoveryProjection;
use AquiHayTema\Engine\DiscoveryVisibilityPolicy;
use AquiHayTema\Engine\DiscoveryVisibilityResolver;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ResidenteRuntime;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'disc-proj-test');
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$runtime = $partida['residentes']['per_qa_valid'] ?? null;
if (!is_array($runtime)) {
    echo "FAIL: residente base ausente\n";
    exit(1);
}
$catalogo = ResidenteRuntime::catalogoParaRuntime($runtime, $service->getCatalog());
$campos = DiscoveryProjection::deCatalogo($catalogo, $runtime);

$config = [
    'default' => DiscoveryVisibilityPolicy::SIN_POLITICA,
    'por_categoria' => [
        'identidad.nombre' => DiscoveryVisibilityPolicy::PUBLICO,
        'vida.hobby_principal' => DiscoveryVisibilityPolicy::OCULTO,
        'vida.hobbies_secundarios' => DiscoveryVisibilityPolicy::PARCIAL,
        'vida.rasgos_ocultos' => DiscoveryVisibilityPolicy::POR_EVENTO,
    ],
];

$proj0 = DiscoveryProjection::proyectar($partida, 'per_qa_valid', $campos, $config, ['evt_demo']);
ok(($proj0['identidad.nombre']['visible_jugador'] ?? null) === true, 'publico visible');
ok(($proj0['vida.hobby_principal']['visible_jugador'] ?? null) === false, 'oculto no visible antes de descubrir');
ok(array_key_exists('valor', $proj0['vida.hobby_principal']) && $proj0['vida.hobby_principal']['valor'] === null, 'oculto devuelve null');
ok(($proj0['vida.hobbies_secundarios']['valor'] ?? null) === DiscoveryVisibilityResolver::PARCIAL_PLACEHOLDER, 'parcial devuelve placeholder');
ok(($proj0['vida.rasgos_ocultos']['visible_jugador'] ?? null) === false, 'por_evento no visible sin descubrir');
ok(($proj0['vida.rasgos_ocultos']['eventos_alcanzados'][0] ?? null) === 'evt_demo', 'por_evento conserva eventos_alcanzados');

DiscoveryEngine::registrar($partida, 'per_qa_valid', 'vida.hobby_principal', $campos['vida.hobby_principal'] ?? null, 'test');
DiscoveryEngine::registrar($partida, 'per_qa_valid', 'vida.hobbies_secundarios', $campos['vida.hobbies_secundarios'] ?? [], 'test');
DiscoveryEngine::registrar($partida, 'per_qa_valid', 'vida.rasgos_ocultos', $campos['vida.rasgos_ocultos'] ?? [], 'test');

$proj1 = DiscoveryProjection::proyectar($partida, 'per_qa_valid', $campos, $config, ['evt_demo']);
ok(($proj1['vida.hobby_principal']['visible_jugador'] ?? null) === true, 'oculto visible tras discovery');
ok(($proj1['vida.hobbies_secundarios']['visible_jugador'] ?? null) === true, 'parcial visible tras discovery');
ok(is_array($proj1['vida.hobbies_secundarios']['valor'] ?? null), 'parcial revela array real tras discovery');
ok(($proj1['vida.rasgos_ocultos']['visible_jugador'] ?? null) === true, 'por_evento visible tras discovery');

$configDefault = ['default' => DiscoveryVisibilityPolicy::SIN_POLITICA, 'por_categoria' => []];
$projDefault = DiscoveryProjection::proyectar($partida, 'per_qa_valid', $campos, $configDefault);
ok(array_key_exists('visible_jugador', $projDefault['vida.hobby_principal']) && $projDefault['vida.hobby_principal']['visible_jugador'] === null, 'sin_politica no fuerza visibilidad');
ok(($projDefault['vida.hobby_principal']['valor'] ?? null) === ($campos['vida.hobby_principal'] ?? null), 'sin_politica conserva valor');

exit($failures > 0 ? 1 : 0);
