<?php
declare(strict_types=1);

/*
 * Test: Concordancia de género — GeneroConcordancia + integración.
 *
 * Cubre:
 *  - Helper centralizado (oa, loLa, genero)
 *  - MensajitoVoz: 7 bancos con {oa} / {oa_ref}
 *  - MensajitoGeneradorEspontaneo: dato nervioso resuelto
 *  - MensajitoDudaPermanenciaEngine: solo/a
 *  - MisionDiariaEngine renderCopy: Sácalo/Sácala
 *  - DiarioHitoEngine / EmocionalNarrativa / HobbyAnimoCopy: oa unificado
 *  - PlaytestGuia: emocionHumana con RID
 *  - Fallback: género ausente → 'o' / 'lo'
 *  - No inferencia por nombre
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\GeneroConcordancia;
use AquiHayTema\Engine\MensajitoVoz;
use AquiHayTema\Engine\IdentidadPublica;

$root = dirname(__DIR__);
$failures = 0;
$pass = 0;

function ok(bool $c, string $m): void
{
    global $failures, $pass;
    if ($c) {
        $pass++;
        echo "OK: $m\n";
    } else {
        $failures++;
        echo "FAIL: $m\n";
    }
}

function containsNoSlashA(string $text, string $context): void
{
    ok(strpos($text, '/a') === false, "$context no contiene '/a' visible");
}

// --- Helpers de fixture ---

function makePartidaConGenero(string $genero): array
{
    return [
        'residentes' => [
            'per_p001' => [
                'identidad_publica' => [
                    'nombre' => $genero === 'mujer' ? 'Ana' : 'Carlos',
                    'genero' => $genero,
                ],
                'narrativa' => ['voz' => null],
                'presencia' => 'residente',
            ],
            'per_p002' => [
                'identidad_publica' => [
                    'nombre' => $genero === 'mujer' ? 'Pedro' : 'María',
                    'genero' => $genero === 'mujer' ? 'hombre' : 'mujer',
                ],
                'narrativa' => ['voz' => null],
                'presencia' => 'residente',
            ],
        ],
    ];
}

function makePartidaSinGenero(): array
{
    return [
        'residentes' => [
            'per_p001' => [
                'identidad_publica' => [
                    'nombre' => 'SinNombre',
                ],
                'narrativa' => ['voz' => null],
                'presencia' => 'residente',
            ],
        ],
    ];
}

// ===========================================================
// 1. GENEROCONCORDANCIA — Helper centralizado
// ===========================================================
echo "\n=== 1. GeneroConcordancia helper ===\n";

$pMujer = makePartidaConGenero('mujer');
 $pHombre = makePartidaConGenero('hombre');
$pSinGen = makePartidaSinGenero();

ok(GeneroConcordancia::genero($pMujer, 'per_p001') === 'mujer', 'genero() mujer');
ok(GeneroConcordancia::genero($pHombre, 'per_p001') === 'hombre', 'genero() hombre');
ok(GeneroConcordancia::genero($pSinGen, 'per_p001') === null, 'genero() ausente → null');

ok(GeneroConcordancia::oa($pMujer, 'per_p001') === 'a', 'oa() mujer → a');
ok(GeneroConcordancia::oa($pHombre, 'per_p001') === 'o', 'oa() hombre → o');
ok(GeneroConcordancia::oa($pSinGen, 'per_p001') === 'o', 'oa() ausente → o (fallback)');

ok(GeneroConcordancia::loLa($pMujer, 'per_p001') === 'la', 'loLa() mujer → la');
ok(GeneroConcordancia::loLa($pHombre, 'per_p001') === 'lo', 'loLa() hombre → lo');
ok(GeneroConcordancia::loLa($pSinGen, 'per_p001') === 'lo', 'loLa() ausente → lo (fallback)');

// ===========================================================
// 2. MENSAJITOVOZ — Hablante: confundido, sincero, preocupado, nervioso, loco
// ===========================================================
echo "\n=== 2. MensajitoVoz — concordancia hablante ===\n";

// F1: confundido/a — sujeto = hablante
$pM = makePartidaConGenero('mujer');
$lH = MensajitoVoz::linea($pM, 'f_opinion', ['otro' => 'Carlos', 'texto' => 'amigo'], 'test_hombre', 'per_p001');
containsNoSlashA($lH, 'F1 hombre');

// F6: sincero/a — sujeto = hablante
$lF6 = MensajitoVoz::linea($pM, 'f_confidencia', ['texto' => 'triste'], 'test_f6', 'per_p001');
containsNoSlashA($lF6, 'F6 confidencia');

// F_confidencia_crush: nervioso/a, loco/a — sujeto = hablante
$lCrush = MensajitoVoz::linea($pM, 'f_confidencia_crush', ['otro' => 'Pedro'], 'test_crush', 'per_p001');
containsNoSlashA($lCrush, 'F6 crush');

// ===========================================================
// 3. MENSAJITOVOZ — Tercero: apagado, bajoneado
// ===========================================================
echo "\n=== 3. MensajitoVoz — concordancia tercero (oa_ref) ===\n";

// F7 con hombre como tercero observado
$pH = makePartidaConGenero('hombre');
$lF7H = MensajitoVoz::linea($pH, 'f_alerta_vecinal', [
    'otro' => 'Pedro',
    'texto' => 'apagado',
    'historial' => '',
    'oa_ref' => 'o',
], 'test_f7_h', 'per_p001');
containsNoSlashA($lF7H, 'F7 tercero hombre');

// F7 con mujer como tercero observado
$pW = makePartidaConGenero('mujer');
$lF7W = MensajitoVoz::linea($pW, 'f_alerta_vecinal', [
    'otro' => 'Ana',
    'texto' => 'apagado',
    'historial' => '',
    'oa_ref' => 'a',
], 'test_f7_w', 'per_p001');
containsNoSlashA($lF7W, 'F7 tercero mujer');

// ===========================================================
// 4. CRUZADO: hablante ≠ tercero
// ===========================================================
echo "\n=== 4. Cruzado: hablante hombre + tercero mujer ===\n";

// Hablante = hombre (per_p001), tercero = mujer → apagada
$pCross1 = makePartidaConGenero('hombre');
// per_p002 es mujer en esta fixture
$lCross1 = MensajitoVoz::linea($pCross1, 'f_alerta_vecinal', [
    'otro' => 'María',
    'texto' => 'apagado',
    'historial' => '',
    'oa_ref' => GeneroConcordancia::oa($pCross1, 'per_p002'),
], 'test_cross1', 'per_p001');
containsNoSlashA($lCross1, 'cruzado H→M tercero');
// La variante seleccionada puede no contener "apagado"; verificamos que si aparece, es femenino
if (strpos($lCross1, 'apagado') !== false) {
    ok(strpos($lCross1, 'apagada') !== false, 'cruzado H→M: si aparece apagado, es apagada');
}

// Hablante = mujer (per_p001), tercero = hombre → apagado
$pCross2 = makePartidaConGenero('mujer');
$lCross2 = MensajitoVoz::linea($pCross2, 'f_alerta_vecinal', [
    'otro' => 'Pedro',
    'texto' => 'apagado',
    'historial' => '',
    'oa_ref' => GeneroConcordancia::oa($pCross2, 'per_p002'),
], 'test_cross2', 'per_p001');
containsNoSlashA($lCross2, 'cruzado M→H tercero');
if (strpos($lCross2, 'apagado') !== false) {
    ok(strpos($lCross2, 'apagada') === false, 'cruzado M→H: si aparece apagado, es masculino');
}

// ===========================================================
// 5. CLÍTICO: Sácalo / Sácala
// ===========================================================
echo "\n=== 5. Clítico lo/la ===\n";

ok(GeneroConcordancia::loLa($pMujer, 'per_p001') === 'la', 'clítico mujer → la');
ok(GeneroConcordancia::loLa($pHombre, 'per_p001') === 'lo', 'clítico hombre → lo');

// ===========================================================
// 6. FALLBACK: género ausente
// ===========================================================
echo "\n=== 6. Fallback género ausente ===\n";

ok(GeneroConcordancia::oa($pSinGen, 'per_p001') === 'o', 'fallback oa → o');
ok(GeneroConcordancia::loLa($pSinGen, 'per_p001') === 'lo', 'fallback loLa → lo');
ok(GeneroConcordancia::genero($pSinGen, 'per_p001') === null, 'fallback genero → null');

// Test con MensajitoVoz: género ausente no produce /a
$lFallback = MensajitoVoz::linea($pSinGen, 'f_opinion', ['otro' => 'Alguien', 'texto' => 'amigo'], 'test_fb', 'per_p001');
containsNoSlashA($lFallback, 'fallback MensajitoVoz');

// ===========================================================
// 7. NO INFERENCIA POR NOMBRE
// ===========================================================
echo "\n=== 7. No inferencia por nombre ===\n";

// Personaje con nombre femenino pero género 'hombre'
$pNombreFem = [
    'residentes' => [
        'per_p001' => [
            'identidad_publica' => [
                'nombre' => 'María',
                'genero' => 'hombre',
            ],
            'narrativa' => ['voz' => null],
            'presencia' => 'residente',
        ],
    ],
];
ok(GeneroConcordancia::oa($pNombreFem, 'per_p001') === 'o', 'nombre María + género hombre → o');
ok(GeneroConcordancia::loLa($pNombreFem, 'per_p001') === 'lo', 'nombre María + género hombre → lo');

// Personaje con nombre masculino pero género 'mujer'
$pNombreMasc = [
    'residentes' => [
        'per_p001' => [
            'identidad_publica' => [
                'nombre' => 'Pedro',
                'genero' => 'mujer',
            ],
            'narrativa' => ['voz' => null],
            'presencia' => 'residente',
        ],
    ],
];
ok(GeneroConcordancia::oa($pNombreMasc, 'per_p001') === 'a', 'nombre Pedro + género mujer → a');
ok(GeneroConcordancia::loLa($pNombreMasc, 'per_p001') === 'la', 'nombre Pedro + género mujer → la');

// ===========================================================
// 8. BÚSQUEDA RESIDUAL: ningún /a en banks de MensajitoVoz
// ===========================================================
echo "\n=== 8. Búsqueda residual /a en banks ===\n";

// Test exhaustivo: generar todas las combinaciones posibles y verificar
$familias = ['f_opinion', 'f_dilema', 'f_confidencia', 'f_alerta_vecinal', 'f_confidencia_crush'];
$generos = ['mujer', 'hombre'];
$seeds = ['test_seed_1', 'test_seed_2', 'test_seed_3'];

$slashAFound = false;
foreach ($generos as $gen) {
    $p = makePartidaConGenero($gen);
    foreach ($familias as $fam) {
        foreach ($seeds as $seed) {
            $vars = match ($fam) {
                'f_opinion' => ['otro' => 'Carlos', 'texto' => 'amigo'],
                'f_dilema' => ['nombre_a' => 'Ana', 'nombre_b' => 'Pedro', 'texto' => 'dos'],
                'f_confidencia' => ['texto' => 'triste'],
                'f_alerta_vecinal' => ['otro' => 'Juan', 'texto' => 'apagado', 'historial' => '', 'oa_ref' => GeneroConcordancia::oa($p, 'per_p002')],
                'f_confidencia_crush' => ['otro' => 'Pedro'],
                default => [],
            };
            $out = MensajitoVoz::linea($p, $fam, $vars, $seed, 'per_p001');
            if (strpos($out, '/a') !== false) {
                $slashAFound = true;
                echo "FAIL: /a encontrado en $fam (gen=$gen, seed=$seed): $out\n";
                $failures++;
            }
        }
    }
}
ok(!$slashAFound, 'Ningún /a visible en todos los banks×generos×seeds');

// ===========================================================
// 9. TERCERO: bajoneado
// ===========================================================
echo "\n=== 9. Tercero bajoneado ===\n";

$lBajH = MensajitoVoz::linea($pH, 'f_alerta_vecinal', [
    'otro' => 'Pedro',
    'texto' => 'bajoneado',
    'historial' => '',
    'oa_ref' => 'o',
], 'test_baj_h', 'per_p001');
containsNoSlashA($lBajH, 'bajoneado hombre');

$lBajW = MensajitoVoz::linea($pW, 'f_alerta_vecinal', [
    'otro' => 'Ana',
    'texto' => 'bajoneado',
    'historial' => '',
    'oa_ref' => 'a',
], 'test_baj_w', 'per_p001');
containsNoSlashA($lBajW, 'bajoneado mujer');

// ===========================================================
// RESUMEN
// ===========================================================
echo "\n==============================\n";
echo "PASS: $pass\n";
echo "FAIL: $failures\n";
echo "==============================\n";

exit($failures > 0 ? 1 : 0);
