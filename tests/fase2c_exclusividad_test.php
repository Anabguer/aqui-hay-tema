<?php
declare(strict_types=1);

// R1 · Exclusividad global + gates de tercero + INC-1 (cupo Celestine).
//
// Cobertura:
//   A  ParejaEngine::parejaActivaDe fuente única (pareja|crisis|ex|ninguna)
//   B  formar(): imposible A-B pareja + A-C pareja (ambas órdenes)
//   C  formar(): par disjunto SÍ puede formarse en paralelo
//   D  formar(): tras ruptura el residente queda libre para nueva pareja
//      y el ex no puede reformar mientras el otro está emparejado
//   E  reconciliar(): el MISMO par siempre puede volver (vuelta)
//   F  SenalRomantica::desbloqueaPrimeraCita/avisarSiAplica gated por tercero
//   G  intentarPrimeraCita: gate_en_pareja_con_otro SIN consumo RNG
//   H  INC-1: primera cita autónoma NO consume cupo Celestine (max_dia=0)
//   I  Regresión: hito_sin_ambos_si intacto en par libre

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\IniciativaRomantica;
use AquiHayTema\Engine\ParejaEngine;
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
$GLOBALS['cal'] = CalibracionConfig::load($root);
$cal = $GLOBALS['cal'];

const XA = 'ana';
const XB = 'bruno';
const XC = 'caro';
const XD = 'diana';

function labPartida4(): array
{
    $mk = static fn (string $n, string $nom) => [
        'identidad_publica' => ['nombre' => $nom],
        'presencia' => 'residente',
        'runtime' => [],
    ];
    return [
        'reloj' => ['dia_pueblo' => 12, 'hora_actual' => 14],
        'rng' => ['seed' => 'r1', 'state' => 101],
        'meta' => ['seed' => 'r1'],
        'residentes' => [
            XA => $mk(XA, 'Ana'),
            XB => $mk(XB, 'Bruno'),
            XC => $mk(XC, 'Caro'),
            XD => $mk(XD, 'Diana'),
        ],
        'celeste' => ['lugares_desbloqueados' => ['lug_cafeteria', 'lug_parque']],
        'relaciones_sociales' => [],
        'relaciones_romanticas' => [],
        'relaciones_conflicto' => [],
        'encuentros' => [],
        'parentesco' => [],
    ];
}

/** Señal romántica mutua entre dos ids (social alto + romance 28 ambos sentidos). */
function senalMutua(array &$p, string $u, string $v): void
{
    foreach ([[$u, $v], [$v, $u]] as [$x, $y]) {
        $resto = 30;
        while ($resto > 0) {
            $d = min(10, $resto);
            RelacionEngine::ajustarSocialHacia($p, $x, $y, $d);
            $resto -= $d;
        }
        RelacionEngine::setRomanceHacia($p, $x, $y, 28);
    }
}

// ============ A: parejaActivaDe ============
$p = labPartida4();
ok(ParejaEngine::parejaActivaDe($p, XA) === null, 'A1 sin relaciones: null');
$r = ParejaEngine::formar($p, XA, XB, true, true, RelacionBitacora::DECLARACION, $cal);
ok(($r['ok'] ?? false) === true, 'A2 formar A-B ok');
ok(ParejaEngine::parejaActivaDe($p, XA) === XB, 'A3 parejaActivaDe(A)=B');
ok(ParejaEngine::parejaActivaDe($p, XB) === XA, 'A4 parejaActivaDe(B)=A');
ok(ParejaEngine::parejaActivaDe($p, XC) === null, 'A5 C libre');
ParejaEngine::crisis($p, XA, XB);
ok(ParejaEngine::parejaActivaDe($p, XA) === XB, 'A6 crisis TAMBIÉN es pareja activa');
ParejaEngine::romper($p, XA, XB, 'test');
ok(ParejaEngine::parejaActivaDe($p, XA) === null, 'A7 ex: libre');

