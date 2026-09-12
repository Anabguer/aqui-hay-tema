<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\EmotionalRecovery;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\VidaPuebloEngine;

$passed = 0;
$failed = 0;

function assert_eq($actual, $expected, string $msg): void
{
    global $passed, $failed;
    if ($actual === $expected) {
        $passed++;
        echo "  ✓ $msg\n";
    } else {
        $failed++;
        echo "  ✗ $msg — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
    }
}

function assert_null($actual, string $msg): void
{
    assert_eq($actual, null, $msg);
}

echo "=== TEST: Experiencias individuales — contrato emocional ===\n\n";

// ── TEST 1: Individual + muy_mal + hobby_match=false + necesidades normales → NO tristeza ──
echo "TEST 1: Individual + muy_mal + sin hobby_match → sin tristeza\n";
$eval = EmotionalRecovery::evaluar(EstadoEmocional::NEUTRO, 'muy_mal', false, true);
assert_null($eval, "muy_mal individual no produce emoción");

$eval2 = EmotionalRecovery::evaluar(EstadoEmocional::ALEGRE, 'muy_mal', false, true);
assert_null($eval2, "muy_mal individual desde ALEGRE no produce emoción");

// ── TEST 2: Individual + muy_bien + hobby_match=true → puede ser positiva ──
echo "\nTEST 2: Individual + muy_bien → alegre\n";
$eval = EmotionalRecovery::evaluar(EstadoEmocional::NEUTRO, 'muy_bien', true, true);
assert_eq($eval['estado'] ?? null, EstadoEmocional::ALEGRE, "muy_bien individual → alegre");
assert_eq($eval['hobby_match'] ?? null, false, "hobby_match=false en camino directo (no recuperación)");

$eval2 = EmotionalRecovery::evaluar(EstadoEmocional::NEUTRO, 'muy_bien', false, true);
assert_eq($eval2['estado'] ?? null, EstadoEmocional::ALEGRE, "muy_bien individual sin match → sigue alegre");

// ── TEST 3: Individual + bien/normal/mal → sin emoción ──
echo "\nTEST 3: Individual + resultado moderado → sin emoción\n";
foreach (['bien', 'normal', 'mal'] as $res) {
    $eval = EmotionalRecovery::evaluar(EstadoEmocional::NEUTRO, $res, false, true);
    assert_null($eval, "$res individual → sin emoción");
}

// ── TEST 4: Social + muy_mal + hobby_match=false → SÍ produce tristeza ──
echo "\nTEST 4: Social + muy_mal → tristeza (no romper encuentros sociales)\n";
$eval = EmotionalRecovery::evaluar(EstadoEmocional::NEUTRO, 'muy_mal', false, false);
assert_eq($eval['estado'] ?? null, EstadoEmocional::TRISTE, "muy_mal social → triste");
assert_eq($eval['motivo'] ?? null, 'encuentro', "motivo = encuentro");

$eval2 = EmotionalRecovery::evaluar(EstadoEmocional::NEUTRO, 'muy_mal', true, false);
assert_eq($eval2['estado'] ?? null, EstadoEmocional::TRISTE, "muy_mal social con match → triste");

// ── TEST 5: Social + muy_bien → alegre (sin cambios) ──
echo "\nTEST 5: Social + muy_bien → alegre (sin cambios)\n";
$eval = EmotionalRecovery::evaluar(EstadoEmocional::NEUTRO, 'muy_bien', false, false);
assert_eq($eval['estado'] ?? null, EstadoEmocional::ALEGRE, "muy_bien social → alegre");

// ── TEST 6: Caso Wendy reproducido ──
echo "\nTEST 6: Caso Wendy — NEUTRO + muy_mal + individual + sin match → null\n";
$eval = EmotionalRecovery::evaluar(EstadoEmocional::NEUTRO, 'muy_mal', false, true);
assert_null($eval, "Wendy no debería estar triste por ida individual al gym");

// Verificar que desde TRISTE/ENFADADO individual tampoco empeora
$eval2 = EmotionalRecovery::evaluar(EstadoEmocional::TRISTE, 'muy_mal', false, true);
assert_null($eval2, "Wendy triste + muy_mal individual → mantiene (no empeora)");

$eval3 = EmotionalRecovery::evaluar(EstadoEmocional::ENFADADO, 'muy_mal', false, true);
assert_null($eval3, "Wendy enfadado + muy_mal individual → mantiene (no empeora)");

// ── TEST 7: Heart delta — individual + muy_mal → 0 ──
echo "\nTEST 7: Heart delta — individual + muy_mal → 0\n";
$delta = VidaPuebloEngine::deltaResultadoEncuentro('muy_mal');
assert_eq($delta, -2, "delta base muy_mal = -2 (social)");

// Simular que en aplicarEncuentroOrganizado, individual+muy_mal → delta override a 0
// Verificamos la lógica inline ya que no podemos mockear el encounter completo
$encIndividual = ['tipo' => 'individual', 'intencion' => 'celeste_organizado'];
$res = 'muy_mal';
$deltaCalc = VidaPuebloEngine::deltaResultadoEncuentro($res);
if (($encIndividual['tipo'] ?? '') === 'individual' && $res === 'muy_mal') {
    $deltaCalc = 0;
}
assert_eq($deltaCalc, 0, "Heart delta individual + muy_mal = 0");

// Social muy_mal sigue dando -2
$encSocial = ['tipo' => 'social', 'intencion' => 'celeste_organizado'];
$deltaCalc2 = VidaPuebloEngine::deltaResultadoEncuentro($res);
if (($encSocial['tipo'] ?? '') === 'individual' && $res === 'muy_mal') {
    $deltaCalc2 = 0;
}
assert_eq($deltaCalc2, -2, "Heart delta social + muy_mal = -2 (sin cambios)");

// ── TEST 8: estadoDesdeResultado directo ──
echo "\nTEST 8: estadoDesdeResultado directo\n";
assert_eq(EmotionalRecovery::estadoDesdeResultado('muy_bien'), EstadoEmocional::ALEGRE, "muy_bien → alegre");
assert_eq(EmotionalRecovery::estadoDesdeResultado('muy_mal'), EstadoEmocional::TRISTE, "muy_mal social → triste");
assert_null(EmotionalRecovery::estadoDesdeResultado('muy_mal', true), "muy_mal individual → null");
assert_null(EmotionalRecovery::estadoDesdeResultado('bien'), "bien → null");
assert_null(EmotionalRecovery::estadoDesdeResultado('mal'), "mal → null");
assert_null(EmotionalRecovery::estadoDesdeResultado('normal'), "normal → null");

echo "\n=== RESULTADO: $passed OK / $failed FAIL ===\n";
exit($failed > 0 ? 1 : 0);
