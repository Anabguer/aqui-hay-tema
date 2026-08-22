<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AgendaEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CopyRechazoPropuesta;
use AquiHayTema\Engine\DisponibilidadEngine;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\ResidenteRuntime;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

/** @return array<string, mixed> */
function partidaOficina(): array
{
    $p = [
        'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10, 'minuto_actual' => 0, 'dia_semana_base' => 'lunes'],
        'residentes' => [],
        'encuentros' => [],
        'celeste' => ['lugares_desbloqueados' => ['lug_cafeteria', 'lug_biblioteca']],
        'propuestas_encuentro' => [],
        'relaciones' => [],
        'relaciones_romanticas' => [],
        'rng' => ['seed' => 'test-rechazo-copy', 'cursor' => 0],
    ];
    $r = ResidenteRuntime::crearPlaceholderDev(1);
    $r['runtime']['ocupacion'] = 'oficina';
    $p['residentes'][$r['catalog_id']] = $r;
    $r2 = ResidenteRuntime::crearPlaceholderDev(2);
    $r2['runtime']['ocupacion'] = 'oficina';
    $p['residentes'][$r2['catalog_id']] = $r2;
    return $p;
}

// --- NO PUEDO: copy causal trabajo (no excusa banal) ---
$p = partidaOficina();
$ids = array_keys($p['residentes']);
$a = (string) $ids[0];
$b = (string) $ids[1];
$dispTrabajo = AgendaEngine::estaDisponible($p, $a, 1, 14);
ok(!($dispTrabajo['disponible'] ?? true) && (($dispTrabajo['tipo'] ?? '') === 'trabajo' || ($dispTrabajo['tipo'] ?? '') === 'trabajo_generico'), 'oficina ocupa hora laboral');
$reacTrabajo = [
    'residente_id' => $a,
    'nombre' => 'Carmen',
    'decision' => PropuestaEncuentro::DECISION_RECHAZA,
    'clase' => PropuestaEncuentro::CLASE_INDISPONIBILIDAD,
    'motivo_tecnico' => (string) ($dispTrabajo['motivo'] ?? 'ocupado'),
    'detalle' => $dispTrabajo,
    'factores' => ['motivo_agenda' => (string) ($dispTrabajo['motivo'] ?? 'ocupado'), 'detalle_agenda' => $dispTrabajo],
];
$familiaTrabajo = CopyRechazoPropuesta::familiaDeReaccion($reacTrabajo, 'lug_cafeteria', 14);
ok($familiaTrabajo === 'trabajo', 'familia indisponibilidad trabajo');
$lineaTrabajo = CopyRechazoPropuesta::linea($p, $reacTrabajo, 'test-trabajo');
ok(stripos($lineaTrabajo, 'trabaj') !== false, 'NO PUEDO copy menciona trabajo');
ok(stripos($lineaTrabajo, 'duchar') === false, 'NO PUEDO no usa excusa banal ducha');

// --- NO QUIERO: causa social emocional, no agenda ---
$p2 = partidaOficina();
$p2['residentes'][$a]['runtime']['estado_emocional'] = ['id' => EstadoEmocional::ENFADADO];
$reacEmo = [
    'residente_id' => $a,
    'nombre' => 'Carmen',
    'decision' => PropuestaEncuentro::DECISION_RECHAZA,
    'clase' => PropuestaEncuentro::CLASE_VOLUNTAD,
    'motivo_tecnico' => 'voluntad_rechaza_emocional',
    'motivo_tipo' => 'emocional',
    'copy_id' => 'emocional',
];
$familiaEmo = CopyRechazoPropuesta::familiaDeReaccion($reacEmo);
ok($familiaEmo === 'emocional', 'familia voluntad emocional');
$lineaEmo = CopyRechazoPropuesta::linea($p2, $reacEmo, 'test-emo');
ok(stripos($lineaEmo, 'ánimo') !== false || stripos($lineaEmo, 'apetece') !== false, 'NO QUIERO copy emocional');
ok(CopyRechazoPropuesta::tipoDeClase(PropuestaEncuentro::CLASE_VOLUNTAD) === CopyRechazoPropuesta::TIPO_NO_QUIERO, 'voluntad es NO QUIERO');

// --- contrapropuesta solo cuando procede (disponibilidad + social ok) ---
$ref = new ReflectionClass(PropuestaEncuentroEngine::class);
$mContra = $ref->getMethod('construirContrapropuesta');
$mContra->setAccessible(true);
$mSocial = $ref->getMethod('aceptariaSocialmente');
$mSocial->setAccessible(true);

