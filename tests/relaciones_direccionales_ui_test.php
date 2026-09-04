<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelacionVistaJugador;

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

$service = new PartidaService($root);
$cal = CalibracionConfig::load($root);

// ── CASO 1: A→B social=Conocido + romance=MeGusta ──
$p1 = $service->nuevaPartida('test_fixtures_v0', 'dir-caso1');
$a1 = 'per_qa_valid';
$ph1 = $service->crearResidentePlaceholderDev($p1);
$b1 = $ph1['residente']['catalog_id'];

RelacionEngine::upsertSocial($p1, $a1, $b1, 'conocidos', 2, true);
RelacionEngine::setRomanceHacia($p1, $a1, $b1, 12);
RelacionBitacora::registrar($p1, RelacionBitacora::FLECHAZO, [$a1, $b1], $a1 . '>' . $b1);

$va1 = RelacionVistaJugador::de($p1, $a1, $b1, $cal);
ok($va1['conocidos'] === true, 'CASO 1: A→B conocidos');
ok($va1['romance_visible'] === true, 'CASO 1: A→B romance_visible');
ok($va1['etiqueta_romance'] === 'Me gusta', 'CASO 1: A→B etiqueta_romance = Me gusta');
ok($va1['etiqueta_social'] !== null, 'CASO 1: A→B tiene etiqueta_social');

// Ficha de A muestra ambos canales
$fichaA1 = $service->fichaResidente($p1, $a1);
$relB1 = $fichaA1['relaciones'][$b1] ?? null;
ok($relB1 !== null, 'CASO 1: ficha A contiene a B');
ok($relB1['romance_visible'] === true, 'CASO 1: ficha A → B romance_visible');
ok($relB1['etiqueta_romance'] === 'Me gusta', 'CASO 1: ficha A → B etiqueta_romance');
ok($relB1['conocidos'] === true, 'CASO 1: ficha A → B conocidos');

// ── CASO 2: B→A social=Conocido, sin romance ──
$vb1 = RelacionVistaJugador::de($p1, $b1, $a1, $cal);
ok($vb1['romance_visible'] === false, 'CASO 2: B→A sin romance_visible');

$fichaB1 = $service->fichaResidente($p1, $b1);
$relA1 = $fichaB1['relaciones'][$a1] ?? null;
ok($relA1 !== null, 'CASO 2: ficha B contiene a A');
ok($relA1['romance_visible'] === false, 'CASO 2: ficha B → A sin romance_visible');
ok($relA1['etiqueta_romance'] === null, 'CASO 2: ficha B → A etiqueta_romance null');

// ── CASO 3: Vista global conserva direcciones distintas sin badge por asim ──
$vGlobal1 = $service->vistaRelacionesPueblo($p1);
$par1 = null;
foreach ($vGlobal1['relaciones'] ?? [] as $fila) {
    $idA = $fila['persona_a']['id'] ?? '';
    $idB = $fila['persona_b']['id'] ?? '';
    if (($idA === $a1 && $idB === $b1) || ($idA === $b1 && $idB === $a1)) {
        $par1 = $fila;
        break;
    }
}
ok($par1 !== null, 'CASO 3: vista global incluye par A-B');
$abDir = $par1['a_hacia_b'] ?? [];
$baDir = $par1['b_hacia_a'] ?? [];
ok($abDir['romance_visible'] === true || $baDir['romance_visible'] === true, 'CASO 3: al menos una dirección con romance_visible');
// Badge se alimenta de f.conflicto. Sin relaciones_conflicto → conflicto=null → sin badge
ok(($par1['conflicto'] ?? null) === null, 'CASO 3: asimetría SIN conflicto → conflicto=null en DTO → badge NO');

// ── CASO 4: Social asimétrico sin conflicto ──
$p4 = $service->nuevaPartida('test_fixtures_v0', 'dir-caso4');
$a4 = 'per_qa_valid';
$ph4 = $service->crearResidentePlaceholderDev($p4);
$b4 = $ph4['residente']['catalog_id'];

RelacionEngine::upsertSocial($p4, $a4, $b4, 'amigos', 20, true);
RelacionEngine::upsertSocial($p4, $b4, $a4, 'conocidos', 3, true);

