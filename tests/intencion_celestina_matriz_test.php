<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\IntencionCelestina;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\CalibracionConfig;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$cal = CalibracionConfig::load($root);
$failures = 0;
$passed = 0;

function ok(bool $c, string $m): void
{
    global $failures, $passed;
    if ($c) {
        $passed++;
        echo "OK: $m\n";
    } else {
        $failures++;
        echo "FAIL: $m\n";
    }
}

function expectEstado(array $resultado, string $intencionId, string $esperado, string $test): void
{
    foreach ($resultado as $item) {
        if ($item['id'] === $intencionId) {
            ok($item['estado'] === $esperado, "$test — {$intencionId}: esperaba '$esperado', got '{$item['estado']}'");
            return;
        }
    }
    ok(false, "$test — intención '$intencionId' no encontrada en resultado");
}

function crearPartidaConResidentes(int $n = 2): array
{
    global $service;
    $partida = $service->nuevaPartida('test_fixtures_v0', 'intencion-test-' . mt_rand());
    $residentes = [];
    for ($i = 0; $i < $n; $i++) {
        $ph = $service->crearResidentePlaceholderDev($partida);
        $residentes[] = $ph['residente']['catalog_id'];
    }
    return [$partida, $residentes];
}

// ═══════════════════════════════════════════════════════════════
// TEST 1 — Desconocidos → Romper el hielo disponible
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 1: desconocidos → Romper el hielo ---\n";
[$p1, [$a1, $b1]] = crearPartidaConResidentes();
$r1 = IntencionCelestina::disponiblesPara($p1, $a1, $b1, $cal);
expectEstado($r1, IntencionCelestina::PRESENTAR, 'visible', 'T1');
expectEstado($r1, IntencionCelestina::PASAR_RATO, 'oculta', 'T1');
expectEstado($r1, IntencionCelestina::HACER_PINA, 'oculta', 'T1');
expectEstado($r1, IntencionCelestina::VER_CHISPA, 'oculta', 'T1');
expectEstado($r1, IntencionCelestina::HACER_PACES, 'oculta', 'T1');

// ═══════════════════════════════════════════════════════════════
// TEST 2 — Conocidos → Romper el hielo oculto
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 2: conocidos → Romper el hielo oculto ---\n";
[$p2, [$a2, $b2]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p2, $a2, $b2, 'conocidos', 2, true);
$r2 = IntencionCelestina::disponiblesPara($p2, $a2, $b2, $cal);
expectEstado($r2, IntencionCelestina::PRESENTAR, 'oculta', 'T2');
expectEstado($r2, IntencionCelestina::PASAR_RATO, 'visible', 'T2');

// ═══════════════════════════════════════════════════════════════
// TEST 3 — Conocidos sin señal → Ver si hay chispa visible si elegibles
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 3: conocidos sin señal → chispa visible si elegibles ---\n";
[$p3, [$a3, $b3]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p3, $a3, $b3, 'conocidos', 5, true);
$r3 = IntencionCelestina::disponiblesPara($p3, $a3, $b3, $cal);
$estadoChispa3 = 'oculta';
foreach ($r3 as $item) {
    if ($item['id'] === IntencionCelestina::VER_CHISPA) {
        $estadoChispa3 = $item['estado'];
        break;
    }
}
ok(
    $estadoChispa3 === 'visible' || $estadoChispa3 === 'bloqueada',
    "T3 — chispa aparece (visible o bloqueada, NO oculta) para conocidos elegibles: got '$estadoChispa3'"
);

