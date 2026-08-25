<?php
declare(strict_types=1);

// PRE FASE 2A · Gate familias_en_play — CONTRATO EFECTIVO en PLAY.
//
// Reproduce y cierra el fallo heredado de Fase 1: el filtro de
// familias_en_play dependía además del flag npc_autonomy_enabled (global a
// false), quedaba MUERTO y permitía que romance_hito/pareja
// (declaracion/crisis_pareja/ruptura/reconciliacion) nacieran en play por el
// hueco de vida, contra lo documentado en el despliegue de Fase 1.
//
// Cobertura:
//   U) Unidad: MotorVidaDiaria::filtrarFamiliasEnPlay respeta el contrato.
//   P) PLAY determinista: avanzando horas con pipeline canónico,
//      romance_hito/pareja NUNCA nacen; romance_accion SÍ puede ejecutarse;
//      trabajo/ocio/romance/consejo conservan comportamiento.
//   L) LAB (lab_vida_activa): sin filtro — declaracion sigue alcanzable
//      (la exclusión en play es contrato, no imposibilidad).

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EmotionalStateService;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\MotorVidaDiaria;
use AquiHayTema\Engine\PersistenciaCaps;
use AquiHayTema\Engine\PartidaSchema;
use AquiHayTema\Engine\PoblacionV3;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelojOperations;
use AquiHayTema\Engine\ResidenteOperations;
use AquiHayTema\Engine\SchemaFields;
use AquiHayTema\Engine\SchemaMigrator;
use AquiHayTema\Engine\VisualPackStore;

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
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$GLOBALS['__catalog_store'] = $catalog->store();

const EVENTOS_PROHIBIDOS_PLAY = ['declaracion', 'crisis_pareja', 'ruptura', 'reconciliacion'];
const FAMILIAS_PROHIBIDAS_PLAY = ['romance_hito', 'pareja'];
const HITOS_PROHIBIDOS_PLAY = ['declaracion', 'inicio_pareja', 'crisis', 'ruptura', 'reconciliacion', 'vuelta'];

/** Avanza 1 hora con el pipeline canónico completo (RelojOperations). */
function avanzarHora(array &$p): void
{
    static $ops = null;
    if ($ops === null) {
        $emociones = new EmotionalStateService(new VisualPackStore($GLOBALS['__root']), $GLOBALS['__catalog_store'], null);
        $ops = new RelojOperations($GLOBALS['__root'], null, $emociones);
    }
    $ops->avanzarPasoAPaso($p, 1);
}

/** Partida real mínima (2 residentes) con par conocido e interesado. */
function miniPartida(string $seed, bool $lab): array
{
    $configId = 'juego_v1';
    $p = PartidaSchema::nueva($GLOBALS['__root'], $configId, $seed);
    $catalog = new Catalog($GLOBALS['__root']);
    $config = $catalog->loadConfigPrevalidada($configId);
    $config['poblacion_v3'] = ['iniciales_aleatorios' => 2];
    unset($config['residentes_iniciales'], $config['parentesco'], $config['tutorial_primeros_pasos'], $config['tutorial_bucle_1'], $config['tutorial_objetivo_residentes']);
    $opsRes = new ResidenteOperations($catalog, null);
    PoblacionV3::incorporarIniciales($p, $config, $GLOBALS['__root'], $opsRes);
    FeatureConfig::mergeIntoPartida($p, $GLOBALS['__root']);
    PersistenciaCaps::mergeIntoPartida($p, $GLOBALS['__root']);
    SchemaFields::ensure($p);
    DomainBootstrap::boot();
    $p = SchemaMigrator::migrate($p);
    if ($lab) {
        $p['lab_vida_activa'] = true;
    }
    // Par elegible para romance_accion y (en lab) declaracion.
    $ids = array_values(array_keys($p['residentes']));
    if (count($ids) >= 2) {
        [$a, $b] = [(string) $ids[0], (string) $ids[1]];
        foreach ([[$a, $b], [$b, $a]] as [$x, $y]) {
            $resto = 30;
            while ($resto > 0) {
                $d = min(10, $resto);
                RelacionEngine::ajustarSocialHacia($p, $x, $y, $d);
                $resto -= $d;
            }
        }
        RelacionEngine::setRomanceHacia($p, $a, $b, 28);
    }
    return $p;
}

/** Trazas de una partida tras avanzar. */
function trazas(array $p): array
{
    $eventos = [];
    foreach (($p['acontecimientos_log'] ?? []) as $r) {
        if (is_array($r) && isset($r['id'])) {
            $eventos[(string) $r['id']] = true;
        }
    }
    $familias = [];
    foreach (($p['memoria_eventos'] ?? []) as $ev) {
        if (is_array($ev) && isset($ev['familia'])) {
            $familias[(string) $ev['familia']] = true;
        }
    }
    $hitos = [];
    foreach (($p['bitacora_relaciones'] ?? []) as $h) {
        if (is_array($h) && isset($h['tipo'])) {
            $hitos[(string) $h['tipo']] = true;
        }
    }
    return ['eventos' => $eventos, 'familias' => $familias, 'hitos' => $hitos];
}