// ============ B: exclusividad en formar ============
$p = labPartida4();
ParejaEngine::formar($p, XA, XB, true, true, RelacionBitacora::DECLARACION, $cal);
$rAC = ParejaEngine::formar($p, XA, XC, true, true, RelacionBitacora::DECLARACION, $cal);
ok(($rAC['error'] ?? '') === 'en_pareja_con_otro', 'B1 A-B luego A-C: bloqueado');
$rCA = ParejaEngine::formar($p, XC, XA, true, true, RelacionBitacora::DECLARACION, $cal);
ok(($rCA['error'] ?? '') === 'en_pareja_con_otro', 'B2 orden inverso C-A: bloqueado');
ok(ParejaEngine::estado($p, XA, XC) === ParejaEngine::NINGUNA, 'B3 sin relación residual A-C');
ok(count(RelacionBitacora::entre($p, XA, XC, RelacionBitacora::INICIO_PAREJA)) === 0, 'B4 sin hito INICIO_PAREJA A-C');
// B también bloqueada hacia tercero
$rBD = ParejaEngine::formar($p, XB, XD, true, true, RelacionBitacora::DECLARACION, $cal);
ok(($rBD['error'] ?? '') === 'en_pareja_con_otro', 'B5 B (emparejada con A) no forma con D');

// ============ C: pares disjuntos sí ============
$p = labPartida4();
$f1 = ParejaEngine::formar($p, XA, XB, true, true, RelacionBitacora::DECLARACION, $cal);
$f2 = ParejaEngine::formar($p, XC, XD, true, true, RelacionBitacora::DECLARACION, $cal);
ok(($f1['ok'] ?? false) === true && ($f2['ok'] ?? false) === true, 'C1 dos parejas disjuntas conviven');

// ============ D: ruptura libera; ex bloqueado si el otro se empareja ============
$p = labPartida4();
ParejaEngine::formar($p, XA, XB, true, true, RelacionBitacora::DECLARACION, $cal);
ParejaEngine::romper($p, XA, XB, 'test');
$fAC = ParejaEngine::formar($p, XA, XC, true, true, RelacionBitacora::DECLARACION, $cal);
ok(($fAC['ok'] ?? false) === true, 'D1 tras ruptura, A forma con C');
$rBA = ParejaEngine::formar($p, XB, XA, true, true, RelacionBitacora::VUELTA, $cal);
ok(($rBA['error'] ?? '') === 'en_pareja_con_otro', 'D2 ex B no reformar con A ya emparejada con C');

// ============ E: reconciliación del mismo par ============
$p = labPartida4();
ParejaEngine::formar($p, XA, XB, true, true, RelacionBitacora::DECLARACION, $cal);
ParejaEngine::romper($p, XA, XB, 'test');
$rec = ParejaEngine::reconciliar($p, XA, XB, true, true, $cal);
ok(($rec['ok'] ?? false) === true && ($rec['vuelta'] ?? false) === true, 'E1 reconciliar mismo par ok (vuelta)');
ok(ParejaEngine::estado($p, XA, XB) === ParejaEngine::PAREJA, 'E2 estado pareja tras vuelta');

