<?php
declare(strict_types=1);
/** Busca partida reciente con contenido rico para validación visual (solo dev). */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\JsonFile;

$dir = dirname(__DIR__) . '/data/partidas';
$files = glob($dir . '/*.json') ?: [];
usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));

$best = null;
$bestScore = -1;

foreach (array_slice($files, 0, 80) as $f) {
    try {
        $p = JsonFile::read($f);
    } catch (Throwable $e) {
        continue;
    }
    $res = count($p['residentes'] ?? []);
    if ($res < 4) {
        continue;
    }
    $score = $res * 10
        + count($p['buzon']['mensajes'] ?? $p['mensajitos'] ?? [])
        + count($p['diario_pueblo'] ?? $p['cotilleos'] ?? [])
        + count($p['encuentros'] ?? [])
        + count($p['misiones'] ?? $p['misiones_dia'] ?? [])
        + count($p['celeste']['inventario'] ?? $p['inventario'] ?? []);
    if ($score > $bestScore) {
        $bestScore = $score;
        $best = $p;
        $bestFile = $f;
    }
}

if ($best === null) {
    echo "NOT_FOUND\n";
    exit(1);
}

$id = $best['meta']['partida_id'] ?? basename($bestFile, '.json');
echo "partida_id={$id}\n";
echo 'residentes=' . count($best['residentes'] ?? []) . "\n";
echo 'encuentros=' . count($best['encuentros'] ?? []) . "\n";
echo 'misiones=' . count($best['misiones'] ?? $best['misiones_dia'] ?? []) . "\n";
