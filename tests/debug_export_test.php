<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/api/bootstrap.php';

use AquiHayTema\Api\ApiContext;
use AquiHayTema\Api\Handlers\PartidaHandler;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('juego_v1', 'debug-export-test');
$partidaId = (string) $partida['meta']['partida_id'];

$_GET['debug'] = '1';
$body = [
    'partida_id' => $partidaId,
    'debug' => 1,
    'historial' => [
        ['prefijo' => '[AHT DEBUG TEST]', 'datos' => ['ping' => true]],
    ],
];

$ctx = new ApiContext($root);
$r = PartidaHandler::debugExport($ctx, $body, $partida);

if (!($r['ok'] ?? false)) {
    fwrite(STDERR, 'debug_export_test FAIL: ' . json_encode($r) . "\n");
    exit(1);
}
$txt = (string) ($r['debug_export']['texto'] ?? '');
if (strpos($txt, '[AHT DEBUG PARTIDA]') === false || strpos($txt, '[AHT DEBUG NPC]') === false) {
    fwrite(STDERR, "debug_export_test FAIL: texto incompleto\n");
    exit(1);
}
if (strpos($txt, 'atraido_por') !== false || strpos($txt, 'heterosexual') !== false) {
    fwrite(STDERR, "debug_export_test FAIL: export contiene orientación legacy\n");
    exit(1);
}
if (strpos($txt, '[AHT DEBUG TEST]') === false) {
    fwrite(STDERR, "debug_export_test FAIL: falta historial cliente\n");
    exit(1);
}
echo "debug_export_test OK\n";
