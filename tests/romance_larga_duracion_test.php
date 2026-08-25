<?php
declare(strict_types=1);

// ROMANCE_CIERRE · SIMULACIÓN LARGA con INVARIANTES (matriz #30).
//
// Pueblo de 6 vecinos · 90 días · avance horario canónico (vida autónoma +
// resolución de encuentros + cierre diario con el evaluador de parejas).
// Flags R2-R8 ON con probabilidades DE PRODUCCIÓN (sin tuning).
//
// Invariantes en d30/d60/d90:
//   I1 Exclusividad global: ningún vecino con >1 pareja activa
//   I2 Sin citas recreativas programadas para parejas EN CRISIS
//   I3 Anti-cascada: ≤ max_hitos_por_dia hitos románticos por día de pueblo
//   I4 Sin HORA_PASADA: nada 'programado' empieza >24 h en el pasado
//   I5 Save siempre re-encodable y RNG con estado entero válido

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\IniciativaPareja;
use AquiHayTema\Engine\MotorVidaDiaria;
use AquiHayTema\Engine\PersistenciaCaps;
use AquiHayTema\Engine\PoblacionV3;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionDesgaste;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SchemaFields;
use AquiHayTema\Engine\SchemaMigrator;
use AquiHayTema\Engine\ResidenteOperations;
use AquiHayTema\Engine\PartidaSchema;

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

$cal = CalibracionConfig::load($root);
$cal['romance_autonomo'] = array_merge($cal['romance_autonomo'] ?? [], [
    'declaracion_activa' => true,
    'pareja_activa' => true,
    'vida_pareja_activa' => true,
    'crisis_activa' => true,
    'ruptura_activa' => true,
    'vuelta_activa' => true,
]);

$p = PartidaSchema::nueva($root, 'juego_v1', 'romcierre-larga');
$catalog = new Catalog($root);
$config = $catalog->loadConfigPrevalidada('juego_v1');
$config['poblacion_v3'] = ['iniciales_aleatorios' => 6];
unset($config['residentes_iniciales'], $config['parentesco'], $config['tutorial_primeros_pasos'], $config['tutorial_bucle_1'], $config['tutorial_objetivo_residentes']);
$opsRes = new ResidenteOperations($catalog, null);
PoblacionV3::incorporarIniciales($p, $config, $root, $opsRes);
FeatureConfig::mergeIntoPartida($p, $root);
PersistenciaCaps::mergeIntoPartida($p, $root);
SchemaFields::ensure($p);
$p = SchemaMigrator::migrate($p);

$rng = RngService::fromPartida($p);
$capHitos = 1;

