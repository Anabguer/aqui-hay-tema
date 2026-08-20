<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\SimuladorHitosRelacionales;

$root = dirname(__DIR__);
$full = in_array('--full', $argv ?? [], true);
$rapido = in_array('--rapido', $argv ?? [], true);

echo 'sim hitos start ' . date('H:i:s') . "\n";
if ($rapido) {
    $rep = SimuladorHitosRelacionales::ejecutar($root, [8, 16], [30, 100], 2, ['activa', 'normal', 'inactiva'], 'hitos-rapido');
} elseif ($full) {
    $rep = SimuladorHitosRelacionales::ejecutar($root, [8, 16, 32, 48], [30, 100, 365, 700], 3, ['activa', 'normal', 'torpe', 'inactiva'], 'hitos-full');
} else {
    $rep = SimuladorHitosRelacionales::ejecutar($root, [8, 16, 32, 48], [30, 100, 365, 700], 2, ['activa', 'normal', 'torpe', 'inactiva'], 'hitos-std');
}

$jsonPath = $root . '/docs/HITOS_RELACIONALES_REPORT.json';
$mdPath = $root . '/docs/HITOS_RELACIONALES_REPORT.md';
file_put_contents($jsonPath, json_encode($rep, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

$md = "# HITOS RELACIONALES — INFORME DE SIMULACIÓN\n\n";
$md .= 'Generado: ' . ($rep['_generado'] ?? '') . "\n\n";
$md .= "_Provisional. No canoniza parámetros._\n\n";
$md .= "## Escenarios dirigidos\n\n";
foreach ($rep['escenarios_dirigidos'] as $esc => $row) {
    $ok = !empty($row['ok']) ? 'OK' : 'FAIL';
    $md .= '- **' . $esc . '**: ' . $ok . ' — estado=' . ($row['estado'] ?? '?')
        . ' romance ' . ($row['romance_ab'] ?? '?') . '/' . ($row['romance_ba'] ?? '?') . "\n";
}
$md .= "\n## Matriz (medias por celda)\n\n";
$md .= "| celda | amistades | hitos_rom | conf | besos | parejas | d1_pareja | dur | crisis | rup | rec | crush3 | tri | inf | estables% | unil | sin_rel |\n";
$md .= "|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|\n";
foreach ($rep['matriz'] as $celda => $m) {
    if (!is_array($m)) {
        continue;
    }
    $md .= sprintf(
        "| %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |\n",
        $celda,
        $m['amistades'] ?? 0,
        $m['hitos_romanticos'] ?? 0,
        $m['confesiones'] ?? 0,
        $m['besos'] ?? 0,
        $m['parejas'] ?? 0,
        $m['dias_media_primera_pareja'] ?? 0,
        $m['duracion_media_parejas'] ?? 0,
        $m['crisis'] ?? 0,
        $m['rupturas'] ?? 0,
        $m['reconciliaciones'] ?? 0,
        $m['crushes_tercero'] ?? 0,
        $m['triangulos'] ?? 0,
        $m['infidelidades'] ?? 0,
        $m['parejas_estables_pct'] ?? 0,
        $m['unilaterales'] ?? 0,
        $m['sin_relacion'] ?? 0
    );
}
$md .= "\n## Historias humanas (muestra)\n\n";
foreach ($rep['historias'] as $h) {
    $md .= '### ' . ($h['titulo'] ?? '') . "\n";
    foreach ($h['lineas'] ?? [] as $ln) {
        $md .= '- ' . $ln . "\n";
    }
    $md .= "\n";
}
$md .= "## Anomalías\n\n";
if (empty($rep['anomalias'])) {
    $md .= "_Ninguna detectada por umbrales de informe._\n\n";
} else {
    foreach ($rep['anomalias'] as $a) {
        $md .= '- ' . $a . "\n";
    }
    $md .= "\n";
}
$md .= "## Recomendación\n\n";
foreach ($rep['recomendacion'] as $r) {
    $md .= '- ' . $r . "\n";
}
file_put_contents($mdPath, $md);
echo "OK wrote {$jsonPath}\n{$mdPath}\n";
echo 'celdas=' . count($rep['matriz']) . ' anomalias=' . count($rep['anomalias']) . ' end ' . date('H:i:s') . "\n";
