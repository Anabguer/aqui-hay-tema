<?php
declare(strict_types=1);

/**
 * ROMANCE_CIERRE · Banco de simulación de balance (plan §17).
 * Patrón: dev/_sim_funnel_romance.php (embudo F1) — headless, avance horario
 * canónico, cero intervención de Celestine/jugador, sondas SimFunnelProbe.
 *
 * Ruta autónoma PURA: NO se activa lab_vida_activa (el hueco sigue limitado
 * por familias_en_play de producción). Los flags R2-R8 se encienden vía cal.
 *
 * Uso:
 *   php dev/_sim_funnel_romance_cierre.php --pop=8 --dias=90 --seed=11 [--flags=off] [--json]
 */

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
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\SenalRomantica;
use AquiHayTema\Engine\IniciativaRomantica;

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--') === 0 && strpos($arg, '=') !== false) {
        [$k, $v] = explode('=', substr($arg, 2), 2);
        $opts[$k] = $v;
    } elseif (strpos($arg, '--') === 0) {
        $opts[substr($arg, 2)] = true;
    }
}
$pop = max(3, min(32, (int) ($opts['pop'] ?? 8)));
$dias = max(1, min(365, (int) ($opts['dias'] ?? 90)));
$seed = (string) ($opts['seed'] ?? '11');
$flagsOn = (($opts['flags'] ?? 'on') !== 'off');
$jsonOut = isset($opts['json']);

// Determinismo del BANCO: la generación de población/catálogos usa azar de
// proceso PHP en algunos puntos (preexistente); se siembra por seed para que
// cada corrida sea reproducible. El motor romántico usa SIEMPRE RngService.
mt_srand(crc32('romcierre|' . $seed));
srand(crc32('romcierre|' . $seed));

$root = dirname(__DIR__);
$GLOBALS['__root'] = $root;
DomainBootstrap::boot();

$cal = CalibracionConfig::load($root);
if ($flagsOn) {
    $cal['romance_autonomo'] = array_merge($cal['romance_autonomo'] ?? [], [
        'declaracion_activa' => true,
        'pareja_activa' => true,
        'vida_pareja_activa' => true,
        'crisis_activa' => true,
        'ruptura_activa' => true,
        'vuelta_activa' => true,
    ]);
}

$p = PartidaSchema::nueva($root, 'juego_v1', 'romcierre-sim');
$catalog = new Catalog($root);
$config = $catalog->loadConfigPrevalidada('juego_v1');
$config['poblacion_v3'] = ['iniciales_aleatorios' => $pop];
unset($config['residentes_iniciales'], $config['parentesco'], $config['tutorial_primeros_pasos'], $config['tutorial_bucle_1'], $config['tutorial_objetivo_residentes']);
$opsRes = new ResidenteOperations($catalog, null);
PoblacionV3::incorporarIniciales($p, $config, $root, $opsRes);
FeatureConfig::mergeIntoPartida($p, $root);
PersistenciaCaps::mergeIntoPartida($p, $root);
SchemaFields::ensure($p);
$p['meta']['sim_funnel'] = true;
$p = SchemaMigrator::migrate($p);

$rng = RngService::fromPartida($p);

$concentracionMax = 0;
function snapshotConcentracion(array $p): int
{
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
        }
    }
    return $conteo === [] ? 0 : max($conteo);
}