function invariantes(array &$p, int $diaCheck, int $cap): array
{
    global $fail;
    $viol = [];
    // I1 exclusividad
    $conteo = [];
    foreach (($p['relaciones_romanticas'] ?? []) as $rel) {
        if (!is_array($rel)) {
            continue;
        }
        $est = (string) ($rel['estado_pareja'] ?? '');
        if ($est !== ParejaEngine::PAREJA && $est !== ParejaEngine::CRISIS) {
            continue;
        }
        foreach ([$rel['persona_a'], $rel['persona_b']] as $rid) {
            $rid = (string) $rid;
            $conteo[$rid] = ($conteo[$rid] ?? 0) + 1;
            if ($conteo[$rid] > 1) {
                $viol[] = "I1:$rid";
            }
        }
    }
    // I2 sin citas para parejas en crisis
    $now = ((int) $p['reloj']['dia_pueblo']) * 24 + (int) $p['reloj']['hora_actual'];
    foreach (($p['encuentros'] ?? []) as $e) {
        if (!is_array($e) || ($e['estado'] ?? '') !== 'programado') {
            continue;
        }
        $tipoE = (string) ($e['tipo'] ?? '');
        if ($tipoE !== 'cita' && $tipoE !== 'primera_cita') {
            continue;
        }
        $parts = is_array($e['participantes'] ?? null) ? $e['participantes'] : [0 => '', 1 => ''];
        if (count($parts) === 2) {
            $estPar = ParejaEngine::estado($p, (string) $parts[0], (string) $parts[1]);
            if ($estPar === ParejaEngine::CRISIS) {
                $viol[] = 'I2:' . ($e['id'] ?? '?');
            }
        }
        // I4 HORA_PASADA
        $ini = ((int) ($e['dia'] ?? 0)) * 24 + (int) ($e['hora'] ?? 0);
        if ($now - $ini > 24) {
            $viol[] = 'I4:' . ($e['id'] ?? '?');
        }
    }
    // I3 cap hitos por día
    $porDia = [];
    $tiposCap = [
        RelacionBitacora::DECLARACION,
        RelacionBitacora::INICIO_PAREJA,
        RelacionBitacora::VUELTA,
        RelacionBitacora::CRISIS,
        RelacionBitacora::RUPTURA,
        RelacionBitacora::RECONCILIACION,
    ];
    foreach (($p['bitacora_relaciones'] ?? []) as $h) {
        if (!is_array($h) || !in_array((string) ($h['tipo'] ?? ''), $tiposCap, true)) {
            continue;
        }
        $d = (int) ($h['fecha']['dia'] ?? 0);
        $porDia[$d] = ($porDia[$d] ?? 0) + 1;
        if ($porDia[$d] > $cap) {
            $viol[] = "I3:d$d";
        }
    }
    // I5 encodable
    $enc = json_encode($p);
    if ($enc === false) {
        $viol[] = 'I5:json';
    }
    return $viol;
}

$checkpoints = [30, 60, 90];
$vioTotal = [];
$nParejasFormadasPrev = 0;
for ($dia = 1; $dia <= 90; $dia++) {
    for ($hora = 0; $hora < 24; $hora++) {
        $p['reloj']['dia_pueblo'] = $dia;
        $p['reloj']['hora_actual'] = $hora;
        if ($hora >= 9 && $hora <= 22) {
            MotorVidaDiaria::tickHora($p, $catalog, $cal, $rng, null);
            $rng->persistToPartida($p);
        }
        EncuentroLifecycle::sincronizarConReloj($p, null, $catalog);
    }
    // cierre del día
    RelacionDesgaste::alCerrarDia($p, $cal);
    IniciativaPareja::evaluarAlCerrarDia($p, $cal, null);
    if (in_array($dia, $checkpoints, true)) {
        $vio = invariantes($p, $dia, $capHitos);
        foreach ($vio as $v) {
            $vioTotal[] = "d$dia:$v";
        }
        $nParejas = count(array_filter(
            ($p['relaciones_romanticas'] ?? []),
            static fn ($r) => is_array($r) && in_array(($r['estado_pareja'] ?? ''), [ParejaEngine::PAREJA, ParejaEngine::CRISIS], true)
        ));
        echo "— d$dia: parejas_activas=$nParejas hitos=" . count($p['bitacora_relaciones'] ?? []) . " violencias=" . count($vio) . "\n";
    }
}
ok($vioTotal === [], 'INVARIANTES I1-I5 sin violaciones (' . implode(',', array_slice($vioTotal, 0, 6)) . ')');

// Liveness: la vida del pueblo se movió
$conocidos = 0;
foreach (($p['relaciones_sociales'] ?? []) as $s) {
    if (!empty($s['conocidos'])) {
        $conocidos++;
    }
}
ok($conocidos > 0, "Liveness: hay relaciones sociales ($conocidos)");
ok(count(($p['bitacora_relaciones'] ?? [])) > 0, 'Liveness: hay hitos relacionales');

echo $fail === 0 ? "\nOK romance_larga_duracion\n" : "\nFAIL romance_larga_duracion ($fail)\n";
exit($fail === 0 ? 0 : 1);
