<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroExperiencia;
use AquiHayTema\Engine\EncuentroIntervencion;
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
$catalog = new Catalog($root);
$hugo = 'per_hugo';
$tamara = 'per_tamara';
$partida = [
    'meta' => ['seed' => 'forense-hugo-tamara'],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 12],
    'residentes' => [
        $hugo => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'neutro']]],
        $tamara => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'neutro']]],
    ],
    'relaciones' => [
        'social' => [
            $hugo => [$tamara => ['valor' => -40, 'tipo' => 'conocido']],
            $tamara => [$hugo => ['valor' => -35, 'tipo' => 'conocido']],
        ],
    ],
];
$enc = [
    'id' => 'enc_forensic',
    'participantes' => [$hugo, $tamara],
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
    'dia' => 1,
    'hora' => 12,
    'intencion' => 'celeste_organizado',
    'intervencion_celeste' => [
        'usada' => true,
        'accion' => EncuentroIntervencion::HOBBY,
        'afinidad_tema' => 'afin',
        'beneficiario' => $tamara,
        'rompe_hielo' => $hugo,
        'objetivo' => $hugo,
        'tema_id' => 'baile',
        'carga' => 0.12,
        'tema_cargas' => MentesTemas::cargasExperienciaPorParticipante([
            'accion' => 'hobby',
            'afinidad_tema' => 'afin',
            'beneficiario' => $tamara,
            'rompe_hielo' => $hugo,
        ], [$hugo, $tamara], $cal),
    ],
];

ok(($enc['intervencion_celeste']['tema_cargas'][$tamara] ?? 0) > ($enc['intervencion_celeste']['tema_cargas'][$hugo] ?? 0),
    'forense: carga tema mayor en Tamara (beneficiaria)');

$rng = RngService::fromPartida($partida);
$exp = EncuentroExperiencia::resolver($partida, $enc, $catalog, $rng, $cal);
$resH = (string) ($exp['por_participante'][$hugo]['resultado'] ?? '');
$resT = (string) ($exp['por_participante'][$tamara]['resultado'] ?? '');
$cargaH = (float) ($exp['por_participante'][$hugo]['carga'] ?? 0);
$cargaT = (float) ($exp['por_participante'][$tamara]['carga'] ?? 0);

ok($cargaT > $cargaH - 0.15, 'forense: carga final Tamara no queda por debajo de Hugo sin motivo');
$txtT = (string) ($exp['por_participante'][$tamara]['texto'] ?? '');
ok($txtT !== '', 'forense: Tamara tiene texto MENTES de cierre');
ok(stripos($txtT, 'baile') !== false || stripos($txtT, 'interes') !== false || stripos($txtT, 'encuentro') !== false,
    'forense: copy Tamara explica tema/encuentro');

echo "\nForense Hugo+Tamara (1 tirada, relación tensa + baile afín):\n";
echo "  Hugo:   $resH (carga " . round($cargaH, 3) . ")\n";
echo "  Tamara: $resT (carga " . round($cargaT, 3) . ")\n";
echo "  Copy Tamara: $txtT\n";

echo $fail === 0 ? "\nmentes_coherencia_experiencia_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
