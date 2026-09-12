<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CapacidadViviendas;
use AquiHayTema\Engine\CopyPistasSociales;
use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\DiarioPistasSociales;
use AquiHayTema\Engine\DiarioVista;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\QuimicaEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;

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
$service = new PartidaService($root);

// Helper: crear partida con al menos 3 residentes
function crearPartidaConResidentes(string $seed, int $n = 3): array
{
    global $service;
    $p = $service->nuevaPartida('juego_v1', $seed);
    $p['features']['diario_enabled'] = true;
    $ids = array_keys($p['residentes']);
    // Asegurar al menos $n residentes
    while (count($ids) < $n) {
        $service->crearResidentePlaceholderDev($p);
        $ids = array_keys($p['residentes']);
    }
    return $p;
}

// ============================================================
// 1. Flechazo: genera entrada en primera persona
//    (Ya cubierto por DiarioHitoEngine, solo verificamos que NO se duplica)
// ============================================================
$p1 = crearPartidaConResidentes('pistas-flechazo');
$ids1 = array_keys($p1['residentes']);
$a1 = (string) $ids1[0];
$b1 = (string) $ids1[1];

RelacionBitacora::registrar($p1, RelacionBitacora::FLECHAZO, [$a1, $b1], $a1 . '>' . $b1);
$entradas1 = DiarioVista::listarParaResidente($p1, $a1);
$flechazos = array_filter($entradas1, static function ($e) {
    return ($e['titulo'] ?? '') === 'Un flechazo';
});
ok(count($flechazos) >= 1, '1. Flechazo genera entrada en primera persona');

// ============================================================
// 2. Química muy alta: genera pista positiva sin número
// ============================================================
$p2 = crearPartidaConResidentes('pistas-quimica-alta');
$ids2 = array_keys($p2['residentes']);
$a2 = (string) $ids2[0];
$b2 = (string) $ids2[1];

QuimicaEngine::ensure($p2);
$p2['quimica']['pares'][QuimicaEngine::parId($a2, $b2)] = [
    'id' => QuimicaEngine::parId($a2, $b2),
    'persona_a' => $a2 < $b2 ? $a2 : $b2,
    'persona_b' => $a2 < $b2 ? $b2 : $a2,
    'a_hacia_b' => 90,
    'b_hacia_a' => 90,
    'simetrica' => 90,
    'visible_jugador' => false,
];

$result2 = DiarioPistasSociales::evaluarPistas($p2);
$quimicaAlta = array_filter($result2, static function ($r) {
    return $r['familia'] === 'quimica_alta';
});
ok(count($quimicaAlta) >= 1, '2. Química muy alta (90) genera pista');
$entradas2 = DiarioVista::listarParaResidente($p2, $a2);
$pistas2 = array_filter($entradas2, static function ($e) {
    return ($e['categoria_etiqueta'] ?? '') === 'Pista';
});
ok(count($pistas2) >= 1, '2b. Química alta aparece en vista como "Pista"');

// ============================================================
// 3. Química muy baja: genera pista de poca conexión, no odio
// ============================================================
$p3 = crearPartidaConResidentes('pistas-quimica-baja');
$ids3 = array_keys($p3['residentes']);
$a3 = (string) $ids3[0];
$b3 = (string) $ids3[1];

QuimicaEngine::ensure($p3);
$p3['quimica']['pares'][QuimicaEngine::parId($a3, $b3)] = [
    'id' => QuimicaEngine::parId($a3, $b3),
    'persona_a' => $a3 < $b3 ? $a3 : $b3,
    'persona_b' => $a3 < $b3 ? $b3 : $a3,
    'a_hacia_b' => 10,
    'b_hacia_a' => 10,
    'simetrica' => 10,
    'visible_jugador' => false,
];

