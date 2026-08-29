<?php
declare(strict_types=1);

/**
 * MANTENIMIENTO SAVES — Limpieza de `resultado._cal` (Fase 1)
 *
 * Uso:
 *   php scripts/mantenimiento_saves.php --dry-run
 *   php scripts/mantenimiento_saves.php --dry-run --partida part_a7c314b50132e177
 *   php scripts/mantenimiento_saves.php --ejecutar
 *
 * Modo --dry-run:
 *   - NO modifica ningún save.
 *   - NO avanza reloj.
 *   - NO ejecuta simulación ni lógica de juego.
 *   - Muestra estadísticas de lo que se haría.
 *
 * Modo --ejecutar:
 *   - Escribe cambios en los saves.
 *   - Crea backup .bak antes de modificar.
 *
 * La partida de NENI queda excluida de cualquier escritura.
 */

if (PHP_INT_SIZE === 4) {
    ini_set('memory_limit', '512M');
} else {
    ini_set('memory_limit', '1G');
}

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\EncuentroResultadoSlim;
use AquiHayTema\Engine\JsonFile;

// --- Configuración ---
$EXCLUIR_PARTIDAS = [
    // Partidas que NUNCA se modifican. Añadir IDs aquí.
    // La partida de NENI se excluye explícitamente.
];

// --- Parseo de argumentos ---
$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$ejecutar = in_array('--ejecutar', $args, true);
$partidaFiltro = null;
foreach ($args as $i => $arg) {
    if ($arg === '--partida' && isset($args[$i + 1])) {
        $partidaFiltro = $args[$i + 1];
    }
}

if (!$dryRun && !$ejecutar) {
    fwrite(STDERR, "Uso: php mantenimiento_saves.php --dry-run [--partida ID] | --ejecutar\n");
    exit(1);
}
if ($dryRun && $ejecutar) {
    fwrite(STDERR, "No se pueden usar --dry-run y --ejecutar a la vez.\n");
    exit(1);
}

$dir = dirname(__DIR__) . '/data/partidas';
$files = glob($dir . '/*.json') ?: [];
// Filtrar .bak y .gitkeep
$files = array_filter($files, static fn(string $f) => !str_ends_with($f, '.bak') && basename($f) !== '.gitkeep' && basename($f) !== '.htaccess');

if ($partidaFiltro !== null) {
    $files = array_filter($files, static fn(string $f) => str_contains(basename($f), $partidaFiltro));
    $files = array_values($files);
    if ($files === []) {
        fwrite(STDERR, "Partida no encontrada: {$partidaFiltro}\n");
        exit(1);
    }
}

// --- Estadísticas globales ---
$stats = [
    'partidas_inspeccionadas' => 0,
    'partidas_con_encuentros' => 0,
    'partidas_afectadas' => 0,
    'partidas_excluidas' => 0,
    'partidas_error' => 0,
    'encuentros_inspectados' => 0,
    'encuentros_terminados_con_cal' => 0,
    'bytes_totales_antes' => 0,
    'bytes_totales_despues' => 0,
];

echo "=== MANTENIMIENTO SAVES — Limpieza _cal ===\n";
echo "Modo: " . ($dryRun ? "DRY RUN (sin modificaciones)" : "EJECUCIÓN (se escribirán cambios)") . "\n";
echo "Partidas encontradas: " . count($files) . "\n";
echo str_repeat('-', 60) . "\n\n";

foreach ($files as $f) {
    $nombre = basename($f, '.json');
    $stats['partidas_inspeccionadas']++;

    // Exclusiones
    if (in_array($nombre, $EXCLUIR_PARTIDAS, true)) {
        $stats['partidas_excluidas']++;
        echo "[EXCLUIDA] {$nombre}\n";
        continue;
    }

    try {
        $partida = JsonFile::read($f);
    } catch (\Throwable $e) {
        $stats['partidas_error']++;
        echo "[ERROR] {$nombre}: " . $e->getMessage() . "\n";
        continue;
    }

    $encuentros = $partida['encuentros'] ?? [];
    if (!is_array($encuentros) || $encuentros === []) {
        continue;
    }
    $stats['partidas_con_encuentros']++;

    // Inspección
    $info = EncuentroResultadoSlim::inspeccionar($partida);
    $stats['encuentros_inspectados'] += $info['inspectados'];
    $stats['encuentros_terminados_con_cal'] += $info['terminados_con_cal'];
    $stats['bytes_totales_antes'] += $info['bytes_antes'];
    $stats['bytes_totales_despues'] += $info['bytes_despues'];

    if ($info['terminados_con_cal'] === 0) {
        continue;
    }

    $stats['partidas_afectadas']++;
    $mbAhorrado = round($info['bytes_ahorrados'] / (1024 * 1024), 2);
    echo "[AFECTADA] {$nombre}: " . $info['terminados_con_cal'] . " encuentros con _cal → ahorraría {$mbAhorrado} MB\n";

    // Ejecución (solo si no es dry-run)
    if (!$dryRun) {
        // Backup
        $bakPath = $f . '.bak';
        if (!copy($f, $bakPath)) {
            echo "  [ERROR] No se pudo crear backup: {$bakPath}\n";
            continue;
        }

        // Limpiar
        $resultado = EncuentroResultadoSlim::limpiarPartida($partida);

        // Guardar
        JsonFile::write($f, $partida);
        echo "  → Limpiados {$resultado['limpiados']} encuentros. Guardado.\n";
    }
}

// --- Resumen ---
echo "\n" . str_repeat('=', 60) . "\n";
echo "RESUMEN\n";
echo str_repeat('-', 60) . "\n";
echo "Partidas inspeccionadas:     {$stats['partidas_inspeccionadas']}\n";
echo "Partidas con encuentros:     {$stats['partidas_con_encuentros']}\n";
echo "Partidas excluidas:          {$stats['partidas_excluidas']}\n";
echo "Partidas con error:          {$stats['partidas_error']}\n";
echo "Partidas afectadas:          {$stats['partidas_afectadas']}\n";
echo "Encuentros inspeccionados:   {$stats['encuentros_inspectados']}\n";
echo "Encuentros con _cal:         {$stats['encuentros_terminados_con_cal']}\n";
echo str_repeat('-', 60) . "\n";

$mbAntes = round($stats['bytes_totales_antes'] / (1024 * 1024), 2);
$mbDespues = round($stats['bytes_totales_despues'] / (1024 * 1024), 2);
$mbAhorrado = round(($stats['bytes_totales_antes'] - $stats['bytes_totales_despues']) / (1024 * 1024), 2);
$pct = $stats['bytes_totales_antes'] > 0
    ? round(($stats['bytes_totales_antes'] - $stats['bytes_totales_despues']) / $stats['bytes_totales_antes'] * 100, 1)
    : 0;

echo "Bytes encuentros antes:      {$mbAntes} MB\n";
echo "Bytes encuentros después:    {$mbDespues} MB\n";
echo "Bytes ahorrados:             {$mbAhorrado} MB ({$pct}%)\n";
echo str_repeat('=', 60) . "\n";

if ($dryRun) {
    echo "\nDRY RUN completado. Ningún archivo fue modificado.\n";
} else {
    echo "\nEjecución completada. Se crearon backups (.bak) de las partidas modificadas.\n";
}
