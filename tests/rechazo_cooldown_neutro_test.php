<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaCooldown;
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

$p = $svc->nuevaPartida('juego_v1', 'cd_neutro_' . time());
$p['reloj']['dia_pueblo'] = 1;
$p['reloj']['hora_actual'] = 12;
$p['reloj']['minuto_actual'] = 0;
$pareja = $p['tutorial']['pareja_mision1'] ?? [];
$F = (string) ($pareja['a'] ?? '');
$H = (string) ($pareja['b'] ?? '');
ok($F !== '' && $H !== '', 'par F/H disponible');
$tipo = 'conocerse';
$lugar = 'lug_cafeteria';

ok(memCount($p) === 0, 'P1: sin memoria inicial');
ok(PropuestaCooldown::activo($p, $F, $H, $tipo) === false && PropuestaCooldown::activo($p, $H, $F, $tipo) === false, 'P1: sin cooldown inicial');
$romFH0 = RelacionEngine::romanceHacia($p, $F, $H);
$romHF0 = RelacionEngine::romanceHacia($p, $H, $F);

RechazoMemoria::registrar($p, $H, $F, 'banal', [], $tipo);
ok(memCount($p) === 1, 'P2: rechazo real H->F deja memoria total = 1');
ok(RechazoMemoria::countHacia($p, $H, $F) === 1, 'P2: countHacia(H,F) = 1');
ok(RechazoMemoria::countHacia($p, $F, $H) === 0, 'P2: countHacia(F,H) = 0');
ok(PropuestaCooldown::activo($p, $F, $H, $tipo) === true, 'P2: cooldown correspondiente activo (F no puede insistir a H)');
ok(PropuestaCooldown::activo($p, $H, $F, $tipo) === false, 'P2: sin cooldown inverso');

for ($i = 1; $i <= 4; $i++) {
    $r = PropuestaEncuentroEngine::proponer($p, [$F, $H], 3, 12, $tipo, $lugar);
    $err = (string) ($r['error'] ?? '');
    ok($err === 'ENCUENTRO_RECHAZADO_COOLDOWN', "P3.$i: reintento bloqueado como cooldown");
    ok(strpos((string) ($r['mensaje_ui'] ?? ''), 'espacio') !== false, "P3.$i: mensaje UX 'Necesitan un poco de espacio'");
    ok(memCount($p) === 1, "P3.$i: memoria sigue = 1");
    ok(RechazoMemoria::countHacia($p, $H, $F) === 1 && RechazoMemoria::countHacia($p, $F, $H) === 0, "P3.$i: rechazos_previos no aumentan");
    ok(PropuestaCooldown::activo($p, $H, $F, $tipo) === false, "P3.$i: el otro participante no gana cooldown nuevo");
    ok(emoId($p, $F) === 'neutro' && emoId($p, $H) === 'neutro', "P3.$i: nadie se pone triste por intentos bloqueados");
    ok(RelacionEngine::romanceHacia($p, $F, $H) === $romFH0 && RelacionEngine::romanceHacia($p, $H, $F) === $romHF0, "P3.$i: romance no cambia");
}

$p['reloj']['dia_pueblo'] = 3;
$p['reloj']['hora_actual'] = 12;
ok(PropuestaCooldown::activo($p, $F, $H, $tipo) === false, 'P4: cooldown expira tras las 6h');

$r5 = PropuestaEncuentroEngine::proponer($p, [$F, $H], 4, 12, $tipo, $lugar);
ok((string) ($r5['error'] ?? '') !== 'ENCUENTRO_RECHAZADO_COOLDOWN', 'P5: propuesta real se evalua normalmente (no cooldown)');
$memAntes = memCount($p);
RechazoMemoria::registrar($p, $H, $F, 'banal', [], $tipo);
ok(memCount($p) === $memAntes + 1, "P5: un rechazo real posterior SI registra memoria ($memAntes -> " . memCount($p) . ')');

$p2 = $svc->nuevaPartida('juego_v1', 'cd_dir_' . time());
$p2['reloj']['dia_pueblo'] = 1;
$p2['reloj']['hora_actual'] = 8;
$q1 = (string) array_key_first(array_filter($p2['residentes'], static fn($r) => ($r['presencia'] ?? '') === 'residente'));
$rest = array_keys(array_filter($p2['residentes'], static fn($r) => ($r['presencia'] ?? '') === 'residente'));
$q2 = (string) ($rest[1] ?? '');

