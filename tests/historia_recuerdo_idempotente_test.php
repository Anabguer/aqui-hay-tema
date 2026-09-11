<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\HistoriaPuebloEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;

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
$service = new PartidaService($root);

// --- 1) registrar() es idempotente: segunda llamada retorna ya_existia ---
$p1 = $service->nuevaPartida('juego_v1', 'hist-idem-1');
$ids1 = array_keys($p1['residentes']);
$a1 = (string) $ids1[0];
$b1 = (string) $ids1[1];

$proto = [$a1, $b1];
$contexto = ['tipo' => 'descubrimiento_cotilleo', 'descubrimiento' => 'prueba'];

$n1 = count($p1['historia_pueblo'] ?? []);
$r1 = HistoriaPuebloEngine::registrar($p1, 'test_hito_idem', $proto, $contexto);
$n2 = count($p1['historia_pueblo'] ?? []);
ok($r1['ya_existia'] === false, '1. primera llamada: ya_existia=false');
ok($n2 === $n1 + 1, '2. primera llamada agrega 1 entrada');

$r2 = HistoriaPuebloEngine::registrar($p1, 'test_hito_idem', $proto, $contexto);
$n3 = count($p1['historia_pueblo'] ?? []);
ok($r2['ya_existia'] === true, '3. segunda llamada: ya_existia=true');
ok($n3 === $n2, '4. segunda llamada NO agrega entrada');

// --- 2) existe() y obtener() confirman la entrada ---
$clave = $r1['entrada']['clave'];
ok(HistoriaPuebloEngine::existe($p1, $clave), '5. existe() retorna true para la clave');
$obt = HistoriaPuebloEngine::obtener($p1, $clave);
ok($obt !== null && ($obt['hito_id'] ?? '') === 'test_hito_idem', '6. obtener() retorna la entrada correcta');

// --- 3) IDs diferentes generan entradas diferentes ---
$r3 = HistoriaPuebloEngine::registrar($p1, 'test_hito_distinto', $proto, $contexto);
ok($r3['ya_existia'] === false, '7. hito diferente → nueva entrada');

echo $failures === 0 ? "OK historia_recuerdo_idempotente\n" : "FAIL historia_recuerdo_idempotente ({$failures})\n";
exit($failures > 0 ? 1 : 0);
