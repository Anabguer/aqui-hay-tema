<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\JsonFile;

$dir = dirname(__DIR__) . '/data/partidas';
$files = glob($dir . '/*.json') ?: [];
usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));

foreach (array_slice($files, 0, 300) as $f) {
    try {
        $p = JsonFile::read($f);
    } catch (Throwable $e) {
        continue;
    }
    $res = count($p['residentes'] ?? []);
    if ($res < 4) {
        continue;
    }
    $msgs = $p['buzon']['mensajes'] ?? [];
    $cot = count($p['diario_pueblo']['entradas'] ?? []);
    $inv = count($p['celeste']['inventario'] ?? []);
    $enc = count($p['encuentros'] ?? []);
    $mis = count($p['misiones_diarias'] ?? []);
    $parejas = count($p['relaciones_romanticas'] ?? []);
    $score = count($msgs) * 5 + $cot * 3 + $inv * 4 + $enc + $mis * 2 + $parejas * 2 + $res;
    if ($score < 30) {
        continue;
    }
    $id = $p['meta']['partida_id'] ?? basename($f, '.json');
    echo "{$id}\tres={$res}\tmsg=" . count($msgs) . "\tcot={$cot}\tinv={$inv}\tenc={$enc}\tmis={$mis}\tpar={$parejas}\n";
}
