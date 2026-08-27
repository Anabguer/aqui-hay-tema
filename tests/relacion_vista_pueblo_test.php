<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'rel-vista-pueblo');
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$ph = $service->crearResidentePlaceholderDev($partida);
$a = 'per_qa_valid';
$b = $ph['residente']['catalog_id'];

RelacionEngine::upsertSocial($partida, $a, $b, 'amigos', 3, true);
RelacionEngine::upsertRomance($partida, $a, $b, ['vinculo' => 2]);

$r = $service->vistaRelacionesPueblo($partida);
ok(($r['ok'] ?? false) === true, 'vistaRelacionesPueblo ok');
ok(is_array($r['relaciones'] ?? null), 'devuelve array relaciones');
ok(count($r['relaciones']) >= 1, 'incluye al menos un par relevante');

$par = $r['relaciones'][0];
ok(isset($par['persona_a']['id'], $par['persona_b']['id']), 'cada fila tiene personas');
ok(isset($par['a_hacia_b'], $par['b_hacia_a']), 'cada fila tiene direcciones');
ok(!array_key_exists('social_valor', $par['a_hacia_b'] ?? []), 'sin valores numericos internos');

echo $failures === 0 ? "OK relacion_vista_pueblo\n" : "FAIL relacion_vista_pueblo ({$failures})\n";
exit($failures > 0 ? 1 : 0);
