<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\MarchaEngine;
use AquiHayTema\Engine\MarchaPresentacionEngine;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$fail = 0;

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'marcha-pres-' . time());
$rid = array_key_first($p['residentes'] ?? []);
ok(is_string($rid), 'residente');

$causa = MarchaEngine::CAUSA_AISLAMIENTO;
$txt = MarchaPresentacionEngine::textoIntencion($p, $rid, $causa, 1);
ok(strpos($txt, 'Celestine') !== false || strpos($txt, 'march') !== false || $txt !== '', 'copy intencion con causa');

$int = MarchaEngine::forzarIntencionDev($p, $rid, $causa);
$msgId = (string) ($int['mensaje_id'] ?? '');
$msg = BuzonEngine::buscar($p, $msgId);
ok(($msg['tipo'] ?? '') === MarchaEngine::TIPO_MSG, 'mensajito intencion');

$r = MarchaEngine::dejarIr($p, $root, $msgId);
ok($r['ok'] ?? false, 'dejar ir ok');

$cot = 0;
$desp = 0;
$legado = 0;
foreach ($p['buzon'] ?? [] as $m) {
    if (!is_array($m)) {
        continue;
    }
    if (($m['tipo'] ?? '') === 'marcha_publica') {
        $cot++;
        ok(strpos((string) ($m['texto'] ?? ''), 'aislamiento') === false, 'cotilleo sin inventar causa falsa');
    }
    if (($m['tipo'] ?? '') === 'marcha_despedida') {
        $desp++;
    }
    if (($m['tipo'] ?? '') === 'legado_despedida') {
        $legado++;
    }
}
ok($cot >= 1, 'cotilleo marcha_publica');
ok($desp >= 1, 'mensajito despedida');

exit($fail > 0 ? 1 : 0);
