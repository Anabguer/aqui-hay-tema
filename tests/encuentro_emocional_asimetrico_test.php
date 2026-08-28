<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroExperiencia;
use AquiHayTema\Engine\EstadoEmocional;

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

$pA = 'per_alice';
$pB = 'per_bob';

// --- Test 1: emocional_asimetrica_cargaDe ---
// A esta alegre, B esta enfadado.
// La carga emocional de A debe usar emocional_a, la de B debe usar emocional_b.
$partida1 = [
    'meta' => ['seed' => 'test-emocional-asimetrico'],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 12],
    'residentes' => [
        $pA => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'alegre']]],
        $pB => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'enfadado']]],
    ],
];

$snap1 = [
    '_provisional' => true,
    'factores' => [
        'compat_ab' => ['total' => 50],
        'compat_ba' => ['total' => 50],
        'quimica' => ['a_hacia_b' => 50],
        'social_ab' => ['valor' => 10],
        'social_ba' => ['valor' => 10],
        'romance_ab' => 0,
        'conflicto' => 0,
        'emocional_a' => 'alegre',
        'emocional_b' => 'enfadado',
        'plan_a' => ['aporte' => 0, 'penalizacion' => 0],
        'plan_b' => ['aporte' => 0, 'penalizacion' => 0],
    ],
    'participantes' => [$pA, $pB],
    'por_participante' => [
        $pA => ['compatibilidad_hacia_otro' => 50],
        $pB => ['compatibilidad_hacia_otro' => 50],
    ],
];

$cargaA = EncuentroExperiencia::cargaDe($snap1, $pA, $cal);
$cargaB = EncuentroExperiencia::cargaDe($snap1, $pB, $cal);

// Verificar que las contribuciones emocionales difieren
$modAlegre = (float) EstadoEmocional::modificadores('alegre', $cal)['experiencia_encuentro'];
$modEnfadado = (float) EstadoEmocional::modificadores('enfadado', $cal)['experiencia_encuentro'];

ok($modAlegre !== $modEnfadado, 'emocional_asimetrica: modificadores alegre != enfadado (setup valido)');

// La diferencia entre cargas debe ser positiva (A mejor que B) y consistente
$diffCargas = $cargaA - $cargaB;
ok($diffCargas > 0,
    'emocional_asimetrica: diferencia de cargas positiva (A mejor que B)');
// La diferencia no debe ser cero (demuestra que la emoción afecta)
ok(abs($diffCargas) > 0.001,
    'emocional_asimetrica: la emoción genera diferencia real de carga, no trivial');

ok($cargaA > $cargaB,
    'emocional_asimetrica: carga de A (alegre) > carga de B (enfadado)');

// --- Test 2: cargaDe usa emocional correcto por participante ---
// Verificar desgloseCarga coincide con cargaDe para ambos
$desgA = EncuentroExperiencia::desgloseCarga($snap1, $pA, $cal);
$desgB = EncuentroExperiencia::desgloseCarga($snap1, $pB, $cal);

ok(abs($desgA['carga'] - $cargaA) < 0.001,
    'emocional_asimetrica: desgloseCarga(A) == cargaDe(A)');
ok(abs($desgB['carga'] - $cargaB) < 0.001,
    'emocional_asimetrica: desgloseCarga(B) == cargaDe(B)');

// Las contribuciones emocionales deben ser diferentes
$contribEmoA = $desgA['contribuciones']['emocional'] ?? 0;
$contribEmoB = $desgB['contribuciones']['emocional'] ?? 0;

ok($contribEmoA !== $contribEmoB,
    'emocional_asimetrica: contribucion emocional difiere entre A y B en desglose');
ok($contribEmoA > $contribEmoB,
    'emocional_asimetrica: contribucion emocional A (alegre) > contribucion B (enfadado)');

// --- Test 3: ambos neutros produce misma carga ---
$snap2 = $snap1;
$snap2['factores']['emocional_a'] = 'neutro';
$snap2['factores']['emocional_b'] = 'neutro';

$cargaA2 = EncuentroExperiencia::cargaDe($snap2, $pA, $cal);
$cargaB2 = EncuentroExperiencia::cargaDe($snap2, $pB, $cal);

ok(abs($cargaA2 - $cargaB2) < 0.001,
    'emocional_neutros: ambos neutros produce carga identica');

// --- Test 4: resolver() produce resultados independientes con emociones asimetricas ---
$partida4 = [
    'meta' => ['seed' => 'test-resolver-asimetrico'],
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 12],
    'residentes' => [
        $pA => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'alegre']]],
        $pB => ['presencia' => 'residente', 'runtime' => ['estado_emocional' => ['id' => 'enfadado']]],
    ],
    'memoria_eventos' => [],
];

$enc4 = [
    'id' => 'enc_test4',
    'participantes' => [$pA, $pB],
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
    'dia' => 1,
    'hora' => 12,
    'intencion' => 'celeste_organizado',
];

$rng4 = AquiHayTema\Engine\RngService::fromPartida($partida4);
$exp4 = EncuentroExperiencia::resolver($partida4, $enc4, $catalog, $rng4, $cal);

$resA4 = (string) ($exp4['por_participante'][$pA]['resultado'] ?? '');
$resB4 = (string) ($exp4['por_participante'][$pB]['resultado'] ?? '');
$cargaA4 = (float) ($exp4['por_participante'][$pA]['carga'] ?? 0);
$cargaB4 = (float) ($exp4['por_participante'][$pB]['carga'] ?? 0);

ok($resA4 !== '', 'resolver_asimetrico: A tiene resultado');
ok($resB4 !== '', 'resolver_asimetrico: B tiene resultado');
ok($cargaA4 > $cargaB4,
    'resolver_asimetrico: carga final A > carga final B (alegre > enfadado)');

echo "\nResultados test emocional asimetrico:\n";
echo "  A (alegre):   carga=" . round($cargaA4, 3) . " resultado=$resA4\n";
echo "  B (enfadado): carga=" . round($cargaB4, 3) . " resultado=$resB4\n";
echo "  Diferencia cargas: " . round($cargaA4 - $cargaB4, 3) . "\n";

echo $fail === 0 ? "\nencuentro_emocional_asimetrico_test OK\n" : "\nFAIL ($fail)\n";
exit($fail > 0 ? 1 : 0);
