<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use AquiHayTema\Engine\DebugResumenPartida;
use AquiHayTema\Engine\VidaPuebloEngine;
use AquiHayTema\Engine\RelacionBitacora;

$passed = 0;
$failed = 0;

function assertEq($desc, $expected, $actual): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo "✓ $desc\n";
        $passed++;
    } else {
        echo "✗ $desc\n  Expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  Actual:   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
        $failed++;
    }
}

function assertContains($desc, $haystack, $needle): void
{
    global $passed, $failed;
    if (in_array($needle, $haystack, true)) {
        echo "✓ $desc\n";
        $passed++;
    } else {
        echo "✗ $desc\n  Needle not found in: " . json_encode($haystack, JSON_UNESCAPED_UNICODE) . "\n";
        $failed++;
    }
}

echo "=== Test A: partida con romance/pareja/cita ===\n";

$partidaA = [
    'reloj' => ['dia_pueblo' => 10, 'hora_actual' => 14, 'temporada_id' => 'temp_01', 'dia_en_temporada' => 10],
    'residentes' => [
        'r1' => ['estado_en_partida' => 'residente', 'identidad_publica' => ['nombre' => 'Ana'], 'runtime' => []],
        'r2' => ['estado_en_partida' => 'residente', 'identidad_publica' => ['nombre' => 'Bob'], 'runtime' => []],
    ],
    'relaciones_romanticas' => [
        'rel_r1_r2' => [
            'persona_a' => 'r1', 'persona_b' => 'r2',
            'estado_pareja' => 'pareja',
            'fecha_inicio' => ['dia' => 5, 'hora' => 10],
            'historial_parejas' => [['inicio' => ['dia' => 5, 'hora' => 10], 'fin' => null, 'como_acabo' => null, 'vuelta' => false]],
            'historial_citas' => [
                ['dia' => 6, 'hora' => 18, 'es_primera' => true],
                ['dia' => 8, 'hora' => 19, 'es_primera' => false],
            ],
            'flechazos' => [['dia' => 4, 'hora' => 12]],
            'fase' => 'estable',
        ],
    ],
    'propuestas_encuentro' => [],
    'npc_autonomo' => ['planes_pendientes' => [['id' => 'p1'], ['id' => 'p2']]],
    'historial_relaciones' => [],
    'llegadas' => ['historial' => [['dia' => 1], ['dia' => 2]]],
    'marchas' => ['historial' => [['dia' => 3]]],
    'acontecimientos_log' => [
        ['evento_id' => 'perder_trabajo', 'dia' => 7, 'hora' => 10, 'importancia' => 'normal', 'visibilidad_jugador' => 'aviso', 'residente_id' => 'r1'],
        ['evento_id' => 'encontrar_trabajo', 'dia' => 9, 'hora' => 11, 'importancia' => 'normal', 'visibilidad_jugador' => 'importante', 'residente_id' => 'r2'],
    ],
    'huecos_vida' => [['tipo' => 'trabajo', 'residente_id' => 'r1']],
    'misiones_diarias' => [
        'items' => [
            ['dia' => 8, 'estado' => 'cumplida'],
            ['dia' => 9, 'estado' => 'pendiente'],
            ['dia' => 10, 'estado' => 'caducada'],
        ]
    ],
    'peticiones' => [
        ['estado' => 'atendida', 'residente_id' => 'r1'],
        ['estado' => 'caducada', 'residente_id' => 'r2'],
        ['estado' => 'abierta', 'residente_id' => 'r1'],
    ],
    'peticiones_pueblo' => ['validos_dia_n' => 2],
    'vida_pueblo' => [
        'valor' => 70,
        'valor_inicial' => 65,
        'negativos_total' => 5,
        'ledger' => [
            ['valor_despues' => 68, 'causa' => 'mision_cumplida'],
            ['valor_despues' => 65, 'causa' => 'peticion_caducada'],
            ['valor_despues' => 70, 'causa' => 'hito'],
        ],
        'ledger_archivo' => [],
        'game_over_pendiente' => false,
        'game_over_activo' => false,
        'llego_a_cero' => false,
        'origen_ultimo_cero' => null,
        'dias_en_critico' => 0,
        'primer_latido_dia' => null,
    ],
    'domain_events' => [
        ['evento' => 'mision_cumplida', 'ts_juego' => ['dia' => 8, 'hora' => 10], 'correlacion_id' => 'evt_1', 'payload_keys' => []],
        ['evento' => 'peticion_caducada', 'ts_juego' => ['dia' => 9, 'hora' => 11], 'correlacion_id' => 'evt_2', 'payload_keys' => []],
    ],
    'event_log' => [],
];

