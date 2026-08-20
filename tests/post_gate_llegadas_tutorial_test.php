<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CandidatoLlegadaEngine;
use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\TutorialBucle;
use AquiHayTema\Engine\TutorialIncorporaciones;
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

// --- Tutorial 3→8 ---
$p = $svc->nuevaPartida('juego_v1', 'tut-38-a');
ok(count(TutorialIncorporaciones::residentesActivos($p)) === 3, 'inicio con 3');
ok(!empty(TutorialBucle::vista($p)['activo']), 'tutorial activo');
ok(($p['llegadas']['modo'] ?? '') === 'tutorial', 'modo tutorial (sin candidatos normales)');

TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_BUZON, $root);
TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_VECINO, $root);
$v = TutorialBucle::registrarConRoot($p, TutorialBucle::HECHO_PLAN, $root);
ok(empty(TutorialBucle::vista($p)['activo']), 'tutorial cerrado');
ok(!empty(TutorialBucle::vista($p)['completado']), 'tutorial completado');
$nTras = count(TutorialIncorporaciones::residentesActivos($p));
ok($nTras >= 5, "tras completar tutorial ya hay oleada (n=$nTras)");

// Avanzar día 1
for ($h = 0; $h < 14; $h++) {
    $svc->avanzarReloj($p, 1);
}
$nFin = count(TutorialIncorporaciones::residentesActivos($p));
ok($nFin >= 8, "fin día 1 ≈8 (n=$nFin)");
ok(($p['llegadas']['modo'] ?? '') === 'normal', 'modo normal tras tutorial');
ok(empty(TutorialBucle::vista($p)['activo']), 'tutorial no se reactiva');

// Save/load
$id = $p['meta']['partida_id'];
$svc->guardar($p);
$p2 = $svc->cargar($id);
ok(count(TutorialIncorporaciones::residentesActivos($p2)) === $nFin, 'save/load conserva roster');

// --- Llegadas ---
// Forzar oferta: huecos, modo normal, cooldown 0
$p2['llegadas']['cooldown_hasta_dia'] = 0;
$p2['llegadas']['candidato_activo'] = null;
$p2['llegadas']['en_camino'] = null;
// Tirar muchas veces hasta ofrecer (o seed forzada)
$ofrecido = null;
for ($i = 0; $i < 80; $i++) {
    $p2['llegadas']['_tick_por_hora'] = false; // tirada diaria completa
    $of = CandidatoLlegadaEngine::intentarOfrecer($p2, $root);
    if ($of !== null) {
        $ofrecido = $of;
        break;
    }
    // avanzar estado RNG
    $svc->avanzarReloj($p2, 24);
    $p2['llegadas']['cooldown_hasta_dia'] = 0;
    $p2['llegadas']['candidato_activo'] = null;
}
ok($ofrecido !== null, 'se ofrece candidato con huecos');
if ($ofrecido) {
    $acc = CandidatoLlegadaEngine::aceptar($p2, $root);
    ok(!empty($acc['ok']), 'aceptar pone en camino');
    ok(isset($p2['llegadas']['en_camino']['espera_minutos']), 'espera 1-10 min');
    $espera = (int) ($p2['llegadas']['en_camino']['espera_minutos'] ?? 0);
    ok($espera >= 1 && $espera <= 10, "espera en rango ($espera)");
    $antesN = count(TutorialIncorporaciones::residentesActivos($p2));
    CandidatoLlegadaEngine::avanzarMinutosReloj($p2, $espera);
    CandidatoLlegadaEngine::tick($p2, $root);
    $despuesN = count(TutorialIncorporaciones::residentesActivos($p2));
    ok($despuesN === $antesN + 1, 'llegada efectiva tras espera');
}

// Rechazo + exclusión
$p3 = $svc->nuevaPartida('playtest_01', 'lleg-rej');
CandidatoLlegadaEngine::activarModoNormal($p3);
$p3['llegadas']['cooldown_hasta_dia'] = 0;
$candId = null;
for ($i = 0; $i < 60; $i++) {
    $p3['llegadas']['_tick_por_hora'] = false;
    $of = CandidatoLlegadaEngine::intentarOfrecer($p3, $root);
    if ($of) {
        $candId = $of['catalog_id'];
        break;
    }
    $svc->avanzarReloj($p3, 24);
    $p3['llegadas']['cooldown_hasta_dia'] = 0;
}
        ok($candId !== null, 'candidato en playtest_01');
if ($candId) {
    CandidatoLlegadaEngine::rechazar($p3, $root);
    ok(isset($p3['llegadas']['cooldown_caras'][$candId]), 'rechazado en cooldown de cara');
    ok(($p3['llegadas']['candidato_activo'] ?? null) === null, 'sin candidato activo tras rechazo');
}

// Capacidad A+B
$p4 = $svc->nuevaPartida('playtest_01', 'cap-ab');
CapacidadViviendas::abrirBloque($p4, 'b');
ok(CapacidadViviendas::capacidadTotal($p4) === 32, 'A+B = 32');
CapacidadViviendas::abrirBloque($p4, 'c');
ok(CapacidadViviendas::capacidadTotal($p4) === 48, 'A+B+C = 48');

// Voluntad lab
$lab = VoluntadPlanLab::simular([[70, 70], [95, 20], [20, 20], [95, 95]], 500);
ok(isset($lab['formulas']['media_geometrica']), 'lab voluntad tiene media_geometrica');
ok(($lab['recomendacion']['formula'] ?? '') === 'media_geometrica', 'recomienda geométrica');
ok(!empty($lab['bloqueado']), 'marca BLOQUEADO_DECISION_VOLUNTAD');

echo $fail === 0 ? "OK post_gate_llegadas_tutorial\n" : "FAIL post_gate ($fail)\n";
exit($fail === 0 ? 0 : 1);