$va4 = RelacionVistaJugador::de($p4, $a4, $b4, $cal);
$vb4 = RelacionVistaJugador::de($p4, $b4, $a4, $cal);
ok($va4['social_valor'] !== $vb4['social_valor'], 'CASO 4: social asimétrico A→B ≠ B→A');
ok($va4['etiqueta_social'] !== $vb4['etiqueta_social'], 'CASO 4: etiquetas sociales distintas');
ok($va4['romance_visible'] === false, 'CASO 4: sin romance');
ok($vb4['romance_visible'] === false, 'CASO 4: sin romance inverse');

// Vista global: sin conflicto → no badge
$vGlobal4 = $service->vistaRelacionesPueblo($p4);
$par4 = null;
foreach ($vGlobal4['relaciones'] ?? [] as $fila) {
    $idA = $fila['persona_a']['id'] ?? '';
    $idB = $fila['persona_b']['id'] ?? '';
    if (($idA === $a4 && $idB === $b4) || ($idA === $b4 && $idB === $a4)) {
        $par4 = $fila;
        break;
    }
}
ok($par4 !== null, 'CASO 4: vista global incluye par');
ok(($par4['conflicto'] ?? null) === null, 'CASO 4: sin conflicto en vista global');

// ── CASO 5: Conflicto real → badge ──
$p5 = $service->nuevaPartida('test_fixtures_v0', 'dir-caso5');
$a5 = 'per_qa_valid';
$ph5 = $service->crearResidentePlaceholderDev($p5);
$b5 = $ph5['residente']['catalog_id'];

RelacionEngine::upsertSocial($p5, $a5, $b5, 'conocidos', 5, true);
RelacionEngine::upsertConflicto($p5, $a5, $b5, 15, 'roce');

$vGlobal5 = $service->vistaRelacionesPueblo($p5);
$par5 = null;
foreach ($vGlobal5['relaciones'] ?? [] as $fila) {
    $idA = $fila['persona_a']['id'] ?? '';
    $idB = $fila['persona_b']['id'] ?? '';
    if (($idA === $a5 && $idB === $b5) || ($idA === $b5 && $idB === $a5)) {
        $par5 = $fila;
        break;
    }
}
ok($par5 !== null, 'CASO 5: vista global incluye par con conflicto');
ok($par5['conflicto'] !== null, 'CASO 5: conflicto presente → badge SÍ');

// ── CASO 6: Conflicto + asimetría → badge por conflicto ──
$p6 = $service->nuevaPartida('test_fixtures_v0', 'dir-caso6');
$a6 = 'per_qa_valid';
$ph6 = $service->crearResidentePlaceholderDev($p6);
$b6 = $ph6['residente']['catalog_id'];

RelacionEngine::upsertSocial($p6, $a6, $b6, 'amigos', 20, true);
RelacionEngine::upsertSocial($p6, $b6, $a6, 'conocidos', 3, true);
RelacionEngine::setRomanceHacia($p6, $a6, $b6, 12);
RelacionBitacora::registrar($p6, RelacionBitacora::FLECHAZO, [$a6, $b6], $a6 . '>' . $b6);
RelacionEngine::upsertConflicto($p6, $a6, $b6, 10, 'roce');

$va6 = RelacionVistaJugador::de($p6, $a6, $b6, $cal);
$vb6 = RelacionVistaJugador::de($p6, $b6, $a6, $cal);
ok($va6['romance_visible'] === true, 'CASO 6: A→B romance visible');
ok($vb6['romance_visible'] === false, 'CASO 6: B→A sin romance');
ok($va6['social_valor'] !== $vb6['social_valor'], 'CASO 6: social asimétrico');

$vGlobal6 = $service->vistaRelacionesPueblo($p6);
$par6 = null;
foreach ($vGlobal6['relaciones'] ?? [] as $fila) {
    $idA = $fila['persona_a']['id'] ?? '';
    $idB = $fila['persona_b']['id'] ?? '';
    if (($idA === $a6 && $idB === $b6) || ($idA === $b6 && $idB === $a6)) {
        $par6 = $fila;
        break;
    }
}
ok($par6 !== null, 'CASO 6: vista global incluye par');
ok($par6['conflicto'] !== null, 'CASO 6: conflicto presente → badge SÍ (por conflicto, no por asim)');

echo "\n" . ($failures === 0 ? "OK relaciones_direccionales_ui" : "FAIL relaciones_direccionales_ui ({$failures})") . "\n";
exit($failures > 0 ? 1 : 0);
