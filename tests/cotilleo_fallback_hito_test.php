<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CotilleoNarrativo;
use AquiHayTema\Engine\CotilleoPatronCadencia;
use AquiHayTema\Engine\RelacionBitacora;

$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function patronesEnBuzon(array $partida): array
{
    return array_values(array_filter($partida['buzon'], static fn($m) => is_array($m)
        && ($m['tipo'] ?? '') === 'cotilleo_patron'));
}

function simularCoincidencia(array &$partida, int $dia, string $lugar, array $ids): void
{
    $partida['historial_coincidencias'][] = [
        'dia' => $dia,
        'hora' => 20,
        'lugar_id' => $lugar,
        'residentes' => $ids,
    ];
    $env = ['dia' => $dia, 'lugar_id' => $lugar, 'residentes' => $ids, 'actores' => $ids];
    if (CotilleoNarrativo::coincidenciaDigna($partida, $env, [])) {
        $msg = CotilleoNarrativo::mensajeCoincidencia($partida, $env, []);
        if ($msg !== null) {
            BuzonEngine::crear($partida, $msg);
        }
    }
}

function makePartidaConocidos(): array
{
    $p = [
        'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 20],
        'buzon' => [],
        'residentes' => [
            'per_a' => ['identidad_publica' => ['nombre' => 'A']],
            'per_b' => ['identidad_publica' => ['nombre' => 'B']],
        ],
        'bitacora_relaciones' => [],
        'relaciones_sociales' => [],
        'historial_coincidencias' => [],
    ];
    RelacionBitacora::registrar($p, RelacionBitacora::SE_CONOCIERON, ['per_a', 'per_b']);
    return $p;
}

function establecerPublicacionBase(array &$partida, int $diaBase): void
{
    for ($d = 1; $d <= $diaBase; $d++) {
        $partida['reloj']['dia_pueblo'] = $d;
        simularCoincidencia($partida, $d, 'lug_bar', ['per_a', 'per_b']);
    }
}

// ============================================================================
// TEST 1: hitoNuevoEntre como fallback cuando el nivel satura
// ============================================================================
echo "=== TEST 1: Fallback por hito relacional real ===\n";

$partida1 = makePartidaConocidos();
establecerPublicacionBase($partida1, 8);
$n8 = count(patronesEnBuzon($partida1));
echo "  Patrones tras 8 días: $n8\n";

// D9-D10 sin hito → no más publicaciones (nivel satura)
for ($d = 9; $d <= 10; $d++) {
    $partida1['reloj']['dia_pueblo'] = $d;
    simularCoincidencia($partida1, $d, 'lug_bar', ['per_a', 'per_b']);
}
$n10 = count(patronesEnBuzon($partida1));
echo "  Patrones tras 10 días (sin hito): $n10\n";

// Registrar hito en D10
$partida1['reloj']['dia_pueblo'] = 10;
RelacionBitacora::registrar($partida1, RelacionBitacora::PLAN_SIGNIFICATIVO, ['per_a', 'per_b']);

// D12: post-cooldown + hito → publica
$partida1['reloj']['dia_pueblo'] = 12;
simularCoincidencia($partida1, 12, 'lug_bar', ['per_a', 'per_b']);
$n12 = count(patronesEnBuzon($partida1));
echo "  Patrones tras hito D10 + D12: $n12\n";
ok($n12 > $n10, 'hito relacional real desbloquea nueva publicación');

// ============================================================================
// TEST 2: Mismo hito NO puede desbloquear más de un cotilleo
// ============================================================================
echo "\n=== TEST 2: Un hito = una publicación (D23→post→post) ===\n";

$partida2 = makePartidaConocidos();

// Simulación continua D1-D28
for ($d = 1; $d <= 28; $d++) {
    $partida2['reloj']['dia_pueblo'] = $d;
    if ($d === 23) {
        RelacionBitacora::registrar($partida2, RelacionBitacora::PLAN_SIGNIFICATIVO, ['per_a', 'per_b']);
    }
    simularCoincidencia($partida2, $d, 'lug_bar', ['per_a', 'per_b']);
}