// ═══════════════════════════════════════════════════════════════
// TEST 4 — Chispa con conflicto resuelto → vuelve a ser disponible
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 4: chispa no bloqueada permanentemente por conflicto ---\n";
[$p4, [$a4, $b4]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p4, $a4, $b4, 'conocidos', 5, true);
// Con conflicto activo: chispa oculta
RelacionEngine::upsertConflicto($p4, $a4, $b4, 10, 'roce');
$r4a = IntencionCelestina::disponiblesPara($p4, $a4, $b4, $cal);
expectEstado($r4a, IntencionCelestina::VER_CHISPA, 'oculta', 'T4a — chispa oculta con conflicto');
// Resolver conflicto (intensidad a 0)
RelacionEngine::upsertConflicto($p4, $a4, $b4, 0);
// Avanzar reloj 15 días para que discusión_fuerte reciente expire
$p4['reloj']['dia_pueblo'] = 20;
// Eliminar discusiones fuertes de la bitácora para este par
$p4['bitacora_relaciones'] = array_values(array_filter(
    $p4['bitacora_relaciones'] ?? [],
    function ($h) use ($a4, $b4) {
        if (($h['tipo'] ?? '') !== RelacionBitacora::DISCUSION_FUERTE) {
            return true;
        }
        $par = $h['par'] ?? [];
        $ids = [$a4, $b4];
        sort($ids);
        return $par !== $ids;
    }
));
$r4b = IntencionCelestina::disponiblesPara($p4, $a4, $b4, $cal);
$chispaVisible = false;
foreach ($r4b as $item) {
    if ($item['id'] === IntencionCelestina::VER_CHISPA && $item['estado'] === 'visible') {
        $chispaVisible = true;
        break;
    }
}
ok($chispaVisible, 'T4 — chispa vuelve a ser visible tras resolver conflicto');

// ═══════════════════════════════════════════════════════════════
// TEST 5 — buen_amigo → Hacer piña sigue disponible
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 5: buen_amigo → Hacer piña visible ---\n";
[$p5, [$a5, $b5]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p5, $a5, $b5, 'buen_amigo', 70, true);
$r5 = IntencionCelestina::disponiblesPara($p5, $a5, $b5, $cal);
expectEstado($r5, IntencionCelestina::HACER_PINA, 'visible', 'T5');
expectEstado($r5, IntencionCelestina::PASAR_RATO, 'visible', 'T5');

// ═══════════════════════════════════════════════════════════════
// TEST 6 — mejor_amigo mutuo → Hacer piña oculta
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 6: mejor_amigo mutuo → Hacer piña oculta ---\n";
[$p6, [$a6, $b6]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p6, $a6, $b6, 'mejor_amigo', 85, true);
RelacionEngine::upsertSocial($p6, $b6, $a6, 'mejor_amigo', 85, true);
$r6 = IntencionCelestina::disponiblesPara($p6, $a6, $b6, $cal);
expectEstado($r6, IntencionCelestina::HACER_PINA, 'oculta', 'T6');
expectEstado($r6, IntencionCelestina::PASAR_RATO, 'visible', 'T6');

// ═══════════════════════════════════════════════════════════════
// TEST 7 — Pareja estable → Pasar el rato disponible
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 7: pareja estable → Pasar el rato visible ---\n";
[$p7, [$a7, $b7]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p7, $a7, $b7, 'amigos', 10, true);
ParejaEngine::formar($p7, $a7, $b7, true, true);
$r7 = IntencionCelestina::disponiblesPara($p7, $a7, $b7, $cal);
expectEstado($r7, IntencionCelestina::PASAR_RATO, 'visible', 'T7');
expectEstado($r7, IntencionCelestina::HACER_PACES, 'oculta', 'T7 — no hay conflicto');

// ═══════════════════════════════════════════════════════════════
// TEST 8 — Pareja en crisis → Pasar el rato NO disponible
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 8: pareja en crisis → Pasar el rato oculto ---\n";
[$p8, [$a8, $b8]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p8, $a8, $b8, 'amigos', 10, true);
ParejaEngine::formar($p8, $a8, $b8, true, true);
ParejaEngine::crisis($p8, $a8, $b8);
$r8 = IntencionCelestina::disponiblesPara($p8, $a8, $b8, $cal);
expectEstado($r8, IntencionCelestina::PASAR_RATO, 'oculta', 'T8');
expectEstado($r8, IntencionCelestina::HACER_PACES, 'visible', 'T8');