// ==================== U) UNIDAD — contrato del filtro ====================
$items = [];
foreach (['trabajo', 'ocio', 'vida', 'romance', 'romance_accion', 'romance_hito', 'pareja', 'consejo'] as $f) {
    $items[] = ['id' => 'ev_' . $f, 'familia' => $f];
}
$familiasPlay = CalibracionConfig::get($cal, 'acontecimientos_dia.familias_en_play', null);
ok(is_array($familiasPlay)
    && in_array('romance_accion', $familiasPlay, true)
    && !in_array('romance_hito', $familiasPlay, true)
    && !in_array('pareja', $familiasPlay, true),
    'U0 calibración F1 intacta: romance_accion dentro; romance_hito/pareja fuera');

$fams = array_map(static fn ($i) => $i['familia'], MotorVidaDiaria::filtrarFamiliasEnPlay($items, $familiasPlay ?: [], false));
sort($fams);
ok($fams === ['consejo', 'ocio', 'romance', 'romance_accion', 'trabajo'], 'U1 PLAY: solo familias_en_play (sin romance_hito/pareja)');
ok(count(MotorVidaDiaria::filtrarFamiliasEnPlay($items, $familiasPlay ?: [], true)) === count($items), 'U2 LAB: catálogo completo, filtro OFF');
ok(count(MotorVidaDiaria::filtrarFamiliasEnPlay($items, [], false)) === count($items), 'U3 config ausente/vacía: legacy sin filtro');

// ==================== P) PLAY — prohibido nacer; permitidos viven ====================
$horasPorRun = 34;
$maxRunsNeg = 45;
$vistosEventosPermitidos = [];
$runConRomanceAccion = -1;
$huecoEjecutadoAlgunaVez = false;
$sucio = null;

for ($i = 0; $i < $maxRunsNeg; $i++) {
    $seed = 'pre-gate-p' . (11 + $i * 7);
    $p = miniPartida($seed, false);
    for ($h = 0; $h < $horasPorRun; $h++) {
        avanzarHora($p);
    }
    $t = trazas($p);
    foreach (EVENTOS_PROHIBIDOS_PLAY as $evId) {
        if (isset($t['eventos'][$evId])) {
            $sucio = "evento $evId nació en play (seed=$seed)";
            break 2;
        }
    }
    foreach (FAMILIAS_PROHIBIDAS_PLAY as $fam) {
        if (isset($t['familias'][$fam])) {
            $sucio = "memoria familia $fam nació en play (seed=$seed)";
            break 2;
        }
    }
    foreach (HITOS_PROHIBIDOS_PLAY as $hit) {
        if (isset($t['hitos'][$hit])) {
            $sucio = "hito $hit nació en play (seed=$seed)";
            break 2;
        }
    }
    foreach (['trabajo', 'ocio', 'romance', 'romance_accion', 'consejo'] as $famOk) {
        if (isset($t['familias'][$famOk])) {
            $vistosEventosPermitidos[$famOk] = true;
        }
    }
    if (isset($t['eventos']['mandar_flores']) || isset($t['eventos']['mandar_mensaje'])) {
        $runConRomanceAccion = $i;
    }
    if ($t['eventos'] !== []) {
        $huecoEjecutadoAlgunaVez = true;
    }
}
ok($sucio === null, 'P1 PLAY determinista: declaracion/crisis/ruptura/reconciliacion NUNCA nacen (' . $maxRunsNeg . ' partidas × ' . $horasPorRun . ' h)');
ok($huecoEjecutadoAlgunaVez, 'P2 control no-vacuo: el hueco de vida sí ejecutó eventos permitidos');
ok($runConRomanceAccion >= 0, "P3 romance_accion SIGUE vivo en play (run #$runConRomanceAccion)");
ok(isset($vistosEventosPermitidos['ocio']), 'P4a familia ocio observada en play');
$permRestantes = count(array_diff_key($vistosEventosPermitidos, ['ocio' => true]));
ok($permRestantes >= 2, "P4b ≥2 familias permitidas más observadas en play (" . implode(',', array_keys($vistosEventosPermitidos)) . ")");

// ==================== L) LAB — sin filtro: declaracion alcanzable ====================
$declaracionLab = -1;
$maxRunsLab = 140;
for ($i = 0; $i < $maxRunsLab; $i++) {
    $seed = 'pre-gate-l' . (13 + $i * 5);
    $p = miniPartida($seed, true);
    for ($h = 0; $h < $horasPorRun; $h++) {
        avanzarHora($p);
    }
    $t = trazas($p);
    if (isset($t['eventos']['declaracion']) || isset($t['familias']['romance_hito']) || isset($t['hitos']['declaracion'])) {
        $declaracionLab = $i;
        break;
    }
}
ok($declaracionLab >= 0, "L1 LAB sin filtro: declaracion alcanza a nacer en lab (run #$declaracionLab)");

echo $fail === 0 ? "\nOK pre_gate_familias_play\n" : "\nFAIL pre_gate_familias_play ($fail)\n";
exit($fail === 0 ? 0 : 1);
