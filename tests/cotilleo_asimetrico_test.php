<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\EncuentroCotilleoCopy;

$root = dirname(__DIR__);
$fail = 0;

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$pA = 'per_hugo';
$pB = 'per_tamara';

// --- Test 1: Ambos positivos → "buenas migas" ---
$resAmbos = [
    'por_participante' => [
        $pA => ['resultado' => 'bien'],
        $pB => ['resultado' => 'bien'],
    ],
    'delta_social' => ['intensidad' => 4],
];
// Usar reflection para testear tonoExperiencia privada
$ref = new ReflectionMethod(EncuentroCotilleoCopy::class, 'tonoExperiencia');
$ref->setAccessible(true);
$tono = $ref->invoke(null, $resAmbos, [$pA, $pB]);
ok($tono === 'Parece que han hecho buenas migas.', 'ambos positivos: buenas migas');

// --- Test 2: Ambos negativos → "fría" ---
$resAmbosNeg = [
    'por_participante' => [
        $pA => ['resultado' => 'mal'],
        $pB => ['resultado' => 'muy_mal'],
    ],
    'delta_social' => ['intensidad' => -5],
];
$tono2 = $ref->invoke(null, $resAmbosNeg, [$pA, $pB]);
ok($tono2 === 'La cosa ha estado algo fría.', 'ambos negativos: fría');

// --- Test 3: Mixto (Hugo bien, Tamara mal) → asimétrico ---
$resMixto = [
    'por_participante' => [
        $pA => ['resultado' => 'bien'],
        $pB => ['resultado' => 'mal'],
    ],
    'delta_social' => ['intensidad' => 0],
];
$tono3 = $ref->invoke(null, $resMixto, [$pA, $pB]);
ok($tono3 === 'A uno le ha ido bien, pero el otro no conectó demasiado.', 'mixto: asimétrico');
ok(!str_contains($tono3, 'buenas migas'), 'mixto: no cae en "buenas migas"');
ok(!str_contains($tono3, 'tensa'), 'mixto: no cae en "tensa" (que es solo negativo)');

// --- Test 4: Mixto inverso (Hugo mal, Tamara muy bien) → también asimétrico ---
$resMixtoInv = [
    'por_participante' => [
        $pA => ['resultado' => 'muy_mal'],
        $pB => ['resultado' => 'muy_bien'],
    ],
    'delta_social' => ['intensidad' => 1],
];
$tono4 = $ref->invoke(null, $resMixtoInv, [$pA, $pB]);
ok($tono4 === 'A uno le ha ido bien, pero el otro no conectó demasiado.', 'mixto inverso: asimétrico');

// --- Test 5: Solo uno con resultado → cae en delta ---
$resUno = [
    'por_participante' => [
        $pA => ['resultado' => 'bien'],
    ],
    'delta_social' => ['intensidad' => 3],
];
$tono5 = $ref->invoke(null, $resUno, [$pA]);
ok($tono5 === 'Parece que han hecho buenas migas.', 'solo uno resultado: delta positivo');

// --- Test 6: Sin por_participante → delta ---
$resVacio = [
    'delta_social' => ['intensidad' => -2],
];
$tono6 = $ref->invoke(null, $resVacio, [$pA, $pB]);
ok($tono6 === 'La cosa ha estado algo tensa.', 'sin participantes: delta negativo');

// --- Test 7: compilar con resultado asimétrico en escenario real ---
// Verificar que el cotilleo global no rompe con mixto
$service = new \AquiHayTema\Engine\PartidaService($root);
$partida = $service->nuevaPartida('playtest_01', 'cotilleo-asim');
$ida = 'per_p001';
$idb = 'per_p002';
$enc = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
ok($enc['ok'] ?? false, 'programa encuentro');

// Forzar resultado mixto
$partida['encuentros'][0]['estado'] = 'terminado';
$partida['encuentros'][0]['resultado'] = [
    '_placeholder' => false,
    '_deltas_reales' => true,
    'por_participante' => [
        $ida => ['resultado' => 'bien', 'carga' => 0.3],
        $idb => ['resultado' => 'mal', 'carga' => -0.2],
    ],
    'delta_social' => ['tipo' => 'reales', 'a_hacia_b' => 5, 'b_hacia_a' => -4, 'intensidad' => 1],
    'delta_romance' => null,
    'conflicto' => null,
    'descubrimientos' => [],
    'experiencia_narrativa' => null,
    'texto_resumen' => 'Encuentro conocerse (bien/mal).',
];

$catalog = $service->getCatalog();
$cot = EncuentroCotilleoCopy::compilar($partida, $partida['encuentros'][0], $partida['encuentros'][0]['resultado'], $catalog, $root);
ok($cot !== null, 'compilar no null con mixto');
ok(is_string($cot['texto'] ?? ''), 'compilar produce texto');
echo "\n--- Cotilleo asimétrico ---\n" . ($cot['texto'] ?? '') . "\n";

echo $fail === 0 ? "\ncotilleo_asimetrico_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
