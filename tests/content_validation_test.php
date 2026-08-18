<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ContentValidationException;
use AquiHayTema\Engine\JsonFile;
use AquiHayTema\Engine\LugarValidator;
use AquiHayTema\Engine\PersonajeValidator;

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

$fixtureDir = __DIR__ . '/fixtures/content';
$lugares = LugarValidator::extraerIds(JsonFile::read("{$root}/data/lugares/lugares.json"));

// Válido QA
$errores = PersonajeValidator::validarArchivo("{$root}/data/personajes/per_qa_valid.json", $lugares);
ok($errores === [], 'per_qa_valid pasa validación');

// Campo obligatorio ausente
$sinId = ['identidad' => ['nombre' => 'X'], 'vida' => []];
ok(count(PersonajeValidator::validar($sinId, 'test.json', $lugares)) > 0, 'campo id ausente rechazado');

// Enum inválido
$enumBad = [
    'id' => 'per_bad01',
    'slot' => 'I01',
    'identidad' => ['nombre' => 'Bad', 'genero' => 'robot'],
    'vida' => [
        'ocupacion' => 'autonomo',
        'hobby_principal' => 'pasear',
        'estilo_social' => 'tranquilo',
        'rasgos_publicos' => ['directo', 'leal', 'empatico'],
    ],
];
ok(count(PersonajeValidator::validar($enumBad, 'test.json', $lugares)) > 0, 'enum genero inválido');

// Referencia lugar inexistente
$refBad = [
    'id' => 'per_bad02',
    'slot' => 'I01',
    'identidad' => ['nombre' => 'Bad2', 'genero' => 'mujer'],
    'vida' => [
        'ocupacion' => 'autonomo',
        'hobby_principal' => 'pasear',
        'estilo_social' => 'tranquilo',
        'rasgos_publicos' => ['directo', 'leal', 'empatico'],
        'lugares_preferentes' => ['lug_inexistente'],
    ],
];
$errs = PersonajeValidator::validar($refBad, 'test.json', $lugares);
$reglas = array_column($errs, 'regla');
ok(in_array('referencia_inexistente', $reglas, true), 'referencia lugar inexistente');

// Placeholder marcado
$ph = ['id' => 'per_ph01', '_placeholder' => true];
ok(PersonajeValidator::validarPlaceholder($ph, 'ph.json') === [], 'placeholder correctamente marcado');

// Catalog rechaza inválido
$catalog = new Catalog($root);
try {
    $catalog->loadPersonaje('per_qa_valid');
    ok(true, 'catalog carga válido');
} catch (ContentValidationException) {
    ok(false, 'catalog debe cargar per_qa_valid');
}

// Duplicado lugares
$lugDup = ['items' => [
    ['id' => 'lug_a', 'nombre' => 'A'],
    ['id' => 'lug_a', 'nombre' => 'A2'],
]];
ok(count(LugarValidator::validar($lugDup, 'lug.json')) > 0, 'lugar id duplicado');

// Informe fichas reales (no falla suite — documenta incumplimientos)
$informe = PersonajeValidator::auditarDirectorio("{$root}/data/personajes", $lugares);
foreach ($informe as $archivo => $errs) {
    if (str_contains($archivo, 'per_qa_valid')) {
        continue;
    }
    echo "REPORTE FICHA REAL {$archivo}: " . count($errs) . " error(es)\n";
    foreach (array_slice($errs, 0, 5) as $e) {
        echo "  - {$e['campo']}: {$e['regla']} = " . json_encode($e['valor']) . "\n";
    }
}
ok(isset($informe['per_i03.json']), 'REPORTE: per_i03 (Rocío) tiene incumplimientos de contrato');
ok(!isset($informe['per_qa_valid.json']), 'per_qa_valid sin incumplimientos');

exit($failures > 0 ? 1 : 0);
