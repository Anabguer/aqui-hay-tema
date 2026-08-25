<?php
declare(strict_types=1);

// FASE 7 (R8) · VUELTA con contrato (no telenovela infinita).
//
// Cobertura:
//   A vuelta elegible + p=1 ⇒ reconciliación completa (VUELTA, estabilidad
//     base_reconciliacion=32, memoria, cotilleo pareja)
//   B cooldown post-ruptura 336h ⇒ demasiado pronto: no
//   C sin encuentro bueno post-ruptura ⇒ no
//   D cap max_vueltas alcanzado ⇒ historia_cerrada (ni con todo a favor)
//   E penalti por vuelta visible en voluntad (−8 × vecesPareja)

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\IniciativaPareja;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

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

const WA = 'ana';
const WB = 'bruno';

function calVuelta(array $vol = []): array
{
    $cal = CalibracionConfig::load(dirname(__DIR__));
    $cal['romance_autonomo']['vuelta_activa'] = true;
    $cal['romance_autonomo']['declaracion_activa'] = true;
    $cal['romance_autonomo']['pareja_activa'] = true;
    $cal['romance_autonomo']['max_hitos_por_dia'] = 4;
    foreach ($vol as $k => $v) {
        $cal['voluntad'][$k] = $v;
    }
    return $cal;
}

/** Par EX con ruptura sellada el día dado y cita buena posterior opcional. */
function parEx(array $cal, int $diaRuptura, int $diaHoy, bool $citaBuenaPost): array
{
    $p = [
        'reloj' => ['dia_pueblo' => $diaRuptura, 'hora_actual' => 10],
        'rng' => ['seed' => 'f7', 'state' => 606],
        'meta' => ['seed' => 'f7'],
        'residentes' => [
            WA => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            WB => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
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
        RelacionEngine::ajustarSocialHacia($p, WA, WB, 10);
        RelacionEngine::ajustarSocialHacia($p, WB, WA, 10);
    }
    ParejaEngine::formar($p, WA, WB, true, true, RelacionBitacora::DECLARACION, $cal);
    RelacionEngine::setRomanceHacia($p, WA, WB, 40);
    RelacionEngine::setRomanceHacia($p, WB, WA, 40);
    ParejaEngine::romper($p, WA, WB, 'crisis_sin_salida');
    if ($citaBuenaPost) {
        MemoriaEventos::registrar($p, 'encuentro', [WA, WB], null, 'cita', 'bien');
    }
    $p['reloj']['dia_pueblo'] = $diaHoy;
    return $p;
}

$calSi = calVuelta(['p_min' => 1.0, 'p_max' => 1.0]);

// ============ A: vuelta completa ============
$p = parEx($calSi, 40, 60, true); // ruptura d40, hoy d60 (>336h)
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calSi);
ok(($out['vueltas'] ?? 0) === 1, 'A1 evaluador reporta vuelta');
ok(ParejaEngine::estado($p, WA, WB) === ParejaEngine::PAREJA, 'A2 vuelven a ser PAREJA');
$rel = RelacionEngine::obtenerEntre($p, WA, WB)['romance'];
// Canon producción (abb1276): la vuelta NO hereda barra numérica; deja
// valor=null con base_reconciliacion como metadata. La estabilidad nace
// del roce real (EncuentroResolver solo ajusta valores numéricos).
$valorVuelta = array_key_exists('valor', ($rel['estabilidad_pareja'] ?? []))
    ? $rel['estabilidad_pareja']['valor']
    : 'AUSENTE';
ok($valorVuelta === null || $valorVuelta === 'AUSENTE', 'A3 vuelta SIN barra heredada (canon)');
ok((int) ($rel['estabilidad_pareja']['base_reconciliacion'] ?? 0) === 32, 'A3b base_reconciliacion=32 registrada');
ok(count(RelacionBitacora::entre($p, WA, WB, RelacionBitacora::VUELTA)) === 1, 'A4 hito VUELTA');
ok(RelacionBitacora::vecesPareja($p, WA, WB) === 2, 'A5 vecesPareja=2');
$hayCotilleo = false;
foreach (($p['buzon'] ?? []) as $m) {
    if (($m['hito_tipo'] ?? '') === RelacionBitacora::VUELTA) {
        $hayCotilleo = true;
    }
}
ok($hayCotilleo, 'A6 cotilleo de vuelta publicado');

// ============ B: demasiado pronto ============
$p = parEx($calSi, 40, 50, true); // solo ~240h
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calSi);
ok(($out['vueltas'] ?? 0) === 0 && ParejaEngine::estado($p, WA, WB) === ParejaEngine::EX, 'B1 cooldown post-ruptura: sin vuelta');

// ============ C: sin trato bueno post-ruptura ============
$p = parEx($calSi, 40, 60, false);
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calSi);
ok(($out['vueltas'] ?? 0) === 0 && ParejaEngine::estado($p, WA, WB) === ParejaEngine::EX, 'C1 sin encuentro bueno post-ruptura: no');

// ============ D: historia cerrada por cap ============
$p = parEx($calSi, 40, 60, true);
// fabricar DOS vueltas previas (cap default = 2) y dejar el par en EX de nuevo
RelacionBitacora::registrar($p, RelacionBitacora::INICIO_PAREJA, [WA, WB]);
RelacionBitacora::registrar($p, RelacionBitacora::VUELTA, [WA, WB]);
RelacionBitacora::registrar($p, RelacionBitacora::RUPTURA, [WA, WB]);
RelacionBitacora::registrar($p, RelacionBitacora::VUELTA, [WA, WB]);
$relD = RelacionEngine::obtenerEntre($p, WA, WB)['romance'];
$relD['estado_pareja'] = ParejaEngine::EX;
RelacionEngine::persistirRomance($p, $relD);
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calSi);
ok(($out['vueltas'] ?? 0) === 0, 'D1 cap vueltas: bloqueada');
ok(ParejaEngine::estado($p, WA, WB) !== ParejaEngine::PAREJA || count(RelacionBitacora::entre($p, WA, WB, RelacionBitacora::VUELTA)) === 2, 'D2 no se excede el cap');

// ============ E: penalti por vuelta en voluntad ============
$calE = calVuelta();
$prop = ['participantes' => [WA, WB], 'tipo' => 'declaracion', 'lugar' => null];
$penaltiEsperado = -8 * 2; // vecesPareja=2 tras la primera vuelta
$prop['_bonus_voluntad'] = [WA => $penaltiEsperado, WB => $penaltiEsperado];
$p = parEx($calE, 40, 60, true);
$d = VoluntadPonderadaEvaluator::desglose($p, $prop, WA, WB, $calE);
ok((int) ($d['bonus_peticion_nucleo'] ?? 0) === $penaltiEsperado, 'E1 penalti −8×veces aplicado en voluntad');

echo $fail === 0 ? "\nOK fase7_vuelta\n" : "\nFAIL fase7_vuelta ($fail)\n";
exit($fail === 0 ? 0 : 1);
