<?php
require dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\SchemaMigrator;

$s = new PartidaService(dirname(__DIR__));
$p = $s->nuevaPartida('juego_v1', 'tut-dbg');
echo 'modo=' . ($p['llegadas']['modo'] ?? 'null') . "\n";
echo 'tutorial_cola=' . json_encode($p['llegadas']['tutorial_cola'] ?? null) . "\n";

$v2 = SchemaMigrator::migrate(['meta' => ['schema_version' => 2], 'residentes' => [], 'relaciones_sociales' => [
    ['id' => 'soc_x_y', 'persona_a' => 'x', 'persona_b' => 'y', 'tipo' => 'amigos', 'intensidad' => 2],
]]);
echo 'valor=' . ($v2['relaciones_sociales'][0]['a_hacia_b']['valor'] ?? 'null') . "\n";
