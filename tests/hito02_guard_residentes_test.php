<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\HistoriaPuebloEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\TutorialPrimerosPasos;
use AquiHayTema\Engine\TutorialIncorporaciones;

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

function hitoExiste(array $p, string $hitoId): bool
{
    foreach ($p['historia_pueblo'] ?? [] as $e) {
        if (($e['hito_id'] ?? '') === $hitoId) {
            return true;
        }
    }
    return false;
}

function contarHito(array $p, string $hitoId): int
{
    $n = 0;
    foreach ($p['historia_pueblo'] ?? [] as $e) {
        if (($e['hito_id'] ?? '') === $hitoId) {
            $n++;
        }
    }
    return $n;
}

function bitacoraTiene(array $p, string $tipo, array $par): bool
{
    $ids = $par;
    sort($ids, SORT_STRING);
    foreach ($p['bitacora_relaciones'] ?? [] as $h) {
        if (($h['tipo'] ?? '') !== $tipo) {
            continue;
        }
        $hPar = $h['par'] ?? [];
        sort($hPar, SORT_STRING);
        if ($hPar === $ids) {
            return true;
        }
    }
    return false;
}

$service = new PartidaService($root);

echo "=== GUARD hito_02: SE_CONOCIERON requiere ≥4 residentes ===\n\n";

// ── Escenario 1: 3 residentes + tutorial activo + SE_CONOCIERON ──────
echo "--- 1. 3 residentes + tutorial activo + SE_CONOCIERON ---\n";
$p1 = $service->nuevaPartida('juego_v1', 'hito02-g1-' . microtime(true));
$ids1 = array_slice(array_keys($p1['residentes']), 0, 3);
foreach ($ids1 as $rid) {
    $p1['residentes'][$rid]['presencia'] = 'residente';
}
ok(count(TutorialIncorporaciones::residentesActivos($p1)) === 3, '1.1 setup: exactamente 3 residentes activos');
ok(!empty($p1['tutorial']['activo']), '1.2 setup: tutorial activo');

RelacionBitacora::registrar($p1, RelacionBitacora::SE_CONOCIERON, [$ids1[0], $ids1[1]]);
ok(!hitoExiste($p1, 'hito_02'), '1.3 hito_02 NO registrado con 3 residentes + tutorial activo');
ok(bitacoraTiene($p1, RelacionBitacora::SE_CONOCIERON, [$ids1[0], $ids1[1]]), '1.4 bitácora SÍ registra SE_CONOCIERON');

// ── Escenario 2: 3 residentes + tutorial terminado + SE_CONOCIERON ───
echo "\n--- 2. 3 residentes + tutorial terminado + SE_CONOCIERON ---\n";
$p2 = $service->nuevaPartida('juego_v1', 'hito02-g2-' . microtime(true));
$ids2 = array_slice(array_keys($p2['residentes']), 0, 3);
foreach ($ids2 as $rid) {
    $p2['residentes'][$rid]['presencia'] = 'residente';
}
$p2['tutorial']['jugable_completado'] = true;
TutorialPrimerosPasos::marcarFinaleVisto($p2);
ok(count(TutorialIncorporaciones::residentesActivos($p2)) === 3, '2.1 setup: exactamente 3 residentes activos');
ok(!empty($p2['tutorial']['finale_visto']), '2.2 setup: tutorial terminado');

RelacionBitacora::registrar($p2, RelacionBitacora::SE_CONOCIERON, [$ids2[0], $ids2[1]]);
ok(!hitoExiste($p2, 'hito_02'), '2.3 hito_02 NO registrado con 3 residentes + tutorial terminado');
ok(bitacoraTiene($p2, RelacionBitacora::SE_CONOCIERON, [$ids2[0], $ids2[1]]), '2.4 bitácora SÍ registra SE_CONOCIERON');

// ── Escenario 3: 4 residentes + SE_CONOCIERON ────────────────────────
echo "\n--- 3. 4 residentes + SE_CONOCIERON ---\n";
$p3 = $service->nuevaPartida('juego_v1', 'hito02-g3-' . microtime(true));
$ids3 = array_slice(array_keys($p3['residentes']), 0, 3);
$p3['residentes']['per_test_hito02_4'] = ['presencia' => 'residente', 'catalog_id' => 'per_test_hito02_4'];
$ids3[] = 'per_test_hito02_4';
$p3['tutorial']['jugable_completado'] = true;
TutorialPrimerosPasos::marcarFinaleVisto($p3);
ok(count(TutorialIncorporaciones::residentesActivos($p3)) === 4, '3.1 setup: exactamente 4 residentes activos');

RelacionBitacora::registrar($p3, RelacionBitacora::SE_CONOCIERON, [$ids3[0], $ids3[1]]);
ok(hitoExiste($p3, 'hito_02'), '3.2 hito_02 SÍ registrado con 4 residentes');
ok(contarHito($p3, 'hito_02') === 1, '3.3 hito_02 exactamente 1 entrada');

// ── Escenario 4: 3 residentes + FLECHAZO (otro hito relacional) ──────
echo "\n--- 4. 3 residentes + FLECHAZO (hito_03) ---\n";
$p4 = $service->nuevaPartida('juego_v1', 'hito02-g4-' . microtime(true));
$ids4 = array_slice(array_keys($p4['residentes']), 0, 3);
foreach ($ids4 as $rid) {
    $p4['residentes'][$rid]['presencia'] = 'residente';
}
ok(count(TutorialIncorporaciones::residentesActivos($p4)) === 3, '4.1 setup: exactamente 3 residentes activos');

RelacionBitacora::registrar($p4, RelacionBitacora::FLECHAZO, [$ids4[0], $ids4[1]], $ids4[0] . '>' . $ids4[1]);
ok(hitoExiste($p4, 'hito_03'), '4.2 hito_03 SÍ registrado con 3 residentes (no bloqueado)');
ok(!hitoExiste($p4, 'hito_02'), '4.3 hito_02 no se registra (no hubo SE_CONOCIERON)');

// ── Escenario 5: 3 residentes, bitácora SÍ, historia NO ──────────────
echo "\n--- 5. Verificación: bitácora se registra siempre ---\n";
$p5 = $service->nuevaPartida('juego_v1', 'hito02-g5-' . microtime(true));
$ids5 = array_slice(array_keys($p5['residentes']), 0, 3);
foreach ($ids5 as $rid) {
    $p5['residentes'][$rid]['presencia'] = 'residente';
}
$antesBitacora = count($p5['bitacora_relaciones'] ?? []);
RelacionBitacora::registrar($p5, RelacionBitacora::SE_CONOCIERON, [$ids5[0], $ids5[1]]);
$despuesBitacora = count($p5['bitacora_relaciones'] ?? []);
ok($despuesBitacora === $antesBitacora + 1, '5.1 bitácora crece (+1 entrada)');
ok(!hitoExiste($p5, 'hito_02'), '5.2 historia_pueblo NO tiene hito_02');

echo "\n" . ($failures === 0 ? 'TODOS LOS TESTS PASARON' : "$failures tests FALLARON") . "\n";
exit($failures > 0 ? 1 : 0);
