<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaCooldown;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\RechazoMemoria;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

$root = dirname(__DIR__);
$svc = new PartidaService($root);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

function emoId(array $p, string $id): string
{
    return (string) ($p['residentes'][$id]['runtime']['estado_emocional']['id'] ?? 'neutro');
}

function memCount(array $p): int
{
    return count($p['rechazos_propuesta'] ?? []);
}

// ============================================================
// A. Plan individual ACEPTADO -> sin memoria de rechazo
// ============================================================
$aceptado = false;
for ($t = 0; $t < 10 && !$aceptado; $t++) {
    $pA = $svc->nuevaPartida('juego_v1', 'indiv_accept_' . time() . '_' . $t);
    $pA['reloj']['dia_pueblo'] = 1;
    $pA['reloj']['hora_actual'] = 12;
    $idsA = array_keys(array_filter($pA['residentes'], static fn ($r) => ($r['presencia'] ?? '') === 'residente'));
    $soloA = (string) $idsA[0];
    $pA['residentes'][$soloA]['runtime']['estado_emocional'] = [
        'id' => 'alegre',
        'hasta' => ['dia_pueblo' => 10, 'hora_actual' => 23],
        'origen' => 'test',
    ];
    $rA = PropuestaEncuentroEngine::proponer($pA, [$soloA], 1, 14, 'individual', 'lug_cine');
    $propA = $rA['propuesta'] ?? [];
    if (($propA['estado'] ?? '') === 'programada') {
        $aceptado = true;
    }
}
ok($aceptado, 'A0: se obtuvo un plan individual aceptado en <=10 intentos');
ok(memCount($pA) === 0, 'A1: plan individual aceptado -> memoria de rechazo = 0');
ok(PropuestaCooldown::activo($pA, $soloA, $soloA, 'individual') === false, 'A2: aceptado -> sin cooldown auto');

// ============================================================
// B. Plan individual RECHAZADO por VOLUNTAD
// ============================================================
$rechazado = false;
for ($t = 0; $t < 20 && !$rechazado; $t++) {
    $pB = $svc->nuevaPartida('juego_v1', 'indiv_reject_' . time() . '_' . $t);
    $pB['reloj']['dia_pueblo'] = 1;
    $pB['reloj']['hora_actual'] = 12;
    $idsB = array_keys(array_filter($pB['residentes'], static fn ($r) => ($r['presencia'] ?? '') === 'residente'));
    $soloB = (string) $idsB[0];
    $romBBantes = RelacionEngine::romanceHacia($pB, $soloB, $soloB);
    $pB['residentes'][$soloB]['runtime']['estado_emocional'] = [
        'id' => 'enfadado',
        'hasta' => ['dia_pueblo' => 10, 'hora_actual' => 23],
        'origen' => 'test',
    ];
    $rB = PropuestaEncuentroEngine::proponer($pB, [$soloB], 1, 14, 'individual', 'lug_cine');
    $propB = $rB['propuesta'] ?? [];
    if (($propB['estado'] ?? '') === 'rechazada') {
        $rechazado = true;
    }
}
ok($rechazado, 'B0: se obtuvo un plan individual rechazado en <=20 intentos');
$reacB = $propB['reacciones'][0] ?? [];
ok(($reacB['clase'] ?? '') === PropuestaEncuentro::CLASE_VOLUNTAD, 'B1: clase = voluntad');
ok(($reacB['motivo_tecnico'] ?? '') === 'voluntad_rechaza_solo', 'B2: motivo_tecnico = voluntad_rechaza_solo');
ok(($rB['error'] ?? '') === 'ENCUENTRO_RECHAZADO_VOLUNTAD', 'B3: error publico = ENCUENTRO_RECHAZADO_VOLUNTAD');
ok(memCount($pB) === 1, 'B4: memoria de rechazo exactamente +1');
ok(RechazoMemoria::countHacia($pB, $soloB, $soloB) === 1, 'B5: fila auto (quien=hacia=solo) registrada UNA vez');
ok(PropuestaCooldown::activo($pB, $soloB, $soloB, 'individual') === true, 'B6: cooldown auto activo (6h)');
ok(PropuestaCooldown::activo($pB, $soloB, $soloB, 'conocerse') === false, 'B7: cooldown acotado al tipo individual');
ok(emoId($pB, $soloB) === 'enfadado', 'B8: SIN tristeza nueva (auto-rechazo no aplica emocion dirigida)');
ok(RelacionEngine::romanceHacia($pB, $soloB, $soloB) === $romBBantes, 'B9: SIN erosion romantica auto');

