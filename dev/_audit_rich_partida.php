<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\Engine\JsonFile;
use AquiHayTema\Engine\PartidaRepository;

$id = $argv[1] ?? 'e2erit-part_5af4821';
$root = dirname(__DIR__);
$repo = new PartidaRepository($root);
$p = $repo->cargar($id);
if ($p === null) {
    $p = JsonFile::read($root . '/data/partidas/' . $id . '.json');
}
echo "id={$id}\n";
echo 'residentes=' . count($p['residentes'] ?? []) . "\n";
$msgs = $p['buzon']['mensajes'] ?? $p['mensajitos'] ?? [];
echo 'mensajitos=' . (is_array($msgs) ? count($msgs) : 0) . "\n";
$diario = $p['diario_pueblo'] ?? [];
$ent = $diario['entradas'] ?? $p['cotilleos'] ?? [];
echo 'cotilleos=' . (is_array($ent) ? count($ent) : 0) . "\n";
echo 'encuentros=' . count($p['encuentros'] ?? []) . "\n";
$encCurso = array_filter($p['encuentros'] ?? [], static fn($e) => ($e['estado'] ?? '') === 'en_curso' || !empty($e['en_curso']));
echo 'encuentros_en_curso=' . count($encCurso) . "\n";
$inv = $p['celeste']['inventario'] ?? $p['inventario'] ?? [];
echo 'inventario=' . (is_array($inv) ? count($inv) : 0) . "\n";
$rels = $p['relaciones_romanticas'] ?? [];
echo 'parejas=' . count($rels) . "\n";
$misiones = $p['misiones_diarias'] ?? $p['misiones'] ?? [];
echo 'misiones=' . (is_array($misiones) ? count($misiones) : 0) . "\n";
foreach ($p['residentes'] ?? [] as $rid => $r) {
    $animo = $r['animo'] ?? $r['emocion'] ?? '';
    if ($animo !== '' && $animo !== 'neutral') {
        echo "animo_activo={$rid}:{$animo}\n";
    }
    $di = count($r['diario'] ?? $r['diario_entradas'] ?? []);
    if ($di > 0) {
        echo "diario_{$rid}={$di}\n";
    }
}
// futuros encuentros
$fut = array_filter($p['encuentros'] ?? [], static fn($e) => ($e['estado'] ?? '') === 'programado' || ($e['estado'] ?? '') === 'futuro');
echo 'enc_futuros=' . count($fut) . "\n";
