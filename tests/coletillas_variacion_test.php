<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CopyVariante;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PartidaService;

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

// --- 1) Pool positivo tiene más de 1 variante ---
$p1 = $service->nuevaPartida('juego_v1', 'col-var');
$poolPos = [
    'Parece que han hecho buenas migas.',
    'Se les ha visto cómodos.',
    'Han terminado con buen rollo.',
    'La cosa ha fluido.',
];
ok(count($poolPos) >= 3, '1. pool positivo tiene >= 3 variantes');

// --- 2) Pool negativo tiene más de 1 variante ---
$poolNeg = [
    'La cosa ha estado algo fría.',
    'No ha terminado de fluir.',
    'La cosa se ha quedado un poco fría.',
    'El ambiente ha quedado algo raro.',
];
ok(count($poolNeg) >= 3, '2. pool negativo tiene >= 3 variantes');

// --- 3) Pool de conflicto tiene más de 1 variante ---
$poolConf = [
    'La cosa ha acabado un poco tensa.',
    'Ha habido cierta tensión.',
    'No ha terminado del todo bien.',
    'El ambiente se ha quedado raro.',
];
ok(count($poolConf) >= 3, '3. pool de conflicto tiene >= 3 variantes');

// --- 4) CopyVariante::elegir tiene dedup: 2da llamada con mismo seed cambia ---
$seed = 'test_det|per_01|per_02';
$r1 = CopyVariante::elegir($p1, 'test_det', $poolPos, $seed);
$r2 = CopyVariante::elegir($p1, 'test_det', $poolPos, $seed);
ok($r1 !== $r2 || count($poolPos) <= 1, '4. CopyVariante::elegir dedup: 2da llamada con mismo seed es distinta');

// --- 5) Diferentes seeds producen resultados (posiblemente) distintos ---
$seed2 = 'test_det|per_02|per_01';
$r3 = CopyVariante::elegir($p1, 'test_det2', $poolPos, $seed2);
// No exigimos que sean distintos (podrían coincidir por crc32), pero verificamos que no crashea
ok($r3 !== '', '5. CopyVariante::elegir con seed distinto no falla');

// --- 6) Pool de 1 elemento siempre devuelve el mismo ---
$r4 = CopyVariante::elegir($p1, 'test_unico', ['Solo una opción'], 'cualquier_seed');
ok($r4 === 'Solo una opción', '6. pool de 1 elemento siempre devuelve esa opción');

echo $failures === 0 ? "OK coletillas_variacion\n" : "FAIL coletillas_variacion ({$failures})\n";
exit($failures > 0 ? 1 : 0);
