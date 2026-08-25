<?php
declare(strict_types=1);

// FASE 3 (R4) · Vida autónoma de pareja: citas de pareja con gap canónico.
//
// Cobertura:
//   A gap de pareja = cooldowns.por_familia.pareja (36h), marcador accion=cita
//   B cita de pareja AGENDADA autónoma (tipo cita, intencion autonomo_npc, sin cupo)
//   C anti-duplicado: segundo consumo en el mismo tick no crea nada
//   D ciclo estable: cita→resolver→nueva cita respetando 36h (x2)
//   E CRISIS: sin nuevas citas recreativas (sin marcador tras resolver)
//   F vida_pareja_activa OFF ⇒ sin marcadores (producción preservada)

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\IniciativaRomantica;
use AquiHayTema\Engine\ParejaEngine;
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

const VA = 'ana';
const VB = 'bruno';

function calVida(): array
{
    $cal = CalibracionConfig::load(dirname(__DIR__));
    $cal['romance_autonomo']['declaracion_activa'] = true;
    $cal['romance_autonomo']['pareja_activa'] = true;
    $cal['romance_autonomo']['vida_pareja_activa'] = true;
    $cal['voluntad']['p_min'] = 1.0;
    $cal['voluntad']['p_max'] = 1.0;
    return $cal;
}

