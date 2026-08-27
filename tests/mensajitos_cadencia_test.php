<?php
declare(strict_types=1);

/*
 * Test: MensajitosCadenciaEngine — presupuesto diario, cooldowns, compactación.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\MensajitosCadenciaEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\BuzonEngine;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) { $failures++; }
}

DomainBootstrap::boot();
$cal = CalibracionConfig::load($root);

// --- 1) Presupuesto diario escala con población ---
$p3 = MensajitosCadenciaEngine::presupuestoDiario(3, $cal);
$p8 = MensajitosCadenciaEngine::presupuestoDiario(8, $cal);
$p16 = MensajitosCadenciaEngine::presupuestoDiario(16, $cal);
$p24 = MensajitosCadenciaEngine::presupuestoDiario(24, $cal);
ok($p3 >= 3 && $p3 <= 10, "pop3 presupuesto=$p3 (esperado 3-10)");
ok($p8 > $p3, "pop8 ($p8) > pop3 ($p3)");
ok($p16 >= $p8, "pop16 ($p16) >= pop8 ($p8)");
ok($p24 > $p16, "pop24 ($p24) > pop16 ($p16)");
ok($p24 <= 20, "pop24 presupuesto=$p24 razonable");

// --- 2) Cooldown por vecino ---
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'cadencia-test-' . time());
$p['reloj']['dia_pueblo'] = 1;
$p['reloj']['hora_actual'] = 12;

ok(!MensajitosCadenciaEngine::enCooldownVecino($p, 'per_p001', $cal), 'sin historial: sin cooldown');

// Registrar un espontáneo
MensajitosCadenciaEngine::registrar($p, 'per_p001', 'f_opinion', 'espontaneo');
ok(MensajitosCadenciaEngine::enCooldownVecino($p, 'per_p001', $cal), 'tras registro: cooldown activo');
ok(!MensajitosCadenciaEngine::enCooldownVecino($p, 'per_p002', $cal), 'otro vecino: sin cooldown');

// Avanzar más allá del cooldown
$p['reloj']['hora_actual'] = 48;
ok(!MensajitosCadenciaEngine::enCooldownVecino($p, 'per_p001', $cal), 'tras 36h: cooldown expirado');

// --- 3) Prioridades ---
ok(MensajitosCadenciaEngine::prioridad('importante') < MensajitosCadenciaEngine::prioridad('f_opinion'), 'importante > opinion');
ok(MensajitosCadenciaEngine::prioridad('peticion') < MensajitosCadenciaEngine::prioridad('f_dilema'), 'peticion > dilema');
ok(MensajitosCadenciaEngine::prioridad('f_confidencia') === 3, 'confidencia prioridad=3');
ok(MensajitosCadenciaEngine::prioridad('f_alerta_vecinal') === 2, 'alerta prioridad=2');

// --- 4) Hay presupuesto ---
$p2 = $svc->nuevaPartida('juego_v1', 'cadencia-hp-' . time());
$p2['reloj']['dia_pueblo'] = 5;
ok(MensajitosCadenciaEngine::hayPresupuesto($p2, $cal), 'partida nueva: hay presupuesto');

// Llenar el presupuesto
$presupuesto = MensajitosCadenciaEngine::presupuestoDiario(count($p2['residentes']), $cal);
for ($i = 0; $i < $presupuesto; $i++) {
    BuzonEngine::crear($p2, [
        'texto' => "msg test $i",
        'tipo' => "test_$i",
        'canal' => BuzonEngine::CANAL_BUZON,
        'clasificacion' => BuzonEngine::OPORTUNIDAD,
    ]);
}
ok(!MensajitosCadenciaEngine::hayPresupuesto($p2, $cal), 'sin presupuesto tras llenar');

// --- 5) Compactación ---
$p3c = $svc->nuevaPartida('juego_v1', 'cadencia-compact-' . time());
$p3c['reloj']['dia_pueblo'] = 20;
BuzonEngine::crear($p3c, ['texto' => 'resuelto viejo', 'tipo' => 'test']);
$last = end($p3c['buzon']);
$last['estado'] = 'resuelto';
$last['dia'] = 10;
$p3c['buzon'][count($p3c['buzon']) - 1] = $last;
BuzonEngine::crear($p3c, ['texto' => 'reciente', 'tipo' => 'test']);
$compactados = MensajitosCadenciaEngine::compactarResueltos($p3c, $cal);
ok($compactados >= 1, "compactacion: $compactados mensajes compactados");

echo "\n";
echo $failures === 0 ? "OK mensajitos_cadencia\n" : "FAIL mensajitos_cadencia ({$failures})\n";
exit($failures > 0 ? 1 : 0);