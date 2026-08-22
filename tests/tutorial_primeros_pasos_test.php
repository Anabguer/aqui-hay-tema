<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\TutorialPrimerosPasos;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'tut_pp_test');

assert(($p['tutorial']['id'] ?? '') === TutorialPrimerosPasos::ID);
$misiones = array_values(array_filter(
    $p['misiones_diarias']['items'] ?? [],
    static fn($m) => ($m['familia'] ?? '') === 'primeros_pasos'
));
assert(count($misiones) === 3, '3 misiones primeros pasos');
assert(($misiones[0]['estado'] ?? '') === MisionDiariaEngine::EST_PENDIENTE);
assert(($misiones[1]['estado'] ?? '') === 'bloqueada');

$pareja = $p['tutorial']['pareja_mision1'];
$a = (string) $pareja['a'];
$b = (string) $pareja['b'];
$tipo = \AquiHayTema\Engine\PropuestaNivel::PRESENTAR;
$r = PropuestaEncuentroEngine::proponer($p, [$a, $b], 1, 18, $tipo, 'lug_cafeteria');
assert(!empty($r['ok']), 'plan pareja procesado');
assert(!empty($r['nuevo_mensajito']), 'respuesta incluye nuevo_mensajito');
assert(!empty($r['mensajito_id']), 'respuesta incluye mensajito_id');
assert(($r['mensajito_aviso_ui'] ?? '') === 'Tienes un nuevo Mensajito.', 'aviso ui mensajito');

$m2 = null;
foreach ($p['misiones_diarias']['items'] as $m) {
    if (($m['id'] ?? '') === TutorialPrimerosPasos::M2) {
        $m2 = $m;
    }
}
assert(($m2['estado'] ?? '') === MisionDiariaEngine::EST_PENDIENTE, 'mision 2 activa');
assert(!empty($p['tutorial']['mensajito_id']), 'mensajito creado');

$msgId = (string) $p['tutorial']['mensajito_id'];
$msg = null;
foreach ($p['buzon'] ?? [] as $m) {
    if (($m['id'] ?? '') === $msgId) {
        $msg = $m;
    }
}
assert($msg !== null, 'mensajito en buzon');
$texto = (string) ($msg['texto'] ?? '');
assert(str_contains($texto, 'ir al cine'), 'mensajito menciona cine');
assert(str_contains($texto, 'Nuevo plan'), 'mensajito indica Nuevo plan');
assert(str_contains($texto, 'por su cuenta'), 'mensajito indica salida individual');

BuzonEngine::marcarLeido($p, $msgId);
TutorialPrimerosPasos::alLeerMensaje($p, $msgId, new Catalog($root));
foreach ($p['misiones_diarias']['items'] as $m) {
    if (($m['id'] ?? '') === TutorialPrimerosPasos::M2) {
        assert(($m['estado'] ?? '') === MisionDiariaEngine::EST_CUMPLIDA, 'mision 2 leida');
    }
}

$tercero = (string) $p['tutorial']['tercero'];
$r3 = PropuestaEncuentroEngine::proponer($p, [$tercero], 1, 19, 'individual', 'lug_cine');
assert(!empty($r3['ok']), 'plan solo procesado');
assert(!empty($p['tutorial']['jugable_completado']), 'tutorial jugable completado');

echo "tutorial_primeros_pasos_test OK\n";
