<?php
declare(strict_types=1);

// ROMANCE_CIERRE ?? DETERMINISMO transversal (R2???R8).
//
// La MISMA secuencia de motor con la misma seed produce EXACTAMENTE el mismo
// estado (hash JSON id??ntico). Y una seed distinta diverge (sanidad).

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\IniciativaPareja;
use AquiHayTema\Engine\IniciativaRomantica;
use AquiHayTema\Engine\MemoriaEventos;
use AquiHayTema\Engine\ParejaEngine;
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
$GLOBALS['__root'] = $root;
DomainBootstrap::boot();

const SA = 'ana';
const SB = 'bruno';

/** Arco COMPLETO determinista: cita???declaraci??n???pareja???cita pareja???crisis???reparaci??n???ruptura. */
function arco(string $seed, int $state): array
{
    $root = dirname(__DIR__);
    $cal = CalibracionConfig::load($root);
    $cal['romance_autonomo'] = array_merge($cal['romance_autonomo'] ?? [], [
        'declaracion_activa' => true,
        'pareja_activa' => true,
        'vida_pareja_activa' => true,
        'crisis_activa' => true,
        'ruptura_activa' => true,
        'vuelta_activa' => true,
        'max_hitos_por_dia' => 8,
        'crisis' => ['probabilidad' => 1.0, 'causas_minimas' => 1],
        'reparacion' => ['gap_intentos_horas' => 1],
        'ruptura_politica' => ['dias_crisis_sin_salida' => 99, 'factor_por_fallo' => 0.0],
    ]);
    $cal['voluntad']['p_min'] = 1.0;
    $cal['voluntad']['p_max'] = 1.0;

    $p = [
        'reloj' => ['dia_pueblo' => 10, 'hora_actual' => 9],
        'rng' => ['seed' => $seed, 'state' => $state],
        'meta' => ['seed' => $seed],
        'residentes' => [
            SA => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            SB => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => [
            'lugares_desbloqueados' => ['lug_cafeteria'],
            'intervenciones_organizadas_max_dia' => 0,
        ],
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
        RelacionEngine::ajustarSocialHacia($p, SA, SB, 10);
        RelacionEngine::ajustarSocialHacia($p, SB, SA, 10);
    }
    RelacionEngine::setRomanceHacia($p, SA, SB, 40);
    RelacionEngine::setRomanceHacia($p, SB, SA, 40);

    // 1) primera cita + cita resueltas bien ??? marcador declaraci??n
    resolverEncuentroSembrado($p, 'pc', 'primera_cita', 10, 12);
    RelacionBitacora::registrar($p, RelacionBitacora::PRIMERA_CITA, [SA, SB]);
    resolverEncuentroSembrado($p, 'c1', 'cita', 11, 12);
    MemoriaEventos::registrar($p, 'romance', [SA, SB], null, 'cita', 'bien');
    IniciativaRomantica::registrarContinuidadPostCita($p, SA, SB, 'bien', $cal);
    $p['reloj'] = ['dia_pueblo' => 13, 'hora_actual' => 12];
    IniciativaRomantica::procesarContinuidad($p, $cal); // declaraci??n aceptada (p=1)

    // 2) cita de pareja aut??noma en marcha ??? resolver ??? nuevo marcador
    resolverEncuentroSembrado($p, 'cp', 'cita', 14, 12);
    IniciativaRomantica::registrarContinuidadPostCita($p, SA, SB, null, $cal);

    // 3) crisis causal forzada (conflicto+racha)
    RelacionEngine::upsertConflicto($p, SA, SB, 9, 'roce', 'test');
    MemoriaEventos::registrar($p, 'encuentro', [SA, SB], null, 'cita', 'mal');
    IniciativaPareja::evaluarAlCerrarDia($p, $cal);

    // 4) reparaci??n exitosa (conflicto decae + cita buena en crisis)
    MemoriaEventos::registrar($p, 'encuentro', [SA, SB], null, 'cita', 'muy_bien');
    $rel = RelacionEngine::obtenerEntre($p, SA, SB)['romance'];
    if (is_array($rel)) {
        $rel['ultimo_intento_reparacion'] = null;
        $rel['crisis_desde']['hora'] = max(0, ($rel['crisis_desde']['hora'] ?? 0) - 5);
        RelacionEngine::persistirRomance($p, $rel);
    }
    IniciativaPareja::evaluarAlCerrarDia($p, $cal);

    // 5) ruptura por golpe duro forzado
    MemoriaEventos::registrar($p, 'encuentro', [SA, SB], null, 'cita', 'muy_mal');
    $rel = RelacionEngine::obtenerEntre($p, SA, SB)['romance'];
    if (is_array($rel) && ($rel['estado_pareja'] ?? '') === ParejaEngine::PAREJA) {
        $rel['estabilidad_pareja']['valor'] = 10;
        RelacionEngine::persistirRomance($p, $rel);
        RelacionEngine::upsertConflicto($p, SA, SB, 9, 'roce', 'test');
        IniciativaPareja::evaluarAlCerrarDia($p, $cal);
    }
    return $p;
}

function resolverEncuentroSembrado(array &$p, string $id, string $tipo, int $dia, int $hora): void
{
    $p['encuentros'][] = [
        'id' => $id,
        'tipo' => $tipo,
        'intencion' => 'autonomo_npc',
        'participantes' => [SA, SB],
        'lugar' => 'lug_cafeteria',
        'dia' => $dia,
        'hora' => $hora,
        'duracion_horas' => 1,
        'duracion_minutos' => 60,
        'estado' => 'programado',
        'resultado' => null,
    ];
    $fin = $dia * 24 + $hora + 1;
    $p['reloj'] = ['dia_pueblo' => intdiv($fin, 24), 'hora_actual' => $fin % 24];
    EncuentroLifecycleResuelve($p);
}

function EncuentroLifecycleResuelve(array &$p): void
{
    $catalog = new \AquiHayTema\Engine\Catalog($GLOBALS['__root']);
    \AquiHayTema\Engine\EncuentroLifecycle::sincronizarConReloj($p, null, $catalog);
}

/**
 * Normaliza SOLO los sufijos volátiles de IDs únicos de infraestructura
 * (diario/buzón/domain_events usan azar de proceso para el sufijo hex:
 * comportamiento PREEXISTENTE en producción, ajeno al motor romántico).
 */
function normalizaIds($x)
{
    if (is_array($x)) {
        $out = [];
        foreach ($x as $k => $v) {
            $out[$k] = normalizaIds($v);
        }
        return $out;
    }
    if (is_string($x) && preg_match('/^[a-z]+_[0-9a-f]{8}$/', $x) === 1) {
        return '[ID]';
    }
    return $x;
}

$h1 = md5(json_encode(normalizaIds(arco('det-seed', 777))));
$h2 = md5(json_encode(normalizaIds(arco('det-seed', 777))));
ok($h1 === $h2, "D1 misma seed ??? hash id??ntico ($h1)");

$h3 = md5(json_encode(normalizaIds(arco('otra-seed', 778))));
ok($h3 !== $h1, 'D2 seed distinta ??? estado distinto (sanidad)');

echo $fail === 0 ? "\nOK romance_cierre_determinismo\n" : "\nFAIL romance_cierre_determinismo ($fail)\n";
exit($fail === 0 ? 0 : 1);
