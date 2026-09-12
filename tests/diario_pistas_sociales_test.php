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
use AquiHayTema\Engine\RechazoMemoria;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;

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

function crearPartidaConResidentes(string $seed, int $n = 3): array
{
    global $service;
    $p = $service->nuevaPartida('juego_v1', $seed);
    $p['features']['diario_enabled'] = true;
    $ids = array_keys($p['residentes']);
    while (count($ids) < $n) {
        $service->crearResidentePlaceholderDev($p);
        $ids = array_keys($p['residentes']);
    }
    return $p;
}

function setQuimicaPares(array &$p, array $ids, int $valor): void
{
    QuimicaEngine::ensure($p);
    for ($i = 0; $i < count($ids); $i++) {
        for ($j = $i + 1; $j < count($ids); $j++) {
            $ax = (string) $ids[$i];
            $bx = (string) $ids[$j];
            $p['quimica']['pares'][QuimicaEngine::parId($ax, $bx)] = [
                'id' => QuimicaEngine::parId($ax, $bx),
                'persona_a' => $ax < $bx ? $ax : $bx,
                'persona_b' => $ax < $bx ? $bx : $ax,
                'a_hacia_b' => $valor,
                'b_hacia_a' => $valor,
                'simetrica' => $valor,
                'visible_jugador' => false,
            ];
        }
    }
}

function countDiario(array $p, string $tipo, ?string $subtipo = null): int
{
    $n = 0;
    foreach ($p['diario'] ?? [] as $e) {
        if (!is_array($e)) { continue; }
        if (($e['tipo'] ?? '') !== $tipo) { continue; }
        if ($subtipo !== null && ($e['subtipo'] ?? '') !== $subtipo) { continue; }
        $n++;
    }
    return $n;
}

// ============================================================
// 1. Flechazo: CERO pista_social (exclusivamente diario_hito)
// ============================================================
$p1 = crearPartidaConResidentes('pistas-flechazo');
$ids1 = array_keys($p1['residentes']);
$a1 = (string) $ids1[0];
$b1 = (string) $ids1[1];

RelacionBitacora::registrar($p1, RelacionBitacora::FLECHAZO, [$a1, $b1], $a1 . '>' . $b1);
$result1 = DiarioPistasSociales::evaluarPistas($p1);
$flechPistas = array_filter($result1, static function ($r) {
    return $r['familia'] === 'flechazo';
});
ok(count($flechPistas) === 0, '1a. Flechazo: CERO pista_social');

// Verificar que diario_hito SÍ se creó (por DiarioHitoEngine)
$hitosFlechazo = 0;
foreach ($p1['diario'] ?? [] as $e) {
    if (($e['tipo'] ?? '') === 'diario_hito' && ($e['subtipo'] ?? '') === 'flechazo') {
        $hitosFlechazo++;
    }
}
ok($hitosFlechazo >= 2, '1b. Flechazo: diario_hito creado (2 entradas, 1 por actor)');

// ============================================================
// 2. Química muy alta: genera pista positiva
// ============================================================
$p2 = crearPartidaConResidentes('pistas-quimica-alta');
$ids2 = array_keys($p2['residentes']);
$a2 = (string) $ids2[0];
$b2 = (string) $ids2[1];

setQuimicaPares($p2, $ids2, 90);

$result2 = DiarioPistasSociales::evaluarPistas($p2);
$quimicaAlta = array_filter($result2, static function ($r) {
    return $r['familia'] === 'quimica_alta';
});
ok(count($quimicaAlta) >= 1, '2. Química alta (90) genera pista');

// ============================================================
// 3. Química muy baja: genera pista sin mencionar odio
// ============================================================
$p3 = crearPartidaConResidentes('pistas-quimica-baja');
$ids3 = array_keys($p3['residentes']);
$a3 = (string) $ids3[0];
$b3 = (string) $ids3[1];

setQuimicaPares($p3, $ids3, 10);

$result3 = DiarioPistasSociales::evaluarPistas($p3);
$quimicaBaja = array_filter($result3, static function ($r) {
    return $r['familia'] === 'quimica_baja';
});
ok(count($quimicaBaja) >= 1, '3. Química baja (10) genera pista');
$pistaTexto3 = '';
foreach ($quimicaBaja as $q) {
    $pistaTexto3 = $q['texto'];
}
ok(
    $pistaTexto3 !== '' && !str_contains(strtolower($pistaTexto3), 'odio'),
    '3b. Texto de química baja no menciona odio'
);

