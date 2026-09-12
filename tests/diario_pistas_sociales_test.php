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

// ============================================================
// 1. Flechazo: genera pista_social complementaria al diario_hito
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
ok(count($flechPistas) >= 1, '1a. Flechazo genera pista_social');

// Solo quien SIENTE el flechazo recibe pista
$desdeA1 = false;
$desdeB1 = false;
foreach ($flechPistas as $fp) {
    if ($fp['desde'] === $a1) { $desdeA1 = true; }
    if ($fp['desde'] === $b1) { $desdeB1 = true; }
}
ok($desdeA1 && !$desdeB1, '1b. Flechazo: solo A (quien siente) recibe pista');

// ============================================================
// 1c. Flechazo repetido: NO spamea
// ============================================================
$p1c = crearPartidaConResidentes('pistas-flechazo-dedup');
$ids1c = array_keys($p1c['residentes']);
$a1c = (string) $ids1c[0];
$b1c = (string) $ids1c[1];

RelacionBitacora::registrar($p1c, RelacionBitacora::FLECHAZO, [$a1c, $b1c], $a1c . '>' . $b1c);
DiarioPistasSociales::evaluarPistas($p1c);
$result1c = DiarioPistasSociales::evaluarPistas($p1c);
$flechDup = array_filter($result1c, static function ($r) {
    return $r['familia'] === 'flechazo';
});
ok(count($flechDup) === 0, '1c. Flechazo: segunda ejecución NO spamea');

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
// 4. Primer rechazo relevante: genera entrada desde rechazos_propuesta
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
ok(count($rechRelevante) >= 1, '4. Primer rechazo relevante (emocional) genera pista');

// Verificar que el rechazado (B) recibe la entrada, no el rechazador (A)
$desdeRech4 = '';
foreach ($rechRelevante as $rr) {
    $desdeRech4 = $rr['desde'];
}
ok($desdeRech4 === $b4, '4b. Primer rechazo: solo el rechazado recibe pista');

// ============================================================
// 5. Rechazo repetido: genera entrada diferente/más fuerte
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
ok(count($rechRepetido) >= 1, '5. Rechazo repetido (2+) genera pista diferente');

// ============================================================
// 6. Rechazo banal: NO genera entrada
// ============================================================
$p6 = crearPartidaConResidentes('pistas-rechazo-banal');
$ids6 = array_keys($p6['residentes']);
$a6 = (string) $ids6[0];
$b6 = (string) $ids6[1];

RelacionEngine::registrarContacto($p6, $a6, $b6, 'normal');
RelacionEngine::registrarContacto($p6, $b6, $a6, 'normal');
RechazoMemoria::registrar($p6, $a6, $b6, 'banal', []);

$result6 = DiarioPistasSociales::evaluarPistas($p6);
$rechBanals = array_filter($result6, static function ($r) {
    return in_array($r['familia'], ['primer_rechazo_relevante', 'rechazo_repetido'], true);
});
ok(count($rechBanals) === 0, '6. Rechazo banal NO genera entrada de diario');

// ============================================================
// 7. Atracción asimétrica: solo quien siente la atracción recibe
// ============================================================
$p7 = crearPartidaConResidentes('pistas-asimetrica');
$ids7 = array_keys($p7['residentes']);
$a7 = (string) $ids7[0];
$b7 = (string) $ids7[1];

RelacionEngine::setRomanceHacia($p7, $a7, $b7, 12);
RelacionEngine::setRomanceHacia($p7, $b7, $a7, 0);
RelacionEngine::registrarContacto($p7, $a7, $b7, 'normal');
RelacionEngine::registrarContacto($p7, $b7, $a7, 'normal');

$result7 = DiarioPistasSociales::evaluarPistas($p7);
$asimetricas = array_filter($result7, static function ($r) {
    return $r['familia'] === 'atraccion_asimetrica';
});
$desdeA7 = false;
$desdeB7 = false;
foreach ($asimetricas as $as) {
    if ($as['desde'] === $a7) { $desdeA7 = true; }
    if ($as['desde'] === $b7) { $desdeB7 = true; }
}
ok($desdeA7 && !$desdeB7, '7. Atracción asimétrica: solo A (quien siente) recibe entrada');

// ============================================================
// 8. Dos eventos distintos mismo día: sobreviven si son diferentes
// ============================================================
$p8 = crearPartidaConResidentes('pistas-dos-eventos');
$ids8 = array_keys($p8['residentes']);
$a8 = (string) $ids8[0];
$b8 = (string) $ids8[1];
$c8 = (string) $ids8[2];

setQuimicaPares($p8, $ids8, 50);
// A-B: química alta
$p8['quimica']['pares'][QuimicaEngine::parId($a8, $b8)] = [
    'id' => QuimicaEngine::parId($a8, $b8),
    'persona_a' => $a8 < $b8 ? $a8 : $b8,
    'persona_b' => $a8 < $b8 ? $b8 : $a8,
    'a_hacia_b' => 90,
    'b_hacia_a' => 90,
    'simetrica' => 90,
    'visible_jugador' => false,
];

// Conflicto A-C
RelacionEngine::upsertConflicto($p8, $a8, $c8, 3, 'discusion');

$result8 = DiarioPistasSociales::evaluarPistas($p8);
$familias8 = array_column($result8, 'familia');
ok(
    in_array('quimica_alta', $familias8, true) && in_array('conflicto_personal', $familias8, true),
    '8. Dos eventos distintos mismo día: ambos sobreviven'
);

// ============================================================
// 9. Química media: NO genera entrada
// ============================================================
$p9 = crearPartidaConResidentes('pistas-cambio-pequeno');
$ids9 = array_keys($p9['residentes']);

setQuimicaPares($p9, $ids9, 50);

$result9 = DiarioPistasSociales::evaluarPistas($p9);
ok(count($result9) === 0, '9. Química media (50) NO genera entrada');

// ============================================================
// 10. Repetición del mismo estado: NO spamea diario
// ============================================================
$p10 = crearPartidaConResidentes('pistas-dedup');
$ids10 = array_keys($p10['residentes']);
$a10 = (string) $ids10[0];
$b10 = (string) $ids10[1];

// Química alta para A-B, media para el resto
QuimicaEngine::ensure($p10);
for ($i = 0; $i < count($ids10); $i++) {
    for ($j = $i + 1; $j < count($ids10); $j++) {
        $ax = (string) $ids10[$i];
        $bx = (string) $ids10[$j];
        $par = [min($ax, $bx), max($ax, $bx)];
        $isAB = in_array($a10, $par, true) && in_array($b10, $par, true);
        $p10['quimica']['pares'][QuimicaEngine::parId($ax, $bx)] = [
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

DiarioPistasSociales::evaluarPistas($p10);
$result10 = DiarioPistasSociales::evaluarPistas($p10);
ok(count($result10) === 0, '10. Segunda ejecución: misma química NO spamea');

// ============================================================
// 11. Género/nombres: frases sin @ ni "o "
// ============================================================
$p11 = crearPartidaConResidentes('pistas-genero');
$ids11 = array_keys($p11['residentes']);
$a11 = (string) $ids11[0];
$b11 = (string) $ids11[1];

setQuimicaPares($p11, $ids11, 90);

$result11 = DiarioPistasSociales::evaluarPistas($p11);
foreach ($result11 as $r) {
    $texto = $r['texto'];
    ok(
        !str_contains($texto, '@') && !str_contains($texto, ' o '),
        '11. Texto "' . substr($texto, 0, 50) . '..." sin @ ni "o "'
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
// DiarioVista: títulos de subtipo para todas las familias
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