for ($dia = 1; $dia <= $dias; $dia++) {
    for ($hora = 0; $hora < 24; $hora++) {
        $p['reloj']['dia_pueblo'] = $dia;
        $p['reloj']['hora_actual'] = $hora;
        if ($hora >= 9 && $hora <= 22) {
            MotorVidaDiaria::tickHora($p, $catalog, $cal, $rng, null);
            $rng->persistToPartida($p);
        }
        $resueltos = EncuentroLifecycle::sincronizarConReloj($p, null, $catalog);
        // Producción con flags ON lee la calibración DEL FICHERO (los flags ya
        // viven ahí). El banco inyecta flags por runtime, así que replica esa
        // semántica re-registrando la continuidad con la cal flaggeada
        // (idempotente por par: sustituye el marcador).
        foreach (($resueltos['encuentros'] ?? []) as $encRes) {
            $tipoRes = (string) ($encRes['tipo'] ?? '');
            if ($tipoRes !== 'primera_cita' && $tipoRes !== 'cita') {
                continue;
            }
            $partsRes = is_array($encRes['participantes'] ?? null) ? $encRes['participantes'] : [];
            if (count($partsRes) !== 2) {
                continue;
            }
            $rank = ['muy_mal' => 0, 'mal' => 1, 'normal' => 2, 'bien' => 3, 'muy_bien' => 4];
            $peor = '';
            foreach ($partsRes as $rid) {
                $exp = (string) (($encRes['resultado']['por_participante'] ?? [])[$rid]['resultado'] ?? '');
                if ($exp !== '' && ($peor === '' || ($rank[$exp] ?? 2) < ($rank[$peor] ?? 2))) {
                    $peor = $exp;
                }
            }
            \AquiHayTema\Engine\IniciativaRomantica::registrarContinuidadPostCita(
                $p,
                (string) $partsRes[0],
                (string) $partsRes[1],
                $peor !== '' ? $peor : null,
                $cal
            );
            if (isset($opts['debug'])) {
                $pa = (string) $partsRes[0];
                $pb = (string) $partsRes[1];
                $nC = 0;
                foreach (($p['encuentros'] ?? []) as $e2) {
                    if (is_array($e2) && ($e2['estado'] ?? '') === 'terminado'
                        && in_array(($e2['tipo'] ?? ''), ['primera_cita', 'cita'], true)
                        && in_array($pa, $e2['participantes'] ?? [], true)
                        && in_array($pb, $e2['participantes'] ?? [], true)) {
                        $nC++;
                    }
                }
                $rAB = RelacionEngine::romanceHacia($p, $pa, $pb);
                $rBA = RelacionEngine::romanceHacia($p, $pb, $pa);
                $sAB = !empty(SenalRomantica::desdeHacia($p, $pa, $pb, $cal)['ok']);
                $sBA = !empty(SenalRomantica::desdeHacia($p, $pb, $pa, $cal)['ok']);
                echo "EVT d$dia{$hora}h $pa>$pb tipo=$tipoRes peor=$peor n=$nC rom=" .
                    var_export($rAB, true) . '/' . var_export($rBA, true) .
                    " senal=" . (int) $sAB . (int) $sBA . "\n";
            }
        }
    }
    RelacionDesgaste::alCerrarDia($p, $cal);
    IniciativaPareja::evaluarAlCerrarDia($p, $cal, null);
    $concentracionMax = max($concentracionMax, snapshotConcentracion($p));
}

// ---------- métricas ----------
$hitosPorTipo = [];
foreach (($p['bitacora_relaciones'] ?? []) as $h) {
    $t = (string) ($h['tipo'] ?? '?');
    $hitosPorTipo[$t] = ($hitosPorTipo[$t] ?? 0) + 1;
}
$counts = $p['sim_funnel_counts'] ?? [];
$decl = is_array($counts['declaracion'] ?? null) ? $counts['declaracion'] : [];

$duracionesDias = [];
$vueltasHistorial = 0;
foreach (($p['relaciones_romanticas'] ?? []) as $rel) {
    if (!is_array($rel)) {
        continue;
    }
    foreach (($rel['historial_parejas'] ?? []) as $hp) {
        if (!is_array($hp)) {
            continue;
        }
        if (!empty($hp['vuelta'])) {
            $vueltasHistorial++;
        }
        if (!is_array($hp['fin'] ?? null)) {
            continue;
        }
        $iniAbs = ((int) ($hp['inicio']['dia'] ?? 0)) * 24 + (int) ($hp['inicio']['hora'] ?? 0);
        $finAbs = ((int) ($hp['fin']['dia'] ?? 0)) * 24 + (int) ($hp['fin']['hora'] ?? 0);
        if ($finAbs > $iniAbs) {
            $duracionesDias[] = ($finAbs - $iniAbs) / 24.0;
        }
    }
}

