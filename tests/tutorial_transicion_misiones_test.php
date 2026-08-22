<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\TutorialPrimerosPasos;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$svc = new PartidaService($root);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function ppCount(array $partida): int
{
    return count(array_filter(
        $partida['misiones_diarias']['items'] ?? [],
        static fn($m) => ($m['familia'] ?? '') === 'primeros_pasos'
    ));
}

function normalesDelDia(array $partida, int $dia): array
{
    return array_values(array_filter(
        $partida['misiones_diarias']['items'] ?? [],
        static fn($m) => ($m['familia'] ?? '') !== 'primeros_pasos' && (int) ($m['dia'] ?? 0) === $dia
    ));
}

function normalesVista(array $vista): array
{
    return array_values(array_filter(
        $vista['misiones'] ?? [],
        static fn($m) => ($m['familia'] ?? '') !== 'primeros_pasos'
    ));
}

function completarHastaM2(array &$partida, string $root): void
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
    TutorialPrimerosPasos::alLeerMensaje($partida, $msgId, new Catalog($root));
}

// A) Partida nueva → solo 3 misiones tutorial.
$p = $svc->nuevaPartida('juego_v1', 'tut-trans-a-' . time());
ok(ppCount($p) === 3, 'A: 3 misiones primeros pasos al inicio');
ok(count(normalesDelDia($p, 1)) === 0, 'A: sin misiones normales día 1 al inicio');
$v = MisionDiariaEngine::vistaHoy($p);
ok(count($v['misiones'] ?? []) === 3, 'A: vista muestra solo 3 tutorial');

// B) M1 + M2 → todavía no normales.
completarHastaM2($p, $root);
ok(ppCount($p) === 3, 'B: siguen 3 tutorial en items');
ok(count(normalesDelDia($p, 1)) === 0, 'B: sin misiones normales tras M2');
$v2 = MisionDiariaEngine::vistaHoy($p);
ok(count(normalesVista($v2)) === 0, 'B: vista sin normales tras M2');

// C) M3 → jugable_completado + normales mismo día 1.
$diaAntes = (int) ($p['reloj']['dia_pueblo'] ?? 1);
$horaAntes = (int) ($p['reloj']['hora_actual'] ?? 0);
$tercero = (string) ($p['tutorial']['tercero'] ?? '');
PropuestaEncuentroEngine::proponer($p, [$tercero], $diaAntes, 19, 'individual', 'lug_cine');
ok(!empty($p['tutorial']['jugable_completado']), 'C: jugable_completado tras M3');
ok((int) ($p['reloj']['dia_pueblo'] ?? 0) === $diaAntes, 'D: día no cambia en transición');
ok((int) ($p['reloj']['hora_actual'] ?? 0) === $horaAntes, 'D: hora no cambia en transición');
$normC = normalesDelDia($p, $diaAntes);
ok(count($normC) >= 1, 'C: paquete normal generado mismo día (' . count($normC) . ' misiones)');
$v3 = MisionDiariaEngine::vistaHoy($p);
$normVista = normalesVista($v3);
ok(count($normVista) >= 1, 'C: vista muestra misiones normales tras M3');
ok(count(array_filter($v3['misiones'] ?? [], static fn($m) => ($m['familia'] ?? '') === 'primeros_pasos')) === 0, 'C: vista oculta tutorial tras M3');

// E) Reconciliar / cargar no duplica paquete.
$countNorm = count($normC);
TutorialPrimerosPasos::reconciliarMisionesNormales($p, new Catalog($root));
ok(count(normalesDelDia($p, $diaAntes)) === $countNorm, 'E: reconciliar no duplica paquete');
$cal = CalibracionConfig::load($root);
MisionDiariaEngine::alComenzarDia($p, $cal, RngService::fromPartida($p));
ok(count(normalesDelDia($p, $diaAntes)) === $countNorm, 'E: alComenzarDia no duplica paquete día 1');
$id = (string) ($p['meta']['partida_id'] ?? '');
$svc->guardar($p);
$cargada = $svc->cargar($id);
ok(count(normalesDelDia($cargada, $diaAntes)) === $countNorm, 'E: cargar no duplica paquete');