$patrones2 = patronesEnBuzon($partida2);
$n2 = count($patrones2);
echo "  Patrones totales D1-D28 (hito en D23): $n2\n";

// Verificar que hay publicación el día del hito o después (hito desbloquea)
$pubsPostHito = array_values(array_filter($patrones2, static fn($m) => (int) ($m['dia'] ?? 0) >= 23));
ok($pubsPostHito !== [], 'hito D23 desbloquea publicación (D23 o después)');

// Verificar que no hay publicación DUPLICADA por mismo hito
// Contar publicaciones después del hito: debe ser exactamente 1
$nPostHito = count($pubsPostHito);
echo "  Publicaciones post-hito: $nPostHito\n";
ok($nPostHito === 1, 'mismo hito desbloquea exactamente 1 publicación');

// Verificar que D28 no tiene pub extra por mismo hito
$pubD28 = array_values(array_filter($patrones2, static fn($m) => (int) ($m['dia'] ?? 0) === 28));
ok($pubD28 === [], 'D28 sin publicación adicional por mismo hito');

// ============================================================================
// TEST 3: emoción persistente NO dispara publicación
// ============================================================================
echo "\n=== TEST 3: emoción persistente NO es trigger ===\n";

$partida3 = makePartidaConocidos();
establecerPublicacionBase($partida3, 3);
$n3_antes = count(patronesEnBuzon($partida3));

// D5: emoción persistente sin hito nuevo
$partida3['reloj']['dia_pueblo'] = 5;
$partida3['residentes']['per_a']['runtime']['estado_emocional']['id'] = 'enamorado';
$interes = CotilleoNarrativo::esInteresNarrativo($partida3, ['per_a', 'per_b']);
ok($interes['emocion_reciente'] === true, 'esInteresNarrativo detecta emocion_reciente');

simularCoincidencia($partida3, 5, 'lug_bar', ['per_a', 'per_b']);
$n3_despues = count(patronesEnBuzon($partida3));
ok($n3_despues === $n3_antes, 'emoción persistente NO desbloquea publicación');

// ============================================================================
// TEST 4: Baseline — cooldown + salto nivel
// ============================================================================
echo "\n=== TEST 4: Baseline — cooldown + salto nivel ===\n";

$partida4 = makePartidaConocidos();

// D1-D3: primer patrón aparece en D3 (minDias=3)
for ($d = 1; $d <= 3; $d++) {
    $partida4['reloj']['dia_pueblo'] = $d;
    simularCoincidencia($partida4, $d, 'lug_bar', ['per_a', 'per_b']);
}
$n_d3 = count(patronesEnBuzon($partida4));
echo "  D3: $n_d3 patrón(es)\n";
ok($n_d3 >= 1, 'D3: primer patrón (minDias=3)');

// D4-D5: cooldown (3 días desde D3)
for ($d = 4; $d <= 5; $d++) {
    $partida4['reloj']['dia_pueblo'] = $d;
    simularCoincidencia($partida4, $d, 'lug_bar', ['per_a', 'per_b']);
}
$n_d5 = count(patronesEnBuzon($partida4));
echo "  D5: $n_d5 patrón(es) total\n";
ok($n_d5 === $n_d3, 'D4-D5: cooldown bloquea');

// D6: post-cooldown (6-3=3 >= 3), nivel=6 >= 3+3=6 → salto
$partida4['reloj']['dia_pueblo'] = 6;
simularCoincidencia($partida4, 6, 'lug_bar', ['per_a', 'per_b']);
$n_d6 = count(patronesEnBuzon($partida4));
echo "  D6: $n_d6 patrón(es) total\n";
ok($n_d6 > $n_d3, 'D6: post-cooldown + salto nivel publica');

// ============================================================================
// TEST 5: hito_reciente SÍ funciona (ventana 5 días)
// ============================================================================
echo "\n=== TEST 5: hito_reciente se mantiene como trigger ===\n";

