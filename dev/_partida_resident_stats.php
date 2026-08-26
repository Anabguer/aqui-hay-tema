<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\PartidaRepository;
$id = $argv[1] ?? 'e2erit-part_5af4821';
$p = (new PartidaRepository(dirname(__DIR__)))->cargar($id);
foreach ($p['residentes'] ?? [] as $rid => $r) {
    $a = $r['animo'] ?? $r['emocion'] ?? '';
    $d = count($r['diario'] ?? $r['diario_entradas'] ?? []);
    if ($d > 0) {
        echo "{$rid} diario={$d}\n";
    }
    if ($a !== '' && $a !== 'neutral') {
        echo "{$rid} animo={$a}\n";
    }
}
$inv = count($p['celeste']['inventario'] ?? $p['inventario'] ?? []);
echo "inv={$inv}\n";
