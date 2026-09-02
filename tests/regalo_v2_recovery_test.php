<?php
declare(strict_types=1);

/* Regalos v2 (2026-09-02) — EmotionalRecovery::evaluarRegalo.
   Contrato: gift puede MEJORAR estado triste/enfadado; NUNCA empeorar.
   Sin modificar el comportamiento legacy de evaluar() (encuentro). */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\EmotionalRecovery;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\RegaloEngine;

$failures = 0;
function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

// -- no_le_gusta NUNCA aplica emoción --
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::TRISTE, RegaloEngine::NO_LE_GUSTA, true);
ok($out === null, 'no_le_gusta + triste -> null (no aplica)');
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::ENFADADO, RegaloEngine::NO_LE_GUSTA, false);
ok($out === null, 'no_le_gusta + enfadado -> null (no aplica)');

// -- indiferente NUNCA aplica emoción --
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::TRISTE, RegaloEngine::INDIFERENTE, true);
ok($out === null, 'indiferente + triste -> null');
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::ALEGRE, RegaloEngine::INDIFERENTE, true);
ok($out === null, 'indiferente + alegre -> null');

// -- le_gusta sobre estado bien: null (caller mantiene flujo legacy de alegre) --
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::NEUTRO, RegaloEngine::LE_GUSTA, true);
ok($out === null, 'le_gusta + neutro -> null (legacy alegre)');
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::ALEGRE, RegaloEngine::LE_GUSTA, true);
ok($out === null, 'le_gusta + alegre -> null');

// -- le_encanta sobre estado bien: null (legacy) --
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::NEUTRO, RegaloEngine::LE_ENCANTA, true);
ok($out === null, 'le_encanta + neutro -> null (legacy alegre)');
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::ALEGRE, RegaloEngine::LE_ENCANTA, false);
ok($out === null, 'le_encanta + alegre -> null');

// -- le_gusta + triste + hobbyMatch: alivia a NEUTRO --
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::TRISTE, RegaloEngine::LE_GUSTA, true);
ok(is_array($out), 'le_gusta + triste + match -> array');
ok(($out['estado'] ?? '') === EstadoEmocional::NEUTRO, '  estado = NEUTRO');
ok(($out['motivo'] ?? '') === 'regalo_alivia', '  motivo = regalo_alivia');
ok(($out['hobby_match'] ?? false) === true, '  hobby_match=true');

// -- le_gusta + triste + sin match: null (no basta) --
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::TRISTE, RegaloEngine::LE_GUSTA, false);
ok($out === null, 'le_gusta + triste + sin_match -> null');

// -- le_gusta + enfadado + match: alivia a NEUTRO --
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::ENFADADO, RegaloEngine::LE_GUSTA, true);
ok(is_array($out) && ($out['estado'] ?? '') === EstadoEmocional::NEUTRO, 'le_gusta + enfadado + match -> NEUTRO');

// -- le_gusta + enfadado + sin match: null --
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::ENFADADO, RegaloEngine::LE_GUSTA, false);
ok($out === null, 'le_gusta + enfadado + sin_match -> null');

// -- le_encanta + triste + match: anima a ALEGRE --
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::TRISTE, RegaloEngine::LE_ENCANTA, true);
ok(is_array($out), 'le_encanta + triste + match -> array');
ok(($out['estado'] ?? '') === EstadoEmocional::ALEGRE, '  estado = ALEGRE');
ok(($out['motivo'] ?? '') === 'regalo_animó', '  motivo = regalo_animó');

// -- le_encanta + triste + sin match: anima a ALEGRE con motivo _sin_match --
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::TRISTE, RegaloEngine::LE_ENCANTA, false);
ok(is_array($out) && ($out['estado'] ?? '') === EstadoEmocional::ALEGRE, 'le_encanta + triste + sin_match -> ALEGRE');
ok(($out['motivo'] ?? '') === 'regalo_animó_sin_match', '  motivo = regalo_animó_sin_match');

// -- le_encanta + enfadado + match: anima a ALEGRE --
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::ENFADADO, RegaloEngine::LE_ENCANTA, true);
ok(is_array($out) && ($out['estado'] ?? '') === EstadoEmocional::ALEGRE, 'le_encanta + enfadado + match -> ALEGRE');
ok(($out['motivo'] ?? '') === 'regalo_animó', '  motivo = regalo_animó');

// -- le_encanta + enfadado + sin match: anima a ALEGRE con motivo _sin_match --
$out = EmotionalRecovery::evaluarRegalo(EstadoEmocional::ENFADADO, RegaloEngine::LE_ENCANTA, false);
ok(is_array($out) && ($out['estado'] ?? '') === EstadoEmocional::ALEGRE, 'le_encanta + enfadado + sin_match -> ALEGRE');
ok(($out['motivo'] ?? '') === 'regalo_animó_sin_match', '  motivo = regalo_animó_sin_match');

// -- Invariante: rank(final) > rank(antes) en TODAS las transiciones devueltas --
$rank = [
    EstadoEmocional::TRISTE => 0,
    EstadoEmocional::ENFADADO => 0,
    EstadoEmocional::NEUTRO => 1,
    EstadoEmocional::ALEGRE => 2,
];
$combinaciones = [
    [EstadoEmocional::TRISTE, RegaloEngine::LE_GUSTA, true],
    [EstadoEmocional::TRISTE, RegaloEngine::LE_GUSTA, false],
    [EstadoEmocional::TRISTE, RegaloEngine::LE_ENCANTA, true],
    [EstadoEmocional::TRISTE, RegaloEngine::LE_ENCANTA, false],
    [EstadoEmocional::ENFADADO, RegaloEngine::LE_GUSTA, true],
    [EstadoEmocional::ENFADADO, RegaloEngine::LE_ENCANTA, true],
    [EstadoEmocional::ENFADADO, RegaloEngine::LE_ENCANTA, false],
];
foreach ($combinaciones as $c) {
    [$estadoAntes, $reaccion, $match] = $c;
    $out = EmotionalRecovery::evaluarRegalo($estadoAntes, $reaccion, $match);
    if ($out !== null) {
        $r1 = $rank[$estadoAntes] ?? 0;
        $r2 = $rank[$out['estado']] ?? 0;
        ok($r2 > $r1, "invariante rank: $estadoAntes/$reaccion/match=$match -> {$out['estado']} (rank $r1 -> $r2)");
    }
}

// -- método legacy evaluar() intacto --
$out = EmotionalRecovery::evaluar(EstadoEmocional::TRISTE, 'muy_bien', true);
ok(is_array($out), 'legacy evaluar() intacto: triste + muy_bien + match -> array');
ok(($out['estado'] ?? '') === EstadoEmocional::ALEGRE, '  estado = ALEGRE');

exit($failures > 0 ? 1 : 0);