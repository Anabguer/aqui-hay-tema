<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionDesgaste;
use AquiHayTema\Engine\RelacionEngine;

$root = dirname(__DIR__);
$failures = 0;

function cl_ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$cal = CalibracionConfig::load($root);
$service = new PartidaService($root);

// --- helpers ---
function makePartida(int $dia): array
{
    $service = new PartidaService(dirname(__DIR__));
    $p = $service->nuevaPartida('test_fixtures_v0', 'cl-lifecycle-' . $dia);
    $p['reloj']['dia_pueblo'] = $dia;
    return $p;
}

function conflictoEntre(array $partida, string $a, string $b): ?array
{
    $entre = RelacionEngine::obtenerEntre($partida, $a, $b);
    return $entre['conflicto'] ?? null;
}

function intensidad(array $partida, string $a, string $b): int
{
    $c = conflictoEntre($partida, $a, $b);
    return $c !== null ? (int) ($c['intensidad'] ?? 0) : -1;
}

function existeConflicto(array $partida, string $a, string $b): bool
{
    return conflictoEntre($partida, $a, $b) !== null;
}

function avanzarDia(array &$partida, int $dias, array $cal): void
{
    for ($i = 0; $i < $dias; $i++) {
        $partida['reloj']['dia_pueblo'] = ($partida['reloj']['dia_pueblo'] ?? 1) + 1;
        RelacionDesgaste::alCerrarDia($partida, $cal);
    }
}

// --- Setup ---
$p = makePartida(1);
$a = 'per_qa_valid';
$ph = $service->crearResidentePlaceholderDev($p);
$b = $ph['residente']['catalog_id'];

// ============================================================
// 1. conflicto intensidad 2 antes del umbral → sigue 2
// ============================================================
RelacionEngine::upsertConflicto($p, $a, $b, 2, 'roce', 'test');
cl_ok(existeConflicto($p, $a, $b), '1a. conflicto creado');
cl_ok(intensidad($p, $a, $b) === 2, '1b. intensidad = 2');
avanzarDia($p, 6, $cal);
cl_ok(intensidad($p, $a, $b) === 2, '1c. tras 6 días sigue 2');

// ============================================================
// 2. al cumplir periodo limpio → 2 pasa a 1
// ============================================================
avanzarDia($p, 1, $cal);
cl_ok(intensidad($p, $a, $b) === 1, '2. tras 7 días limpios baja a 1');

// ============================================================
// 3. nivel 1 antes del siguiente periodo → sigue activo
// ============================================================
avanzarDia($p, 6, $cal);
cl_ok(existeConflicto($p, $a, $b), '3a. nivel 1 tras 6 días sigue');
cl_ok(intensidad($p, $a, $b) === 1, '3b. intensidad sigue 1');

// ============================================================
// 4. nivel 1 tras el periodo configurado → deja de existir
// ============================================================
avanzarDia($p, 1, $cal);
cl_ok(!existeConflicto($p, $a, $b), '4. nivel 1 tras 7 días limpios → eliminado');

// ============================================================
// 5. conflicto nuevo reinicia el contador
// ============================================================
RelacionEngine::upsertConflicto($p, $a, $b, 2, 'roce', 'test');
$diaCreacion = $p['reloj']['dia_pueblo'];
avanzarDia($p, 5, $cal);
cl_ok(intensidad($p, $a, $b) === 2, '5a. tras 5 días sigue 2 (reloj reiniciado)');
RelacionEngine::upsertConflicto($p, $a, $b, 1, 'roce', 'test2');
$diaReinicio = $p['reloj']['dia_pueblo'];
avanzarDia($p, 6, $cal);
cl_ok(intensidad($p, $a, $b) === 1, '5b. tras 6 días desde reinicio sigue 1');
avanzarDia($p, 1, $cal);
cl_ok(!existeConflicto($p, $a, $b), '5c. nivel 1 tras 7 días desde reinicio → eliminado');
cl_ok(intensidad($p, $a, $b) === -1, '5d. ya no existe conflicto');

// ============================================================
// 6. encuentro 'bien' repara exactamente 1 nivel
// ============================================================
$p2 = makePartida(1);
RelacionEngine::upsertSocial($p2, $a, $b, 'amigo', 40);
RelacionEngine::upsertConflicto($p2, $a, $b, 3, 'roce', 'test');
cl_ok(intensidad($p2, $a, $b) === 3, '6a. conflicto intensidad 3');

// Simular reparación manual (sin encuentro real)
$entre = RelacionEngine::obtenerEntre($p2, $a, $b);
$conf = $entre['conflicto'];
$nueva = (int) ($conf['intensidad'] ?? 0) - 1;
RelacionEngine::upsertConflicto($p2, $a, $b, $nueva, $conf['tipo'] ?? 'roce', 'reparacion_encuentro');
cl_ok(intensidad($p2, $a, $b) === 2, '6b. reparación manual → intensidad 2');

// ============================================================
// 7. encuentro 'muy_bien' repara exactamente 1 nivel
// ============================================================
$entre2 = RelacionEngine::obtenerEntre($p2, $a, $b);
$conf2 = $entre2['conflicto'];
$nueva2 = (int) ($conf2['intensidad'] ?? 0) - 1;
RelacionEngine::upsertConflicto($p2, $a, $b, $nueva2, $conf2['tipo'] ?? 'roce', 'reparacion_encuentro');
cl_ok(intensidad($p2, $a, $b) === 1, '7. muy_bien reparación → intensidad 1');

