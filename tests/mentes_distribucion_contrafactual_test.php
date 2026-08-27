<?php
declare(strict_types=1);

/**
 * MENTES iter2: distribución contrafactual afin/neutro/aversión vs sin intervención.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AzarPonderado;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\MentesTemas;
use AquiHayTema\Engine\RngService;

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

$cal = CalibracionConfig::load($root);
$resultados = ['muy_mal', 'mal', 'normal', 'bien', 'muy_bien'];
$cargaBaseMala = -0.55;
$seed = 'mentes-dist-' . __FILE__;

$rngBase = new RngService($seed);
$sin = MentesTemas::simularDistribucion($rngBase, $resultados, $cargaBaseMala, 0.0, $cal, 400);
$neutro = MentesTemas::simularDistribucion(new RngService($seed), $resultados, $cargaBaseMala, (float) CalibracionConfig::get($cal, 'mentes.carga_neutro', 0.05), $cal, 400);
$afin = MentesTemas::simularDistribucion(new RngService($seed), $resultados, $cargaBaseMala, (float) CalibracionConfig::get($cal, 'mentes.carga_afin', 0.36), $cal, 400);
$aversion = MentesTemas::simularDistribucion(new RngService($seed), $resultados, $cargaBaseMala, (float) CalibracionConfig::get($cal, 'mentes.carga_aversion', -0.28), $cal, 400);

$pesoMal = static function (array $h): int {
    return ($h['muy_mal'] ?? 0) + ($h['mal'] ?? 0);
};
$pesoBien = static function (array $h): int {
    return ($h['muy_bien'] ?? 0) + ($h['bien'] ?? 0);
};

ok($pesoMal($aversion) >= $pesoMal($sin), 'aversión ≥ sin intervención en resultados malos');
ok($pesoMal($afin) < $pesoMal($sin), 'afín reduce resultados malos vs sin intervención');
ok($pesoMal($neutro) <= $pesoMal($sin), 'neutro no empeora vs sin intervención');
ok($pesoBien($afin) > $pesoBien($neutro), 'afín > neutro en resultados buenos');
ok($pesoBien($neutro) >= $pesoBien($aversion), 'neutro ≥ aversión en resultados buenos');

$intervAfin = [
    'accion' => 'hobby',
    'afinidad_tema' => 'afin',
    'beneficiario' => 'per_b',
    'rompe_hielo' => 'per_a',
    'objetivo' => 'per_a',
];
$cargas = MentesTemas::cargasExperienciaPorParticipante($intervAfin, ['per_a', 'per_b'], $cal);
ok(($cargas['per_b'] ?? 0) > ($cargas['per_a'] ?? 0), 'carga asimétrica: beneficiario > rompe hielo');
ok(($cargas['per_b'] ?? 0) >= 0.30, 'beneficiario afin tiene peso apreciable');

echo "\nDistribución (carga base mala {$cargaBaseMala}, 400 tiradas, misma semilla):\n";
echo '  sin:      ' . json_encode($sin) . "\n";
echo '  neutro:   ' . json_encode($neutro) . "\n";
echo '  afin:     ' . json_encode($afin) . "\n";
echo '  aversión: ' . json_encode($aversion) . "\n";

echo $fail === 0 ? "\nmentes_distribucion_contrafactual_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
