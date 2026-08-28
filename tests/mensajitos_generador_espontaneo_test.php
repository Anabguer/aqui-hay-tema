<?php
declare(strict_types=1);

/*
 * Test: MensajitoGeneradorEspontaneo — generación espontánea de F1/F2/F6/F7/F15.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoGeneradorEspontaneo;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RngService;

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
$svc = new PartidaService($root);

// --- 1) Sin buzon_enabled: no genera ---
$p = $svc->nuevaPartida('juego_v1', 'gen-no-buzon-' . time());
unset($p['features']['buzon_enabled']);
$rng = new RngService('gen-test-1');
$resultado = MensajitoGeneradorEspontaneo::evaluar($p, 'per_p001', $cal, $rng);
ok($resultado === null, 'sin buzon_enabled: no genera');

// --- 2) Con buzon_enabled: puede generar (o no, según RNG/probabilidades) ---
$p2 = $svc->nuevaPartida('juego_v1', 'gen-con-buzon-' . time());
$p2['features']['buzon_enabled'] = true;
$p2['reloj']['dia_pueblo'] = 5;
$p2['reloj']['hora_actual'] = 12;

// Darle relaciones para F1
$p2['relaciones_sociales'] = [
    [
        'id' => 'soc_per_p001_per_p002',
        'persona_a' => 'per_p001', 'persona_b' => 'per_p002',
        'conocidos' => true,
        'a_hacia_b' => ['valor' => 25],
        'b_hacia_a' => ['valor' => 20],
    ],
    [
        'id' => 'soc_per_p001_per_p003',
        'persona_a' => 'per_p001', 'persona_b' => 'per_p003',
        'conocidos' => true,
        'a_hacia_b' => ['valor' => 30],
        'b_hacia_a' => ['valor' => 28],
    ],
];
$p2['residentes']['per_p002']['identidad_publica']['nombre'] = 'Carlos';
$p2['residentes']['per_p003']['identidad_publica']['nombre'] = 'Laura';

// Intentar varias veces con diferentes seeds
$generados = 0;
for ($i = 0; $i < 100; $i++) {
    $rngTry = new RngService("gen-f1-$i");
    $antes = count($p2['buzon'] ?? []);
    $r = MensajitoGeneradorEspontaneo::evaluar($p2, 'per_p001', $cal, $rngTry);
    if ($r !== null) {
        $generados++;
        break;
    }
}
// Con prob base 15% y 100 intentos, es extremadamente probable que al menos 1 genere
ok($generados >= 1, "generador produce al menos 1 mensajito en 100 intentos (got=$generados)");

// --- 3) cooldown previene repetición ---
if ($generados > 0) {
    $rngCool = new RngService('gen-cool');
    $r2 = MensajitoGeneradorEspontaneo::evaluar($p2, 'per_p001', $cal, $rngCool);
    ok($r2 === null, 'tras generar: cooldown impide segundo');
}

// --- 4) El generado es un mensaje válido en el buzón ---
$ultimo = null;
foreach ($p2['buzon'] ?? [] as $m) {
    if (is_array($m)) { $ultimo = $m; }
}
if ($ultimo !== null) {
    ok(($ultimo['de_persona'] ?? '') === 'per_p001', 'generado: tiene de_persona correcta');
    ok(trim((string) ($ultimo['texto'] ?? '')) !== '', 'generado: tiene texto');
    ok(in_array($ultimo['clasificacion'] ?? '', BuzonEngine::CLASIFICACIONES, true), 'generado: clasificación válida');
    ok(!empty($ultimo['familia_mensajito']), 'generado: tiene familia_mensajito');
}

// --- 5) Residente inexistente: no rompe ---
$resultadoNoExiste = MensajitoGeneradorEspontaneo::evaluar($p2, 'per_inexistente', $cal, new RngService('gen-nx'));
ok($resultadoNoExiste === null, 'residente inexistente: retorna null');

echo "\n";
echo $failures === 0 ? "OK mensajitos_generador_espontaneo\n" : "FAIL mensajitos_generador_espontaneo ({$failures})\n";
exit($failures > 0 ? 1 : 0);