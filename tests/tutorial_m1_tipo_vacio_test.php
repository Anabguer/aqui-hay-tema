<?php
declare(strict_types=1);

/**
 * BLOQUE 2 — Regression: M1 tutorial proposal sent with EMPTY tipo (real JS
 * payload uses `tipo: org.tipo || ''`, and for modo 'pareja' org.tipo === '').
 * The pedagogical guarantee must still apply so the first proposal can NEVER be
 * rejected by Voluntad.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
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

/** Voluntad que rechaza de forma directa (clase VOLUNTAD), como puede pasar en M1. */
final class VoluntadRechazoM1Test implements VoluntadEvaluator
{
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        return [
            'residente_id' => $residenteId,
            'nombre' => \AquiHayTema\Engine\IdentidadPublica::nombre($partida, $residenteId),
            'decision' => PropuestaEncuentro::DECISION_RECHAZA,
            'clase' => PropuestaEncuentro::CLASE_VOLUNTAD,
            'motivo_tecnico' => 'voluntad_rechazo_m1',
            'motivo_tipo' => 'banal',
            'copy_id' => 'banal',
            'p' => 0.01,
            '_joint_plan' => true,
            '_bloqueado_decision' => true,
        ];
    }
}

function proponerM1TipoVacio(array &$p, string $a, string $b, ?VoluntadEvaluator $vol = null): array
{
    $dia = (int) ($p['reloj']['dia_pueblo'] ?? 1);
    for ($h = 8; $h < 22; $h++) {
        if (!Reloj::esFuturo($p['reloj'] ?? [], $dia, $h)) {
            continue;
        }
        // tipo '' vacío: replica el payload real del JS para modo 'pareja'.
        return PropuestaEncuentroEngine::proponer(
            $p,
            [$a, $b],
            $dia,
            $h,
            '',
            'lug_cafeteria',
            null,
            $vol
        );
    }
    return ['ok' => false];
}

$svc = new PartidaService($root);
$cal = \AquiHayTema\Engine\CalibracionConfig::load($root);

$p = $svc->nuevaPartida('juego_v1', 'tutorial-m1-tipo-vacio');
$a = (string) ($p['tutorial']['pareja_mision1']['a'] ?? '');
$b = (string) ($p['tutorial']['pareja_mision1']['b'] ?? '');

ok($a !== '' && $b !== '', '0. semilla tutorial con pareja M1');
ok(TutorialPrimerosPasos::esPropuestaPedagogicaM1($p, [$a, $b], '') === false,
    '1. con tipo vacio la deteccion M1 falla (reproduce causa)');
ok(TutorialPrimerosPasos::esPropuestaPedagogicaM1($p, [$a, $b], PropuestaNivel::PRESENTAR) === true,
    '1b. con PRESENTAR la deteccion M1 funciona (comportamiento esperado)');

$r = proponerM1TipoVacio($p, $a, $b, new VoluntadRechazoM1Test());
ok(!empty($r['ok']) && !empty($r['programado']), '2. propuesta M1 con tipo vacio + voluntad hostil QUEDA programada (garantia)');
ok(empty($r['rechazada']), '2b. sin rechazo pese a voluntad hostil');
$m1 = '';
foreach ($p['misiones_diarias']['items'] ?? [] as $m) {
    if (($m['id'] ?? '') === TutorialPrimerosPasos::M1) {
        $m1 = (string) ($m['estado'] ?? '');
    }
}
ok($m1 === MisionDiariaEngine::EST_CUMPLIDA, '2c. M1 queda cumplida');

echo $fail === 0 ? "\ntutorial_m1_tipo_vacio_test OK\n" : "\ntutorial_m1_tipo_vacio_test FAIL ($fail)\n";
exit($fail === 0 ? 0 : 1);