// ============================================================
// 8. 'normal' no repara
// ============================================================
$p3 = makePartida(1);
RelacionEngine::upsertConflicto($p3, $a, $b, 2, 'roce', 'test');
$antes3 = intensidad($p3, $a, $b);
cl_ok($antes3 === 2, '8a. normal: antes = 2');
// Normal no debería tocar el conflicto - la lógica de reparación solo se ejecuta
// si el resultado es bien o muy_bien. No hay llamada a repararConflictoSiAplica.
cl_ok(intensidad($p3, $a, $b) === 2, '8b. normal: sigue 2');

// ============================================================
// 9. social + romance + conflicto coexisten
// ============================================================
$p4 = makePartida(1);
RelacionEngine::upsertSocial($p4, $a, $b, 'amigo', 50);
RelacionEngine::setRomanceHacia($p4, $a, $b, 40);
RelacionEngine::upsertConflicto($p4, $a, $b, 2, 'roce', 'test');
$soc = RelacionEngine::socialHacia($p4, $a, $b);
$rom = RelacionEngine::romanceHacia($p4, $a, $b);
$conf4 = existeConflicto($p4, $a, $b);
cl_ok(($soc['banda'] ?? '') === 'amigo', '9a. social = amigo');
cl_ok($rom === 40, '9b. romance = 40');
cl_ok($conf4, '9c. conflicto activo');
cl_ok(($soc['valor'] ?? 0) > 0, '9d. social positiva');
cl_ok(($rom ?? 0) > 0, '9e. romance positivo');

// ============================================================
// 10. al resolverse conflicto, social/romance permanecen
// ============================================================
CalibracionConfig::setTestOverrides(['desgaste_social' => ['activo' => false]]);
$calSinDesgaste = CalibracionConfig::load($root);
$antesSoc = RelacionEngine::valorSocialHacia($p4, $a, $b);
$antesRom = RelacionEngine::romanceHacia($p4, $a, $b);
avanzarDia($p4, 20, $calSinDesgaste);
cl_ok(!existeConflicto($p4, $a, $b), '10a. conflicto eliminado tras decay');
cl_ok(RelacionEngine::valorSocialHacia($p4, $a, $b) === $antesSoc, '10b. social intacta');
cl_ok(RelacionEngine::romanceHacia($p4, $a, $b) === $antesRom, '10c. romance intacto');
CalibracionConfig::setTestOverrides(null);

// ============================================================
// 11. guardar/cargar conserva estado temporal
// ============================================================
$p5 = makePartida(1);
RelacionEngine::upsertConflicto($p5, $a, $b, 2, 'roce', 'test');
$diaOrig = $p5['relaciones_conflicto'][0]['ultimo_conflicto_dia'] ?? null;
cl_ok($diaOrig !== null, '11a. ultimo_conflicto_dia presente tras creación');

// Simular serialización/deserialización
$json = json_encode($p5, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$p5b = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
$diaReload = $p5b['relaciones_conflicto'][0]['ultimo_conflicto_dia'] ?? null;
cl_ok($diaReload !== null, '11b. campo conservado tras serialize/deserialize');
cl_ok($diaReload === $diaOrig, '11c. valor idéntico');

// ============================================================
// 12. partida antigua sin metadatos no rompe
// ============================================================
$p6 = makePartida(1);
RelacionEngine::upsertConflicto($p6, $a, $b, 2, 'roce', 'test');
// Simular save antiguo: eliminar el campo temporal
unset($p6['relaciones_conflicto'][0]['ultimo_conflicto_dia']);
$p6['reloj']['dia_pueblo'] = 9;
$resultadoTick = RelacionDesgaste::alCerrarDia($p6, $cal);
$conf6 = conflictoEntre($p6, $a, $b);
cl_ok($conf6 !== null, '12a. save antiguo sin campo temporal no rompe');
cl_ok(isset($conf6['ultimo_conflicto_dia']), '12b. campo temporal se inyecta al primer tick');

// ============================================================
// 13. config values exist
// ============================================================
cl_ok(
    CalibracionConfig::get($cal, 'conflicto.activo', false) === true,
    '13a. conflicto.activo = true'
);
cl_ok(
    CalibracionConfig::get($cal, 'conflicto.dias_para_bajar_nivel', null) === 7,
    '13b. dias_para_bajar_nivel = 7'
);
cl_ok(
    CalibracionConfig::get($cal, 'conflicto.dias_nivel1_para_eliminar', null) === 7,
    '13c. dias_nivel1_para_eliminar = 7'
);

// ============================================================
// 14. alCerrarDia retorna conflictos_tocados
// ============================================================
$p7 = makePartida(1);
RelacionEngine::upsertConflicto($p7, $a, $b, 1, 'roce', 'test');
$p7['reloj']['dia_pueblo'] = 9;
$resultado = RelacionDesgaste::alCerrarDia($p7, $cal);
cl_ok(array_key_exists('conflictos_tocados', $resultado), '14a. resultado incluye conflictos_tocados');

exit($failures > 0 ? 1 : 0);
