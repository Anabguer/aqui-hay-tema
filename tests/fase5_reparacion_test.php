<?php
declare(strict_types=1);

// FASE 5 (R6) · REPARACIÓN de crisis.
//
// Cobertura:
//   A sin encuentro bueno en crisis ⇒ no hay intento (no se repara sola)
//   B conflicto alto ⇒ no hay intento aunque haya cita buena
//   C intento con éxito forzado: vuelve a PAREJA, estabilidad parcial,
//     hito APOYO_IMPORTANTE, reset fallos, memoria
//   D intento fallido forzado: sigue CRISIS, fallos++, gap 24h anti-spam
//   E dos fallos + ruptura OFF ⇒ NO ruptura (sigue en crisis)

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\IniciativaPareja;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;

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

const RA = 'ana';
const RB = 'bruno';

function calRep(array $vol = []): array
{
    $cal = CalibracionConfig::load(dirname(__DIR__));
    $cal['romance_autonomo']['crisis_activa'] = true;
    $cal['romance_autonomo']['max_hitos_por_dia'] = 3;
    foreach ($vol as $k => $v) {
        $cal['voluntad'][$k] = $v;
    }
    return $cal;
}

function labPareja(array $cal, int $estabilidad = 30): array
{
    $p = [
        'reloj' => ['dia_pueblo' => 50, 'hora_actual' => 10],
        'rng' => ['seed' => 'f5', 'state' => 404],
        'meta' => ['seed' => 'f5'],
        'residentes' => [
            RA => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            RB => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
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
        RelacionEngine::ajustarSocialHacia($p, RA, RB, 10);
        RelacionEngine::ajustarSocialHacia($p, RB, RA, 10);
    }
    ParejaEngine::formar($p, RA, RB, true, true, RelacionBitacora::DECLARACION, $cal);
    $rel = RelacionEngine::obtenerEntre($p, RA, RB)['romance'];
    $rel['estabilidad_pareja']['valor'] = $estabilidad;
    RelacionEngine::persistirRomance($p, $rel);
    ParejaEngine::crisis($p, RA, RB);
    return $p;
}

/** Cita buena DURANTE la crisis (memoria con fecha posterior a crisis_desde). */
function citaBuenaEnCrisis(array &$p): void
{
    MemoriaEventos::registrar($p, 'encuentro', [RA, RB], null, 'cita', 'bien');
}

$calSi = calRep(['p_min' => 1.0, 'p_max' => 1.0]);
$calNo = calRep(['p_min' => 0.0, 'p_max' => 0.0]);

// ============ A: sin cita buena ⇒ no se repara sola ============
$p = labPareja($calSi);
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calSi);
ok(($out['reparaciones_ok'] ?? 0) === 0 && ParejaEngine::estado($p, RA, RB) === ParejaEngine::CRISIS, 'A1 sin encuentro bueno: CRISIS persiste');

// ============ B: conflicto alto bloquea el intento ============
$p = labPareja($calSi);
citaBuenaEnCrisis($p);
RelacionEngine::upsertConflicto($p, RA, RB, 9, 'roce', 'test');
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calSi);
ok(($out['reparaciones_ok'] ?? 0) === 0 && ParejaEngine::estado($p, RA, RB) === ParejaEngine::CRISIS, 'B conflicto ≥ min: sin reparación');

// ============ C: éxito forzado ============
$p = labPareja($calSi);
citaBuenaEnCrisis($p);
RelacionEngine::setRomanceHacia($p, RA, RB, 40);
RelacionEngine::setRomanceHacia($p, RB, RA, 40);
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calSi);
ok(($out['reparaciones_ok'] ?? 0) === 1, 'C1 evaluador reporta reparación');
ok(ParejaEngine::estado($p, RA, RB) === ParejaEngine::PAREJA, 'C2 vuelve a PAREJA');
$rel = RelacionEngine::obtenerEntre($p, RA, RB)['romance'];
ok((int) ($rel['estabilidad_pareja']['valor'] ?? 0) === 50, 'C3 estabilidad 30+20=50 (parcial, no mágica)');
ok($rel['crisis_desde'] === null && (int) ($rel['fallos_reparacion'] ?? -1) === 0, 'C4 carril de crisis reseteado');
ok(count(RelacionBitacora::entre($p, RA, RB, RelacionBitacora::APOYO_IMPORTANTE)) === 1, 'C5 hito APOYO_IMPORTANTE');

// ============ D: fallo determinista + gap 24h ============
$p = labPareja($calNo);
citaBuenaEnCrisis($p);
$out = IniciativaPareja::evaluarAlCerrarDia($p, $calNo);
ok(($out['reparaciones_fail'] ?? 0) === 1 && ParejaEngine::estado($p, RA, RB) === ParejaEngine::CRISIS, 'D1 fallo deja en crisis');
$rel = RelacionEngine::obtenerEntre($p, RA, RB)['romance'];
ok((int) ($rel['fallos_reparacion'] ?? 0) === 1, 'D2 fallo registrado en memoria del par');
$out2 = IniciativaPareja::evaluarAlCerrarDia($p, $calNo); // mismo tick/día
ok(($out2['reparaciones_fail'] ?? 0) === 0, 'D3 gap 24h: sin re-intento inmediato');

// ============ E: dos fallos + ruptura OFF ⇒ sigue crisis ============
$p['reloj']['hora_actual'] += 24; // vencer gap manualmente
$rel = RelacionEngine::obtenerEntre($p, RA, RB)['romance'];
$rel['ultimo_intento_reparacion'] = null; // permitir reintento ya
RelacionEngine::persistirRomance($p, $rel);
$out3 = IniciativaPareja::evaluarAlCerrarDia($p, $calNo);
ok(($out3['reparaciones_fail'] ?? 0) === 1, 'E1 segundo fallo contado');
$rel = RelacionEngine::obtenerEntre($p, RA, RB)['romance'];
ok((int) ($rel['fallos_reparacion'] ?? 0) === 2, 'E2 fallos=2');
ok(ParejaEngine::estado($p, RA, RB) === ParejaEngine::CRISIS, 'E3 ruptura OFF ⇒ NUNCA ruptura automática');

echo $fail === 0 ? "\nOK fase5_reparacion\n" : "\nFAIL fase5_reparacion ($fail)\n";
exit($fail === 0 ? 0 : 1);