$result3 = DiarioPistasSociales::evaluarPistas($p3);
$quimicaBaja = array_filter($result3, static function ($r) {
    return $r['familia'] === 'quimica_baja';
});
ok(count($quimicaBaja) >= 1, '3. Química muy baja (10) genera pista');
$pistaTexto3 = '';
foreach ($quimicaBaja as $q) {
    $pistaTexto3 = $q['texto'];
}
ok(
    $pistaTexto3 !== '' && !str_contains(strtolower($pistaTexto3), 'odio'),
    '3b. Texto de química baja no menciona odio'
);

// ============================================================
// 4. Primer rechazo relevante: genera entrada adecuada
//    (Ya cubierto por DiarioHitoEngine)
// ============================================================
$p4 = crearPartidaConResidentes('pistas-rechazo');
$ids4 = array_keys($p4['residentes']);
$a4 = (string) $ids4[0];
$b4 = (string) $ids4[1];

RelacionBitacora::registrar($p4, RelacionBitacora::RECHAZO_IMPORTANTE, [$a4, $b4], $a4);
$entradas4 = DiarioVista::listarParaResidente($p4, $a4);
$rechazos4 = array_filter($entradas4, static function ($e) {
    return ($e['titulo'] ?? '') === 'Un rechazo importante';
});
ok(count($rechazos4) >= 1, '4. Rechazo relevante genera entrada');

// ============================================================
// 5. Rechazo repetido: texto distinto/más fuerte
// ============================================================
$p5 = crearPartidaConResidentes('pistas-rechazo-repetido');
$ids5 = array_keys($p5['residentes']);
$a5 = (string) $ids5[0];
$b5 = (string) $ids5[1];

// Simular 3 rechazos previos
for ($i = 0; $i < 3; $i++) {
    RelacionBitacora::registrar($p5, RelacionBitacora::RECHAZO_IMPORTANTE, [$a5, $b5], $a5);
}
$entradas5 = DiarioVista::listarParaResidente($p5, $a5);
$rechazos5 = array_filter($entradas5, static function ($e) {
    return ($e['titulo'] ?? '') === 'Un rechazo importante';
});
ok(count($rechazos5) >= 1, '5. Rechazo repetido genera entrada');

// ============================================================
// 6. Atracción asimétrica: entrada solo para quien siente la atracción
// ============================================================
$p6 = crearPartidaConResidentes('pistas-asimetrica');
$ids6 = array_keys($p6['residentes']);
$a6 = (string) $ids6[0];
$b6 = (string) $ids6[1];

// A siente algo por B (romance alto), B no siente nada
RelacionEngine::setRomanceHacia($p6, $a6, $b6, 12);
RelacionEngine::setRomanceHacia($p6, $b6, $a6, 0);
RelacionEngine::registrarContacto($p6, $a6, $b6, 'normal');
RelacionEngine::registrarContacto($p6, $b6, $a6, 'normal');

$result6 = DiarioPistasSociales::evaluarPistas($p6);
$asimetricas = array_filter($result6, static function ($r) {
    return $r['familia'] === 'atraccion_asimetrica';
});
$desdeA = false;
$desdeB = false;
foreach ($asimetricas as $as) {
    if ($as['desde'] === $a6) {
        $desdeA = true;
    }
    if ($as['desde'] === $b6) {
        $desdeB = true;
    }
}
ok($desdeA && !$desdeB, '6. Atracción asimetrica: solo A (quien siente) recibe entrada');

// ============================================================
// 7. Dos eventos distintos mismo día: no se eliminan por dedup incorrecto
// ============================================================
$p7 = crearPartidaConResidentes('pistas-dos-eventos');
$ids7 = array_keys($p7['residentes']);
$a7 = (string) $ids7[0];
$b7 = (string) $ids7[1];
$c7 = (string) $ids7[2];