// F) Día 2 genera su paquete; día 1 conserva historial.
$p['tutorial']['finale_visto'] = true;
$p['tutorial']['activo'] = false;
$svc->avanzarReloj($p, 24);
$dia2 = (int) ($p['reloj']['dia_pueblo'] ?? 2);
$normD1 = normalesDelDia($p, 1);
$normD2 = normalesDelDia($p, $dia2);
ok(count($normD1) === $countNorm, 'F: día 1 conserva paquete normal histórico');
ok(count($normD2) >= 1, 'F: día 2 genera paquete normal (' . count($normD2) . ' misiones)');

// G) Save legacy tutorial incompleto caducado — recuperación previa.
$g = $svc->nuevaPartida('juego_v1', 'tut-trans-g-' . time());
completarHastaM2($g, $root);
foreach ($g['misiones_diarias']['items'] as $i => $m) {
    if (($m['id'] ?? '') === TutorialPrimerosPasos::M3) {
        $g['misiones_diarias']['items'][$i]['estado'] = MisionDiariaEngine::EST_CADUCADA;
    }
}
$g['reloj']['dia_pueblo'] = 3;
$g['reloj']['hora_actual'] = 8;
$gid = (string) $g['meta']['partida_id'];
$svc->guardar($g);
$gLoad = $svc->cargar($gid);
$m3g = [];
foreach ($gLoad['misiones_diarias']['items'] ?? [] as $m) {
    if (($m['id'] ?? '') === TutorialPrimerosPasos::M3) {
        $m3g = $m;
        break;
    }
}
ok(($m3g['estado'] ?? '') === MisionDiariaEngine::EST_PENDIENTE, 'G: M3 caducada se reabre al cargar');
ok(empty($gLoad['tutorial']['jugable_completado']), 'G: tutorial incompleto no marca jugable completado');
PropuestaEncuentroEngine::proponer($gLoad, [(string) ($gLoad['tutorial']['tercero'] ?? '')], 3, 19, 'individual', 'lug_cine');
ok(!empty($gLoad['tutorial']['jugable_completado']), 'G: M3 recuperada completa jugable');
ok(count(normalesDelDia($gLoad, 3)) >= 1, 'G: tras completar genera normales día 3');

// H) Save legacy: jugable completado sin paquete normal día 1.
$h = $svc->nuevaPartida('juego_v1', 'tut-trans-h-' . time());
$h['tutorial']['jugable_completado'] = true;
$h['tutorial']['finale_visto'] = false;
$h['tutorial']['activo'] = true;
$h['reloj']['dia_pueblo'] = 1;
$h['reloj']['hora_actual'] = 14;
$h['misiones_diarias']['dia'] = 1;
$h['misiones_diarias']['items'] = array_values(array_filter(
    $h['misiones_diarias']['items'] ?? [],
    static fn($m) => ($m['familia'] ?? '') === 'primeros_pasos'
));
ok(count(normalesDelDia($h, 1)) === 0, 'H: save legacy sin normales');
TutorialPrimerosPasos::reconciliarMisionesNormales($h, new Catalog($root));
$normH = normalesDelDia($h, 1);
ok(count($normH) >= 1, 'H: reconciliar genera paquete día 1 una vez');
TutorialPrimerosPasos::reconciliarMisionesNormales($h, new Catalog($root));
ok(count(normalesDelDia($h, 1)) === count($normH), 'H: segunda reconciliar no duplica');

echo $failures === 0
    ? "tutorial_transicion_misiones_test OK\n"
    : "tutorial_transicion_misiones_test FAIL ({$failures})\n";
exit($failures > 0 ? 1 : 0);