RechazoMemoria::registrar($p2, $q1, $q2, 'banal', [], $tipo);
ok(PropuestaCooldown::activo($p2, $q2, $q1, $tipo) === true, 'DIR A->B: cooldown activo en sentido B no insiste a A');
ok(PropuestaCooldown::activo($p2, $q1, $q2, $tipo) === false, 'DIR A->B: sentido contrario libre');
$p2['reloj']['dia_pueblo'] = 2;
RechazoMemoria::registrar($p2, $q2, $q1, 'banal', [], $tipo);
ok(PropuestaCooldown::activo($p2, $q1, $q2, $tipo) === true, 'DIR B->A: cooldown activo en sentido inverso');
ok(memCount($p2) === 2 && RechazoMemoria::countHacia($p2, $q1, $q2) === 1 && RechazoMemoria::countHacia($p2, $q2, $q1) === 1, 'DIR: memoria direccional correcta ambos sentidos');

$p3 = $svc->nuevaPartida('juego_v1', 'cd_real_' . time());
$p3['reloj']['dia_pueblo'] = 1;
$p3['reloj']['hora_actual'] = 8;
$r3 = array_keys(array_filter($p3['residentes'], static fn($x) => ($x['presencia'] ?? '') === 'residente'));
$c = (string) $r3[0];
$d = (string) $r3[1];
$romDC0 = 10;
RelacionEngine::setRomanceHacia($p3, $d, $c, $romDC0);
RechazoMemoria::registrar($p3, $c, $d, 'emocional', [], $tipo);
ok(memCount($p3) === 1, 'REAL: rechazo emocional registra memoria');
ok(emoId($p3, $d) === 'triste', 'REAL: tristeza aplicada al rechazado (consecuencia autentica)');
ok((RelacionEngine::romanceHacia($p3, $d, $c) ?? 0) <= $romDC0 - 3, 'REAL: erosion romantica aplicada en rechazo real');
ok(PropuestaCooldown::activo($p3, $d, $c, $tipo) === true, 'REAL: cooldown marcado tras rechazo real');

$m = new ReflectionMethod(PropuestaEncuentroEngine::class, 'evaluarParticipante');
$m->setAccessible(true);
$p4 = $svc->nuevaPartida('juego_v1', 'cd_wrap_' . time());
$p4['reloj']['dia_pueblo'] = 1;
$p4['reloj']['hora_actual'] = 12;
$rw = array_keys(array_filter($p4['residentes'], static fn($x) => ($x['presencia'] ?? '') === 'residente'));
$w1 = (string) $rw[0];
$w2 = (string) $rw[1];
RechazoMemoria::registrar($p4, $w2, $w1, 'banal', [], $tipo);
$prop = ['participantes' => [$w1, $w2], 'tipo' => $tipo, 'lugar' => $lugar];
$vol = new VoluntadPonderadaEvaluator([]);
$rx1 = $m->invokeArgs(null, [&$p4, $prop, $w1, 2, 12, [$w1, $w2], $vol]);
ok(($rx1['clase'] ?? '') === 'cooldown' && ($rx1['motivo_tipo'] ?? '') === 'cooldown', 'WRAP: reaccion cooldown propagada con motivo_tipo=cooldown');
ok(array_key_exists('score', $rx1) && $rx1['score'] === null, 'WRAP: cooldown sin score');
ok(memCount($p4) === 1, 'WRAP: evaluarParticipante NO registra cooldown en memoria');
ok(PropuestaCooldown::activo($p4, $w2, $w1, $tipo) === false, 'WRAP: no crea cooldown inverso');
$rx2 = $m->invokeArgs(null, [&$p4, $prop, $w2, 2, 12, [$w1, $w2], $vol]);
ok(empty($rx2['_joint_plan']) === false || ($rx2['clase'] ?? '') === null, 'WRAP: segundo participante evaluado normalmente (sin contaminacion)');
ok(memCount($p4) === 1, 'WRAP: memoria intacta tras evaluar ambos');

exit($failures > 0 ? 1 : 0);
