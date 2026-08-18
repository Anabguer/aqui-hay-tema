<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('test_fixtures_v0', 'rel-test');
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

$r = RelacionEngine::upsertSocial($partida, $a, $b, 'conocidos', 2, true);
ok($r['ok'] ?? false && ($r['creada'] ?? false), 'crear relación social');

$r2 = RelacionEngine::upsertSocial($partida, $b, $a, 'amigos', 3, true);
ok($r2['ok'] ?? false && !($r2['creada'] ?? true), 'actualizar social (orden simétrico)');

$rel = RelacionEngine::obtenerEntre($partida, $b, $a);
ok($rel['social']['tipo'] === 'amigos', 'simetría par social');
ok($rel['social']['persona_a'] < $rel['social']['persona_b'], 'par ordenado lexicográficamente');
$haciaA = RelacionEngine::socialHacia($partida, $a, $b);
$haciaB = RelacionEngine::socialHacia($partida, $b, $a);
ok(($haciaA['banda'] ?? '') === 'conocidos', 'A→B conserva primera dirección');
ok(($haciaB['banda'] ?? '') === 'amigos', 'B→A se actualiza aparte');

RelacionEngine::upsertRomance($partida, $a, $b, ['vinculo' => 4, 'conflicto' => 1]);
$rel2 = RelacionEngine::obtenerEntre($partida, $a, $b);
ok($rel2['social'] !== null && $rel2['romance'] !== null, 'social independiente de romance');
ok(($rel2['romance']['conflicto'] ?? null) === 1, 'conflicto en romance independiente');

RelacionEngine::upsertConflicto($partida, $a, $b, null, 'roce');
$rel3 = RelacionEngine::obtenerEntre($partida, $a, $b);
ok($rel3['conflicto'] !== null, 'canal roce/conflicto propio');
ok(array_key_exists('fase', $rel3['social']), 'fase aditiva en social');
ok($rel3['social']['fase'] === null, 'fase no auto-calculada');
ok(RelacionEngine::seConocen($partida, $a, $b), 'contacto implica conocidos');

exit($failures > 0 ? 1 : 0);
