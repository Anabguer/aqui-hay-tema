<?php
declare(strict_types=1);

// FASE 1 ┬À Test A ÔÇö HORA_PASADA / salidas individuales a franja futura.

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EmotionalStateService;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\MotorVidaDiaria;
use AquiHayTema\Engine\PartidaSchema;
use AquiHayTema\Engine\PersistenciaCaps;
use AquiHayTema\Engine\PoblacionV3;
use AquiHayTema\Engine\RelojOperations;
use AquiHayTema\Engine\RngService;
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

/** Avanza 1 hora con el pipeline can├│nico completo. */
function avanzarHora(array &$p): void
{
    static $ops = null;
    if ($ops === null) {
        $emociones = new EmotionalStateService(new VisualPackStore($GLOBALS['__root']), $GLOBALS['__catalog_store'], null);
        $ops = new RelojOperations($GLOBALS['__root'], null, $emociones);
    }
    $ops->avanzarPasoAPaso($p, 1);
}

$configId = 'juego_v1';
$p = PartidaSchema::nueva($root, $configId, 'fase1-a');
$config = $catalog->loadConfigPrevalidada($configId);
$config['poblacion_v3'] = ['iniciales_aleatorios' => 2];
unset($config['residentes_iniciales'], $config['parentesco'], $config['tutorial_primeros_pasos'], $config['tutorial_bucle_1'], $config['tutorial_objetivo_residentes']);
$opsRes = new ResidenteOperations($catalog, null);
PoblacionV3::incorporarIniciales($p, $config, $root, $opsRes);
FeatureConfig::mergeIntoPartida($p, $root);
if (!empty($config['features']) && is_array($config['features'])) {
    $p['features'] = array_merge(is_array($p['features'] ?? null) ? $p['features'] : [], $config['features']);
}
PersistenciaCaps::mergeIntoPartida($p, $root);
SchemaFields::ensure($p);
DomainBootstrap::boot();
$p = SchemaMigrator::migrate($p);

$ids = array_keys($p['residentes']);
$quien = (string) $ids[0];

while ((int) $p['reloj']['dia_pueblo'] < 2 || (int) $p['reloj']['hora_actual'] < 10) {
    avanzarHora($p);
}
echo "reloj base: d{$p['reloj']['dia_pueblo']} h{$p['reloj']['hora_actual']}\n";

$rm = new ReflectionMethod(MotorVidaDiaria::class, 'siguienteFranjaFutura');
$rm->setAccessible(true);

// 1) franja estrictamente futura y en ventana
$f1 = $rm->invoke(null, $p, $quien, 'lug_cafeteria', $cal);
ok($f1 !== null, 'existe franja futura');
$absNow = ((int) $p['reloj']['dia_pueblo']) * 24 + (int) $p['reloj']['hora_actual'];
$absF1 = ((int) $f1['dia']) * 24 + (int) $f1['hora'];
ok($absF1 > $absNow, "franja futura estricta (ahora=$absNow, franja=$absF1)");
ok((int) $f1['hora'] >= 9 && (int) $f1['hora'] <= 22, 'franja dentro de ventana 09-22');
ok($absF1 !== $absNow, 'la misma hora nunca se propone (fix HORA_PASADA)');

// 2) respeta reservas: ocupamos esa franja y la franja debe moverse despu├®s
$rReserva = EncuentroEngine::programar($p, [$quien], (int) $f1['dia'], (int) $f1['hora'], 'individual', 'lug_parque');
ok((bool) ($rReserva['ok'] ?? false), 'reserva previa creada en la franja (control)');
$f2 = $rm->invoke(null, $p, $quien, 'lug_cafeteria', $cal);
$absF2 = $f2 !== null ? ((int) $f2['dia']) * 24 + (int) $f2['hora'] : -1;
ok($f2 !== null && $absF2 > $absF1, "respeta ocupaciones: nueva franja ($absF2) > anterior ($absF1)");

// 3) de noche (23:00) salta al d├¡a siguiente dentro de la ventana diurna
while ((int) $p['reloj']['hora_actual'] !== 23) {
    avanzarHora($p);
}
$f3 = $rm->invoke(null, $p, $quien, 'lug_cafeteria', $cal);
ok($f3 !== null && (int) $f3['dia'] > (int) $p['reloj']['dia_pueblo'] && (int) $f3['hora'] >= 9,
    'de noche salta al d├¡a siguiente en ventana diurna');

// 4) v├¡a real del motor (pipeline horario): las salidas se programan SIEMPRE a futuro.
$histAntes = count($p['npc_autonomo']['historial_eventos'] ?? []);
for ($i = 0; $i < 40; $i++) {
    avanzarHora($p);
}
$nuevas = array_slice($p['npc_autonomo']['historial_eventos'] ?? [], $histAntes);
$conFranja = array_values(array_filter($nuevas, static fn($ev) => isset($ev['programado_dia'], $ev['programado_hora'])));
ok(count($conFranja) >= 1, 'el motor real program├│ al menos una salida individual con franja futura');
$todasFuturas = true;
foreach ($conFranja as $ev) {
    $absLog = ((int) $ev['dia']) * 24 + (int) $ev['hora'];
    $absProg = ((int) $ev['programado_dia']) * 24 + (int) $ev['programado_hora'];
    if ($absProg <= $absLog) {
        $todasFuturas = false;
    }
}
ok($todasFuturas, 'todas las franjas son estrictamente posteriores al momento de la decisi├│n');
$individualesProgramados = 0;
foreach (($p['encuentros'] ?? []) as $e) {
    if (($e['tipo'] ?? '') === 'individual') {
        $individualesProgramados++;
    }
}
ok($individualesProgramados >= 1, 'existen encuentros individuales en la partida (sin HORA_PASADA)');
$hoyUltima = $conFranja !== [] ? end($conFranja) : null;
ok($hoyUltima !== null && isset($hoyUltima['programado_dia'], $hoyUltima['programado_hora']),
    'historial registra la franja futura programada');

echo $fail === 0 ? "\nOK fase1_salida_individual_franja\n" : "\nFAIL fase1_salida_individual_franja ($fail)\n";
exit($fail === 0 ? 0 : 1);