// cadencia mínima entre hitos consecutivos del mismo par
$porPar = [];
foreach (($p['bitacora_relaciones'] ?? []) as $h) {
    $t = (string) ($h['tipo'] ?? '');
    if (!in_array($t, [
        RelacionBitacora::DECLARACION,
        RelacionBitacora::INICIO_PAREJA,
        RelacionBitacora::CRISIS,
        RelacionBitacora::RUPTURA,
        RelacionBitacora::RECONCILIACION,
        RelacionBitacora::VUELTA,
    ], true)) {
        continue;
    }
    $parKey = implode('>', (array) ($h['par'] ?? []));
    $abs = ((int) ($h['fecha']['dia'] ?? 0)) * 24 + (int) ($h['fecha']['hora'] ?? 0);
    $porPar[$parKey][] = $abs;
}
$cadenciaMinHoras = null;
foreach ($porPar as $times) {
    sort($times);
    for ($i = 1; $i < count($times); $i++) {
        $gapH = $times[$i] - $times[$i - 1];
        if ($cadenciaMinHoras === null || $gapH < $cadenciaMinHoras) {
            $cadenciaMinHoras = $gapH;
        }
    }
}

$logIR = is_array($p['iniciativa_romantica_log'] ?? null) ? $p['iniciativa_romantica_log'] : [];
$triangulosBloqueados = 0;
$exesBloqueados = 0;
foreach ($logIR as $row) {
    $res = (string) ($row['resultado'] ?? '');
    if (strpos($res, 'en_pareja_con_otro') !== false) {
        $triangulosBloqueados++;
    }
    if (strpos($res, 'ex_sin_vuelta') !== false) {
        $exesBloqueados++;
    }
}

$nConocidos = 0;
foreach (($p['relaciones_sociales'] ?? []) as $s) {
    if (!empty($s['conocidos'])) {
        $nConocidos++;
    }
}
$aislados = 0;
foreach (($p['residentes'] ?? []) as $rid => $r) {
    $tieneContacto = false;
    foreach (($p['relaciones_sociales'] ?? []) as $s) {
        if (!empty($s['conocidos'])
            && in_array((string) $rid, [(string) ($s['persona_a'] ?? ''), (string) ($s['persona_b'] ?? '')], true)
            && (((int) ($s['a_hacia_b']['valor'] ?? 0)) > 0 || ((int) ($s['b_hacia_a']['valor'] ?? 0)) > 0)) {
            $tieneContacto = true;
            break;
        }
    }
    if (!$tieneContacto) {
        $aislados++;
    }
}

$m = [
    'pop' => $pop,
    'dias' => $dias,
    'seed' => $seed,
    'flags' => $flagsOn ? 'on' : 'off',
    'flechazos' => (int) ($hitosPorTipo[RelacionBitacora::FLECHAZO] ?? 0),
    'primeras_citas' => (int) ($hitosPorTipo[RelacionBitacora::PRIMERA_CITA] ?? 0),
    'declaraciones_ok' => (int) ($decl['declaracion_ok'] ?? 0),
    'declaraciones_rechazadas' => (int) ($decl['declaracion_rechazada'] ?? 0),
    'parejas_formadas' => (int) ($hitosPorTipo[RelacionBitacora::INICIO_PAREJA] ?? 0),
    'crisis' => (int) ($hitosPorTipo[RelacionBitacora::CRISIS] ?? 0),
    'reparaciones' => (int) ($hitosPorTipo[RelacionBitacora::APOYO_IMPORTANTE] ?? 0),
    'rupturas' => (int) ($hitosPorTipo[RelacionBitacora::RUPTURA] ?? 0),
    'vueltas' => (int) ($hitosPorTipo[RelacionBitacora::VUELTA] ?? 0),
    'duracion_media_pareja_dias' => $duracionesDias === [] ? null : round(array_sum($duracionesDias) / count($duracionesDias), 1),
    'concentracion_max' => $concentracionMax,
    'triangulos_bloqueados' => $triangulosBloqueados,
    'exes_bloqueados' => $exesBloqueados,
    'cadencia_min_horas' => $cadenciaMinHoras,
    'aislados_sin_contacto' => $aislados,
    'pares_conocidos' => $nConocidos,
];

echo json_encode($m) . "\n";

if (isset($opts['debug'])) {
    $hist = [];
    foreach ($logIR as $row) {
        $res = (string) ($row['resultado'] ?? '');
        $hist[$res] = ($hist[$res] ?? 0) + 1;
    }
    arsort($hist);
    echo "LOG_HIST=" . json_encode($hist) . "\n";
    echo "MARKERS=" . json_encode($p['continuidad_romantica'] ?? []) . "\n";
    echo "PROBE_DECL=" . json_encode($counts['declaracion'] ?? []) . "\n";
    echo "PROBE_SENAL=" . json_encode($counts['senal'] ?? []) . "\n";
}
