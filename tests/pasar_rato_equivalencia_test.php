<?php
declare(strict_types=1);

/**
 * PASAR EL RATO — Test de equivalencia de regresión.
 *
 * Objetivo: blindar que el flujo real del botón "Pasar el rato"
 * (24 pulsaciones de 1h) es funcionalmente equivalente a
 * avanzarRelojPasoAPaso(24h), y que avanzarReloj(24h) batch NO lo es.
 *
 * Para simular vida del pueblo durante N horas NO usar batch como
 * sustituto de N ticks horarios.
 *
 * A) 24 avances de 1h (simula pasarElRato del cliente)
 * B) avanzarRelojPasoAPaso(24h) (pipeline canónico paso a paso)
 * C) avanzarReloj(24h) (batch — ejecuta tickHora una sola vez)
 *
 * Esperado: A ≡ B, C ≠ A/B.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$failures = 0;

function ok(bool $cond, string $msg): void
{
    global $failures;
    echo ($cond ? 'OK' : 'FAIL') . ": $msg\n";
    if (!$cond) {
        $failures++;
    }
}

function approxEq(int $a, int $b, string $msg, int $tolerance = 0): void
{
    ok(abs($a - $b) <= $tolerance, $msg . " (A=$a B=$b tol=$tolerance)");
}

function collectingHash(array $partida): array
{
    $hash = [];

    // Reloj final
    $hash['dia'] = (int) ($partida['reloj']['dia_pueblo'] ?? 0);
    $hash['hora'] = (int) ($partida['reloj']['hora_actual'] ?? 0);

    // Huecos de vida ejecutados (cuenta real de ticks que procesaron vida)
    $huecos = $partida['huecos_vida']['ejecutados'] ?? [];
    $hash['huecos_ejecutados_count'] = count($huecos);
    $hash['huecos_ejecutados'] = array_values($huecos);

    // Presupuesto de huecos del día
    $hash['huecos_presupuesto'] = (int) ($partida['huecos_vida']['presupuesto'] ?? 0);

    // Encuentros
    $encuentros = $partida['encuentros'] ?? [];
    $hash['encuentros_count'] = count($encuentros);
    $hash['encuentros_estados'] = array_map(
        fn($e) => $e['estado'] ?? '???',
        array_values($encuentros)
    );

    // Autonomía NPC (salidas individuales programadas)
    $autonomo = $partida['npc_autonomo'] ?? [];
    $hash['autonomo_historial_count'] = count($autonomo['historial_eventos'] ?? []);

    // Bitácora de relaciones
    $hash['bitacora_count'] = count($partida['bitacora_relaciones'] ?? []);

    // Romanza: suma total de romanticidad entre todos los pares
    $romanceTotal = 0;
    foreach ($partida['relaciones'] ?? [] as $par) {
        $romanceTotal += (int) ($par['romance_hacia'] ?? 0);
        $romanceTotal += (int) ($par['romance_desde'] ?? 0);
    }
    $hash['romance_total'] = $romanceTotal;

    // Emociones: cuántos residentes tienen estado emocional no neutro
    $emocionesActivas = 0;
    foreach ($partida['residentes'] ?? [] as $res) {
        $emo = $res['runtime']['estado_emocional']['id'] ?? 'neutro';
        if ($emo !== 'neutro') {
            $emocionesActivas++;
        }
    }
    $hash['emociones_activas'] = $emocionesActivas;

    // Domain events count (proxy de actividad general)
    $hash['domain_events_count'] = count($partida['domain_events'] ?? []);

    // Acontecimientos log
    $hash['acontecimientos_log_count'] = count($partida['acontecimientos_log'] ?? []);

    // Buzón
    $hash['buzon_count'] = count($partida['buzon'] ?? []);

    // Llegadas de NPC
    $hash['llegadas_count'] = count($partida['llegadas']['historial'] ?? []);

    return $hash;
}

// ============================================================
// ESCENARIO A: 24 avances individuales de 1h (simula pasarElRato)
// ============================================================
$seed = 'pasar-rato-equiv-v1';
$pa = $service->nuevaPartida('playtest_01', $seed);
$horasAvanzadasA = 0;
$ticksVidaA = 0;
for ($i = 0; $i < 24; $i++) {
    $r = $service->avanzarReloj($pa, 1);
    $horasAvanzadasA++;
    if (($r['ok'] ?? false) === false) {
        fwrite(STDERR, "FATAL: avanzarReloj fallo en paso $i: " . json_encode($r) . "\n");
        exit(1);
    }
    // Contar si tickHora ejecutó vida este tick
    $horaActual = (int) ($pa['reloj']['hora_actual'] ?? 0);
    if ($horaActual >= 9 && $horaActual <= 22) {
        $ticksVidaA++;
    }
}
$hashA = collectingHash($pa);

// ============================================================
// ESCENARIO B: avanzarRelojPasoAPaso(24h)
// ============================================================
$pb = $service->nuevaPartida('playtest_01', $seed);
$rB = $service->avanzarRelojPasoAPaso($pb, 24);
ok(($rB['ok'] ?? false) === true, 'B: avanzarRelojPasoAPaso ok');
ok((int) ($rB['pasos'] ?? 0) === 24, 'B: 24 pasos ejecutados');
$hashB = collectingHash($pb);

// ============================================================
// ESCENARIO C: avanzarReloj(24h) batch
// ============================================================
$pc = $service->nuevaPartida('playtest_01', $seed);
$rC = $service->avanzarReloj($pc, 24);
ok(($rC['ok'] ?? false) === true, 'C: avanzarReloj batch ok');
$hashC = collectingHash($pc);

// ============================================================
// VALIDACIÓN A ≡ B (paso a paso = pasar el rato)
// ============================================================
echo "\n--- A vs B (esperado: equivalentes) ---\n";
ok($hashA['dia'] === $hashB['dia'], "A≡B: día final {$hashA['dia']} == {$hashB['dia']}");
ok($hashA['hora'] === $hashB['hora'], "A≡B: hora final {$hashA['hora']} == {$hashB['hora']}");
ok(
    $hashA['huecos_ejecutados_count'] === $hashB['huecos_ejecutados_count'],
    "A≡B: huecos ejecutados {$hashA['huecos_ejecutados_count']} == {$hashB['huecos_ejecutados_count']}"
);
ok(
    $hashA['huecos_presupuesto'] === $hashB['huecos_presupuesto'],
    "A≡B: presupuesto huecos {$hashA['huecos_presupuesto']} == {$hashB['huecos_presupuesto']}"
);
ok(
    $hashA['encuentros_count'] === $hashB['encuentros_count'],
    "A≡B: encuentros count {$hashA['encuentros_count']} == {$hashB['encuentros_count']}"
);
ok(
    $hashA['encuentros_estados'] === $hashB['encuentros_estados'],
    "A≡B: encuentros estados iguales"
);
ok(
    $hashA['autonomo_historial_count'] === $hashB['autonomo_historial_count'],
    "A≡B: autonomía NPCs {$hashA['autonomo_historial_count']} == {$hashB['autonomo_historial_count']}"
);
ok(
    $hashA['bitacora_count'] === $hashB['bitacora_count'],
    "A≡B: bitácora relaciones {$hashA['bitacora_count']} == {$hashB['bitacora_count']}"
);
ok(
    $hashA['romance_total'] === $hashB['romance_total'],
    "A≡B: romance total {$hashA['romance_total']} == {$hashB['romance_total']}"
);
ok(
    $hashA['emociones_activas'] === $hashB['emociones_activas'],
    "A≡B: emociones activas {$hashA['emociones_activas']} == {$hashB['emociones_activas']}"
);
ok(
    $hashA['acontecimientos_log_count'] === $hashB['acontecimientos_log_count'],
    "A≡B: acontecimientos log {$hashA['acontecimientos_log_count']} == {$hashB['acontecimientos_log_count']}"
);
ok(
    $hashA['buzon_count'] === $hashB['buzon_count'],
    "A≡B: buzon count {$hashA['buzon_count']} == {$hashB['buzon_count']}"
);
ok(
    $hashA['domain_events_count'] === $hashB['domain_events_count'],
    "A≡B: domain events {$hashA['domain_events_count']} == {$hashB['domain_events_count']}"
);

// ============================================================
// VALIDACIÓN C ≠ A/B (batch pierde ticks)
// ============================================================
echo "\n--- C vs A (esperado: batch DIFERENTE — demuestra bug del harness antiguo) ---\n";
$batchDiferente = false;
$checksC = [
    'dia' => $hashC['dia'] !== $hashA['dia'],
    'hora' => $hashC['hora'] !== $hashA['hora'],
    'huecos_ejecutados' => $hashC['huecos_ejecutados_count'] !== $hashA['huecos_ejecutados_count'] || $hashC['huecos_ejecutados'] !== $hashA['huecos_ejecutados'],
    'encuentros_count' => $hashC['encuentros_count'] !== $hashA['encuentros_count'],
    'autonomo' => $hashC['autonomo_historial_count'] !== $hashA['autonomo_historial_count'],
    'bitacora' => $hashC['bitacora_count'] !== $hashA['bitacora_count'],
    'romance' => $hashC['romance_total'] !== $hashA['romance_total'],
    'acontecimientos' => $hashC['acontecimientos_log_count'] !== $hashA['acontecimientos_log_count'],
];

foreach ($checksC as $campo => $diferente) {
    if ($diferente) {
        $batchDiferente = true;
    }
    $va = is_array($hashA[$campo] ?? null) ? json_encode($hashA[$campo]) : ($hashA[$campo] ?? '?');
    $vb = is_array($hashB[$campo] ?? null) ? json_encode($hashB[$campo]) : ($hashB[$campo] ?? '?');
    $vc = is_array($hashC[$campo] ?? null) ? json_encode($hashC[$campo]) : ($hashC[$campo] ?? '?');
    echo ($diferente ? 'DIFF' : 'SAME') . ": C.$campo (A=$va B=$vb C=$vc)\n";
}

// Al menos UNA diferencia funcional demostrable entre batch y paso a paso
ok($batchDiferente, 'C: batch difiere de A en al menos un campo funcional (tickHora subejecutado)');

// ============================================================
// RESUMEN DE Ticks de vida
// ============================================================
echo "\n--- Ticks de vida (hora 9-22) ---\n";
echo "A (24x1h): $ticksVidaA ticks de vida\n";
// B debería tener los mismos ticks que A
$horaActualB = (int) ($pb['reloj']['hora_actual'] ?? 0);
$diaActualB = (int) ($pb['reloj']['dia_pueblo'] ?? 1);
$horaInicial = (int) ($pb['reloj']['hora_actual'] ?? 0); // ya avanzado
// En B, como se ejecuta avanzar(p,1) 24 veces, los ticks de vida son los mismos
echo "B (pasoAPaso 24h): ejecutó 24 ticks (avanzar × 24)\n";
echo "C (batch 24h): ejecutó 1 tick (avanzar × 1)\n";

// ============================================================
// DOCUMENTACIÓN: Protección para futuros harnesses
// ============================================================
echo "\n=== PROTECCIÓN DE TOOLING ===\n";
echo "Para simular vida del pueblo durante N horas NO usar batch\n";
echo "como sustituto de N ticks horarios.\n";
echo "Usar avanzarRelojPasoAPaso() o loop de avanzar(p, 1).\n";
echo "El batch solo mueve el reloj y ejecuta el pipeline una vez.\n";

// ============================================================
// RESULTADO
// ============================================================
if ($failures === 0) {
    echo "\npasar_rato_equivalencia_test OK\n";
    exit(0);
}
echo "\npasar_rato_equivalencia_test FAIL ($failures)\n";
exit(1);
