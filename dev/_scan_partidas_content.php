<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\JsonFile;

$dir = dirname(__DIR__) . '/data/partidas';
$files = glob($dir . '/*.json') ?: [];
usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));

$want = ['msg', 'cot', 'inv', 'diario', 'animo', 'enc_curso'];
$found = [];

foreach (array_slice($files, 0, 800) as $f) {
    try {
        $p = JsonFile::read($f);
    } catch (Throwable $e) {
        continue;
    }
    if (count($p['residentes'] ?? []) < 4) {
        continue;
    }
    $id = $p['meta']['partida_id'] ?? basename($f, '.json');
    $msgs = count($p['buzon']['mensajes'] ?? []);
    $cot = count($p['diario_pueblo']['entradas'] ?? []);
    $inv = count($p['celeste']['inventario'] ?? []);
    $diarioRes = 0;
    $animo = 0;
    foreach ($p['residentes'] ?? [] as $r) {
        $diarioRes += count($r['diario'] ?? $r['diario_entradas'] ?? []);
        $a = $r['animo'] ?? $r['emocion'] ?? '';
        if ($a && $a !== 'neutral') {
            $animo++;
        }
    }
    $encCurso = 0;
    foreach ($p['encuentros'] ?? [] as $e) {
        if (($e['estado'] ?? '') === 'en_curso' || !empty($e['encuentro_en_curso'])) {
            $encCurso++;
        }
    }
    if ($msgs >= 2) {
        $found['msg'][] = "{$id} ({$msgs})";
    }
    if ($cot >= 3) {
        $found['cot'][] = "{$id} ({$cot})";
    }
    if ($inv >= 1) {
        $found['inv'][] = "{$id} ({$inv})";
    }
    if ($diarioRes >= 3) {
        $found['diario'][] = "{$id} ({$diarioRes})";
    }
    if ($animo >= 1) {
        $found['animo'][] = "{$id} ({$animo})";
    }
    if ($encCurso >= 1) {
        $found['enc_curso'][] = "{$id} ({$encCurso})";
    }
}

foreach ($want as $k) {
    echo strtoupper($k) . ': ' . (isset($found[$k]) ? implode(', ', array_slice($found[$k], 0, 3)) : 'NONE') . "\n";
}
