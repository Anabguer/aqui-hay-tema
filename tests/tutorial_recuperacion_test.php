<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\TutorialPrimerosPasos;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$svc = new PartidaService($root);
$failures = 0;

function ok(bool $condition, string $message): void
{
    global $failures;
    echo ($condition ? 'OK' : 'FAIL') . ": {$message}\n";
    if (!$condition) {
        $failures++;
    }
}

/**
 * Deja el tutorial justo antes de la misión 3.
 *
 * @param array<string, mixed> $partida
 */
function completarHastaM3(array &$partida, string $root): void
{
    $pareja = $partida['tutorial']['pareja_mision1'] ?? [];
    PropuestaEncuentroEngine::proponer(
        $partida,
        [(string) ($pareja['a'] ?? ''), (string) ($pareja['b'] ?? '')],
        (int) ($partida['reloj']['dia_pueblo'] ?? 1),
        18,
        PropuestaNivel::PRESENTAR,
        'lug_cafeteria'
    );
    $msgId = (string) ($partida['tutorial']['mensajito_id'] ?? '');
    BuzonEngine::marcarLeido($partida, $msgId);
    TutorialPrimerosPasos::alLeerMensaje($partida, $msgId, new \AquiHayTema\Engine\Catalog($root));
}

// A) Día 1 normal: las tres misiones, finale y cierre.
$a = $svc->nuevaPartida('juego_v1', 'tutorial-recuperacion-a');
ok(count(array_filter(
    $a['misiones_diarias']['items'] ?? [],
    static fn($m) => ($m['familia'] ?? '') === 'primeros_pasos'
)) === 3, 'A: día 1 tiene 3 misiones tutoriales');
completarHastaM3($a, $root);
$tercero = (string) ($a['tutorial']['tercero'] ?? '');
PropuestaEncuentroEngine::proponer($a, [$tercero], 1, 19, 'individual', 'lug_cine');
$vistaA = TutorialPrimerosPasos::vistaPublica($a);
ok(!empty($a['tutorial']['jugable_completado']), 'A: tercera misión completa el jugable');
ok(!empty($vistaA['finale_pendiente']), 'A: aparece finale_pendiente');
TutorialPrimerosPasos::marcarFinaleVisto($a);
ok(!empty($a['tutorial']['finale_visto']) && empty($a['tutorial']['activo']), 'A: cerrar finale marca finale_visto');

// B) Saltar al día 2 con M3 pendiente no la caduca ni la oculta.
$b = $svc->nuevaPartida('juego_v1', 'tutorial-recuperacion-b');
completarHastaM3($b, $root);
$svc->avanzarReloj($b, 24);
$m3b = [];
foreach ($b['misiones_diarias']['items'] ?? [] as $m) {
    if (($m['id'] ?? '') === TutorialPrimerosPasos::M3) {
        $m3b = $m;
        break;
    }
}
$vistaB = MisionDiariaEngine::vistaHoy($b);
ok(($m3b['estado'] ?? '') === MisionDiariaEngine::EST_PENDIENTE, 'B: M3 sigue pendiente al pasar de día');
ok(count(array_filter(
    $vistaB['misiones'] ?? [],
    static fn($m) => ($m['familia'] ?? '') === 'primeros_pasos'
)) === 3, 'B: las misiones tutoriales siguen disponibles en día 2');
PropuestaEncuentroEngine::proponer($b, [(string) ($b['tutorial']['tercero'] ?? '')], 2, 19, 'individual', 'lug_cine');
ok(!empty($b['tutorial']['jugable_completado']), 'B: M3 se puede completar en día 2');
ok(!empty(TutorialPrimerosPasos::vistaPublica($b)['finale_pendiente']), 'B: aparece finale tras M3 en día 2');

// C) Save legacy: M3 caducada se reabre al cargar y conserva M1/M2.
$c = $svc->nuevaPartida('juego_v1', 'tutorial-recuperacion-c');
completarHastaM3($c, $root);
foreach ($c['misiones_diarias']['items'] as $i => $m) {
    if (($m['id'] ?? '') === TutorialPrimerosPasos::M3) {
        $c['misiones_diarias']['items'][$i]['estado'] = MisionDiariaEngine::EST_CADUCADA;
    }
}
$c['reloj']['dia_pueblo'] = 3;
$c['reloj']['hora_actual'] = 8;
$legacyId = (string) $c['meta']['partida_id'];
$svc->guardar($c);
$cargada = $svc->cargar($legacyId);
$m3c = [];
foreach ($cargada['misiones_diarias']['items'] ?? [] as $m) {
    if (($m['id'] ?? '') === TutorialPrimerosPasos::M3) {
        $m3c = $m;
        break;
    }
}
ok(($m3c['estado'] ?? '') === MisionDiariaEngine::EST_PENDIENTE, 'C: cargar reabre M3 caducada');
ok(empty($cargada['tutorial']['jugable_completado']), 'C: recuperar no inventa jugable completado');
PropuestaEncuentroEngine::proponer($cargada, [(string) ($cargada['tutorial']['tercero'] ?? '')], 3, 19, 'individual', 'lug_cine');
ok(!empty($cargada['tutorial']['jugable_completado']), 'C: M3 recuperada se puede completar');
ok(!empty(TutorialPrimerosPasos::vistaPublica($cargada)['finale_pendiente']), 'C: aparece finale tras recuperación');

// D) Tras cerrar el finale, el siguiente día vuelve el paquete normal.
TutorialPrimerosPasos::marcarFinaleVisto($b);
$svc->avanzarReloj($b, 24);
$normales = array_values(array_filter(
    $b['misiones_diarias']['items'] ?? [],
    static fn($m) => ($m['familia'] ?? '') !== 'primeros_pasos'
        && (int) ($m['dia'] ?? 0) === 3
));
ok(count($normales) === 3, 'D: el siguiente ciclo genera 3 misiones normales');

echo $failures === 0
    ? "tutorial_recuperacion_test OK\n"
    : "tutorial_recuperacion_test FAIL ({$failures})\n";
exit($failures > 0 ? 1 : 0);