// ============================================================
// C. Reintento inmediato -> bloqueo cooldown NEUTRO
// ============================================================
for ($i = 1; $i <= 3; $i++) {
    $rC = PropuestaEncuentroEngine::proponer($pB, [$soloB], 1, 15 + $i, 'individual', 'lug_cine');
    ok(($rC['error'] ?? '') === 'ENCUENTRO_RECHAZADO_COOLDOWN', "C$i: reintento bloqueado como COOLDOWN");
    ok(strpos((string) ($rC['mensaje_ui'] ?? ''), 'espacio') !== false, "C$i: mensaje neutro temporal ('espacio')");
    ok(!isset($rC['propuesta']), "C$i: no se crea propuesta en reintento bloqueado");
    ok(memCount($pB) === 1, "C$i: memoria permanece = 1");
    ok(RechazoMemoria::countHacia($pB, $soloB, $soloB) === 1, "C$i: rechazos_previos no aumentan");
    ok(emoId($pB, $soloB) === 'enfadado', "C$i: sin tristeza por reintentos");
}
// Cooldown NO es direccional inverso: nadie mas implicado; comprobar que otro tipo libre
ok(PropuestaCooldown::activo($pB, $soloB, $soloB, 'quedar') === false, 'C4: otros tipos sin cooldown');

// ============================================================
// D. Tras expirar cooldown -> vuelve a evaluarse normalmente
// ============================================================
$pB['reloj']['dia_pueblo'] = 3;
$pB['reloj']['hora_actual'] = 12;
ok(PropuestaCooldown::activo($pB, $soloB, $soloB, 'individual') === false, 'D1: cooldown expirado (+48h > 6h)');
$rD = PropuestaEncuentroEngine::proponer($pB, [$soloB], 4, 14, 'individual', 'lug_cine');
$propD = $rD['propuesta'] ?? [];
ok(($rD['error'] ?? '') !== 'ENCUENTRO_RECHAZADO_COOLDOWN', 'D2: ya NO bloquea por cooldown');
$estadoD = $propD['estado'] ?? '';
ok(in_array($estadoD, ['programada', 'rechazada'], true), "D3: resolucion normal (estado=$estadoD)");
if ($estadoD === 'rechazada') {
    $reacD = $propD['reacciones'][0] ?? [];
    ok(($reacD['clase'] ?? '') === PropuestaEncuentro::CLASE_VOLUNTAD, 'D4: nuevo rechazo es por voluntad real');
    ok(memCount($pB) === 2, 'D5: nuevo rechazo real registra memoria otra vez (total 2)');
} else {
    ok(memCount($pB) === 1, 'D5-alt: aceptado tras expiracion; memoria sigue 1');
}

// ============================================================
// E. Agenda/indisponibilidad -> NUNCA es rechazo de voluntad
// ============================================================
$pE = $svc->nuevaPartida('juego_v1', 'indiv_agenda_' . time());
$pE['reloj']['dia_pueblo'] = 1;
$pE['reloj']['hora_actual'] = 12;
$idsE = array_keys(array_filter($pE['residentes'], static fn ($r) => ($r['presencia'] ?? '') === 'residente'));
$soloE = (string) $idsE[0];
$rEnc = EncuentroEngine::programar($pE, [$soloE], 1, 17, 'individual', 'lug_cine');
ok(!empty($rEnc['ok']), 'E0: encuentro ocupando la franja programado');
$m = new ReflectionMethod(PropuestaEncuentroEngine::class, 'evaluarParticipante');
$m->setAccessible(true);
$vol = new VoluntadPonderadaEvaluator([]);
$rxE = $m->invokeArgs(null, [&$pE, ['participantes' => [$soloE], 'tipo' => 'individual', 'lugar' => 'lug_cine'], $soloE, 1, 17, [$soloE], $vol]);
ok(($rxE['clase'] ?? '') === PropuestaEncuentro::CLASE_INDISPONIBILIDAD, 'E1: clase = indisponibilidad (agenda)');
ok(memCount($pE) === 0, 'E2: agenda NO crea memoria de rechazo');
ok(PropuestaCooldown::activo($pE, $soloE, $soloE, 'individual') === false, 'E3: agenda NO crea cooldown');

echo "\n" . ($failures === 0 ? "TODOS LOS TESTS PASARON\n" : "FALLOS: $failures\n");
exit($failures === 0 ? 0 : 1);
