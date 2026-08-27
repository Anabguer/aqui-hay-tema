<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\TutorialIncorporaciones;
use AquiHayTema\Engine\TutorialPrimerosPasos;
use AquiHayTema\Engine\Voluntad\VoluntadPlanLab;

$root = dirname(__DIR__);
$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$svc = new PartidaService($root);

// --- Primeros pasos 3→8 ---
$p = $svc->nuevaPartida('juego_v1', 'tut-38-a');
ok(count(TutorialIncorporaciones::residentesActivos($p)) === 3, 'inicio con 3');
ok(($p['tutorial']['id'] ?? '') === TutorialPrimerosPasos::ID, 'tutorial primeros pasos');
ok(($p['llegadas']['modo'] ?? '') === 'tutorial', 'modo tutorial (sin candidatos normales)');
ok(count(TutorialIncorporaciones::tickDia1($p, $root)) === 0, 'sin incorporaciones durante primeros pasos');

$pareja = $p['tutorial']['pareja_mision1'];
$a = (string) $pareja['a'];
$b = (string) $pareja['b'];
PropuestaEncuentroEngine::proponer($p, [$a, $b], 1, 18, PropuestaNivel::PRESENTAR, 'lug_cafeteria');
$mid = (string) ($p['tutorial']['mensajito_id'] ?? '');
BuzonEngine::marcarLeido($p, $mid);
TutorialPrimerosPasos::alLeerMensaje($p, $mid, new \AquiHayTema\Engine\Catalog($root));
$tercero = (string) $p['tutorial']['tercero'];
PropuestaEncuentroEngine::proponer($p, [$tercero], 1, 19, 'individual', 'lug_cine');
ok(!empty($p['tutorial']['jugable_completado']), 'primeros pasos completados');
$nTras = count(TutorialIncorporaciones::residentesActivos($p));
ok($nTras === 3, "tras completar primeros pasos sigue en núcleo (n=$nTras)");

for ($h = 0; $h < 14; $h++) {
    $svc->avanzarReloj($p, 1);
}
$nFin = count(TutorialIncorporaciones::residentesActivos($p));
ok($nFin === 3, "fin día 1 sigue en núcleo (n=$nFin)");
ok(($p['llegadas']['modo'] ?? '') === 'normal', 'modo normal tras tutorial');
ok(empty($p['tutorial']['activo'] ?? true) || !empty($p['tutorial']['jugable_completado']), 'tutorial jugable cerrado');

$id = $p['meta']['partida_id'];
$svc->guardar($p);
$p2 = $svc->cargar($id);
ok(count(TutorialIncorporaciones::residentesActivos($p2)) === $nFin, 'save/load conserva roster');

// --- Llegadas ---
$p2['llegadas']['cooldown_hasta_dia'] = 0;
$p2['llegadas']['candidato_activo'] = null;
$p2['llegadas']['en_camino'] = null;
// Tirar muchas veces hasta ofrecer (o seed forzada)
$ofrecido = null;