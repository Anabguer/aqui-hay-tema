<?php
declare(strict_types=1);

// FASE 2C (R3) · Contrato completo de FORMACIÓN de pareja autónoma.
//
// Cobertura:
//   A persistencia: estado, estabilidad 55 activa, fecha_inicio, historial_parejas
//   B bitácora INICIO_PAREJA única + cotilleo 'pareja' publicado (una vez)
//   C ficha/vista: EtiquetaRelacionPlay = Pareja ❤️
//   D Celestine: tiposPermitidos pasa a [quedar, cita] para el par
//   E cadena GATE-D→GATE-P: pareja_activa OFF ⇒ ni siquiera hay declaraciones
//   F idempotencia narrativa: segundo hito igual NO republica cotilleo

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EtiquetaRelacionPlay;
use AquiHayTema\Engine\IniciativaRomantica;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;

$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$root = dirname(__DIR__);
DomainBootstrap::boot();

const FA = 'ana';
const FB = 'bruno';

/** Partida con par DECLARABLE y declaración ACEPTADA (p=1) ya consumada. */
function partidaFormada(): array
{
    $cal = CalibracionConfig::load(dirname(__DIR__));
    $cal['romance_autonomo']['declaracion_activa'] = true;
    $cal['romance_autonomo']['pareja_activa'] = true;
    $cal['voluntad']['p_min'] = 1.0;
    $cal['voluntad']['p_max'] = 1.0;

    $p = [
        'reloj' => ['dia_pueblo' => 20, 'hora_actual' => 12],
        'rng' => ['seed' => 'f2c', 'state' => 101],
        'meta' => ['seed' => 'f2c'],
        'residentes' => [
            FA => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            FB => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => ['lugares_desbloqueados' => ['lug_cafeteria']],
        'relaciones_sociales' => [],
        'relaciones_romanticas' => [],
        'relaciones_conflicto' => [],
        'encuentros' => [],
        'parentesco' => [],
        'continuidad_romantica' => [],
        'bitacora_relaciones' => [],
        'memoria_eventos' => [],
        'propuestas_cooldown' => [],
        'rechazos_propuesta' => [],
        'diario' => [],
        'buzon' => [],
        'narrativa_hitos_publicados' => [],
    ];
    for ($i = 0; $i < 3; $i++) {
        RelacionEngine::ajustarSocialHacia($p, FA, FB, 10);
        RelacionEngine::ajustarSocialHacia($p, FB, FA, 10);
    }
    RelacionEngine::setRomanceHacia($p, FA, FB, 28);
    RelacionEngine::setRomanceHacia($p, FB, FA, 28);
    $p['encuentros'][] = [
        'id' => 'enc_pc', 'tipo' => 'primera_cita', 'participantes' => [FA, FB], 'lugar' => 'lug_cafeteria',
        'dia' => 18, 'hora' => 17, 'duracion_horas' => 1, 'estado' => 'terminado',
        'resultado' => ['por_participante' => [FA => ['resultado' => 'bien'], FB => ['resultado' => 'bien']]],
    ];
    RelacionBitacora::registrar($p, RelacionBitacora::PRIMERA_CITA, [FA, FB]);
    $p['encuentros'][] = [
        'id' => 'enc_c1', 'tipo' => 'cita', 'participantes' => [FA, FB], 'lugar' => 'lug_cafeteria',
        'dia' => 19, 'hora' => 18, 'duracion_horas' => 1, 'estado' => 'terminado',
        'resultado' => ['por_participante' => [FA => ['resultado' => 'bien'], FB => ['resultado' => 'bien']]],
    ];
    MemoriaEventos::registrar($p, 'romance', [FA, FB], null, 'cita', 'bien');
    IniciativaRomantica::registrarContinuidadPostCita($p, FA, FB, 'bien', $cal);
    $p['reloj'] = ['dia_pueblo' => 22, 'hora_actual' => 12]; // abs 540 = creado(492)+gap(48)
    $res = IniciativaRomantica::procesarContinuidad($p, $cal);
    if (($res[0]['resultado'] ?? '') !== 'declaracion_aceptada') {
        echo "FAIL setup: " . json_encode($res) . "\n";
        exit(1);
    }
    return $p;
}

$p = partidaFormada();

// ============ A: persistencia ============
$rel = RelacionEngine::obtenerEntre($p, FA, FB)['romance'];
ok(($rel['estado_pareja'] ?? '') === ParejaEngine::PAREJA, 'A1 estado_pareja=pareja');
ok((bool) ($rel['estabilidad_pareja']['activa'] ?? false), 'A2 estabilidad ACTIVA');
ok((int) ($rel['estabilidad_pareja']['valor'] ?? 0) === 55, 'A3 estabilidad_inicial=55 (canon)');
ok(is_array($rel['fecha_inicio']) && (int) ($rel['fecha_inicio']['dia'] ?? 0) === 22, 'A4 fecha_inicio sellada');
ok(count($rel['historial_parejas'] ?? []) === 1 && ($rel['historial_parejas'][0]['vuelta'] ?? null) === false, 'A5 historial_parejas[1] no-vuelta');

// ============ B: bitácora + cotilleo ============
ok(count(RelacionBitacora::entre($p, FA, FB, RelacionBitacora::INICIO_PAREJA)) === 1, 'B1 hito INICIO_PAREJA único');
$hayCotilleo = false;
foreach (($p['buzon'] ?? []) as $m) {
    if (($m['hito_tipo'] ?? '') === RelacionBitacora::INICIO_PAREJA) {
        $hayCotilleo = true;
    }
}
ok($hayCotilleo, 'B2 cotilleo familia pareja publicado');

// ============ C: ficha/vista ============
$et = EtiquetaRelacionPlay::romanceHacia($p, FA, FB, CalibracionConfig::load($root));
ok(($et['banda'] ?? '') === 'pareja' && ($et['etiqueta'] ?? '') === 'Pareja', 'C1 etiqueta Pareja en vista');

// ============ D: Celestine ve [quedar, cita] ============
$tipos = PropuestaNivel::tiposPermitidos($p, FA, FB, CalibracionConfig::load($root));
ok(in_array(PropuestaNivel::CITA, $tipos, true) && !in_array(PropuestaNivel::PRIMERA_CITA, $tipos, true), 'D1 tipos permitidos pareja: cita sí, primera_cita no');

// ============ E: cadena de gates GATE-D→GATE-P ============
$calSinPareja = CalibracionConfig::load($root);
$calSinPareja['romance_autonomo']['declaracion_activa'] = true;
$calSinPareja['romance_autonomo']['pareja_activa'] = false;
ok(IniciativaRomantica::declaracionActiva($calSinPareja) === false, 'E1 pareja_activa OFF ⇒ declaración OFF (cadena)');
$pE = partidaFormada_sinFormar($calSinPareja);
$m = null;
foreach (($pE['continuidad_romantica'] ?? []) as $mk) {
    if (in_array(FA, $mk['par'], true)) {
        $m = $mk;
    }
}
ok($m !== null && ($m['accion'] ?? '') === 'cita', 'E2 sin pareja_activa el marcador sigue siendo cita (nada nuevo)');

// ============ F: idempotencia narrativa del hito ============
$nMensajes = count(array_filter(
    ($p['buzon'] ?? []),
    static fn ($m) => ($m['hito_tipo'] ?? '') === RelacionBitacora::INICIO_PAREJA
));
RelacionBitacora::registrar($p, RelacionBitacora::INICIO_PAREJA, [FA, FB]);
$nMensajes2 = count(array_filter(
    ($p['buzon'] ?? []),
    static fn ($m) => ($m['hito_tipo'] ?? '') === RelacionBitacora::INICIO_PAREJA
));
ok($nMensajes === 1 && $nMensajes2 === 1, 'F1 cotilleo NO se duplica al repetir hito');

echo $fail === 0 ? "\nOK fase2c_formacion\n" : "\nFAIL fase2c_formacion ($fail)\n";
exit($fail === 0 ? 0 : 1);

function partidaFormada_sinFormar(array $cal): array
{
    $p = [
        'reloj' => ['dia_pueblo' => 20, 'hora_actual' => 12],
        'rng' => ['seed' => 'f2c2', 'state' => 101],
        'meta' => ['seed' => 'f2c2'],
        'residentes' => [
            FA => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            FB => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => ['lugares_desbloqueados' => ['lug_cafeteria']],
        'relaciones_sociales' => [], 'relaciones_romanticas' => [], 'relaciones_conflicto' => [],
        'encuentros' => [], 'parentesco' => [], 'continuidad_romantica' => [], 'bitacora_relaciones' => [],
        'memoria_eventos' => [], 'propuestas_cooldown' => [], 'rechazos_propuesta' => [], 'diario' => [],
        'buzon' => ['mensajes' => []], 'narrativa_hitos_publicados' => [],
    ];
    for ($i = 0; $i < 3; $i++) {
        RelacionEngine::ajustarSocialHacia($p, FA, FB, 10);
        RelacionEngine::ajustarSocialHacia($p, FB, FA, 10);
    }
    RelacionEngine::setRomanceHacia($p, FA, FB, 28);
    RelacionEngine::setRomanceHacia($p, FB, FA, 28);
    $p['encuentros'][] = [
        'id' => 'enc_pc', 'tipo' => 'primera_cita', 'participantes' => [FA, FB], 'lugar' => 'lug_cafeteria',
        'dia' => 18, 'hora' => 17, 'duracion_horas' => 1, 'estado' => 'terminado',
        'resultado' => ['por_participante' => [FA => ['resultado' => 'bien'], FB => ['resultado' => 'bien']]],
    ];
    RelacionBitacora::registrar($p, RelacionBitacora::PRIMERA_CITA, [FA, FB]);
    $p['encuentros'][] = [
        'id' => 'enc_c1', 'tipo' => 'cita', 'participantes' => [FA, FB], 'lugar' => 'lug_cafeteria',
        'dia' => 19, 'hora' => 18, 'duracion_horas' => 1, 'estado' => 'terminado',
        'resultado' => ['por_participante' => [FA => ['resultado' => 'bien'], FB => ['resultado' => 'bien']]],
    ];
    MemoriaEventos::registrar($p, 'romance', [FA, FB], null, 'cita', 'bien');
    IniciativaRomantica::registrarContinuidadPostCita($p, FA, FB, 'bien', $cal);
    return $p;
}
