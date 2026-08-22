<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DisponibilidadEngine;
use AquiHayTema\Engine\PartidaService;

$root = dirname(__DIR__);
$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'slots_ui_' . time());
$tercero = (string) ($p['tutorial']['tercero'] ?? '');
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

$slots = DisponibilidadEngine::slotsCompatibles($p, [$tercero], 'individual', 1, null, 1, 48, null, 'lug_cine');
ok($slots['ok'] ?? false, 'slots individual cine ok');
$horas = array_map(static fn($s) => (int) ($s['hora'] ?? -1), $slots['slots'] ?? []);
ok($horas !== [], 'hay horas para cine');
foreach ($horas as $h) {
    ok(DisponibilidadEngine::franjaValida($p, [$tercero], 1, $h, 'lug_cine'), "franja $h coherente motor");
}
ok(!in_array(10, $horas, true), '10h no válida (cine cerrado)');

$pareja = $p['tutorial']['pareja_mision1'] ?? [];
$a = (string) ($pareja['a'] ?? '');
$b = (string) ($pareja['b'] ?? '');
if ($a !== '' && $b !== '') {
    $slots2 = DisponibilidadEngine::slotsCompatibles($p, [$a, $b], 'conocerse', 1, null, 1, 48, null, 'lug_cafeteria');
    ok($slots2['ok'] ?? false, 'slots pareja cafeteria');
    foreach ($slots2['slots'] ?? [] as $s) {
        $h = (int) ($s['hora'] ?? -1);
        ok(DisponibilidadEngine::franjaValida($p, [$a, $b], 1, $h, 'lug_cafeteria'), "franja $h válida cafeteria");
    }
}

exit($failures > 0 ? 1 : 0);
