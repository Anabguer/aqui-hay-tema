<?php
declare(strict_types=1);

/**
 * Simulación longitudinal de necesidades: valida evolución a 10, 30, 100 días.
 * Ejecuta tickHora real hora a hora, incluyendo recuperación por lugares.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\MotorVidaDiaria;
use AquiHayTema\Engine\NecesidadEstado;
use AquiHayTema\Engine\PartidaLifecycle;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorMisionesDiarias;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);

// Crear partida con 8 residentes
$rng = new RngService('necesidades-longitudinal-test');
$p = SimuladorMisionesDiarias::partidaLab(8, $rng, $cal);
unset($p['_lab_misiones_b3']);
$p['features'] = [
    'necesidades_enabled' => true,
    'encuentros_enabled' => true,
    'npc_autonomy_enabled' => false,
    'vida_pueblo_enabled' => false,
];
$p['reloj']['hora_actual'] = 9;
$p['reloj']['minuto_actual'] = 0;

// Asegurar necesidades inicializadas
foreach ($p['residentes'] as &$res) {
    NecesidadEstado::ensureResidente($res, $p['reloj']);
}
unset($res);

$rids = array_keys($p['residentes']);
echo "Residentes: " . count($rids) . "\n";

// Lugares básicos para recuperación (bar, parque, biblioteca, gym)
$lugares = [
    ['nombre' => 'Bar', 'necesidades' => ['social' => 'principal', 'diversion' => 'secundaria', 'actividad' => null, 'calma' => null]],
    ['nombre' => 'Parque', 'necesidades' => ['social' => null, 'diversion' => null, 'actividad' => 'principal', 'calma' => 'secundaria']],
    ['nombre' => 'Biblioteca', 'necesidades' => ['social' => null, 'diversion' => null, 'actividad' => null, 'calma' => 'principal']],
    ['nombre' => 'Plaza', 'necesidades' => ['social' => 'principal', 'diversion' => null, 'actividad' => null, 'calma' => null]],
];

// Función para simular recuperación aleatoria por lugar
function simularRecuperacion(array &$p, array $rids, array $lugares, RngService $rng, array $cal): void {
    foreach ($rids as $rid) {
        // 30% probabilidad de visitar un lugar cada hora
        if ($rng->nextFloat() < 0.30) {
            $lugar = $lugares[array_rand($lugares)];
            $estaAcompanado = $rng->nextFloat() < 0.4;
            $hobbyMatch = $rng->nextFloat() < 0.25;
            NecesidadEstado::aplicarRecuperacion(
                $p['residentes'][$rid],
                $lugar['necesidades'],
                $estaAcompanado,
                $hobbyMatch,
                $cal
            );
        }
    }
}

// Estado inicial
function estadisticas(array $p, array $rids): array {
    $stats = [];
    foreach (NecesidadEstado::TODAS as $nec) {
        $vals = [];
        foreach ($rids as $rid) {
            $n = NecesidadEstado::obtener($p['residentes'][$rid]);
            $vals[] = $n[$nec]['valor'];
        }
        $stats[$nec] = [
            'min' => min($vals),
            'max' => max($vals),
            'avg' => round(array_sum($vals) / count($vals), 1),
            'vals' => $vals,
        ];
    }
    return $stats;
}

function vectoresResidentes(array $p, array $rids): array {
    $vectores = [];
    foreach ($rids as $rid) {
        $n = NecesidadEstado::obtener($p['residentes'][$rid]);
        $vectores[$rid] = array_map(function($x) { return $x['valor']; }, $n);
    }
    return $vectores;
}

function contarVectoresUnicos(array $vectores): int {
    $unique = [];
    foreach ($vectores as $vec) {
        $key = implode(',', $vec);
        $unique[$key] = true;
    }
    return count($unique);
}

// Estado inicial
$stats0 = estadisticas($p, $rids);
echo "\n=== ESTADO INICIAL (día 0) ===\n";
foreach ($stats0 as $nec => $s) {
    echo "  $nec: min={$s['min']} max={$s['max']} avg={$s['avg']}\n";
}

// Simular 100 días: 24 horas por día
$maxDias = 100;
$marcas = [10, 30, 50, 100];

for ($dia = 1; $dia <= $maxDias; $dia++) {
    // Avanzar 24 horas
    for ($hora = 0; $hora < 24; $hora++) {
        $p['reloj']['hora_actual'] = $hora;
        $p['reloj']['minuto_actual'] = 0;

        // tickHora aplica decay si hora >= 9 y <= 22
        MotorVidaDiaria::tickHora($p, $catalog, $cal, $rng);

        // Recuperación aleatoria por lugar
        simularRecuperacion($p, $rids, $lugares, $rng, $cal);
    }

    // Avanzar día
    Reloj::avanzarHoras($p, 0); // Force day change
    $p['reloj']['dia_pueblo'] = ($p['reloj']['dia_pueblo'] ?? 1) + 1;
    $p['reloj']['dia_en_temporada'] = ($p['reloj']['dia_en_temporada'] ?? 1) + 1;

    // Reportar en marcas
    if (in_array($dia, $marcas, true)) {
        $stats = estadisticas($p, $rids);
        $vectores = vectoresResidentes($p, $rids);
        $diversos = contarVectoresUnicos($vectores);

        echo "\n=== DÍA $dia ===\n";
        foreach ($stats as $nec => $s) {
            echo "  $nec: min={$s['min']} max={$s['max']} avg={$s['avg']}\n";
        }
        echo "  Vectores únicos: $diversos / " . count($rids) . "\n";

        // Verificar no hay fantasma
        if (isset($p['runtime']['necesidades'])) {
            echo "  ** FANTASMA DETECTADO **\n";
        }
    }
}

// Estado final detallado
echo "\n=== ESTADO FINAL (día $maxDias) - POR RESIDENTE ===\n";
foreach ($rids as $rid) {
    $n = NecesidadEstado::obtener($p['residentes'][$rid]);
    $vals = array_map(function($x) { return $x['valor']; }, $n);
    $bands = array_map(function($x) { return $x['banda']; }, $n);
    echo "  $rid: soc={$n['social']['valor']}({$n['social']['banda']}) "
       . "div={$n['diversion']['valor']}({$n['diversion']['banda']}) "
       . "act={$n['actividad']['valor']}({$n['actividad']['banda']}) "
       . "cal={$n['calma']['valor']}({$n['calma']['banda']})\n";
}

// Verificar diversidad
$vectoresFinales = vectoresResidentes($p, $rids);
$uniqueFinal = contarVectoresUnicos($vectoresFinales);
echo "\nVectores únicos finales: $uniqueFinal / " . count($rids) . "\n";

// Verificar que ninguna necesidad está sistemáticamente en 0 o 100
$enCero = 0;
$enCien = 0;
foreach ($vectoresFinales as $vec) {
    foreach ($vec as $v) {
        if ($v <= 0) $enCero++;
        if ($v >= 100) $enCien++;
    }
}
$totalChecks = count($rids) * 4;
echo "Valores en 0: $enCero / $totalChecks\n";
echo "Valores en 100: $enCien / $totalChecks\n";

// Verificar fantasma
$hasPhantom = isset($p['runtime']['necesidades']);
echo "Fantasma en runtime raíz: " . ($hasPhantom ? "SÍ (PROBLEMA)" : "NO (OK)") . "\n";

echo "\n=== DIAGNÓSTICO ===\n";
if ($uniqueFinal >= 2) {
    echo "OK: Diversidad de vectores entre residentes\n";
} else {
    echo "PROBLEMA: Todos los residentes tienen el mismo vector\n";
}
if ($enCero === 0 && $enCien === 0) {
    echo "OK: No hay necesidades pegadas en 0 o 100\n";
} else {
    echo "AVISO: Hay valores en extremos (0=$enCero, 100=$enCien)\n";
}
if (!$hasPhantom) {
    echo "OK: Sin fantasma en runtime raíz\n";
} else {
    echo "PROBLEMA: Fantasma detectado\n";
}