$p3 = partidaOficina();
/** @var array<string, mixed>|null $slotOk */
$slotOk = $mContra->invoke(null, $p3, [$a, $b], 'conocerse', 'lug_cafeteria', 1, 14);
ok($slotOk !== null && ($slotOk['dia'] ?? 0) >= 1, 'hay siguiente slot compatible tras hora laboral');
ok($mSocial->invoke(null, $p3, ['participantes' => [$a, $b], 'tipo' => 'conocerse'], CalibracionConfig::load(dirname(__DIR__))), 'aceptaría socialmente sin bloqueo');

$p3['residentes'][$a]['runtime']['estado_emocional'] = ['id' => EstadoEmocional::ENFADADO];
/** @var array<string, mixed>|null $slotNo */
$slotNo = $mContra->invoke(null, $p3, [$a, $b], 'conocerse', 'lug_cafeteria', 1, 14);
ok(!$mSocial->invoke(null, $p3, ['participantes' => [$a, $b], 'tipo' => 'conocerse'], CalibracionConfig::load(dirname(__DIR__))), 'enfadado bloquea contrapropuesta social');

$propIndisp = [
    'id' => 'prop_test',
    'participantes' => [$a, $b],
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
    'dia' => 1,
    'hora' => 14,
    'estado' => 'rechazada',
    'reacciones' => [$reacTrabajo],
];
$mAnotar = $ref->getMethod('anotarRechazoNarrativo');
$mAnotar->setAccessible(true);
$mAnotar->invokeArgs(null, [&$p3, &$propIndisp, 'lug_cafeteria', 1, 14, CalibracionConfig::load(dirname(__DIR__))]);
ok(empty($propIndisp['contrapropuesta']), 'sin contrapropuesta si social bloquea');

$p4 = partidaOficina();
$propIndisp2 = [
    'id' => 'prop_test2',
    'participantes' => [$a, $b],
    'tipo' => 'conocerse',
    'lugar' => 'lug_cafeteria',
    'dia' => 1,
    'hora' => 14,
    'estado' => 'rechazada',
    'reacciones' => [$reacTrabajo],
];
$mAnotar->invokeArgs(null, [&$p4, &$propIndisp2, 'lug_cafeteria', 1, 14, CalibracionConfig::load(dirname(__DIR__))]);
ok(!empty($propIndisp2['contrapropuesta']['texto']), 'contrapropuesta cuando solo disponibilidad');
ok(stripos((string) $propIndisp2['contrapropuesta']['texto'], '?') !== false, 'contrapropuesta con pregunta');

// --- siguiente slot canónico ---
$sig = DisponibilidadEngine::siguienteSlotTras($p4, [$a, $b], 'conocerse', 1, 14, 'lug_cafeteria');
ok($sig !== null, 'siguienteSlotTras devuelve slot');
ok(DisponibilidadEngine::franjaValida($p4, [$a, $b], (int) $sig['dia'], (int) $sig['hora'], 'lug_cafeteria'), 'slot canónico es válido');

// --- no pisa planes existentes ---
$p5 = partidaOficina();
$p5['encuentros'][] = [
    'id' => 'enc_bloqueo',
    'tipo' => 'conocerse',
    'participantes' => [$a],
    'lugar' => 'lug_cafeteria',
    'hora' => (int) $sig['hora'],
    'dia' => (int) $sig['dia'],
    'duracion_horas' => 1,
    'estado' => 'programado',
    'reserva_agenda' => ['tipo' => 'encuentro'],
];
$sig2 = DisponibilidadEngine::siguienteSlotTras($p5, [$a, $b], 'conocerse', 1, 14, 'lug_cafeteria');
ok($sig2 !== null, 'hay slot tras encuentro programado');
ok(
    (int) ($sig2['dia'] ?? -1) * 24 + (int) ($sig2['hora'] ?? -1) !== (int) $sig['dia'] * 24 + (int) $sig['hora'],
    'contrapropuesta no reutiliza hora con encuentro existente'
);
ok(!EncuentroEngine::hayConflictoHorario($p5, [$a, $b], (int) $sig2['dia'], (int) $sig2['hora']), 'slot alternativo sin conflicto');

// --- hint UI horarios ---
$slots = DisponibilidadEngine::slotsCompatibles($p4, [$a, $b], 'conocerse', 1, 14, 7, 24, null, 'lug_cafeteria');
ok(!empty($slots['hint_ui']) && str_contains((string) $slots['hint_ui'], 'Primera hora compatible'), 'hint_ui cuando hora pedida bloqueada');

echo "\nTodas las comprobaciones de rechazo_copy_coherente pasaron.\n";