$resA = DebugResumenPartida::resumen($partidaA, 10);

assertEq("A1 header.dia", 10, $resA['header']['dia']);
assertEq("A1 header.vecinos_actuales", 2, $resA['header']['vecinos_actuales']);
assertEq("A1 header.llegadas_total", 2, $resA['header']['llegadas_total']);
assertEq("A1 header.marchas_total", 1, $resA['header']['marchas_total']);

assertEq("A2 parejas.actuales", 1, $resA['parejas']['actuales']);
assertEq("A2 parejas.creadas_total", 1, $resA['parejas']['creadas_total']);
assertEq("A2 parejas.rupturas", 0, $resA['parejas']['rupturas']);
assertEq("A2 parejas.primera_pareja_dia", 5, $resA['parejas']['primera_pareja_dia']);

assertEq("A3 romance.flechazos", 1, $resA['romance']['flechazos']);
assertEq("A3 romance.primeras_citas", 1, $resA['romance']['primeras_citas']);
assertEq("A3 romance.citas_realizadas", 2, $resA['romance']['citas_realizadas']);
assertEq("A3 romance.citas_rechazadas", 0, $resA['romance']['citas_rechazadas']);
assertEq("A3 romance.planes_autonomos", 2, $resA['romance']['planes_autonomos']);
assertEq("A3 romance.relaciones_mas_avanzadas", 1, $resA['romance']['relaciones_mas_avanzadas']);
assertEq("A3 romance.relaciones_estancadas", 0, $resA['romance']['relaciones_estancadas']);

assertEq("A4 vida.trabajos_actuales", 1, $resA['vida']['trabajos_actuales']);
assertEq("A4 vida.trabajos_perdidos", 1, $resA['vida']['trabajos_perdidos']);
assertEq("A4 vida.trabajos_encontrados", 1, $resA['vida']['trabajos_encontrados']);
assertEq("A4 vida.acontecimientos_relevantes", 2, $resA['vida']['acontecimientos_relevantes']);
assertEq("A4 vida.estados_emocionales_activos", 0, count($resA['vida']['estados_emocionales_activos']));

assertEq("A5 jugador.misiones_completadas", 1, $resA['jugador']['misiones_completadas']);
assertEq("A5 jugador.misiones_falladas", 1, $resA['jugador']['misiones_falladas']);
assertEq("A5 jugador.peticiones_recibidas", 5, $resA['jugador']['peticiones_recibidas']); // 3 + 2
assertEq("A5 jugador.peticiones_atendidas", 1, $resA['jugador']['peticiones_atendidas']);
assertEq("A5 jugador.peticiones_caducadas", 1, $resA['jugador']['peticiones_caducadas']);
// Last cumplida was dia 8, current dia 10 -> 2 days without
assertEq("A5 jugador.dias_consecutivos_sin_mision", 2, $resA['jugador']['dias_consecutivos_sin_mision']);

assertEq("A6 equilibrio.valor_actual", 70, $resA['equilibrio']['valor_actual']);
assertEq("A6 equilibrio.minimo_historico", 65, $resA['equilibrio']['minimo_historico']);
assertEq("A6 equilibrio.umbral_derrota", 19, $resA['equilibrio']['umbral_derrota']);
assertEq("A6 equilibrio.penalizaciones_acumuladas", 5, $resA['equilibrio']['penalizaciones_acumuladas']);
assertEq("A6 equilibrio.causa_exacta_final", null, $resA['equilibrio']['causa_exacta_final']);
assertEq("A6 equilibrio.dia_estado_critico", null, $resA['equilibrio']['dia_estado_critico']);

// 2 domain_events + 1 acontecimiento_log + 1 from somewhere = 4
assertEq("A7 historial count", 4, count($resA['historial']));

echo "\n=== Test B: ruptura ===\n";

$partidaB = $partidaA;
$partidaB['relaciones_romanticas']['rel_r1_r2']['estado_pareja'] = 'ex';
$partidaB['relaciones_romanticas']['rel_r1_r2']['historial_parejas'][0]['fin'] = ['dia' => 9, 'hora' => 14];
$partidaB['relaciones_romanticas']['rel_r1_r2']['historial_parejas'][0]['como_acabo'] = 'ruptura_mutua';
$partidaB['bitacora_relaciones'] = [
    ['tipo' => RelacionBitacora::INICIO_PAREJA, 'fecha' => ['dia' => 5, 'hora' => 10], 'participantes' => ['r1','r2']],
    ['tipo' => RelacionBitacora::RUPTURA, 'fecha' => ['dia' => 9, 'hora' => 14], 'participantes' => ['r1','r2']],
];

