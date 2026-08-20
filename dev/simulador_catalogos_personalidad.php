<?php
declare(strict_types=1);

/**
 * Lab catálogos candidatos V2. No escribe P001–P200.
 * Uso: php dev/simulador_catalogos_personalidad.php
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CatalogosCandidatos;
use AquiHayTema\Engine\SimuladorCatalogosPersonalidad;

$root = dirname(__DIR__);
CatalogosCandidatos::resetCache();
$lab = SimuladorCatalogosPersonalidad::ejecutar($root, 30, 'lab-catalogos-30');
$pack = CatalogosCandidatos::cargar($root);
$md = SimuladorCatalogosPersonalidad::markdownMuestras($lab, $pack);
file_put_contents($root . '/dev/MUESTRA_30_CATALOGOS.md', $md);

$a = $lab['auditoria'];
$auditMd = "# Auditoría catálogos V2 (no canon)\n\n";
$auditMd .= "Seed `lab-catalogos-30`. 30 fichas. Generador de producción para 200: ver tests.\n\n";
$auditMd .= "## Conteos\n\n";
foreach ($a['conteos'] as $k => $v) {
    $auditMd .= '- ' . $k . ': ' . $v . "\n";
}
$auditMd .= "\n- Exactamente 3+3 en la muestra: **" . ($a['pct_exactamente_3_3'] ?? '?') . "%**\n";
$auditMd .= '- Similitud máxima: **' . (($a['pares_mas_parecidos'][0]['similitud'] ?? '?')) . "**\n";
$auditMd .= '- Clones ≥0.55: ' . count($a['avisos_clon'] ?? []) . "\n";
$auditMd .= '- Copy bajo mínimo: ' . count($a['copy_por_debajo_del_minimo'] ?? []) . "\n\n";
$auditMd .= "## Contradicciones en la muestra de 30\n\n";
$d = $a['distribucion_contradicciones'] ?? [];
$auditMd .= '- 0 tensiones: ' . ($d['0'] ?? 0) . "\n";
$auditMd .= '- 1 tensión: ' . ($d['1'] ?? 0) . "\n";
$auditMd .= '- 2+: ' . ($d['2plus'] ?? 0) . "\n";
$auditMd .= "\nLas 10 primeras son showcase a propósito.\n\n";
$auditMd .= "## Manías repetidas\n\n";
$mr = $a['manias_repetidas_en_muestra'] ?? [];
if ($mr === []) {
    $auditMd .= "Ninguna manía repetida (pool suficiente).\n\n";
} else {
    foreach ($mr as $row) {
        $auditMd .= '- ' . $row['id'] . ': ' . $row['n'] . "\n";
    }
    $auditMd .= "\n";
}
$auditMd .= "## Frases de libreta repetidas (top)\n\n";
foreach ($a['frases_libreta_repetidas'] ?? [] as $row) {
    $auditMd .= '- (' . $row['n'] . ') ' . $row['frase'] . "\n";
}
$auditMd .= "\n## Familias en la muestra\n\n";
foreach ($a['cobertura_familias_en_muestra'] ?? [] as $f => $n) {
    $auditMd .= '- ' . $f . ': ' . $n . "\n";
}
$auditMd .= "\n## Ejes sociales\n\n";
foreach ($a['distribucion_ejes_social'] ?? [] as $k => $n) {
    $auditMd .= '- ' . $k . ': ' . $n . "\n";
}
$auditMd .= "\n## Ejes de afecto\n\n";
foreach ($a['distribucion_ejes_afecto'] ?? [] as $k => $n) {
    $auditMd .= '- ' . $k . ': ' . $n . "\n";
}
$auditMd .= "\nPares más parecidos: " . json_encode($a['pares_mas_parecidos'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
file_put_contents($root . '/dev/AUDITORIA_CATALOGOS_V2.md', $auditMd);

$resumen = [
    '_provisional' => true,
    'conteos' => $lab['conteos'],
    'pct_3_3' => $a['pct_exactamente_3_3'] ?? null,
    'contradicciones' => $d,
    'manias_repetidas' => $mr,
    'clones' => $a['avisos_clon'],
    'pares_parecidos' => $a['pares_mas_parecidos'],
    'copy_corta' => $a['copy_por_debajo_del_minimo'] ?? [],
    'muestra_md' => 'dev/MUESTRA_30_CATALOGOS.md',
    'auditoria_md' => 'dev/AUDITORIA_CATALOGOS_V2.md',
];
echo json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