// ============================================================
// 4. Primer rechazo relevante n=1: exactamente 1 entrada
// ============================================================
$p4 = crearPartidaConResidentes('pistas-primer-rechazo');
$ids4 = array_keys($p4['residentes']);
$a4 = (string) $ids4[0];
$b4 = (string) $ids4[1];

RelacionEngine::registrarContacto($p4, $a4, $b4, 'normal');
RelacionEngine::registrarContacto($p4, $b4, $a4, 'normal');
RechazoMemoria::registrar($p4, $a4, $b4, 'emocional', []);

$result4 = DiarioPistasSociales::evaluarPistas($p4);
$rechRelevante = array_filter($result4, static function ($r) {
    return $r['familia'] === 'primer_rechazo_relevante';
});
ok(count($rechRelevante) === 1, '4. Primer rechazo relevante (n=1): exactamente 1 pista');

// Verificar que el rechazado (B) recibe la entrada
$desdeRech4 = '';
foreach ($rechRelevante as $rr) {
    $desdeRech4 = $rr['desde'];
}
ok($desdeRech4 === $b4, '4b. Primer rechazo: solo el rechazado recibe pista');

// ============================================================
// 5. Rechazo repetido n=2: sin duplicación
// ============================================================
$p5 = crearPartidaConResidentes('pistas-rechazo-repetido');
$ids5 = array_keys($p5['residentes']);
$a5 = (string) $ids5[0];
$b5 = (string) $ids5[1];

RelacionEngine::registrarContacto($p5, $a5, $b5, 'normal');
RelacionEngine::registrarContacto($p5, $b5, $a5, 'normal');
RechazoMemoria::registrar($p5, $a5, $b5, 'emocional', []);
RechazoMemoria::registrar($p5, $a5, $b5, 'relacional', []);

$result5 = DiarioPistasSociales::evaluarPistas($p5);
$rechRepetido = array_filter($result5, static function ($r) {
    return $r['familia'] === 'rechazo_repetido';
});
ok(count($rechRepetido) === 1, '5. Rechazo repetido (n=2): exactamente 1 pista, sin duplicación');

// ============================================================
// 6. Rechazo repetido n=3: respeta cooldown
// ============================================================
$p6 = crearPartidaConResidentes('pistas-rechazo-n3');
$ids6 = array_keys($p6['residentes']);
$a6 = (string) $ids6[0];
$b6 = (string) $ids6[1];

RelacionEngine::registrarContacto($p6, $a6, $b6, 'normal');
RelacionEngine::registrarContacto($p6, $b6, $a6, 'normal');
RechazoMemoria::registrar($p6, $a6, $b6, 'emocional', []);
RechazoMemoria::registrar($p6, $a6, $b6, 'emocional', []);
RechazoMemoria::registrar($p6, $a6, $b6, 'relacional', []);

DiarioPistasSociales::evaluarPistas($p6);
$result6 = DiarioPistasSociales::evaluarPistas($p6);
$rechN3 = array_filter($result6, static function ($r) {
    return in_array($r['familia'], ['primer_rechazo_relevante', 'rechazo_repetido'], true);
});
ok(count($rechN3) === 0, '6. Rechazo n=3: segunda ejecución respeta cooldown');

// ============================================================
// 7. Rechazo importante n>=4: exactamente 1 representación
//    (diario_hito de DiarioHitoEngine, CERO pista_social)
// ============================================================
$p7 = crearPartidaConResidentes('pistas-rechazo-importante');
$ids7 = array_keys($p7['residentes']);
$a7 = (string) $ids7[0];
$b7 = (string) $ids7[1];

RelacionEngine::registrarContacto($p7, $a7, $b7, 'normal');
RelacionEngine::registrarContacto($p7, $b7, $a7, 'normal');
for ($i = 0; $i < 4; $i++) {
    RechazoMemoria::registrar($p7, $a7, $b7, 'emocional', []);
}

// Verificar diario_hito existe
$hitosRechazo = 0;
foreach ($p7['diario'] ?? [] as $e) {
    if (($e['tipo'] ?? '') === 'diario_hito' && ($e['subtipo'] ?? '') === 'rechazo_importante') {
        $hitosRechazo++;
    }
}
ok($hitosRechazo >= 1, '7a. Rechazo n>=4: diario_hito creado por DiarioHitoEngine');

