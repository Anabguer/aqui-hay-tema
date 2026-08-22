<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MarchaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionNarrativaBridge;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

DomainBootstrap::boot();
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'marcha-' . time());
$rid = array_key_first($p['residentes'] ?? []);
ok(is_string($rid), 'residente');
if (!is_string($rid)) {
    exit(1);
}

$int = MarchaEngine::forzarIntencionDev($p, $rid);
ok(($int['estado'] ?? '') === 'pendiente', 'intencion marcha creada');
$msgId = (string) ($int['mensaje_id'] ?? '');
$msg = BuzonEngine::buscar($p, $msgId);
ok(BuzonEngine::tieneDecisionPendiente($msg ?? []), 'mensajito con decision');
ok(count($msg['acciones_ui'] ?? []) === 2, 'dos acciones marcha');

$r = MarchaEngine::pedirQuedarse($p, $root, $msgId);
ok($r['ok'] ?? false, 'pedir quedarse ok');
ok(($p['residentes'][$rid]['presencia'] ?? '') !== 'antiguo_residente', 'sigue activo');
BuzonEngine::resolverDecision($p, $msgId);
$msg2 = BuzonEngine::buscar($p, $msgId);
ok(!BuzonEngine::tieneDecisionPendiente($msg2 ?? []), 'decision cerrada');

$int2 = MarchaEngine::forzarIntencionDev($p, $rid, MarchaEngine::CAUSA_AISLAMIENTO);
$bloqueada = !empty($p['marchas']['causas_vivas'][$rid][MarchaEngine::CAUSA_AISLAMIENTO]['bloquea_reintento']);
ok($bloqueada, 'misma causa bloqueada tras pedir quedarse');

$a = array_key_first($p['residentes'] ?? []);
$b = null;
foreach (array_keys($p['residentes'] ?? []) as $x) {
    if ($x !== $a) {
        $b = $x;
        break;
    }
}
if (is_string($a) && is_string($b)) {
    RelacionBitacora::registrar($p, RelacionBitacora::SE_CONOCIERON, [$a, $b]);
    $cot = null;
    foreach ($p['buzon'] ?? [] as $m) {
        if (is_array($m) && ($m['tipo'] ?? '') === 'cotilleo_hito') {
            $cot = $m;
            break;
        }
    }
    ok($cot !== null, 'cotilleo hito se_conocieron');
    RelacionBitacora::registrar($p, RelacionBitacora::SE_CONOCIERON, [$a, $b]);
    $n = 0;
    foreach ($p['buzon'] ?? [] as $m) {
        if (is_array($m) && ($m['tipo'] ?? '') === 'cotilleo_hito') {
            $n++;
        }
    }
    ok($n === 1, 'hito no duplicado');
}

exit($failures > 0 ? 1 : 0);
