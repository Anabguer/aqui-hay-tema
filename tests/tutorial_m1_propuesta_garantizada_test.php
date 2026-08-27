<?php
declare(strict_types=1);

/**
 * Tutorial M1 — propuesta pedagógica determinista (voluntad hostil).
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroIntervencion;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaCooldown;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RechazoMemoria;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\TutorialPrimerosPasos;
use AquiHayTema\Engine\Voluntad\VoluntadEvaluator;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$fail = 0;

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ': ' . $m . PHP_EOL;
    if (!$c) {
        $fail++;
    }
}

/** Voluntad extremadamente desfavorable: p_plan ~0.0001 en propuesta normal. */
final class VoluntadHostilTutorialTest implements VoluntadEvaluator
{
    /** @var array<string, mixed> */
    private array $cal;

    /** @param array<string, mixed> $cal */
    public function __construct(array $cal)
    {
        $this->cal = $cal;
    }

    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        $otro = '';
        foreach ($propuesta['participantes'] ?? [] as $rid) {
            if ((string) $rid !== $residenteId) {
                $otro = (string) $rid;
                break;
            }
        }
        return [
            'residente_id' => $residenteId,
            'nombre' => \AquiHayTema\Engine\IdentidadPublica::nombre($partida, $residenteId),
            'decision' => PropuestaEncuentro::DECISION_ACEPTA,
            'clase' => null,
            'motivo_tecnico' => 'voluntad_hostil_test',
            'motivo_tipo' => null,
            'copy_id' => null,
            'p' => 0.01,
            '_joint_plan' => true,
            'factores' => ['p_hostil_test' => 0.01],
        ];
    }
}

/** Rechazo directo de voluntad (post-M1, sin garantía pedagógica). */
final class VoluntadRechazoDirectoTest implements VoluntadEvaluator
{
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        return [
            'residente_id' => $residenteId,
            'nombre' => \AquiHayTema\Engine\IdentidadPublica::nombre($partida, $residenteId),
            'decision' => PropuestaEncuentro::DECISION_RECHAZA,
            'clase' => PropuestaEncuentro::CLASE_VOLUNTAD,
            'motivo_tecnico' => 'voluntad_rechazo_test',
            'motivo_tipo' => 'banal',
            'copy_id' => 'banal',
            'p' => 0.01,
            '_bloqueado_decision' => true,
        ];
    }
}

function proponerPresentarM1(array &$p, string $a, string $b, ?VoluntadEvaluator $vol = null): array
{
    $dia = (int) ($p['reloj']['dia_pueblo'] ?? 1);
    for ($h = 8; $h < 22; $h++) {
        if (!Reloj::esFuturo($p['reloj'] ?? [], $dia, $h)) {
            continue;
        }
        return PropuestaEncuentroEngine::proponer(
            $p,
            [$a, $b],
            $dia,
            $h,
            PropuestaNivel::PRESENTAR,
            'lug_cafeteria',
            null,
            $vol
        );
    }
    return ['ok' => false];
}

$svc = new PartidaService($root);
$cal = \AquiHayTema\Engine\CalibracionConfig::load($root);
$hostil = new VoluntadHostilTutorialTest($cal);

/* 1. Seed conocido que antes rechazaba (~repro-m1-0) */
$p0 = $svc->nuevaPartida('juego_v1', 'repro-m1-0');
$a0 = (string) ($p0['tutorial']['pareja_mision1']['a'] ?? '');
$b0 = (string) ($p0['tutorial']['pareja_mision1']['b'] ?? '');
$r0 = proponerPresentarM1($p0, $a0, $b0);
ok(!empty($r0['ok']) && !empty($r0['programado']), '1. seed repro-m1-0 programado con garantia M1');
ok(empty($r0['rechazada']), '1b. sin rechazo en seed antes hostil');