// Verificar que pista_social NO se genera para el mismo rechazo
$result7 = DiarioPistasSociales::evaluarPistas($p7);
$rechRepetido7 = array_filter($result7, static function ($r) {
    return $r['familia'] === 'rechazo_repetido';
});
ok(count($rechRepetido7) === 0, '7b. Rechazo n>=4: CERO pista_social (evita duplicación con diario_hito)');

// Contar entradas totales del par en diario
$entradasPar7 = 0;
foreach ($p7['diario'] ?? [] as $e) {
    if (!is_array($e)) { continue; }
    $actores = $e['actores'] ?? [];
    if (in_array($a7, $actores, true) || in_array($b7, $actores, true)) {
        $entradasPar7++;
    }
}
ok($entradasPar7 >= 1 && $entradasPar7 <= 8, '7c. Rechazo n>=4: entradas del par razonables (' . $entradasPar7 . ')');

// ============================================================
// 8. Dos rechazos realmente distintos: NO se deduplican
// ============================================================
$p8 = crearPartidaConResidentes('pistas-rechazos-distintos');
$ids8 = array_keys($p8['residentes']);
$a8 = (string) $ids8[0];
$b8 = (string) $ids8[1];
$c8 = (string) $ids8[2];

setQuimicaPares($p8, $ids8, 50);
RelacionEngine::registrarContacto($p8, $a8, $b8, 'normal');
RelacionEngine::registrarContacto($p8, $b8, $a8, 'normal');
RelacionEngine::registrarContacto($p8, $a8, $c8, 'normal');
RelacionEngine::registrarContacto($p8, $c8, $a8, 'normal');

// A rechaza a B (emocional), A rechaza a C (relacional)
RechazoMemoria::registrar($p8, $a8, $b8, 'emocional', []);
RechazoMemoria::registrar($p8, $a8, $c8, 'relacional', []);

$result8 = DiarioPistasSociales::evaluarPistas($p8);
$rechRelevantes8 = array_filter($result8, static function ($r) {
    return $r['familia'] === 'primer_rechazo_relevante';
});
ok(count($rechRelevantes8) === 2, '8. Dos rechazos distintos (B y C): ambos generan entrada');

// ============================================================
// 9. Rechazo banal: NO genera entrada
// ============================================================
$p9 = crearPartidaConResidentes('pistas-rechazo-banal');
$ids9 = array_keys($p9['residentes']);
$a9 = (string) $ids9[0];
$b9 = (string) $ids9[1];

RelacionEngine::registrarContacto($p9, $a9, $b9, 'normal');
RelacionEngine::registrarContacto($p9, $b9, $a9, 'normal');
RechazoMemoria::registrar($p9, $a9, $b9, 'banal', []);

$result9 = DiarioPistasSociales::evaluarPistas($p9);
$rechBanals = array_filter($result9, static function ($r) {
    return in_array($r['familia'], ['primer_rechazo_relevante', 'rechazo_repetido'], true);
});
ok(count($rechBanals) === 0, '9. Rechazo banal NO genera entrada de diario');

// ============================================================
// 10. Atracción asimétrica: solo quien siente recibe
// ============================================================
$p10 = crearPartidaConResidentes('pistas-asimetrica');
$ids10 = array_keys($p10['residentes']);
$a10 = (string) $ids10[0];
$b10 = (string) $ids10[1];

RelacionEngine::setRomanceHacia($p10, $a10, $b10, 12);
RelacionEngine::setRomanceHacia($p10, $b10, $a10, 0);
RelacionEngine::registrarContacto($p10, $a10, $b10, 'normal');
RelacionEngine::registrarContacto($p10, $b10, $a10, 'normal');

$result10 = DiarioPistasSociales::evaluarPistas($p10);
$asimetricas = array_filter($result10, static function ($r) {
    return $r['familia'] === 'atraccion_asimetrica';
});
$desdeA10 = false;
$desdeB10 = false;
foreach ($asimetricas as $as) {
    if ($as['desde'] === $a10) { $desdeA10 = true; }
    if ($as['desde'] === $b10) { $desdeB10 = true; }
}
ok($desdeA10 && !$desdeB10, '10. Atracción asimétrica: solo A recibe entrada');

