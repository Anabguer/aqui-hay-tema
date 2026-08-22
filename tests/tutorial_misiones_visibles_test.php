<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\TutorialPrimerosPasos;

$root = dirname(__DIR__);
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'tut_misiones_vis_' . time());
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$v = MisionDiariaEngine::vistaHoy($p);
$pp = array_values(array_filter($v['misiones'] ?? [], static fn($m) => ($m['familia'] ?? '') === 'primeros_pasos'));
ok(count($pp) === 3, 'vista muestra 3 primeros pasos al inicio');
ok(($pp[0]['titulo'] ?? '') === 'Romper el hielo', 'orden 1 romper hielo');
ok(($pp[1]['titulo'] ?? '') === 'Alguien quiere contarte algo', 'orden 2 mensajito');
ok(($pp[2]['titulo'] ?? '') === 'Pues habrá que hacerle caso', 'orden 3 cine');

$pareja = $p['tutorial']['pareja_mision1'] ?? [];
$a = (string) ($pareja['a'] ?? '');
$b = (string) ($pareja['b'] ?? '');
\AquiHayTema\Engine\PropuestaEncuentroEngine::proponer($p, [$a, $b], 1, 18, \AquiHayTema\Engine\PropuestaNivel::PRESENTAR, 'lug_cafeteria');
$v2 = MisionDiariaEngine::vistaHoy($p);
$pp2 = array_values(array_filter($v2['misiones'] ?? [], static fn($m) => ($m['familia'] ?? '') === 'primeros_pasos'));
ok(count($pp2) === 3, 'tras M1 siguen 3 visibles');
$m1 = array_values(array_filter($pp2, static fn($m) => ($m['id'] ?? '') === TutorialPrimerosPasos::M1))[0] ?? [];
ok(($m1['estado'] ?? '') === MisionDiariaEngine::EST_CUMPLIDA, 'M1 cumplida visible');

$mid = (string) ($p['tutorial']['mensajito_id'] ?? '');
\AquiHayTema\Engine\BuzonEngine::marcarLeido($p, $mid);
TutorialPrimerosPasos::alLeerMensaje($p, $mid, new \AquiHayTema\Engine\Catalog($root));
$v3 = MisionDiariaEngine::vistaHoy($p);
$pp3 = array_values(array_filter($v3['misiones'] ?? [], static fn($m) => ($m['familia'] ?? '') === 'primeros_pasos'));
ok(count($pp3) === 3, 'tras M2 siguen 3 visibles');

$tercero = (string) ($p['tutorial']['tercero'] ?? '');
\AquiHayTema\Engine\PropuestaEncuentroEngine::proponer($p, [$tercero], 1, 19, 'individual', 'lug_cine');
ok(!empty($p['tutorial']['jugable_completado']), 'jugable completado');
$v4 = MisionDiariaEngine::vistaHoy($p);
$pp4 = array_values(array_filter($v4['misiones'] ?? [], static fn($m) => ($m['familia'] ?? '') === 'primeros_pasos'));
$norm4 = array_values(array_filter($v4['misiones'] ?? [], static fn($m) => ($m['familia'] ?? '') !== 'primeros_pasos'));
ok(count($pp4) === 0, 'tras M3 ocultas primeros pasos en vista activa');
ok(count($norm4) >= 1, 'tras M3 aparecen misiones normales del mismo día');

$tut = TutorialPrimerosPasos::vistaPublica($p);
ok(!empty($tut['finale_pendiente']), 'finale pendiente tras tercera');

TutorialPrimerosPasos::marcarFinaleVisto($p);
$v5 = MisionDiariaEngine::vistaHoy($p);
$pp5 = array_values(array_filter($v5['misiones'] ?? [], static fn($m) => ($m['familia'] ?? '') === 'primeros_pasos'));
ok(count($pp5) === 0, 'tras finale_visto siguen ocultas primeros pasos');

exit($failures > 0 ? 1 : 0);
