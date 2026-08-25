<?php
declare(strict_types=1);

// FASE 6 (R7) · RUPTURA + POST-RUPTURA.
//
// Cobertura:
//   A crisis joven + p=1 ⇒ NO ruptura (sin origen O1)
//   B crisis sin salida + p=1 + voluntad=1 ⇒ ruptura completa:
//     EX, como_acabo, historial sellado, citas futuras CANCELADAS,
//     marcadores purgados, emociones asimétricas, memoria romance_hito
//   C autoría: rompe el de menor romance; decisión unilateral declinable
//   D gates post-ruptura: ex_sin_vuelta en iniciativas; desbloqueaPrimeraCita
//     false; tiposPermitidos solo quedar
//   E O2 golpe duro en pareja estable (muy_mal+conflicto+suelo) ⇒ ruptura

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EtiquetaRelacionPlay;
use AquiHayTema\Engine\IniciativaPareja;
use AquiHayTema\Engine\IniciativaRomantica;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\SenalRomantica;

$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$root = dirname(__DIR__);
$GLOBALS['__root'] = $root;
DomainBootstrap::boot();

const UA = 'ana';
const UB = 'bruno';

function calRup(array $rupOverrides = [], array $vol = []): array
{
    $cal = CalibracionConfig::load(dirname(__DIR__));
    $cal['romance_autonomo']['crisis_activa'] = true;
    $cal['romance_autonomo']['ruptura_activa'] = true;
    $cal['romance_autonomo']['max_hitos_por_dia'] = 4;
    foreach ($rupOverrides as $k => $v) {
        $cal['romance_autonomo']['ruptura'][$k] = $v;
    }
    foreach ($vol as $k => $v) {
        $cal['voluntad'][$k] = $v;
    }
    return $cal;
}

function labPareja(array $cal): array
{
    $p = [
        'reloj' => ['dia_pueblo' => 40, 'hora_actual' => 23],
        'rng' => ['seed' => 'f6', 'state' => 505],
        'meta' => ['seed' => 'f6'],
        'residentes' => [
            UA => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            UB => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => ['lugares_desbloqueados' => ['lug_cafeteria']],
        'relaciones_sociales' => [],
        'relaciones_romanticas' => [],
        'relaciones_conflicto' => [],
        'encuentros' => [],
        'parentesco' => [],
        'continuidad_romantica' => [],
        'bitacora_relaciones' => [],
        'memoria_eventos' => [],
        'propuestas_cooldown' => [],
        'rechazos_propuesta' => [],
        'diario' => [],
        'buzon' => [],
        'narrativa_hitos_publicados' => [],
    ];
    for ($i = 0; $i < 3; $i++) {
        RelacionEngine::ajustarSocialHacia($p, UA, UB, 10);
        RelacionEngine::ajustarSocialHacia($p, UB, UA, 10);
    }
    ParejaEngine::formar($p, UA, UB, true, true, RelacionBitacora::DECLARACION, $cal);
    return $p;
}

/** Pareja EN CRISIS con sello antiguo (día 40) y reloj actual día dado. */
function parejaCrisisAntigua(array $cal, int $diaActual, int $fallos = 0): array
{
    $p = labPareja($cal);
    ParejaEngine::crisis($p, UA, UB);
    $rel = RelacionEngine::obtenerEntre($p, UA, UB)['romance'];
    $rel['crisis_desde'] = ['dia' => 40, 'hora' => 0];
    $rel['fallos_reparacion'] = $fallos;
    RelacionEngine::persistirRomance($p, $rel);
    $p['reloj']['dia_pueblo'] = $diaActual;
    return $p;
}

$calSi = calRup(['probabilidad' => 1.0], ['p_min' => 1.0, 'p_max' => 1.0]);
$calNoVol = calRup(['probabilidad' => 1.0], ['p_min' => 0.0, 'p_max' => 0.0]);

// ============ A: crisis joven sin origen ⇒ nada ============
$p = parejaCrisisAntigua($calSi, 42); // solo 2 días en crisis, fallos 0
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calSi);
ok(($out['rupturas'] ?? 0) === 0 && ParejaEngine::estado($p, UA, UB) === ParejaEngine::CRISIS, 'A1 sin origen: no hay ruptura');