/* 2. Voluntad hostil explícita en M1 pendiente */
$p1 = $svc->nuevaPartida('juego_v1', 'tutorial-m1-hostil');
$a = (string) ($p1['tutorial']['pareja_mision1']['a'] ?? '');
$b = (string) ($p1['tutorial']['pareja_mision1']['b'] ?? '');
ok(TutorialPrimerosPasos::esPropuestaPedagogicaM1($p1, [$a, $b], PropuestaNivel::PRESENTAR), '2. detecta propuesta pedagogica M1');
$r1 = proponerPresentarM1($p1, $a, $b, $hostil);
ok(!empty($r1['ok']) && !empty($r1['programado']), '2b. propuesta M1 hostil programada');
$enc1 = null;
foreach ($p1['encuentros'] ?? [] as $enc) {
    if (!is_array($enc)) {
        continue;
    }
    $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
    sort($parts);
    $par = [$a, $b];
    sort($par);
    if ($parts === $par) {
        $enc1 = $enc;
    }
}
ok($enc1 !== null && ($enc1['intencion'] ?? '') === 'celeste_organizado', '2c. encuentro celeste_organizado');
ok(
    PropuestaCooldown::activo($p1, $a, $b, PropuestaNivel::PRESENTAR, $cal) === false
    && PropuestaCooldown::activo($p1, $b, $a, PropuestaNivel::PRESENTAR, $cal) === false,
    '2d. sin cooldown artificial tras garantia M1'
);
ok(RechazoMemoria::countHacia($p1, $a, $b) === 0 && RechazoMemoria::countHacia($p1, $b, $a) === 0, '2e. sin memoria rechazo artificial');

$target = (int) ($enc1['dia'] ?? 1) * 24 + (int) ($enc1['hora'] ?? 0);
while (((int) ($p1['reloj']['dia_pueblo'] ?? 1) * 24 + (int) ($p1['reloj']['hora_actual'] ?? 0)) < $target) {
    $svc->avanzarReloj($p1, 1);
}
EncuentroLifecycle::sincronizarConReloj($p1, null, $svc->getCatalog());
foreach ($p1['encuentros'] ?? [] as $enc) {
    if (($enc['id'] ?? '') === ($enc1['id'] ?? '')) {
        $enc1 = $enc;
    }
}
$iv1 = EncuentroIntervencion::vistaParaPlay($p1, $enc1, $svc->getCatalog());
ok(!empty($iv1['disponible']), '2f. MENTES disponible en primer encuentro');

$m1 = '';
foreach ($p1['misiones_diarias']['items'] ?? [] as $m) {
    if (($m['id'] ?? '') === TutorialPrimerosPasos::M1) {
        $m1 = (string) ($m['estado'] ?? '');
    }
}
ok($m1 === MisionDiariaEngine::EST_CUMPLIDA, '2g. M1 cumplida');
ok(!TutorialPrimerosPasos::esPropuestaPedagogicaM1($p1, [$a, $b], PropuestaNivel::PRESENTAR), '2h. reserva/garantia M1 liberada tras cumplir');

$dur = max(1, (int) ceil(((int) ($enc1['duracion_minutos'] ?? 90)) / 60));
$finEnc1 = (int) ($enc1['dia'] ?? 1) * 24 + (int) ($enc1['hora'] ?? 0) + $dur;
while (((int) ($p1['reloj']['dia_pueblo'] ?? 1) * 24 + (int) ($p1['reloj']['hora_actual'] ?? 0)) < $finEnc1) {
    $svc->avanzarReloj($p1, 1);
}
EncuentroLifecycle::sincronizarConReloj($p1, null, $svc->getCatalog());

/* 3. Propuesta POSTERIOR normal puede rechazar (M1 ya cumplida, sin garantía) */
$antesEnc = count($p1['encuentros'] ?? []);
$rPost = null;
$dia2 = (int) ($p1['reloj']['dia_pueblo'] ?? 1);
for ($d = $dia2; $d <= $dia2 + 2; $d++) {
    $hMin = ($d === $dia2) ? ((int) ($p1['reloj']['hora_actual'] ?? 0) + 1) : 8;
    for ($h = $hMin; $h < 22; $h++) {
        if (!Reloj::esFuturo($p1['reloj'] ?? [], $d, $h)) {
            continue;
        }
        $try = PropuestaEncuentroEngine::proponer(
            $p1,
            [$a, $b],
            $d,
            $h,
            PropuestaNivel::QUEDAR,
            'lug_cafeteria',
            null,
            new VoluntadRechazoDirectoTest()
        );
        if (!empty($try['ok'])) {
            $rPost = $try;
            break 2;
        }
    }
}
ok($rPost !== null, '3. propuesta posterior procesada');
ok(empty($rPost['programado']) && !empty($rPost['rechazada']), '3b. posterior con voluntad hostil puede rechazar');
ok(count($p1['encuentros'] ?? []) === $antesEnc, '3c. sin encuentro duplicado tras rechazo posterior');

echo $fail === 0 ? "\ntutorial_m1_propuesta_garantizada_test OK\n" : "\ntutorial_m1_propuesta_garantizada_test FAIL ($fail)\n";
exit($fail === 0 ? 0 : 1);