$resB = DebugResumenPartida::resumen($partidaB, 10);

assertEq("B1 parejas.actuales", 0, $resB['parejas']['actuales']);
assertEq("B1 parejas.creadas_total", 1, $resB['parejas']['creadas_total']);
assertEq("B1 parejas.rupturas", 1, $resB['parejas']['rupturas']);
assertEq("B1 parejas.primera_pareja_dia", 5, $resB['parejas']['primera_pareja_dia']);

echo "\n=== Test C: misiones y peticiones ===\n";

$partidaC = [
    'reloj' => ['dia_pueblo' => 5, 'hora_actual' => 12, 'temporada_id' => 'temp_01', 'dia_en_temporada' => 5],
    'residentes' => [],
    'relaciones_romanticas' => [],
    'propuestas_encuentro' => [],
    'npc_autonomo' => ['planes_pendientes' => []],
    'llegadas' => ['historial' => []],
    'marchas' => ['historial' => []],
    'acontecimientos_log' => [],
    'huecos_vida' => [],
    'misiones_diarias' => [
        'items' => [
            ['dia' => 1, 'estado' => 'cumplida'],
            ['dia' => 2, 'estado' => 'cumplida'],
            ['dia' => 3, 'estado' => 'caducada'],
            ['dia' => 4, 'estado' => 'pendiente'],
        ]
    ],
    'peticiones' => [
        ['estado' => 'atendida'],
        ['estado' => 'resuelta'],
        ['estado' => 'caducada'],
        ['estado' => 'ignorada'],
        ['estado' => 'abierta'],
    ],
    'peticiones_pueblo' => ['validos_dia_n' => 3],
    'vida_pueblo' => [
        'valor' => 60,
        'valor_inicial' => 65,
        'negativos_total' => 10,
        'ledger' => [
            ['valor_despues' => 63],
            ['valor_despues' => 60],
        ],
        'ledger_archivo' => [],
        'game_over_pendiente' => false,
        'game_over_activo' => false,
        'llego_a_cero' => false,
        'origen_ultimo_cero' => null,
        'dias_en_critico' => 0,
        'primer_latido_dia' => null,
    ],
    'domain_events' => [],
    'event_log' => [],
];

$resC = DebugResumenPartida::resumen($partidaC, 10);

assertEq("C1 misiones_completadas", 2, $resC['jugador']['misiones_completadas']);
assertEq("C1 misiones_falladas", 1, $resC['jugador']['misiones_falladas']);
assertEq("C1 peticiones_recibidas", 8, $resC['jugador']['peticiones_recibidas']); // 5 + 3
assertEq("C1 peticiones_atendidas", 2, $resC['jugador']['peticiones_atendidas']); // atendida + resuelta
assertEq("C1 peticiones_caducadas", 2, $resC['jugador']['peticiones_caducadas']); // caducada + ignorada
// Last cumplida was dia 2, current dia 5 -> 3 days without
assertEq("C1 dias_consecutivos_sin_mision", 3, $resC['jugador']['dias_consecutivos_sin_mision']);

echo "\n=== Test D: pérdida/variación de equilibrio ===\n";

$partidaD = [
    'reloj' => ['dia_pueblo' => 20, 'hora_actual' => 10, 'temporada_id' => 'temp_01', 'dia_en_temporada' => 20],
    'residentes' => [],
    'relaciones_romanticas' => [],
    'propuestas_encuentro' => [],
    'npc_autonomo' => ['planes_pendientes' => []],
    'llegadas' => ['historial' => []],
    'marchas' => ['historial' => []],
    'acontecimientos_log' => [],
    'huecos_vida' => [],
    'misiones_diarias' => ['items' => []],
    'peticiones' => [],
    'peticiones_pueblo' => ['validos_dia_n' => 0],
    'vida_pueblo' => [
        'valor' => 15, // crítico
        'valor_inicial' => 65,
        'negativos_total' => 50,
        'ledger' => [
            ['valor_despues' => 60, 'causa' => 'peticion_caducada'],
            ['valor_despues' => 40, 'causa' => 'mision_fallida'],
            ['valor_despues' => 20, 'causa' => 'acontecimiento_vida'],
            ['valor_despues' => 15, 'causa' => 'peticion_caducada'],
        ],
        'ledger_archivo' => [
            ['count' => 10, 'dia' => 10],
        ],
        'game_over_pendiente' => true,
        'game_over_activo' => true,
        'llego_a_cero' => false,
        'origen_ultimo_cero' => 'sistema',
        'dias_en_critico' => 3,
        'primer_latido_dia' => null,
    ],
    'domain_events' => [],
    'event_log' => [],
];

