<?php
declare(strict_types=1);

/* Regalos F2: Discovery por regalos (B, C, D).
   B - reaccion fuerte produce discovery real con campos canonicos.
   C - no revela nada que no corresponda al perfil ni datos no descubiertos.
   D - repetir regalo NO permite farmear discovery. */

require_once __DIR__ . '/regalos_f1_fixture.php';

use AquiHayTema\Engine\DiscoveryEngine;
use AquiHayTema\Engine\InventarioEngine;
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

function perfil_f2(array $over = []): array
{
    return regalo_perfil(array_merge([
        'hobbies' => ['pintar'],
        'preferencias' => array_merge(regalo_perfil()['preferencias'], [
            'hobbies_pos' => ['leer'],
            'hobbies_neg' => ['correr'],
        ]),
    ], $over));
}

function descubiertos(array $partida, string $rid): array
{
    $out = [];
    foreach ($partida['descubrimientos'] ?? [] as $d) {
        if (($d['residente_id'] ?? '') === $rid && ($d['estado'] ?? '') === DiscoveryEngine::DESCUBIERTO) {
            $out[] = (string) $d['campo'];
        }
    }
    return $out;
}

$cal = regalo_cal();
$catalogo = regalo_catalogo();

// ---- B: discovery real en reaccion fuerte ---------------------------------
$p = regalo_fixture_partida(['per_a' => perfil_f2()]);
$p['features']['discovery_enabled'] = true;
InventarioEngine::anadir($p, 'libro', 1, $catalogo); // hobby_ids: leer, escribir

$r = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
ok($r['ok'] && $r['reaccion'] === RegaloEngine::LE_ENCANTA, 'B: libro a fan de leer -> encanta');
ok(count($r['descubrimientos']) === 1, 'B: exactamente 1 descubrimiento por regalo');
$campo = (string) ($r['descubrimientos'][0]['campo'] ?? '');
ok($campo === 'gusto_hobby:leer', "B: campo canonico gusto_hobby:leer (got $campo)");
ok($r['descubrimientos'][0]['texto'] !== '', 'B: copy humano incluido');
ok(DiscoveryEngine::estado($p, 'per_a', 'gusto_hobby:leer') === DiscoveryEngine::DESCUBIERTO, 'B: estado DESCUBIERTO');
ok(in_array('gusto_hobby:leer', descubiertos($p, 'per_a'), true), 'B: registrado en partida.descubrimientos');

// rechazo fuerte tambien descubre
$p['reloj']['dia_pueblo'] = 2; // fuera de cooldown
$p['reloj']['hora_actual'] = 8;
InventarioEngine::anadir($p, 'peluche', 0, $catalogo); // noop para mantener total
InventarioEngine::anadir($p, 'cantimplora', 1, $catalogo); // deporte, correr (+pasear/senderismo)
$r2 = RegaloEngine::entregar($p, 'per_a', 'cantimplora', $cal, $catalogo);
ok($r2['ok'] && $r2['reaccion'] === RegaloEngine::NO_LE_GUSTA, 'B: cantimplora a quien odia correr -> no_le_gusta');
ok(($r2['descubrimientos'][0]['campo'] ?? '') === 'rechazo_hobby:correr', 'B: campo canonico rechazo_hobby:correr');

// ---- C: no revela datos no correspondientes -------------------------------
$campos = descubiertos($p, 'per_a');
ok(count($campos) === 2, 'C: solo los 2 campos justificados');
ok(!in_array('hobby:pintar', $campos, true), 'C: el hobby propio no se filtra por regalar');
ok(!in_array('gusto_hobby:escribir', $campos, true), 'C: solo el primer match, no todo el cruce');
foreach ($campos as $c) {
    ok(strpos($c, 'gusto_hobby:') === 0 || strpos($c, 'rechazo_hobby:') === 0, "C: campo permitido $c");
}
$valores = [];
foreach ($p['descubrimientos'] as $d) {
    $valores[] = (string) ($d['valor'] ?? '');
}
ok($valores === ['leer', 'correr'], 'C: valores son IDs reales del perfil, sin inventar');

// le_gusta (regalo afin a hobby propio) NO descubre
$p['reloj']['dia_pueblo'] = 3;
$p['reloj']['hora_actual'] = 9;
InventarioEngine::anadir($p, 'kit_creativo', 1, $catalogo); // manualidades; pintar NO esta en hobby_ids
$r3 = RegaloEngine::entregar($p, 'per_a', 'kit_creativo', $cal, $catalogo);
ok($r3['ok'] && $r3['reaccion'] === RegaloEngine::INDIFERENTE, 'C: sin match -> indiferente');
ok($r3['descubrimientos'] === [], 'C: indiferente no descubre');

// ---- D: anti-farming -------------------------------------------------------
// mismo objeto repetido: degradacion F1 evita segunda revelation
$p['reloj']['dia_pueblo'] = 4;
$p['reloj']['hora_actual'] = 10;
$nAntes = count(descubiertos($p, 'per_a'));
InventarioEngine::anadir($p, 'libro', 1, $catalogo);
$r4 = RegaloEngine::entregar($p, 'per_a', 'libro', $cal, $catalogo);
ok($r4['ok'] && $r4['reaccion'] === RegaloEngine::LE_GUSTA, 'D: repeticion degrada encanta->gusta');
ok($r4['descubrimientos'] === [], 'D: objeto repetido no vuelve a descubrir');

// otro objeto con la misma pref ya descubierta: sin fila nueva
$p['reloj']['dia_pueblo'] = 5;
$p['reloj']['hora_actual'] = 11;
InventarioEngine::anadir($p, 'marcapaginas', 1, $catalogo); // leer
$r5 = RegaloEngine::entregar($p, 'per_a', 'marcapaginas', $cal, $catalogo);
ok($r5['ok'] && $r5['reaccion'] === RegaloEngine::LE_ENCANTA, 'D: otro objeto con pref conocida sigue encantando');
ok($r5['descubrimientos'] === [], 'D: pref ya descubierta no genera discovery');
ok(count(descubiertos($p, 'per_a')) === $nAntes, 'D: cero crecimiento de discoveries por farmeo');

// cooldown F1 sigue bloqueando spam dentro de la ventana
$p6 = regalo_fixture_partida(['per_a' => perfil_f2()]);
$p6['features']['discovery_enabled'] = true;
InventarioEngine::anadir($p6, 'libro', 2, $catalogo);
RegaloEngine::entregar($p6, 'per_a', 'libro', $cal, $catalogo);
$rDup = RegaloEngine::entregar($p6, 'per_a', 'libro', $cal, $catalogo);
ok(!$rDup['ok'] && $rDup['error'] === 'regalo_cooldown', 'D: cooldown F1 intacto');

// sin flag discovery_enabled: cero revelations (convencion Encuentros)
$p7 = regalo_fixture_partida(['per_a' => perfil_f2()]);
InventarioEngine::anadir($p7, 'libro', 1, $catalogo);
$r7 = RegaloEngine::entregar($p7, 'per_a', 'libro', $cal, $catalogo);
ok($r7['ok'] && $r7['descubrimientos'] === [], 'flag apagado: regalo no descubre');
ok(($p7['descubrimientos'] ?? []) === [], 'flag apagado: sin entradas en partida');

exit($failures > 0 ? 1 : 0);