// ═══════════════════════════════════════════════════════════════
// TEST 9 — Pareja en crisis → sólo Hacer las paces visible
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 9: pareja crisis → sólo HP visible ---\n";
[$p9, [$a9, $b9]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p9, $a9, $b9, 'amigos', 10, true);
ParejaEngine::formar($p9, $a9, $b9, true, true);
ParejaEngine::crisis($p9, $a9, $b9);
$r9 = IntencionCelestina::disponiblesPara($p9, $a9, $b9, $cal);
expectEstado($r9, IntencionCelestina::HACER_PACES, 'visible', 'T9');
expectEstado($r9, IntencionCelestina::VER_CHISPA, 'oculta', 'T9 — pareja en crisis');
expectEstado($r9, IntencionCelestina::HACER_PINA, 'oculta', 'T9 — pareja en crisis');
expectEstado($r9, IntencionCelestina::PRESENTAR, 'oculta', 'T9 — ya se conocen');

// ═══════════════════════════════════════════════════════════════
// TEST 10 — Conflicto muy alto → Hacer las paces bloqueada
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 10: conflicto muy alto → HP bloqueada ---\n";
[$p10, [$a10, $b10]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p10, $a10, $b10, 'conocidos', 2, true);
RelacionEngine::upsertConflicto($p10, $a10, $b10, 80, 'roce');
$r10 = IntencionCelestina::disponiblesPara($p10, $a10, $b10, $cal);
expectEstado($r10, IntencionCelestina::HACER_PACES, 'bloqueada', 'T10');
expectEstado($r10, IntencionCelestina::PASAR_RATO, 'oculta', 'T10 — conflicto activo');
expectEstado($r10, IntencionCelestina::VER_CHISPA, 'oculta', 'T10 — conflicto activo');
expectEstado($r10, IntencionCelestina::HACER_PINA, 'oculta', 'T10 — conflicto activo');

// ═══════════════════════════════════════════════════════════════
// TEST 11 — Enemigo → romance/socialización directa oculta
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 11: enemigo → opciones absurdas ocultas ---\n";
[$p11, [$a11, $b11]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p11, $a11, $b11, 'enemigos', -80, false);
RelacionEngine::upsertSocial($p11, $b11, $a11, 'enemigos', -80, false);
$r11 = IntencionCelestina::disponiblesPara($p11, $a11, $b11, $cal);
expectEstado($r11, IntencionCelestina::VER_CHISPA, 'oculta', 'T11 — enemigo, sin romance');
expectEstado($r11, IntencionCelestina::HACER_PINA, 'oculta', 'T11 — enemigo, sin amistad');
expectEstado($r11, IntencionCelestina::PASAR_RATO, 'oculta', 'T11 — enemigo, sin quedada');
expectEstado($r11, IntencionCelestina::HACER_PACES, 'oculta', 'T11 — sin conflicto activo');

// ═══════════════════════════════════════════════════════════════
// TEST 12 — Ex pareja → Hacer las paces disponible, no reconciliación automática
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 12: ex pareja → HP disponible ---\n";
[$p12, [$a12, $b12]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p12, $a12, $b12, 'amigos', 10, true);
ParejaEngine::formar($p12, $a12, $b12, true, true);
ParejaEngine::romper($p12, $a12, $b12);
$r12 = IntencionCelestina::disponiblesPara($p12, $a12, $b12, $cal);
$hpEstado = 'oculta';
foreach ($r12 as $item) {
    if ($item['id'] === IntencionCelestina::HACER_PACES) {
        $hpEstado = $item['estado'];
        break;
    }
}
ok(in_array($hpEstado, ['visible', 'bloqueada'], true), "T12 — ex: HP visible o bloqueada, NO oculta: got '$hpEstado'");
ok(ParejaEngine::estado($p12, $a12, $b12) === ParejaEngine::EX, 'T12 — sigue siendo ex, sin reconciliación automática');

