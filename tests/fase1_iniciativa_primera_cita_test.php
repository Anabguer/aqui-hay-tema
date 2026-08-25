<?php
declare(strict_types=1);

// FASE 1 ┬À Tests BÔÇôH ÔÇö Se├▒al ÔåÆ iniciativa aut├│noma ÔåÆ primera_cita can├│nica.

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\IniciativaRomantica;
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
$GLOBALS['cal'] = CalibracionConfig::load($root);
$cal = $GLOBALS['cal'];

const A = 'ana';
const B = 'bruno';

function labPartida(): array
{
    return [
        'reloj' => ['dia_pueblo' => 12, 'hora_actual' => 14],
        'rng' => ['seed' => 'lab', 'state' => 101],
        'meta' => ['seed' => 'lab'],
        'residentes' => [
            A => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            B => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => ['lugares_desbloqueados' => ['lug_cafeteria', 'lug_parque']],
        'relaciones_sociales' => [],
        'relaciones_romanticas' => [],
        'relaciones_conflicto' => [],
        'encuentros' => [],
        'parentesco' => [],
    ];
}

function social(array &$p, string $desde, string $hacia, int $objetivo): void
{
    if ($objetivo === 0) {
        RelacionEngine::ajustarSocialHacia($p, $desde, $hacia, 0);
        return;
    }
    $resto = $objetivo;
    while ($resto !== 0) {
        $d = abs($resto) > 10 ? ($resto > 0 ? 10 : -10) : $resto;
        RelacionEngine::ajustarSocialHacia($p, $desde, $hacia, $d);
        $resto -= $d;
    }
}

function conSenal(array &$p, bool $mutua = false): void
{
    social($p, A, B, 30);
    social($p, B, A, 30);
    RelacionEngine::setRomanceHacia($p, A, B, 28);
    if ($mutua) {
        RelacionEngine::setRomanceHacia($p, B, A, 28);
    }
}

function pcCount(array $p): int
{
    $n = 0;
    foreach (($p['encuentros'] ?? []) as $e) {
        if (($e['tipo'] ?? '') === 'primera_cita') {
            $n++;
        }
    }
    return $n;
}

function intentar(array &$p): array
{
    return IniciativaRomantica::intentarPrimeraCita($p, A, B, $GLOBALS['cal']);
}

/** Primer estado rng del scan determinista que produce el resultado buscado. */
function findEstado(callable $mk, string $prefijo): int
{
    for ($s = 11; $s <= 4000; $s += 17) {
        $p = $mk();
        $p['rng']['state'] = $s;
        $r = intentar($p);
        if (str_starts_with((string) ($r['resultado'] ?? ''), $prefijo)) {
            return $s;
        }
    }
    return -1;
}

// ============ B: SIN SE├æAL / VETOS ============
$p = labPartida();
social($p, A, B, 30);
social($p, B, A, 30);
$r = intentar($p);
ok(($r['resultado'] ?? '') === 'gate_sin_senal' && ($r['ok'] ?? true) === false, 'B1 sin se├▒al: gate_sin_senal');
ok(pcCount($p) === 0 && count($p['iniciativa_romantica_log']) === 1, 'B1 sin se├▒al: sin encuentro, con traza');

$p = labPartida();
conSenal($p);
$p['parentesco'][] = ['persona_a' => A, 'persona_b' => B, 'tipo' => 'padre'];
$r = intentar($p);
ok(($r['resultado'] ?? '') === 'gate_parentesco_veto' && pcCount($p) === 0, 'B2 parentesco veto intacto aun con se├▒al');

$p = labPartida();
$r = intentar($p, );
$r = IniciativaRomantica::intentarPrimeraCita($p, A, A, $cal);
ok(($r['resultado'] ?? '') === 'gate_par_invalido', 'B3 par inv├ílido rechazado');

// ============ E: DIRECCIONALIDAD ============
$p = labPartida();
social($p, A, B, 30);
social($p, B, A, 30);
RelacionEngine::setRomanceHacia($p, A, B, 28); // solo AÔåÆB
$rA = intentar($p);
ok(!str_contains((string) ($rA['resultado'] ?? ''), 'sin_senal'), 'E1 A interesado puede INICIAR');
$p2 = labPartida();
social($p2, A, B, 30);
social($p2, B, A, 30);
RelacionEngine::setRomanceHacia($p2, A, B, 28);
$rB = IniciativaRomantica::intentarPrimeraCita($p2, B, A, $cal);
ok(($rB['resultado'] ?? '') === 'gate_sin_senal', 'E1 B no interesado NO inicia hacia A');

$pE = labPartida();
conSenal($pE, true);
$prop = ['tipo' => 'primera_cita', 'participantes' => [A, B], 'lugar' => null];
$dA = VoluntadPonderadaEvaluator::desglose($pE, $prop, A, B, $cal);
$dB = VoluntadPonderadaEvaluator::desglose($pE, $prop, B, A, $cal);
ok((int) $dA['bonus_primera_cita_reciproca'] === 12 && (int) $dB['bonus_primera_cita_reciproca'] === 12,
    'E2 bonus rec├¡proco +12 con se├▒al mutua (canon P02 intacto)');

// ============ C: VOLUNTAD DE AMBOS (media geom├®trica canon) ============
$mkMutua = static function (): array {
    $p = labPartida();
    conSenal($p, true);
    return $p;
};
$mkRechazo = static function (): array {
    $p = labPartida();
    conSenal($p, true);
    social($p, B, A, -70);
    $p['relaciones_conflicto'][] = ['id' => 'conf', 'persona_a' => A, 'persona_b' => B, 'intensidad' => 8];
    return $p;
};

$stOk = findEstado($mkMutua, 'primera_cita_agendada');
ok($stOk > 0, 'C1 existe estado rng determinista con aceptaci├│n mutua');
$p = $mkMutua();
$p['rng']['state'] = $stOk;
$r = intentar($p);
ok(str_starts_with((string) ($r['resultado'] ?? ''), 'primera_cita_agendada'), 'C1 primera cita AGENDADA');
ok(pcCount($p) === 1, 'C1 exactamente UNA primera_cita creada');
$pcs = array_values(array_filter(($p['encuentros'] ?? []) ?: [], static fn($e) => ($e['tipo'] ?? '') === 'primera_cita'));
if ($pcs !== []) {
    $pc = $pcs[0];
    ok(($pc['estado'] ?? '') === 'programado', 'C1 encuentro programado a futuro');
    ok(($pc['intencion'] ?? '') === 'autonomo_npc', 'C1 intencion=autonomo_npc');
    ok(in_array(A, $pc['participantes'], true) && in_array(B, $pc['participantes'], true), 'C1 participantes correctos');
}
ok(count(RelacionBitacora::entre($p, A, B, RelacionBitacora::PRIMERA_CITA)) === 0,
    'C1 hito PRIMERA_CITA a├║n NO registrado (canon: al resolver)');

$stRej = findEstado($mkRechazo, 'plan_geom_rechazado');
ok($stRej > 0, 'C2 existe estado rng determinista con plan rechazado por voluntad');
$p = $mkRechazo();
$p['rng']['state'] = $stRej;
$r = intentar($p);
ok(str_starts_with((string) ($r['resultado'] ?? ''), 'plan_geom_rechazado'), 'C2 plan geom├®trico RECHAZADO por voluntad real');
ok(pcCount($p) === 0, 'C2 rechazo: NO se crea encuentro');
$rech = $p['rechazos_propuesta'] ?? [];
ok(count($rech) >= 1 && ($rech[0]['tipo'] ?? '') === 'primera_cita', 'C2 RechazoMemoria registra el rechazo (tipo primera_cita)');
$cdRows = array_values(array_filter(($p['propuestas_cooldown'] ?? []) ?: [], static fn($c) => ($c['tipo'] ?? '') === 'primera_cita'));
ok(count($cdRows) >= 1, 'C2 cooldown de propuesta marcado (anti-spam)');

// ============ F: COOLDOWN ============
$p = $mkRechazo();
$p['rng']['state'] = $stRej;
intentar($p);
$r2 = intentar($p);
$g2 = (string) ($r2['gate'] ?? $r2['resultado'] ?? '');
ok($g2 === 'cooldown_propuesta' || str_contains($g2, 'cooldown'), "F reintento inmediato bloqueado por cooldown ($g2)");
$p['reloj']['hora_actual'] += 7;
$r3 = intentar($p);
$g3 = (string) ($r3['gate'] ?? $r3['resultado'] ?? '');
ok(!str_contains($g3, 'cooldown'), "F pasado el cooldown vuelve a ser posible (gate=$g3)");

// ============ D: IDEMPOTENCIA ============
$p = $mkMutua();
$p['rng']['state'] = $stOk;
intentar($p);
$rDup = intentar($p);
$gDup = (string) ($rDup['gate'] ?? $rDup['resultado'] ?? '');
ok(str_contains($gDup, 'primera_cita_ya') || str_contains($gDup, 'cooldown'),
    "D1 cita EN MARCHA no se duplica ($gDup)");
ok(pcCount($p) === 1, 'D1 sigue habiendo exactamente una primera_cita');

$p = labPartida();
conSenal($p, true);
RelacionBitacora::registrar($p, RelacionBitacora::PRIMERA_CITA, [A, B]);
$rHecha = intentar($p);
ok(($rHecha['resultado'] ?? '') === 'gate_primera_cita_ya' && pcCount($p) === 0,
    'D2 primera cita YA REALIZADA (hito) no se regenera');
ok(count(RelacionBitacora::entre($p, A, B, RelacionBitacora::PRIMERA_CITA)) === 1, 'D2 hito ├║nico');

// ============ G: PRIORIDAD JUGADOR/CELESTE ============
$p = labPartida();
conSenal($p, true);
$rJug = EncuentroEngine::programar($p, [A, B], 12, 15, 'quedar', 'lug_parque');
ok((bool) ($rJug['ok'] ?? false), 'G reserva del jugador creada (control)');
$p['rng']['state'] = $stOk;
$rAut = intentar($p);
if (str_starts_with((string) ($rAut['resultado'] ?? ''), 'primera_cita_agendada')) {
    ok(!((int) ($rAut['programado_dia'] ?? 0) === 12 && (int) ($rAut['programado_hora'] ?? 0) === 15),
        'G la autonom├¡a NO pisa la franja reservada por el jugador');
} else {
    ok(str_contains((string) ($rAut['resultado'] ?? ''), 'sin_franja_agenda')
        || str_contains((string) ($rAut['resultado'] ?? ''), 'gate_'),
        'G si no hay franja alternativa, la iniciativa cede (agenda del jugador manda)');
}
$jugIntacto = false;
foreach (($p['encuentros'] ?? []) as $e) {
    if (($e['tipo'] ?? '') === 'quedar' && (int) ($e['dia'] ?? -1) === 12 && (int) ($e['hora'] ?? -1) === 15
        && ($e['intencion'] ?? '') !== 'autonomo_npc') {
        $jugIntacto = ($e['estado'] ?? '') === 'programado';
    }
}
ok($jugIntacto, 'G el plan del jugador permanece programado e intacto');

// ============ H: SAVE ANTIGUO (sin campos nuevos) ============
$p = labPartida();
unset($p['propuestas_cooldown'], $p['memoria_eventos']);
conSenal($p, true);
$p['rng']['state'] = $stOk;
$rOld = intentar($p);
ok(isset($rOld['resultado']) || isset($rOld['gate']), 'H save sin campos nuevos ejecuta sin errores');
ok(is_array($p['iniciativa_romantica_log']), 'H log de iniciativa auto-creado');

echo $fail === 0 ? "\nOK fase1_iniciativa_primera_cita\n" : "\nFAIL fase1_iniciativa_primera_cita ($fail)\n";
exit($fail === 0 ? 0 : 1);