// ============================================================
// 11. Dos eventos distintos mismo día: sobreviven
// ============================================================
$p11 = crearPartidaConResidentes('pistas-dos-eventos');
$ids11 = array_keys($p11['residentes']);
$a11 = (string) $ids11[0];
$b11 = (string) $ids11[1];
$c11 = (string) $ids11[2];

setQuimicaPares($p11, $ids11, 50);
$p11['quimica']['pares'][QuimicaEngine::parId($a11, $b11)] = [
    'id' => QuimicaEngine::parId($a11, $b11),
    'persona_a' => $a11 < $b11 ? $a11 : $b11,
    'persona_b' => $a11 < $b11 ? $b11 : $a11,
    'a_hacia_b' => 90,
    'b_hacia_a' => 90,
    'simetrica' => 90,
    'visible_jugador' => false,
];

RelacionEngine::upsertConflicto($p11, $a11, $c11, 3, 'discusion');

$result11 = DiarioPistasSociales::evaluarPistas($p11);
$familias11 = array_column($result11, 'familia');
ok(
    in_array('quimica_alta', $familias11, true) && in_array('conflicto_personal', $familias11, true),
    '11. Dos eventos distintos mismo día: ambos sobreviven'
);

// ============================================================
// 12. Química media: NO genera entrada
// ============================================================
$p12 = crearPartidaConResidentes('pistas-cambio-pequeno');
$ids12 = array_keys($p12['residentes']);

setQuimicaPares($p12, $ids12, 50);

$result12 = DiarioPistasSociales::evaluarPistas($p12);
ok(count($result12) === 0, '12. Química media (50) NO genera entrada');

// ============================================================
// 13. Repetición del mismo estado: NO spamea
// ============================================================
$p13 = crearPartidaConResidentes('pistas-dedup');
$ids13 = array_keys($p13['residentes']);
$a13 = (string) $ids13[0];
$b13 = (string) $ids13[1];

QuimicaEngine::ensure($p13);
for ($i = 0; $i < count($ids13); $i++) {
    for ($j = $i + 1; $j < count($ids13); $j++) {
        $ax = (string) $ids13[$i];
        $bx = (string) $ids13[$j];
        $par = [min($ax, $bx), max($ax, $bx)];
        $isAB = in_array($a13, $par, true) && in_array($b13, $par, true);
        $p13['quimica']['pares'][QuimicaEngine::parId($ax, $bx)] = [
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

DiarioPistasSociales::evaluarPistas($p13);
$result13 = DiarioPistasSociales::evaluarPistas($p13);
ok(count($result13) === 0, '13. Segunda ejecución: misma química NO spamea');

// ============================================================
// 14. Género/nombres: frases sin @ ni "o "
// ============================================================
$p14 = crearPartidaConResidentes('pistas-genero');
$ids14 = array_keys($p14['residentes']);
$a14 = (string) $ids14[0];
$b14 = (string) $ids14[1];

setQuimicaPares($p14, $ids14, 90);

$result14 = DiarioPistasSociales::evaluarPistas($p14);
foreach ($result14 as $r) {
    $texto = $r['texto'];
    ok(
        !str_contains($texto, '@') && !str_contains($texto, ' o '),
        '14. Texto "' . substr($texto, 0, 50) . '..." sin @ ni "o "'
    );
}

// ============================================================
// CopyPistasSociales: todos los pools devuelven texto
// ============================================================
$familias = DiarioPistasSociales::FAMILIAS;
foreach ($familias as $fam) {
    $t = CopyPistasSociales::texto($fam, 'TestNombre');
    ok($t !== '', "Copy.{$fam} devuelve texto no vacío");
}

// ============================================================
// DiarioVista: títulos de subtipo
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
// MAX_POR_DIA: respeta límite
// ============================================================
$pMax = crearPartidaConResidentes('pistas-max-dia', 5);
$idsMax = array_keys($pMax['residentes']);
setQuimicaPares($pMax, $idsMax, 90);

$resultMax = DiarioPistasSociales::evaluarPistas($pMax);
ok(
    count($resultMax) <= DiarioPistasSociales::MAX_POR_DIA,
    'MAX_POR_DIA: ' . count($resultMax) . ' entradas (máx ' . DiarioPistasSociales::MAX_POR_DIA . ')'
);

// ============================================================
echo "\n" . ($failures === 0 ? 'ALL TESTS PASSED' : "FAILURES: $failures") . "\n";
exit($failures > 0 ? 1 : 0);
