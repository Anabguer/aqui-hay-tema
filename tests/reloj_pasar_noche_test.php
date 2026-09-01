<?php
declare(strict_types=1);

/**
 * Regresión: «Pasar la noche» (23:00 → 08:00) debe cerrar día sin fatal
 * MensajitoDudaPermanenciaEngine not found.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoDudaPermanenciaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelojOperations;

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

ok(
    class_exists(MensajitoDudaPermanenciaEngine::class),
    'autoload: MensajitoDudaPermanenciaEngine existe'
);

$svc = new PartidaService($root);
$partida = $svc->nuevaPartida('juego_v1', 'pasar-noche-' . time());
$partida['features']['buzon_enabled'] = true;
$partida['features']['mensajitos_espontaneos_enabled'] = false;

$diaInicio = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
$partida['reloj']['hora_actual'] = 23;
$partida['reloj']['dia_pueblo'] = $diaInicio;

$ro = new RelojOperations($root);
$horasHastaManana = 9; // 23 → 08 del día siguiente

try {
    $r = $ro->avanzarPasoAPaso($partida, $horasHastaManana);
    ok(($r['ok'] ?? true) !== false, 'pasar noche: avanzarPasoAPaso sin error');
} catch (Throwable $e) {
    ok(false, 'pasar noche: excepción — ' . $e->getMessage());
    $r = ['ok' => false];
}

if (($r['ok'] ?? true) !== false) {
    ok((int) ($partida['reloj']['hora_actual'] ?? -1) === 8, 'pasar noche: hora 08:00');
    ok(
        (int) ($partida['reloj']['dia_pueblo'] ?? 0) === $diaInicio + 1,
        'pasar noche: dia_pueblo +1'
    );
}

if ($failures > 0) {
    fwrite(STDERR, "FAIL reloj_pasar_noche ($failures)\n");
    exit(1);
}

echo "OK reloj_pasar_noche\n";
