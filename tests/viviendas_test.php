<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BloqueA;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$partida = $service->nuevaPartida('debug_v0', 'viv-test');
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

ok(BloqueA::resumen($partida)['ocupadas'] === 1, 'Rocío ocupa A01');

$ph = $service->crearResidentePlaceholderDev($partida);
ok($ph['vivienda_id'] === 'A02', 'placeholder en A02');

$r = $service->incorporarResidenteCatalogo($partida, 'per_i03');
ok(!($r['ok'] ?? true), 'residente duplicado rechazado');

for ($i = 0; $i < 20; $i++) {
    $service->crearResidentePlaceholderDev($partida);
}
ok(BloqueA::resumen($partida)['libres'] === 0, 'bloque lleno tras llenar');

$lib = $service->liberarVivienda($partida, 'A02');
ok($lib['ok'] ?? false, 'liberar vivienda');

exit($failures > 0 ? 1 : 0);