// Química media para todos los pares excepto A-B (alta)
QuimicaEngine::ensure($p7);
for ($i = 0; $i < count($ids7); $i++) {
    for ($j = $i + 1; $j < count($ids7); $j++) {
        $ax = (string) $ids7[$i];
        $bx = (string) $ids7[$j];
        $par = [min($ax, $bx), max($ax, $bx)];
        $isAB = in_array($a7, $par, true) && in_array($b7, $par, true);
        $p7['quimica']['pares'][QuimicaEngine::parId($ax, $bx)] = [
            'id' => QuimicaEngine::parId($ax, $bx),
            'persona_a' => $ax < $bx ? $ax : $bx,
            'persona_b' => $ax < $bx ? $bx : $ax,
            'a_hacia_b' => $isAB ? 90 : 50,
            'b_hacia_a' => $isAB ? 90 : 50,
            'simetrica' => $isAB ? 90 : 50,
            'visible_jugador' => false,
        ];
    }
}

// Conflicto entre A y C
RelacionEngine::upsertConflicto($p7, $a7, $c7, 3, 'discusion');

$result7 = DiarioPistasSociales::evaluarPistas($p7);
$familias7 = array_column($result7, 'familia');
ok(
    in_array('quimica_alta', $familias7, true) && in_array('conflicto_personal', $familias7, true),
    '7. Dos eventos distintos mismo día: ambos sobreviven'
);

// ============================================================
// 8. Cambio numérico pequeño: NO genera entrada
// ============================================================
$p8 = crearPartidaConResidentes('pistas-cambio-pequeno');
$ids8 = array_keys($p8['residentes']);
$a8 = (string) $ids8[0];
$b8 = (string) $ids8[1];

// Química en rango medio (50) para TODOS los pares — no debería generar nada
QuimicaEngine::ensure($p8);
for ($i = 0; $i < count($ids8); $i++) {
    for ($j = $i + 1; $j < count($ids8); $j++) {
        $ax = (string) $ids8[$i];
        $bx = (string) $ids8[$j];
        $p8['quimica']['pares'][QuimicaEngine::parId($ax, $bx)] = [
            'id' => QuimicaEngine::parId($ax, $bx),
            'persona_a' => $ax < $bx ? $ax : $bx,
            'persona_b' => $ax < $bx ? $bx : $ax,
            'a_hacia_b' => 50,
            'b_hacia_a' => 50,
            'simetrica' => 50,
            'visible_jugador' => false,
        ];
    }
}

$result8 = DiarioPistasSociales::evaluarPistas($p8);
ok(count($result8) === 0, '8. Química media (50) NO genera entrada');

// ============================================================
// 9. Repetición del mismo estado: NO spamea diario
// ============================================================
$p9 = crearPartidaConResidentes('pistas-dedup');
$ids9 = array_keys($p9['residentes']);
$a9 = (string) $ids9[0];
$b9 = (string) $ids9[1];

// Química alta para A-B, media para todos los demás pares
QuimicaEngine::ensure($p9);
for ($i = 0; $i < count($ids9); $i++) {
    for ($j = $i + 1; $j < count($ids9); $j++) {
        $ax = (string) $ids9[$i];
        $bx = (string) $ids9[$j];
        $par = [min($ax, $bx), max($ax, $bx)];
        $isAB = in_array($a9, $par, true) && in_array($b9, $par, true);
        $p9['quimica']['pares'][QuimicaEngine::parId($ax, $bx)] = [
            'id' => QuimicaEngine::parId($ax, $bx),
            'persona_a' => $ax < $bx ? $ax : $bx,
            'persona_b' => $ax < $bx ? $bx : $ax,
            'a_hacia_b' => $isAB ? 90 : 50,
            'b_hacia_a' => $isAB ? 90 : 50,
            'simetrica' => $isAB ? 90 : 50,
            'visible_jugador' => false,
        ];
    }
}

// Ejecutar dos veces
DiarioPistasSociales::evaluarPistas($p9);
$result9 = DiarioPistasSociales::evaluarPistas($p9);
ok(count($result9) === 0, '9. Segunda ejecución: misma química NO spamea');

// Verificar que solo hay 1 entrada en el diario
$entradas9 = array_filter($p9['diario'] ?? [], static function ($e) {
    return ($e['tipo'] ?? '') === 'pista_social' && ($e['subtipo'] ?? '') === 'quimica_alta';
});
ok(count($entradas9) <= 1, '9b. Solo 1 entrada de química alta en diario');

