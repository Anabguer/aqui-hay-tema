<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\NecesidadEstado;
use AquiHayTema\Engine\SchemaFields;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\MotorVidaDiaria;
use AquiHayTema\Engine\FeatureConfig;

$ok = 0;
$fail = 0;

function ok(bool $cond, string $msg): void
{
    global $ok, $fail;
    if ($cond) { $ok++; echo "  OK: $msg\n"; }
    else { $fail++; echo "  FAIL: $msg\n"; }
}

echo "=== PARTIDA NUEVA: Necesidades Initialization ===\n\n";

// ============================================================
// Helper: crear residente nuevo SIN necesidades (simula creación real)
// ============================================================
function crearResidenteNuevo(string $id): array
{
    return [
        'catalog_id' => $id,
        'presencia' => 'residente',
        'runtime' => [
            'ocupacion' => 'estudiante',
            'compromisos_recurrentes' => [],
            'visual_pack_id' => $id,
            'estado_emocional' => [
                'id' => 'neutro',
                'intensidad' => null,
                'origen' => 'inicial',
            ],
        ],
    ];
}

function crearPartidaNueva(array $residentes): array
{
    return [
        'reloj' => [
            'dia_en_temporada' => 1,
            'dia_pueblo' => 1,
            'hora_actual' => 9,
            'minuto_actual' => 0,
            'zona' => 'Europe/Madrid',
        ],
        'residentes' => $residentes,
        'features' => [
            'necesidades_enabled' => true,
        ],
    ];
}

// ============================================================
// A) SchemaFields::ensure inicializa necesidades
// ============================================================
echo "--- A) SchemaFields::ensure inicializa necesidades ---\n";

$r1 = crearResidenteNuevo('per_p005');
ok(!isset($r1['runtime']['necesidades']), 'Residente nuevo NO tiene necesidades antes de ensure');

$partida = crearPartidaNueva(['per_p005' => $r1]);
SchemaFields::ensure($partida);

$res = $partida['residentes']['per_p005'];
ok(isset($res['runtime']['necesidades']), 'Después de ensure, necesidades existen');
ok(is_array($res['runtime']['necesidades']), 'necesidades es array');

// ============================================================
// B) Las 4 necesidades canónicas existen
// ============================================================
echo "\n--- B) Las 4 necesidades canónicas existen ---\n";

$necs = $res['runtime']['necesidades'];
ok(isset($necs['social']), 'social existe');
ok(isset($necs['diversion']), 'diversion existe');
ok(isset($necs['actividad']), 'actividad existe');
ok(isset($necs['calma']), 'calma existe');
ok(count($necs) === 4, 'Exactamente 4 necesidades');

// ============================================================
// C) Valores iniciales correctos
// ============================================================
echo "\n--- C) Valores iniciales correctos ---\n";

foreach (NecesidadEstado::TODAS as $nec) {
    $n = $necs[$nec];
    ok($n['valor'] === 75, "$nec valor = 75 (INITIAL_VALUE)");
    ok($n['banda'] === 'bien', "$nec banda = 'bien'");
    ok(!is_null($n['valor']), "$nec valor no es null");
    ok($n['valor'] !== 0, "$nec valor no es 0");
}

// ============================================================
// D) Sin nulls y sin 0 artificiales
// ============================================================
echo "\n--- D) Sin nulls y sin 0 artificiales ---\n";

$hasNull = false;
$hasZero = false;
foreach ($necs as $nec => $n) {
    if ($n['valor'] === null) $hasNull = true;
    if ($n['valor'] === 0) $hasZero = true;
}
ok(!$hasNull, 'Ninguna necesidad tiene valor null');
ok(!$hasZero, 'Ninguna necesidad tiene valor 0');

// ============================================================
// E) fichaResidente retorna valores reales
// ============================================================
echo "\n--- E) fichaResidente retorna valores reales ---\n";

$ps = new PartidaService(dirname(__DIR__));
$ficha = $ps->fichaResidente($partida, 'per_p005');

ok(isset($ficha['necesidades']), 'ficha tiene key necesidades');
ok(is_array($ficha['necesidades']), 'ficha necesidades es array');
ok(isset($ficha['necesidades']['items']), 'ficha necesidades tiene items');
ok(count($ficha['necesidades']['items']) === 4, 'ficha retorna 4 items');

foreach ($ficha['necesidades']['items'] as $item) {
    ok($item['valor'] !== null, "ficha item {$item['id']} valor no es null");
    ok($item['valor'] !== 0, "ficha item {$item['id']} valor no es 0");
    ok($item['valor'] === 75, "ficha item {$item['id']} valor = 75");
    ok($item['banda'] === 'bien', "ficha item {$item['id']} banda = bien");
}

// ============================================================
// F) Primer tick parte desde esos valores (no reinicializa)
// ============================================================
echo "\n--- F) Primer tick parte desde 75, no reinicializa ---\n";

// Simular un tick de decay
$cal = [
    'necesidades' => [
        'decay' => [
            'bien' => ['decay_por_hora' => 0.30],
            'le_vendria_bien' => ['decay_por_hora' => 0.35],
            'lo_necesita' => ['decay_por_hora' => 0.20],
            'en_rojo' => ['decay_por_hora' => 0.15],
        ],
        'recuperacion' => [
            'rec_lugar_base' => 7.0,
            'prob_visita_autonoma' => [
                'bien' => 0.020,
                'le_vendria_bien' => 0.035,
                'lo_necesita' => 0.070,
                'en_rojo' => 0.100,
            ],
        ],
        'rec_auto_pasiva' => 0,
    ],
];

