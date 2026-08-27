<?php
declare(strict_types=1);

/*
 * Test: CanalDeduplicador — previene duplicados entre canales.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CanalDeduplicador;
use AquiHayTema\Engine\DomainBootstrap;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) { $failures++; }
}

DomainBootstrap::boot();

$p = ['buzon' => [], 'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10]];

// --- 1) Sin evento_id: siempre permite ---
$r = CanalDeduplicador::crearSiAplica($p, [
    'texto' => 'msg sin evento',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    'canal' => BuzonEngine::CANAL_BUZON,
    'origen' => ['evento_id' => null, 'tipo_evento' => 'test'],
    '_placeholder_contenido' => false,
]);
ok(($r['ok'] ?? false), 'sin evento_id: crea mensaje');

// --- 2) Mismo evento en mismo canal: bloquea ---
CanalDeduplicador::registrar($p, 'evt_001', BuzonEngine::CANAL_BUZON);
ok(CanalDeduplicador::yaPublicado($p, 'evt_001', BuzonEngine::CANAL_BUZON), 'registrado como publicado');
$r2 = CanalDeduplicador::crearSiAplica($p, [
    'texto' => 'msg duplicado',
    'clasificacion' => BuzonEngine::OPORTUNIDAD,
    'canal' => BuzonEngine::CANAL_BUZON,
    'origen' => ['evento_id' => 'evt_001', 'tipo_evento' => 'test'],
    '_placeholder_contenido' => false,
]);
ok($r2 === null, 'duplicado en mismo canal: bloqueado');

// --- 3) Mismo evento en canal distinto: permite si es elegible ---
$r3 = CanalDeduplicador::crearSiAplica($p, [
    'texto' => 'msg otro canal',
    'clasificacion' => BuzonEngine::COTILLEO,
    'canal' => BuzonEngine::CANAL_COTILLEO,
    'origen' => ['evento_id' => 'evt_001', 'tipo_evento' => 'discusion'],
    '_placeholder_contenido' => false,
]);
// discusion puede ir a cotilleo
ok(!empty($r3['ok']), 'mismo evento en cotilleo: permitido');

// --- 4) Elegibilidad ---
$perm = CanalDeduplicador::permisos();
ok(CanalDeduplicador::elegibleParaCanal('discusion', BuzonEngine::CANAL_COTILLEO, $perm), 'discusion elegible en cotilleo');
ok(!CanalDeduplicador::elegibleParaCanal('discusion', BuzonEngine::CANAL_BUZON, $perm), 'discusion NO elegible en buzon');

// --- 5) Evento sin permiso explícito: permite por defecto ---
ok(CanalDeduplicador::elegibleParaCanal('evento_desconocido', BuzonEngine::CANAL_BUZON, $perm), 'evento sin regla: permite');

echo "\n";
echo $failures === 0 ? "OK mensajitos_dedup\n" : "FAIL mensajitos_dedup ({$failures})\n";
exit($failures > 0 ? 1 : 0);