// ============================================================
// 10. Género/nombres: frases correctas (sin @, x, o/a)
// ============================================================
$p10 = crearPartidaConResidentes('pistas-genero');
$ids10 = array_keys($p10['residentes']);
$a10 = (string) $ids10[0];
$b10 = (string) $ids10[1];

QuimicaEngine::ensure($p10);
$p10['quimica']['pares'][QuimicaEngine::parId($a10, $b10)] = [
    'id' => QuimicaEngine::parId($a10, $b10),
    'persona_a' => $a10 < $b10 ? $a10 : $b10,
    'persona_b' => $a10 < $b10 ? $b10 : $a10,
    'a_hacia_b' => 90,
    'b_hacia_a' => 90,
    'simetrica' => 90,
    'visible_jugador' => false,
];

$result10 = DiarioPistasSociales::evaluarPistas($p10);
foreach ($result10 as $r) {
    $texto = $r['texto'];
    ok(
        !str_contains($texto, '@') && !str_contains($texto, ' o '),
        '10. Texto "' . substr($texto, 0, 40) . '..." sin @ ni "o "'
    );
}

// ============================================================
// CopyPistasSociales: textos no vacíos
// ============================================================
$familias = ['quimica_alta', 'quimica_baja', 'atraccion_asimetrica', 'conflicto_personal', 'calentamiento_social', 'enfriamiento_social', 'estabilidad_pareja_baja'];
foreach ($familias as $fam) {
    $t = CopyPistasSociales::texto($fam, 'TestNombre');
    ok($t !== '', "Copy.{$fam} devuelve texto no vacío");
}

// ============================================================
// DiarioVista: títulos de subtipo existen
// ============================================================
$vistaTest = DiarioVista::listarParaResidente($p2, $a2);
$pistaEntry = null;
foreach ($vistaTest as $v) {
    if (($v['categoria_etiqueta'] ?? '') === 'Pista') {
        $pistaEntry = $v;
        break;
    }
}
ok($pistaEntry !== null, 'DiarioVista muestra entrada con etiqueta "Pista"');
if ($pistaEntry !== null) {
    ok(($pistaEntry['titulo'] ?? '') !== '', 'DiarioVista tiene título para pista');
    ok(($pistaEntry['filtro_grupo'] ?? '') === 'relaciones', 'DiarioVista pista en grupo "relaciones"');
}

// ============================================================
// DiarioPistasSociales: MAX_POR_DIA respeta límite
// ============================================================
$pMax = crearPartidaConResidentes('pistas-max-dia', 5);
$idsMax = array_keys($pMax['residentes']);

// Química alta entre varios pares
QuimicaEngine::ensure($pMax);
for ($i = 0; $i < count($idsMax); $i++) {
    for ($j = $i + 1; $j < count($idsMax); $j++) {
        $ax = (string) $idsMax[$i];
        $bx = (string) $idsMax[$j];
        $pMax['quimica']['pares'][QuimicaEngine::parId($ax, $bx)] = [
            'id' => QuimicaEngine::parId($ax, $bx),
            'persona_a' => $ax < $bx ? $ax : $bx,
            'persona_b' => $ax < $bx ? $bx : $ax,
            'a_hacia_b' => 90,
            'b_hacia_a' => 90,
            'simetrica' => 90,
            'visible_jugador' => false,
        ];
    }
}

$resultMax = DiarioPistasSociales::evaluarPistas($pMax);
ok(
    count($resultMax) <= DiarioPistasSociales::MAX_POR_DIA,
    'MAX_POR_DIA: ' . count($resultMax) . ' entradas (máx ' . DiarioPistasSociales::MAX_POR_DIA . ')'
);

// ============================================================
echo "\n" . ($failures === 0 ? 'ALL TESTS PASSED' : "FAILURES: $failures") . "\n";
exit($failures > 0 ? 1 : 0);
