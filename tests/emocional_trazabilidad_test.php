<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AcontecimientoDiario;
use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\LabAudit;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\VistaCotilleoV3;

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

$cal = CalibracionConfig::load($root);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('playtest_01', 'emocional-trazabilidad-1');
$store = (new Catalog($root))->store();

ok(FeatureConfig::isEnabled($partida, 'buzon_enabled'), 'buzon habilitado en playtest');

$rid = null;
foreach ($partida['residentes'] as $id => $res) {
    $oc = (string) ($res['runtime']['ocupacion'] ?? '');
    if ($oc !== '' && $oc !== 'desempleado' && $oc !== 'jubilado' && $oc !== 'ninguna') {
        $rid = (string) $id;
        break;
    }
}
ok($rid !== null, 'NPC empleado para perder_trabajo');

LabAudit::reset();
$r = AcontecimientoDiario::ejecutar($partida, 'perder_trabajo', [$rid], $store, $cal);
ok($r['ok'] ?? false, 'perder_trabajo ejecuta');
ok(($partida['residentes'][$rid]['runtime']['ocupacion'] ?? '') === 'desempleado', 'ocupacion desempleado');
ok(
    ($partida['residentes'][$rid]['runtime']['estado_emocional']['id'] ?? '') === EstadoEmocional::TRISTE,
    'estado triste'
);
ok(
    ($partida['residentes'][$rid]['runtime']['estado_emocional']['origen'] ?? '') === 'perder_trabajo',
    'origen perder_trabajo'
);

$exp = $partida['residentes'][$rid]['runtime']['expresion_visual'] ?? [];
ok(($exp['motivo'] ?? '') !== 'inicial', 'expresion re-resuelta tras emocion');

$buzon = $partida['buzon'] ?? [];
ok(count($buzon) >= 1, 'mensaje en buzon');
$msg = $buzon[count($buzon) - 1];
ok(BuzonEngine::tieneContenido($msg), 'mensaje con texto real');
ok(($msg['clasificacion'] ?? '') === BuzonEngine::IMPORTANTE, 'aviso → importante (Mensajitos)');
ok(stripos((string) ($msg['texto'] ?? ''), 'trabajo') !== false, 'copy menciona trabajo');

$cot = VistaCotilleoV3::de($partida);
$enCotilleo = false;
foreach (['hoy', 'ayer', 'viejos'] as $bucket) {
    foreach ($cot[$bucket] ?? [] as $e) {
        if (stripos((string) ($e['texto'] ?? ''), 'trabajo') !== false) {
            $enCotilleo = true;
        }
    }
}
ok(!$enCotilleo, 'aviso no va al feed Cotilleo (canal Mensajitos)');

$audit = LabAudit::flush();
$emoDbg = array_filter($audit, static fn($e) => ($e['prefijo'] ?? '') === '[AHT DEBUG EMOCION]');
ok(count($emoDbg) >= 1, 'LabAudit registra cambio emocional');

// Avanzar día sin perder_trabajo forzado: no tristeza arbitraria en Sara
$partida2 = $service->nuevaPartida('playtest_01', 'emocional-trazabilidad-2');
$saraId = 'per_p008';
$antes = (string) ($partida2['residentes'][$saraId]['runtime']['estado_emocional']['id'] ?? 'neutro');
$service->avanzarRelojPasoAPaso($partida2, 24);
$des = (string) ($partida2['residentes'][$saraId]['runtime']['estado_emocional']['id'] ?? 'neutro');
$origen = (string) ($partida2['residentes'][$saraId]['runtime']['estado_emocional']['origen'] ?? '');
if ($des === EstadoEmocional::TRISTE) {
    ok($origen !== 'inicial' && $origen !== '', 'si Sara triste, tiene origen registrado');
    $tieneInfo = false;
    foreach ($partida2['buzon'] ?? [] as $m) {
        if (BuzonEngine::tieneContenido($m) && stripos(json_encode($m), 'per_p008') !== false) {
            $tieneInfo = true;
        }
    }
    foreach ($partida2['acontecimientos_log'] ?? [] as $row) {
        if (in_array($saraId, $row['participantes'] ?? [], true) && ($row['id'] ?? '') === 'perder_trabajo') {
            $tieneInfo = true;
        }
    }
    ok($tieneInfo, 'tristeza de Sara con trazabilidad narrativa');
} else {
    ok(true, 'Sara no triste tras 1 día sin evento forzado (seed)');
}

echo $failures === 0 ? "ALL OK\n" : "FAILURES: $failures\n";
exit($failures > 0 ? 1 : 0);