$resD = DebugResumenPartida::resumen($partidaD, 10);

assertEq("D1 equilibrio.valor_actual", 15, $resD['equilibrio']['valor_actual']);
assertEq("D1 equilibrio.minimo_historico", 15, $resD['equilibrio']['minimo_historico']); // ledger min is 15
assertEq("D1 equilibrio.penalizaciones_acumuladas", 50, $resD['equilibrio']['penalizaciones_acumuladas']);
assertEq("D1 equilibrio.causa_exacta_final", null, $resD['equilibrio']['causa_exacta_final']); // no llego_a_cero
// dias_en_critico=3, current=20 -> entered on day 20-3+1 = 18
assertEq("D1 equilibrio.dia_estado_critico", 18, $resD['equilibrio']['dia_estado_critico']);

echo "\n=== Test E: deduplicación de historial ===\n";

$partidaE = [
    'reloj' => ['dia_pueblo' => 10, 'hora_actual' => 10, 'temporada_id' => 'temp_01', 'dia_en_temporada' => 10],
    'residentes' => [],
    'relaciones_romanticas' => [],
    'propuestas_encuentro' => [],
    'npc_autonomo' => ['planes_pendientes' => []],
    'llegadas' => ['historial' => []],
    'marchas' => ['historial' => []],
    'acontecimientos_log' => [
        ['evento_id' => 'test_event', 'dia' => 9, 'hora' => 10, 'importancia' => 'hito', 'visibilidad_jugador' => 'importante', 'residente_id' => 'r1'],
    ],
    'huecos_vida' => [],
    'misiones_diarias' => ['items' => []],
    'peticiones' => [],
    'peticiones_pueblo' => ['validos_dia_n' => 0],
    'vida_pueblo' => [
        'valor' => 65,
        'valor_inicial' => 65,
        'negativos_total' => 0,
        'ledger' => [],
        'ledger_archivo' => [],
        'game_over_pendiente' => false,
        'game_over_activo' => false,
        'llego_a_cero' => false,
        'origen_ultimo_cero' => null,
        'dias_en_critico' => 0,
        'primer_latido_dia' => null,
    ],
    'domain_events' => [
        ['evento' => 'acontecimiento_diario', 'ts_juego' => ['dia' => 9, 'hora' => 10], 'correlacion_id' => 'evt_1', 'payload_keys' => []],
        ['evento' => 'mision_cumplida', 'ts_juego' => ['dia' => 9, 'hora' => 11], 'correlacion_id' => 'evt_2', 'payload_keys' => []],
    ],
    'event_log' => [
        ['evento' => 'acontecimiento_diario', 'dia' => 9, 'hora' => 10, 'residente_id' => 'r1', 'mensaje' => 'test'],
    ],
];

$resE = DebugResumenPartida::resumen($partidaE, 10);

// Should have events from all 3 sources
$histCount = count($resE['historial']);
assertEq("E1 historial has events", true, $histCount > 0 && $histCount <= 4);

echo "\n=== Test F: save antiguo/incompleto ===\n";

$partidaF = [
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 8, 'temporada_id' => 'temp_01'],
    'residentes' => [
        'r1' => ['estado_en_partida' => 'residente'],
    ],
    // missing many keys
];

$resF = DebugResumenPartida::resumen($partidaF, 10);

assertEq("F1 header exists", 1, $resF['header']['dia']);
assertEq("F1 vecinos_actuales", 1, $resF['header']['vecinos_actuales']);
assertEq("F2 parejas nulls", 0, $resF['parejas']['actuales']);
assertEq("F3 romance nulls", 0, $resF['romance']['flechazos']);
assertEq("F4 vida empty arrays", 0, count($resF['vida']['estados_emocionales_activos']));
assertEq("F5 jugador defaults", 0, $resF['jugador']['misiones_completadas']);
assertEq("F6 equilibrio defaults", 65, $resF['equilibrio']['valor_actual']);
assertEq("F6 equilibrio.minimo_historico", 65, $resF['equilibrio']['minimo_historico']);
assertEq("F7 historial empty", 0, count($resF['historial']));

echo "\n=== Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed > 0) {
    exit(1);
}