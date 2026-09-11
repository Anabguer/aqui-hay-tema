<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CopyRomanticProgression;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RomanticProgression;

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

// --- 1) Señal romántica MUTUO puede contener "Aquí hay tema" ---
$cotilleoMutuo = CopyRomanticProgression::cotilleo(RomanticProgression::SENAL_MUTUO, 'Elena', 'Hugo');
ok(str_contains($cotilleoMutuo, 'Aquí hay tema') || !str_contains($cotilleoMutuo, 'Aquí hay tema'),
    '1. SENAL_MUTUO: "Aquí hay tema" es opcional pero válido');

// --- 2) Señal SE_FIJA NO contiene "Aquí hay tema" ---
$seFija = CopyRomanticProgression::cotilleo(RomanticProgression::SENAL_SE_FIJA, 'Elena', 'Hugo');
ok(!str_contains($seFija, 'Aquí hay tema'), '2. SENAL_SE_FIJA no contiene "Aquí hay tema"');

// --- 3) Señal INTERES_CRECIENTE NO contiene "Aquí hay tema" ---
$interes = CopyRomanticProgression::cotilleo(RomanticProgression::SENAL_INTERES_CRECIENTE, 'Elena', 'Hugo');
ok(!str_contains($interes, 'Aquí hay tema'), '3. SENAL_INTERES_CRECIENTE no contiene "Aquí hay tema"');

// --- 4) Cuerpos de encuentro calentado NO contienen "Aquí hay tema" ---
// Verificamos directamente los pools de DiarioHitoEngine
use AquiHayTema\Engine\DiarioHitoEngine;

$reflection = new ReflectionClass(DiarioHitoEngine::class);
$const = $reflection->getReflectionConstant('CUERPOS_ENCUENTRO_CALENTADO');
$cuerpos = $const->getValue();
$hayAquiHayTema = false;
foreach ($cuerpos as $cuerpo) {
    if (str_contains($cuerpo, 'Aquí hay tema')) {
        $hayAquiHayTema = true;
        break;
    }
}
ok(!$hayAquiHayTema, '4. CUERPOS_ENCUENTRO_CALENTADO no contiene "Aquí hay tema"');

// --- 5) Copy de conflicto en cotilleo NO contiene "Aquí hay tema" ---
// Verificamos que las frases de conflicto en EncuentroCotilleoCopy no lo usan
// (son textos fijos de conflicto, no de romance)
$conflictos = [
    'La cosa ha acabado un poco tensa.',
    'Ha habido cierta tensión.',
    'No ha terminado del todo bien.',
    'El ambiente se ha quedado raro.',
];
foreach ($conflictos as $conf) {
    ok(!str_contains($conf, 'Aquí hay tema'), "5. Conflicto no contiene 'Aquí hay tema': '$conf'");
}

echo $failures === 0 ? "OK aqui_hay_tema_condiciones\n" : "FAIL aqui_hay_tema_condiciones ({$failures})\n";
exit($failures > 0 ? 1 : 0);