// ═══════════════════════════════════════════════════════════════
// TEST 13 — disponiblesPara NO muta la partida
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 13: disponiblesPara idempotente ---\n";
[$p13, [$a13, $b13]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p13, $a13, $b13, 'conocidos', 5, true);
$snapshotBefore = json_encode($p13);
IntencionCelestina::disponiblesPara($p13, $a13, $b13, $cal);
$snapshotAfter = json_encode($p13);
ok($snapshotBefore === $snapshotAfter, 'T13 — disponiblesPara no muta partida');

// ═══════════════════════════════════════════════════════════════
// TEST 14 — Identificadores internos estables y labels correctos
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 14: identificadores y labels ---\n";
$map = IntencionCelestina::map();
ok(count($map) === 5, 'T14 — 5 intenciones en catálogo');
$expectedLabels = [
    'presentar' => 'Romper el hielo',
    'pasar_rato' => 'Pasar el rato',
    'hacer_pina' => 'Hacer piña',
    'ver_chispa' => 'Ver si hay chispa',
    'hacer_paces' => 'Hacer las paces',
];
foreach ($map as $item) {
    $id = $item['id'];
    ok(isset($expectedLabels[$id]), "T14 — id '$id' es válido");
    ok($item['label'] === ($expectedLabels[$id] ?? ''), "T14 — label para '$id': esperaba '{$expectedLabels[$id]}', got '{$item['label']}'");
    ok($item['tipo_encuentro'] !== '', "T14 — tipo_encuentro no vacío para '$id'");
}

// ═══════════════════════════════════════════════════════════════
// TEST 15 — Contrato organizar tiene shape correcto
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 15: contratoOrganizar ---\n";
$contrato = IntencionCelestina::contratoOrganizar();
ok($contrato['version'] === 'p1', 'T15 — version = p1');
ok(count($contrato['intenciones']) === 5, 'T15 — 5 intenciones en contrato');
ok(count($contrato['estados']) === 3, 'T15 — 3 estados posibles');
ok(is_array($contrato['motivos_bloqueo']), 'T15 — motivos_bloqueo es array');

// ═══════════════════════════════════════════════════════════════
// TEST 16 — Conflicto moderado → Hacer las paces visible (no bloqueada)
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 16: conflicto moderado → HP visible ---\n";
[$p16, [$a16, $b16]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p16, $a16, $b16, 'conocidos', 2, true);
RelacionEngine::upsertConflicto($p16, $a16, $b16, 5, 'roce');
$r16 = IntencionCelestina::disponiblesPara($p16, $a16, $b16, $cal);
expectEstado($r16, IntencionCelestina::HACER_PACES, 'visible', 'T16');
expectEstado($r16, IntencionCelestina::PASAR_RATO, 'oculta', 'T16 — conflicto activo');

// ═══════════════════════════════════════════════════════════════
// TEST 17 — Pareja estable → HP y VC ocultas, PR visible
// ═══════════════════════════════════════════════════════════════
echo "\n--- TEST 17: pareja estable → HP/VC ocultas ---\n";
[$p17, [$a17, $b17]] = crearPartidaConResidentes();
RelacionEngine::upsertSocial($p17, $a17, $b17, 'amigos', 10, true);
ParejaEngine::formar($p17, $a17, $b17, true, true);
$r17 = IntencionCelestina::disponiblesPara($p17, $a17, $b17, $cal);
expectEstado($r17, IntencionCelestina::HACER_PINA, 'oculta', 'T17 — pareja no necesita piña');
expectEstado($r17, IntencionCelestina::VER_CHISPA, 'oculta', 'T17 — pareja no necesita chispa');
expectEstado($r17, IntencionCelestina::PASAR_RATO, 'visible', 'T17 — pareja puede pasar el rato');
expectEstado($r17, IntencionCelestina::PRESENTAR, 'oculta', 'T17 — ya se conocen');

// ═══════════════════════════════════════════════════════════════
// RESUMEN
// ═══════════════════════════════════════════════════════════════
echo "\n════════════════════════════════════════════════\n";
echo "PASSED: $passed\n";
echo "FAILED: $failures\n";
echo "════════════════════════════════════════════════\n";

exit($failures > 0 ? 1 : 0);