// ============ B: ruptura completa por O1 ============
$p = parejaCrisisAntigua($calSi, 48, 2); // 8 días + 2 fallos
// cita futura del par que DEBE cancelarse
$p['encuentros'][] = [
    'id' => 'enc_futura', 'tipo' => 'cita', 'participantes' => [UA, UB], 'lugar' => 'lug_cafeteria',
    'dia' => 49, 'hora' => 12, 'duracion_horas' => 1, 'estado' => 'programado', 'resultado' => null,
];
// marcador pendiente que DEBE purgarse
$p['continuidad_romantica'][] = ['par' => [UA, UB], 'accion' => 'cita', 'desde_abs' => 99999];
// rompe Bruno (menor romance)
RelacionEngine::setRomanceHacia($p, UA, UB, 45);
RelacionEngine::setRomanceHacia($p, UB, UA, 5);
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calSi);
ok(($out['rupturas'] ?? 0) === 1, 'B1 evaluador reporta ruptura');
ok(ParejaEngine::estado($p, UA, UB) === ParejaEngine::EX, 'B2 estado EX');
ok(count(RelacionBitacora::entre($p, UA, UB, RelacionBitacora::RUPTURA)) === 1, 'B3 hito RUPTURA');
$rel = RelacionEngine::obtenerEntre($p, UA, UB)['romance'];
$n = count($rel['historial_parejas'] ?? []);
ok($n >= 1 && (($rel['historial_parejas'][$n - 1]['como_acabo'] ?? '') === 'crisis_sin_salida'), 'B4 como_acabo registrado');
ok(is_array($rel['historial_parejas'][$n - 1]['fin'] ?? null), 'B5 fin sellado');
ok((bool) ($rel['estabilidad_pareja']['memoria'] !== null), 'B6 estabilidad queda como memoria');
$citaFut = null;
foreach ($p['encuentros'] as $e) {
    if (($e['id'] ?? '') === 'enc_futura') {
        $citaFut = $e;
    }
}
ok($citaFut !== null && ($citaFut['estado'] ?? '') === 'cancelado' && ($citaFut['motivo_cancelacion'] ?? '') === 'ruptura', 'B7 cita futura CANCELADA con motivo');
ok(markerDeF6($p) === null, 'B8 marcadores del par PURGADOS');
$emoUa = (string) ($p['residentes'][UA]['runtime']['estado_emocional']['id'] ?? '');
$emoUb = (string) ($p['residentes'][UB]['runtime']['estado_emocional']['id'] ?? '');
ok($emoUa === 'triste' && $emoUb === 'triste', 'B9 tristeza en ambos');
$hayMemRup = false;
foreach (($p['memoria_eventos'] ?? []) as $ev) {
    if (($ev['tipo'] ?? '') === 'ruptura') {
        $hayMemRup = true;
    }
}
ok($hayMemRup, 'B10 memoria romance_hito/ruptura');

// ============ C: autoría y decisión unilateral declinable ============
$p = parejaCrisisAntigua($calNoVol, 48, 2); // p=1 pero voluntad=0 ⇒ declina
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calNoVol);
ok(($out['rupturas'] ?? 0) === 0 && ParejaEngine::estado($p, UA, UB) === ParejaEngine::CRISIS, 'C1 voluntad del rompedor manda: declinada');

// ============ D: gates post-ruptura (par EX fresco) ============
$calPlain = CalibracionConfig::load($root);
$p = labPareja($calPlain);
RelacionEngine::setRomanceHacia($p, UA, UB, 30);
RelacionEngine::setRomanceHacia($p, UB, UA, 30);
ParejaEngine::romper($p, UA, UB, 'test');
ok(ParejaEngine::estado($p, UA, UB) === ParejaEngine::EX, 'D0 fixture: par EX');
ok(SenalRomantica::desbloqueaPrimeraCita($p, UA, UB, $calPlain) === false, 'D1 exes: desbloqueaPrimeraCita false');
$tipos = PropuestaNivel::tiposPermitidos($p, UA, UB, $calPlain);
ok($tipos === [PropuestaNivel::QUEDAR], 'D2 exes: solo quedar amistoso');
$rG = IniciativaRomantica::intentarSiguienteCita($p, UA, UB, $calPlain);
ok(($rG['resultado'] ?? '') === 'gate_ex_sin_vuelta', 'D3 iniciativa entre exes: gate_ex_sin_vuelta');
$et = EtiquetaRelacionPlay::romanceHacia($p, UA, UB, $calPlain);
ok(($et['banda'] ?? '') === 'ex', 'D4 etiqueta Ex pareja');

// ============ E: O2 golpe duro en pareja estable ============
$p = labPareja($calSi);
RelacionEngine::setRomanceHacia($p, UA, UB, 45);
RelacionEngine::setRomanceHacia($p, UB, UA, 5); // Ana rompe (menor... 45>5 → rompe Bruno? menor=Bruno)
$rel = RelacionEngine::obtenerEntre($p, UA, UB)['romance'];
$rel['estabilidad_pareja']['valor'] = 15;
RelacionEngine::persistirRomance($p, $rel);
RelacionEngine::upsertConflicto($p, UA, UB, 9, 'roce', 'test');
MemoriaEventos::registrar($p, 'encuentro', [UA, UB], null, 'cita', 'muy_mal');
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calSi);
ok(($out['rupturas'] ?? 0) === 1 && ParejaEngine::estado($p, UA, UB) === ParejaEngine::EX, 'E1 golpe duro ⇒ ruptura');
$n = count(RelacionEngine::obtenerEntre($p, UA, UB)['romance']['historial_parejas'] ?? []);
$hist = RelacionEngine::obtenerEntre($p, UA, UB)['romance']['historial_parejas'];
ok(($hist[$n - 1]['como_acabo'] ?? '') === 'golpe_duro', 'E2 como_acabo=golpe_duro');

echo $fail === 0 ? "\nOK fase6_ruptura\n" : "\nFAIL fase6_ruptura ($fail)\n";
exit($fail === 0 ? 0 : 1);

function markerDeF6(array $p): ?array
{
    foreach (($p['continuidad_romantica'] ?? []) as $m) {
        if (is_array($m) && in_array(UA, $m['par'], true) && in_array(UB, $m['par'], true)) {
            return $m;
        }
    }
    return null;
}