// ============ F: gates de tercero en señal ============
$p = labPartida4();
senalMutua($p, XA, XC);
ParejaEngine::formar($p, XA, XB, true, true, RelacionBitacora::DECLARACION, $cal);
ok(SenalRomantica::desbloqueaPrimeraCita($p, XA, XC, $cal) === false, 'F1 desbloqueaPrimeraCita A-C false con A emparejada');
ok(SenalRomantica::desbloqueaPrimeraCita($p, XC, XA, $cal) === false, 'F2 simétrico C-A false');
RelacionEngine::upsertRomance($p, XA, XC, []);
// Limpia avisos previos (el setup ya emitió vía setRomanceHacia) para probar el gate en frío.
$relPre = RelacionEngine::obtenerEntre($p, XA, XC)['romance'];
if (isset($relPre['avisos_senal'])) {
    unset($relPre['avisos_senal']);
    RelacionEngine::persistirRomance($p, $relPre);
}
SenalRomantica::avisarSiAplica($p, XC, XA, $cal);
$despues = RelacionEngine::obtenerEntre($p, XA, XC)['romance']['avisos_senal'] ?? [];
ok(!isset($despues[XC . '>' . XA]), 'F3 sin aviso de señal hacia emparejada');
ParejaEngine::romper($p, XA, XB, 'test');
ok(SenalRomantica::desbloqueaPrimeraCita($p, XA, XC, $cal) === true, 'F4 tras ruptura vuelve a desbloquear');

// ============ G: iniciativa gated sin RNG ============
$p = labPartida4();
senalMutua($p, XA, XC);
ParejaEngine::formar($p, XA, XB, true, true, RelacionBitacora::DECLARACION, $cal);
$st0 = 777;
$p['rng']['state'] = $st0;
$rG = IniciativaRomantica::intentarPrimeraCita($p, XC, XA, $cal);
ok(($rG['resultado'] ?? '') === 'gate_en_pareja_con_otro', 'G1 iniciativa A<-C: gate_en_pareja_con_otro');
ok((int) $p['rng']['state'] === $st0, 'G2 gates puros: cero draws RNG');
$nEnc = count(array_filter($p['encuentros'], static fn ($e) => ($e['tipo'] ?? '') !== 'quedar'));
ok($nEnc === 0, 'G3 ningún encuentro creado');

// ============ H: INC-1 cupo Celestine ============
$p = labPartida4();
senalMutua($p, XA, XB);
$p['celeste']['intervenciones_organizadas_max_dia'] = 0;
$stOk = -1;
for ($s = 11; $s <= 4000; $s += 17) {
    $q = labPartida4();
    senalMutua($q, XA, XB);
    $q['celeste']['intervenciones_organizadas_max_dia'] = 0;
    $q['rng']['state'] = $s;
    $rr = IniciativaRomantica::intentarPrimeraCita($q, XA, XB, $cal);
    if (str_starts_with((string) ($rr['resultado'] ?? ''), 'primera_cita_agendada')) {
        $stOk = $s;
        break;
    }
}
ok($stOk > 0, 'H1 existe estado determinista que agenda primera cita');
if ($stOk > 0) {
    $p['rng']['state'] = $stOk;
    $rH = IniciativaRomantica::intentarPrimeraCita($p, XA, XB, $cal);
    ok(str_starts_with((string) ($rH['resultado'] ?? ''), 'primera_cita_agendada'), 'H2 agendada con cupo Celestine AGOTADO (max_dia=0)');
    $pc = null;
    foreach ($p['encuentros'] as $e) {
        if (($e['tipo'] ?? '') === 'primera_cita') {
            $pc = $e;
        }
    }
    ok($pc !== null && ($pc['intencion'] ?? '') === 'autonomo_npc', 'H3 intencion=autonomo_npc');
}

// ============ I: regresión hito_sin_ambos_si ============
$p = labPartida4();
$rI = ParejaEngine::formar($p, XA, XB, false, true, RelacionBitacora::DECLARACION, $cal);
ok(($rI['error'] ?? '') === 'hito_sin_ambos_si', 'I1 rechazo individual sigue canónico');
ok(ParejaEngine::estado($p, XA, XB) === ParejaEngine::NINGUNA, 'I2 sin formación');
ok(count(RelacionBitacora::entre($p, XA, XB, RelacionBitacora::DECLARACION)) === 1, 'I3 hito DECLARACION registrado con resultado');

echo $fail === 0 ? "\nOK fase2c_exclusividad\n" : "\nFAIL fase2c_exclusividad ($fail)\n";
exit($fail === 0 ? 0 : 1);
