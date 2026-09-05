<?php
declare(strict_types=1);
/**
 * Test: Nuevo Plan sin intención explícita — backend auto-asigna tipo.
 *
 * Verifica que cuando la UI no envía tipo (tipo vacío), el backend lo deriva
 * automáticamente desde el estado de la relación vía PropuestaNivel::tipoPara().
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\ParejaEngine;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$svc = new PartidaService($root);
$failures = 0;
$passed = 0;

function ok(bool $cond, string $msg): void
{
    global $failures, $passed;
    if ($cond) {
        $passed++;
        echo "  OK: $msg\n";
    } else {
        $failures++;
        echo "  FAIL: $msg\n";
    }
}

function crearPar(array &$p): array
{
    global $svc;
    $rA = $svc->crearResidentePlaceholderDev($p);
    $rB = $svc->crearResidentePlaceholderDev($p);
    return [$rA['residente']['catalog_id'], $rB['residente']['catalog_id']];
}

function franja(array $p): int
{
    return (int) ($p['reloj']['dia_pueblo'] ?? 1) + 1;
}

// ── TEST 1: Dos desconocidos → tipo = 'conocerse' ──
echo "\n--- TEST 1: Desconocidos sin tipo explícito ---\n";
$p1 = $svc->nuevaPartida('test_fixtures_v0', 'tipo-auto-1-' . mt_rand());
[$a, $b] = crearPar($p1);
$r1 = PropuestaEncuentroEngine::proponer($p1, [$a, $b], franja($p1), 18, '', 'lug_cafeteria');
ok(($r1['ok'] ?? false) === true, 'proponer sin tipo devolvió ok');
$tipoProp1 = $r1['propuesta']['tipo'] ?? '';
ok($tipoProp1 === 'conocerse', "tipo auto = '$tipoProp1' (esperaba 'conocerse')");

// ── TEST 2: Dos conocidos → tipo = 'quedar' ──
echo "\n--- TEST 2: Conocidos sin tipo explícito ---\n";
$p2 = $svc->nuevaPartida('test_fixtures_v0', 'tipo-auto-2-' . mt_rand());
[$c, $d] = crearPar($p2);
RelacionEngine::registrarContacto($p2, $c, $d, 'normal', []);
RelacionEngine::registrarContacto($p2, $d, $c, 'normal', []);
ok(RelacionEngine::seConocen($p2, $c, $d), 'verificación: se conocen');
$r2 = PropuestaEncuentroEngine::proponer($p2, [$c, $d], franja($p2), 18, '', 'lug_parque');
ok(($r2['ok'] ?? false) === true, 'proponer sin tipo devolvió ok');
$tipoProp2 = $r2['propuesta']['tipo'] ?? '';
ok($tipoProp2 === 'quedar', "tipo auto = '$tipoProp2' (esperaba 'quedar')");

// ── TEST 3: Pareja estable → tipo = 'cita' ──
echo "\n--- TEST 3: Pareja estable sin tipo explícito ---\n";
$p3 = $svc->nuevaPartida('test_fixtures_v0', 'tipo-auto-3-' . mt_rand());
[$e, $f] = crearPar($p3);
RelacionEngine::registrarContacto($p3, $e, $f, 'normal', []);
RelacionEngine::registrarContacto($p3, $f, $e, 'normal', []);
// Establecer romance + estado_pareja vía RelacionEngine
$rom = RelacionEngine::obtenerEntre($p3, $e, $f);
$rom['romance'] = $rom['romance'] ?? [];
$rom['romance']['estado_pareja'] = ParejaEngine::PAREJA;
$rom['romance']['a_hacia_b'] = 60;
$rom['romance']['b_hacia_a'] = 65;
RelacionEngine::upsertRomance($p3, $e, $f, $rom['romance']);
// Forzar estado_pareja directamente en la relación
foreach ($p3['relaciones_romanticas'] ?? [] as &$rel) {
    if (($rel['persona_a'] ?? '') === min($e, $f) && ($rel['persona_b'] ?? '') === max($e, $f)) {
        $rel['estado_pareja'] = ParejaEngine::PAREJA;
        break;
    }
}
unset($rel);
ok(ParejaEngine::estado($p3, $e, $f) === ParejaEngine::PAREJA, 'verificación: es pareja');
$r3 = PropuestaEncuentroEngine::proponer($p3, [$e, $f], franja($p3), 18, '', 'lug_restaurante');
ok(($r3['ok'] ?? false) === true, 'proponer sin tipo devolvió ok');
$tipoProp3 = $r3['propuesta']['tipo'] ?? '';
ok($tipoProp3 === 'cita', "tipo auto = '$tipoProp3' (esperaba 'cita')");

// ── TEST 4: Tipo explícito válido se respeta (quedar para conocidos) ──
echo "\n--- TEST 4: Tipo explícito se respeta ---\n";
$p4 = $svc->nuevaPartida('test_fixtures_v0', 'tipo-auto-4-' . mt_rand());
[$g, $h] = crearPar($p4);
RelacionEngine::registrarContacto($p4, $g, $h, 'normal', []);
RelacionEngine::registrarContacto($p4, $h, $g, 'normal', []);
$r4 = PropuestaEncuentroEngine::proponer($p4, [$g, $h], franja($p4), 18, 'quedar', 'lug_cafeteria');
ok(($r4['ok'] ?? false) === true, 'proponer con tipo explícito devolvió ok');
$tipoProp4 = $r4['propuesta']['tipo'] ?? '';
ok($tipoProp4 === 'quedar', "tipo explícito = '$tipoProp4' (esperaba 'quedar')");

// ── TEST 5: Propuesta se crea correctamente sin campo intencion del jugador ──
echo "\n--- TEST 5: Propuesta sin campo intencion del jugador ---\n";
$prop = $r1['propuesta'] ?? [];
ok(isset($prop['id']), 'propuesta tiene id');
ok(($prop['intencion'] ?? '') === 'jugador_propone', 'intencion interna = jugador_propone (default)');
ok(($prop['tipo'] ?? '') !== '', 'propuesta tiene tipo asignado');

// ── TEST 6: No queda llamada frontend a intenciones_disponibles ──
echo "\n--- TEST 6: Frontend sin llamada a intenciones_disponibles ---\n";
$jsFile = file_get_contents(dirname(__DIR__) . '/assets/js/play-v3.js');
ok(strpos($jsFile, 'encuentro.intenciones_disponibles') === false, 'play-v3.js sin encuentro.intenciones_disponibles');
ok(strpos($jsFile, 'org.intencion') === false, 'play-v3.js sin org.intencion');
ok(strpos($jsFile, 'orgIntencionHtml') === false, 'play-v3.js sin orgIntencionHtml');
ok(strpos($jsFile, 'orgTipoHtml') === false, 'play-v3.js sin orgTipoHtml');

// ── TEST 7: HTML sin sección ¿Qué harán? ──
echo "\n--- TEST 7: HTML sin sección ¿Qué harán? ---\n";
$htmlFile = file_get_contents(dirname(__DIR__) . '/play.php');
ok(strpos($htmlFile, 'org-seccion--que') === false, 'play.php sin org-seccion--que');
ok(strpos($htmlFile, 'data-org-tipos') === false, 'play.php sin data-org-tipos');

// ── TEST 8: Encuentros Interactivos siguen funcionando ──
echo "\n--- TEST 8: EncuentroIntervencion sigue disponible ---\n";
ok(class_exists('AquiHayTema\\Engine\\EncuentroIntervencion'), 'EncuentroIntervencion clase existe');
$acciones = \AquiHayTema\Engine\EncuentroIntervencion::ACCIONES_SOCIALES ?? [];
ok(count($acciones) >= 4, "ACCIONES_SOCIALES tiene " . count($acciones) . " acciones (>= 4)");

// ── RESULTADO ──
echo "\n" . str_repeat('=', 50) . "\n";
echo "Tests: $passed passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
