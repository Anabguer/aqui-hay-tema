<?php
declare(strict_types=1);

/**
 * A2 — Evitación por conflicto en pesos de selección social.
 *
 * Penaliza conflicto.intensidad en paresPonderados y elegirEvento.
 * Omite pares con intensidad >= evitar_conflicto_intensidad_min (calibración).
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\InteraccionCasual;
use AquiHayTema\Engine\MotorVidaDiaria;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SeleccionSocialPeso;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

DomainBootstrap::boot();
$svc = new PartidaService($root);
$cal = CalibracionConfig::load($root);

$mPares = new ReflectionMethod(InteraccionCasual::class, 'paresPonderados');
$mPares->setAccessible(true);
$mEv = new ReflectionMethod(MotorVidaDiaria::class, 'elegirEvento');
$mEv->setAccessible(true);

function posicionPar(array $orden, string $a, string $b): ?int
{
    foreach ($orden as $i => $par) {
        if ($par[0] === $a && $par[1] === $b) {
            return $i;
        }
    }

    return null;
}

function parPresente(array $orden, string $a, string $b): bool
{
    return posicionPar($orden, $a, $b) !== null;
}

$p = $svc->nuevaPartida('juego_v1', 'a2-conflicto-fixture');
$ids = array_keys($p['residentes']);
[$r1, $r2, $r3] = [$ids[0], $ids[1], $ids[2]];

RelacionEngine::ajustarSocialHacia($p, $r1, $r2, 20, $cal);
RelacionEngine::ajustarSocialHacia($p, $r1, $r3, 20, $cal);
RelacionEngine::upsertConflicto($p, $r1, $r3, 4);

$rng = new RngService('a2-orden', 12345);
$orden = $mPares->invoke(null, $p, $ids, $rng, $cal);
$posNeutro = posicionPar($orden, $r1, $r2);
$posConf = posicionPar($orden, $r1, $r3);
ok($posNeutro !== null && $posConf !== null, 'pares con y sin conflicto moderado siguen elegibles');
ok($posNeutro < $posConf, 'conflicto moderado (4) ordena despues del par sin conflicto');

RelacionEngine::upsertConflicto($p, $r1, $r3, 9);
$rng2 = new RngService('a2-omit', 54321);
$ordenAlto = $mPares->invoke(null, $p, $ids, $rng2, $cal);
ok(!parPresente($ordenAlto, $r1, $r3), 'conflicto alto (9) omite el par de paresPonderados');

$pen = SeleccionSocialPeso::penalizacionConflicto($p, $r1, $r2, $cal);
ok($pen === 0.0, 'sin conflicto => penalizacion 0');
RelacionEngine::upsertConflicto($p, $r1, $r2, 5);
$pen2 = SeleccionSocialPeso::penalizacionConflicto($p, $r1, $r2, $cal);
$factor = (float) CalibracionConfig::get($cal, 'seleccion_social.penalizacion_por_punto_conflicto', 0.12);
ok(abs($pen2 - 5 * $factor) < 1e-9, 'penalizacion lineal por intensidad');

$item = ['id' => 'ev_a2_vida', 'familia' => 'vida', 'participantes' => 2];
$pEv = $svc->nuevaPartida('juego_v1', 'a2-elegir-fixture');
$idsEv = array_keys($pEv['residentes']);
[$prot, $sinConf, $conConf] = [$idsEv[0], $idsEv[1], $idsEv[2]];
RelacionEngine::ajustarSocialHacia($pEv, $prot, $sinConf, 40, $cal);
RelacionEngine::ajustarSocialHacia($pEv, $prot, $conConf, 40, $cal);
RelacionEngine::upsertConflicto($pEv, $prot, $conConf, 6);

$cnt = ['neutro' => 0, 'conf' => 0];
for ($i = 0; $i < 600; $i++) {
    $rngI = new RngService('a2-elegir', 200000 + $i * 3571);
    $res = $mEv->invoke(null, $pEv, [$item], $prot, $cal, $rngI);
    if ($res === null) {
        continue;
    }
    $otro = (string) $res['participantes'][1];
    if ($otro === $sinConf) {
        $cnt['neutro']++;
    } elseif ($otro === $conConf) {
        $cnt['conf']++;
    }
}
ok($cnt['neutro'] > 0 && $cnt['conf'] > 0, 'elegirEvento mantiene ambos socios elegibles con conflicto moderado');
ok($cnt['neutro'] > $cnt['conf'], 'elegirEvento favorece socio sin conflicto frente a conflicto 6');

echo "\nA2 evitacion-conflicto: " . ($failures === 0 ? 'TODO OK' : "$failures FALLOS") . "\n";
exit($failures > 0 ? 1 : 0);