NecesidadEstado::aplicarDecay($res, $cal);

$necsPostTick = $res['runtime']['necesidades'];
foreach (NecesidadEstado::TODAS as $nec) {
    $v = $necsPostTick[$nec]['valor'];
    ok($v < 75, "$nec decayed from 75 to $v (< 75)");
    ok($v > 0, "$nec no decayed a 0 ($v > 0)");
    ok($necsPostTick[$nec]['banda'] !== null, "$nec banda post-tick no es null");
}

// Verificar que no se reinicializaron (siguen siendo las mismas keys)
ok(count($necsPostTick) === 4, 'Sigue habiendo 4 necesidades post-tick (no duplicó)');

// ============================================================
// G) Save antiguo con null se repara
// ============================================================
echo "\n--- G) Save antiguo con null se repara ---\n";

$resAntiguo = crearResidenteNuevo('per_p120');
$resAntiguo['runtime']['necesidades'] = [
    'social' => null,
    'diversion' => ['valor' => 50, 'banda' => 'le_vendria_bien', 'ultima_actualizacion' => null, 'ultima_recuperacion' => null],
    'actividad' => null,
    'calma' => ['valor' => 30, 'banda' => 'lo_necesita', 'ultima_actualizacion' => null, 'ultima_recuperacion' => null],
];

NecesidadEstado::ensureResidente($resAntiguo);

$necsRep = $resAntiguo['runtime']['necesidades'];
// social was null → should be initialized to INITIAL_VALUE
ok($necsRep['social']['valor'] === 75, 'Save antiguo: social null reparado a 75');
ok($necsRep['social']['banda'] === 'bien', 'Save antiguo: social null reparado a bien');
// diversion had real value → preserved
ok($necsRep['diversion']['valor'] === 50, 'Save antiguo: diversion 50 preservado');
ok($necsRep['diversion']['banda'] === 'le_vendria_bien', 'Save antiguo: diversion banda preservada');
// actividad was null → should be initialized
ok($necsRep['actividad']['valor'] === 75, 'Save antiguo: actividad null reparado a 75');
ok($necsRep['actividad']['banda'] === 'bien', 'Save antiguo: actividad null reparado a bien');
// calma had real value → preserved
ok($necsRep['calma']['valor'] === 30, 'Save antiguo: calma 30 preservado');
ok($necsRep['calma']['banda'] === 'lo_necesita', 'Save antiguo: calma banda preservada');

// ============================================================
// H) Save antiguo sin key necesidades se repara
// ============================================================
echo "\n--- H) Save antiguo sin key necesidades se repara ---\n";

$resSinKey = crearResidenteNuevo('per_p105');
// No设置了 necesidades at all
NecesidadEstado::ensureResidente($resSinKey);

$necsSinKey = $resSinKey['runtime']['necesidades'];
ok(isset($necsSinKey), 'Save sin key: necesidades creadas');
ok(count($necsSinKey) === 4, 'Save sin key: 4 necesidades');
foreach (NecesidadEstado::TODAS as $nec) {
    ok($necsSinKey[$nec]['valor'] === 75, "Save sin key: $nec = 75");
    ok($necsSinKey[$nec]['banda'] === 'bien', "Save sin key: $nec banda = bien");
}

// ============================================================
// I) Múltiples llamadas a ensure no duplican ni sobreescriben
// ============================================================
echo "\n--- I) ensureResidente idempotente ---\n";

$resMulti = crearResidenteNuevo('per_p200');
NecesidadEstado::ensureResidente($resMulti);
// Modify one value
$resMulti['runtime']['necesidades']['social']['valor'] = 60;
$resMulti['runtime']['necesidades']['social']['banda'] = 'le_vendria_bien';

// Call ensure again
NecesidadEstado::ensureResidente($resMulti);

ok($resMulti['runtime']['necesidades']['social']['valor'] === 60, 'ensure no sobreescribe social=60 existente');
ok($resMulti['runtime']['necesidades']['social']['banda'] === 'le_vendria_bien', 'ensure no sobreescribe banda existente');
ok($resMulti['runtime']['necesidades']['diversion']['valor'] === 75, 'ensure mantiene diversion=75');
ok(count($resMulti['runtime']['necesidades']) === 4, 'ensure no duplica necesidades');

// ============================================================
// J) Múltiples residentes en ensure batch
// ============================================================
echo "\n--- J) Múltiples residentes en ensure batch ---\n";

$r2 = crearResidenteNuevo('per_p005');
$r3 = crearResidenteNuevo('per_p120');
$r4 = crearResidenteNuevo('per_p105');
$partidaMulti = crearPartidaNueva([
    'per_p005' => $r2,
    'per_p120' => $r3,
    'per_p105' => $r4,
]);

SchemaFields::ensure($partidaMulti);

foreach (['per_p005', 'per_p120', 'per_p105'] as $rid) {
    $n = $partidaMulti['residentes'][$rid]['runtime']['necesidades'] ?? null;
    ok(is_array($n), "$rid tiene necesidades");
    ok(count($n) === 4, "$rid tiene 4 necesidades");
    foreach (NecesidadEstado::TODAS as $nec) {
        ok($n[$nec]['valor'] === 75, "$rid/$nec = 75");
        ok($n[$nec]['banda'] === 'bien', "$rid/$nec banda = bien");
    }
}

// ============================================================
// Resultado
// ============================================================
echo "\n=== Resultado: $ok ok, $fail fail ===\n";
exit($fail > 0 ? 1 : 0);
