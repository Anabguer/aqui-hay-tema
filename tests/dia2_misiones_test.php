<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\TutorialPrimerosPasos;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'dia2-misiones-test');
$p['tutorial']['jugable_completado'] = true;
$p['tutorial']['finale_visto'] = true;
$p['tutorial']['activo'] = false;
$p['reloj']['dia_pueblo'] = 2;
$p['reloj']['hora_actual'] = 9;
$p['misiones_diarias']['dia'] = 1;
$cal = \AquiHayTema\Engine\CalibracionConfig::load($root);
MisionDiariaEngine::alComenzarDia($p, $cal);
$normales = array_values(array_filter(
    $p['misiones_diarias']['items'] ?? [],
    static fn($m) => ($m['familia'] ?? '') !== 'primeros_pasos' && (int) ($m['dia'] ?? 0) === 2
));
if (count($normales) < 1) {
    fwrite(STDERR, "FAIL: sin misiones normales dia 2\n");
    exit(1);
}
echo "dia2_misiones_test OK (" . count($normales) . " misiones)\n";