$partida5 = makePartidaConocidos();
establecerPublicacionBase($partida5, 5);
$n5_antes = count(patronesEnBuzon($partida5));

// D5: registrar primera_cita (hito_significativo)
$partida5['reloj']['dia_pueblo'] = 5;
RelacionBitacora::registrar($partida5, RelacionBitacora::PRIMERA_CITA, ['per_a', 'per_b']);

// D6: hito_reciente ventana=5 (5 >= 6-5=1), cooldown ok (6-ultima)
$partida5['reloj']['dia_pueblo'] = 6;
simularCoincidencia($partida5, 6, 'lug_bar', ['per_a', 'per_b']);
$n5_despues = count(patronesEnBuzon($partida5));
echo "  Antes: $n5_antes, después: $n5_despues\n";
ok($n5_despues > $n5_antes, 'hito_reciente (primera_cita) desbloquea publicación');

// ============================================================================
// TEST 6: Feed más largo sin emoción_reciente
// ============================================================================
echo "\n=== TEST 6: Feed controlado sin emoción_reciente ===\n";

$partida6 = makePartidaConocidos();
$partida6['residentes']['per_a']['runtime']['estado_emocional']['id'] = 'enamorado';

for ($d = 1; $d <= 20; $d++) {
    $partida6['reloj']['dia_pueblo'] = $d;
    simularCoincidencia($partida6, $d, 'lug_bar', ['per_a', 'per_b']);
}

$n6 = count(patronesEnBuzon($partida6));
echo "  Feed con emoción persistente (sin hitos): $n6 pub en 20 días\n";
ok($n6 <= 5, "feed controlado: $n6 <= 5 (sin spam)");

// ============================================================================
// TEST 7: hitoNuevoEntre solo mira DESPUÉS de ultima['dia']
// ============================================================================
echo "\n=== TEST 7: hitoNuevoEntre solo mira después de ultima['dia'] ===\n";

$partida7 = makePartidaConocidos();
establecerPublicacionBase($partida7, 5);

// Registrar hito en D2 (antes de ultima publicación)
$partida7['reloj']['dia_pueblo'] = 2;
RelacionBitacora::registrar($partida7, RelacionBitacora::PLAN_SIGNIFICATIVO, ['per_a', 'per_b']);

// D8: hito D2 < ultima[dia] (>=5) → NO desbloquea por hito
$partida7['reloj']['dia_pueblo'] = 8;
simularCoincidencia($partida7, 8, 'lug_bar', ['per_a', 'per_b']);
$n8_7 = count(patronesEnBuzon($partida7));

// D10: hito D2 sigue < ultima[dia] → NO desbloquea
$partida7['reloj']['dia_pueblo'] = 10;
simularCoincidencia($partida7, 10, 'lug_bar', ['per_a', 'per_b']);
$n10_7 = count(patronesEnBuzon($partida7));
echo "  D8: $n8_7, D10: $n10_7\n";
ok($n10_7 <= $n8_7 + 1, 'hito antiguo no desbloquea repetidamente');

// Ahora registrar hito NUEVO en D9
$partida7['reloj']['dia_pueblo'] = 9;
RelacionBitacora::registrar($partida7, RelacionBitacora::PLAN_SIGNIFICATIVO, ['per_a', 'per_b']);

// D12: hito D9 > ultima[dia] → SÍ desbloquea
$partida7['reloj']['dia_pueblo'] = 12;
simularCoincidencia($partida7, 12, 'lug_bar', ['per_a', 'per_b']);
$n12_7 = count(patronesEnBuzon($partida7));
echo "  D12 (con hito D9): $n12_7\n";
ok($n12_7 > $n10_7, 'hito nuevo D9 desbloquea publicación');

// ============================================================================
// Resumen
// ============================================================================
echo "\n=== RESUMEN ===\n";
echo "Fallos: $failures\n";

exit($failures > 0 ? 1 : 0);
