<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\DiarioHitoEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;

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

// --- 1) Primer contacto incluye nombre del otro ---
$p1 = $service->nuevaPartida('juego_v1', 'pc-nombre');
$ids = array_keys($p1['residentes']);
$a = (string) $ids[0];
$b = (string) $ids[1];
RelacionBitacora::registrar($p1, RelacionBitacora::SE_CONOCIERON, [$a, $b]);

$nombreB = $p1['residentes'][$b]['identidad_publica']['nombre'] ?? $b;
$entradas = DiarioEngine::listarPorResidente($p1, $a);
$hayConNombre = false;
foreach ($entradas as $e) {
    $texto = (string) ($e['texto'] ?? '');
    if (str_contains($texto, $nombreB)) {
        $hayConNombre = true;
        break;
    }
}
ok($hayConNombre, '1. primer contacto incluye nombre del otro');

// --- 2) No aparece "somos desconocidos" sin nombre ---
$hayGenerico = false;
foreach ($entradas as $e) {
    $texto = (string) ($e['texto'] ?? '');
    if (str_contains($texto, 'somos desconocidos') || str_contains($texto, 'conocernos')) {
        $hayGenerico = true;
        break;
    }
}
ok(!$hayGenerico, '2. no aparece "somos desconocidos" genérico');

// --- 3) Primer contacto es en 1ra persona ---
$hayPrimeraPersona = false;
foreach ($entradas as $e) {
    $texto = (string) ($e['texto'] ?? '');
    if (str_contains($texto, 'he conocido') || str_contains($texto, 'he hablado') || str_contains($texto, 'hemos presentado')) {
        $hayPrimeraPersona = true;
        break;
    }
}
ok($hayPrimeraPersona, '3. primer contacto en 1ra persona');

// --- 4) Ambos residentes tienen entrada propia ---
$entradasB = DiarioEngine::listarPorResidente($p1, $b);
$nombreA = $p1['residentes'][$a]['identidad_publica']['nombre'] ?? $a;
$hayEntradaB = false;
foreach ($entradasB as $e) {
    $texto = (string) ($e['texto'] ?? '');
    if (str_contains($texto, $nombreA)) {
        $hayEntradaB = true;
        break;
    }
}
ok($hayEntradaB, '4. residente B también tiene entrada con nombre de A');

echo $failures === 0 ? "OK diario_primer_contacto\n" : "FAIL diario_primer_contacto ({$failures})\n";
exit($failures > 0 ? 1 : 0);