function labPartida(): array
{
    return [
        'reloj' => ['dia_pueblo' => 30, 'hora_actual' => 9],
        'rng' => ['seed' => 'f3', 'state' => 202],
        'meta' => ['seed' => 'f3'],
        'residentes' => [
            VA => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            VB => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => [
            'lugares_desbloqueados' => ['lug_cafeteria'],
            'intervenciones_organizadas_max_dia' => 0,
        ],
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
}

function social30(array &$p, string $x, string $y): void
{
    for ($i = 0; $i < 3; $i++) {
        RelacionEngine::ajustarSocialHacia($p, $x, $y, 10);
    }
}

/** Pareja formada + una cita de pareja PROGRAMADA que se resuelve canónicamente.
 * Tras resolver, registra el marcador con la CAL OVERRIDDEADA del test
 * (producción pasará su cal cargada con flags ON; aquí simulamos ese estado). */
function parejaConCitaResuelta(array $cal): array
{
    $p = labPartida();
    social30($p, VA, VB);
    social30($p, VB, VA);
    RelacionEngine::setRomanceHacia($p, VA, VB, 40);
    RelacionEngine::setRomanceHacia($p, VB, VA, 40);
    ParejaEngine::formar($p, VA, VB, true, true, RelacionBitacoraHito(), $cal);
    // cita de pareja programada hoy mismo (futura estricta)
    $p['encuentros'][] = [
        'id' => 'enc_pv1',
        'tipo' => 'cita',
        'intencion' => 'autonomo_npc',
        'participantes' => [VA, VB],
        'lugar' => 'lug_cafeteria',
        'dia' => 30,
        'hora' => 12,
        'duracion_minutos' => 60,
        'duracion_horas' => 1,
        'estado' => 'programado',
        'resultado' => null,
    ];
    // resolver saltando al fin de la cita
    $p['reloj'] = ['dia_pueblo' => 30, 'hora_actual' => 13];
    resolverYRegistrar($p, $cal);
    return $p;
}

/** Resuelve encuentros vencidos y registra continuidad con la cal del test. */
function resolverYRegistrar(array &$p, array $cal): void
{
    $antes = count(array_filter(
        $p['encuentros'],
        static fn ($e) => is_array($e) && in_array(($e['estado'] ?? ''), ['programado', 'en_curso'], true)
    ));
    if ($antes === 0) {
        return;
    }
    $catalog = new Catalog($GLOBALS['__root']);
    EncuentroLifecycle::sincronizarConReloj($p, null, $catalog);
    // peor experiencia del último resuelto (misma regla canónica del resolver)
    $peor = '';
    $rank = ['muy_mal' => 0, 'mal' => 1, 'normal' => 2, 'bien' => 3, 'muy_bien' => 4];
    foreach (($p['encuentros'] ?? []) as $e) {
        if (!is_array($e) || ($e['estado'] ?? '') !== 'terminado'
            || !in_array(($e['tipo'] ?? ''), ['primera_cita', 'cita'], true)) {
            continue;
        }
        foreach ([VA, VB] as $rid) {
            $exp = (string) (($e['resultado']['por_participante'] ?? [])[$rid]['resultado'] ?? '');
            if ($exp !== '' && ($peor === '' || ($rank[$exp] ?? 2) < ($rank[$peor] ?? 2))) {
                $peor = $exp;
            }
        }
    }
    IniciativaRomantica::registrarContinuidadPostCita($p, VA, VB, $peor !== '' ? $peor : null, $cal);
}

function setAbs(array &$p, int $abs): void
{
    $p['reloj']['dia_pueblo'] = intdiv($abs, 24);
    $p['reloj']['hora_actual'] = $abs % 24;
}

function RelacionBitacoraHito(): string
{
    return \AquiHayTema\Engine\RelacionBitacora::DECLARACION;
}

function markerDe(array $p): ?array
{
    foreach (($p['continuidad_romantica'] ?? []) as $m) {
        if (is_array($m) && in_array(VA, $m['par'], true) && in_array(VB, $m['par'], true)) {
            return $m;
        }
    }
    return null;
}

function absNow(array $p): int
{
    return ((int) $p['reloj']['dia_pueblo']) * 24 + (int) $p['reloj']['hora_actual'];
}

$cal = calVida();

// ============ A: gap de pareja ============
$p = parejaConCitaResuelta($cal);
$m = markerDe($p);
ok($m !== null && ($m['accion'] ?? '') === 'cita', 'A1 marcador cita para la pareja');
ok((int) ($m['gap_horas'] ?? 0) === IniciativaRomantica::gapParejaHoras($cal), 'A2 gap = familia pareja (36h)');

// ============ B: cita de pareja agendada ============
setAbs($p, (int) $m['desde_abs']);
$res = IniciativaRomantica::procesarContinuidad($p, $cal);
ok(count($res) === 1 && ($res[0]['resultado'] ?? '') === 'cita_pareja_agendada', 'B1 cita_pareja_agendada');
$nuevas = array_values(array_filter(
    $p['encuentros'],
    static fn ($e) => is_array($e) && ($e['estado'] ?? '') === 'programado'
        && ($e['tipo'] ?? '') === 'cita'
));
ok(count($nuevas) === 1, 'B2 exactamente UNA cita futura');
if ($nuevas !== []) {
    ok(($nuevas[0]['intencion'] ?? '') === 'autonomo_npc', 'B3 intencion=autonomo_npc');
}
$usadasCupo = (int) ($p['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
ok($usadasCupo === 0, 'B4 SIN consumir cupo Celestine');

// ============ C: anti-duplicado ============
$res2 = IniciativaRomantica::procesarContinuidad($p, $cal);
ok($res2 === [], 'C1 segundo consumo mismo tick: nada');
$nuevas2 = array_values(array_filter(
    $p['encuentros'],
    static fn ($e) => is_array($e) && ($e['estado'] ?? '') === 'programado'
));
ok(count($nuevas2) === 1, 'C2 sigue habiendo solo UNA cita futura');

// ============ D: ciclo estable x2 ============
// resolver la cita agendada y comprobar cadencia
$fin = ((int) $nuevas[0]['dia']) * 24 + (int) $nuevas[0]['hora'] + max(1, (int) ($nuevas[0]['duracion_horas'] ?? 1));
$p['reloj'] = ['dia_pueblo' => intdiv($fin, 24), 'hora_actual' => $fin % 24];
resolverYRegistrar($p, $cal);
$m2 = markerDe($p);
ok($m2 !== null, 'D1 tras resolver, nuevo marcador de pareja');
if ($m2 !== null) {
    $gapReal = (int) $m2['desde_abs'] - absNow($p);
    ok($gapReal >= IniciativaRomantica::gapParejaHoras($cal), 'D2 cadencia ≥36h entre citas de pareja');
    setAbs($p, (int) $m2['desde_abs']);
    $res3 = IniciativaRomantica::procesarContinuidad($p, $cal);
    ok(count($res3) === 1 && str_starts_with((string) ($res3[0]['resultado'] ?? ''), 'cita_pareja_agendada'), 'D3 segunda cita de pareja agendada');
}
ok(ParejaEngine::estado($p, VA, VB) === ParejaEngine::PAREJA, 'D4 la pareja SIGUE siendo pareja (sin auto-hitos)');

// ============ E: CRISIS sin citas recreativas ============
$p = parejaConCitaResuelta($cal);
ParejaEngine::crisis($p, VA, VB);
// resolver OTRA cita estando en crisis
$p['encuentros'][] = [
    'id' => 'enc_pv2', 'tipo' => 'cita', 'participantes' => [VA, VB], 'lugar' => 'lug_cafeteria',
    'dia' => 31, 'hora' => 12, 'duracion_horas' => 1, 'estado' => 'programado', 'resultado' => null,
];
$p['reloj'] = ['dia_pueblo' => 31, 'hora_actual' => 13];
resolverYRegistrar($p, $cal);
ok(markerDe($p) === null, 'E1 en CRISIS no hay marcador de cita recreativa');
setAbs($p, absNow($p) + 48);
$resE = IniciativaRomantica::procesarContinuidad($p, $cal);
ok($resE === [], 'E2 continuidad no agenda nada en crisis');

// ============ F: flag OFF preserva producción ============
$calOff = CalibracionConfig::load($root);
$p = parejaConCitaResuelta($calOff);
ok(markerDe($p) === null, 'F1 vida_pareja OFF ⇒ sin marcadores de pareja');

echo $fail === 0 ? "\nOK fase3_vida_pareja\n" : "\nFAIL fase3_vida_pareja ($fail)\n";
exit($fail === 0 ? 0 : 1);
