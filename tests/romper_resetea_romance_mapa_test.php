<?php
declare(strict_types=1);

/**
 * BLOQUE 5 — Mapa funcional contradictorio: tras una ruptura el vínculo queda
 * EX pero el romance direccional seguía vivo, así el mapa mostraba "ex" y
 * "romance vivo" a la vez. Al romper, el romance debe resetearse a 0 en
 * ambas direcciones.
 *
 * PHP 7.4. Pipeline real ParejaEngine / RelacionEngine.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;

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
$p = $service->nuevaPartida('juego_v1', 'b5-romance', ['fecha' => '2026-08-17', 'hora' => 8]);
$a = (string) ($p['tutorial']['pareja_mision1']['a'] ?? '');
$b = (string) ($p['tutorial']['pareja_mision1']['b'] ?? '');
$cal = CalibracionConfig::load($root);

$rForm = ParejaEngine::formar($p, $a, $b, true, true, RelacionBitacora::DECLARACION, $cal);
ok(($rForm['ok'] ?? false) && ParejaEngine::estado($p, $a, $b) === ParejaEngine::PAREJA, 'B5: pareja formada');

RelacionEngine::setRomanceHacia($p, $a, $b, 30);
RelacionEngine::setRomanceHacia($p, $b, $a, 25);
ok((RelacionEngine::romanceHacia($p, $a, $b) ?? -1) === 30, 'B5: romance a->b vivo antes de romper');
ok((RelacionEngine::romanceHacia($p, $b, $a) ?? -1) === 25, 'B5: romance b->a vivo antes de romper');

$r = ParejaEngine::romper($p, $a, $b, 'test');
ok(($r['ok'] ?? false) && ParejaEngine::estado($p, $a, $b) === ParejaEngine::EX, 'B5: ruptura => estado EX');

ok((RelacionEngine::romanceHacia($p, $a, $b) ?? -1) === 0, 'B5: romance a->b reset a 0 tras romper (sin contradicción en mapa)');
ok((RelacionEngine::romanceHacia($p, $b, $a) ?? -1) === 0, 'B5: romance b->a reset a 0 tras romper (sin contradicción en mapa)');

echo ($failures === 0 ? "\ntests OK\n" : "\nFAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
