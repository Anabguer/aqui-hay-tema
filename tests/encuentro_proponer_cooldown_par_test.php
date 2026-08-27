<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CopyRechazoPropuesta;
use AquiHayTema\Engine\GameError;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaCooldown;
use AquiHayTema\Engine\PropuestaEncuentroEngine;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

$svc = new PartidaService(dirname(__DIR__));
$p = $svc->nuevaPartida('juego_v1', 'cooldown_par_' . time());
$p['reloj'] = ['dia_pueblo' => 2, 'hora_actual' => 9];
$ids = array_values(array_keys(array_filter(
    $p['residentes'],
    static fn ($r) => ($r['presencia'] ?? '') === 'residente'
)));
$a = (string) $ids[0];
$b = (string) $ids[1];

PropuestaCooldown::marcar($p, $a, $b, 'conocerse', []);
$r = PropuestaEncuentroEngine::proponer($p, [$a, $b], 2, 10, 'conocerse', 'lug_cafeteria');

ok(($r['error'] ?? '') === GameError::ENCUENTRO_RECHAZADO_COOLDOWN, 'cooldown par devuelve error COOLDOWN, no 500');
ok(($r['mensaje_ui'] ?? '') !== '', 'cooldown par incluye mensaje_ui');
ok(is_string($r['mensaje_ui'] ?? null), 'mensaje_ui es string');
ok(!isset($r['propuesta']), 'cooldown par no crea propuesta');
ok(
    ($r['mensaje_ui'] ?? '') === CopyRechazoPropuesta::mensajeCooldownPar($p, [$a, $b], 'conocerse'),
    'mensaje_ui coherente con CopyRechazoPropuesta::mensajeCooldownPar'
);

echo "\nencuentro_proponer_cooldown_par_test OK\n";
