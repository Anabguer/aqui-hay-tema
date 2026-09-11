<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\BuzonPlayBridge;
use AquiHayTema\Engine\DiarioEngine;
use AquiHayTema\Engine\DiarioHitoEngine;
use AquiHayTema\Engine\DiarioNarrativaBridge;
use AquiHayTema\Engine\DiarioVista;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\DomainEventDispatcher;
use AquiHayTema\Engine\DomainEvents;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;

$root = dirname(__DIR__);
$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

DomainBootstrap::boot();
$service = new PartidaService($root);

// ============================================================
// CASO A: Un encuentro genera hito + emoción → sin duplicados equivalentes
// ============================================================
$pA = $service->nuevaPartida('juego_v1', 'dedup-encuentro');
$pA['features']['buzon_enabled'] = true;
$ids = array_keys($pA['residentes']);
$a = (string) $ids[0];
$b = (string) $ids[1];
$pA['reloj']['hora_actual'] = 15;

// Simular un encuentro terminado con resultado mal
$encId = 'enc_dedup_A';
$pA['encuentros'][] = [
    'id' => $encId,
    'tipo' => 'plan',
    'participantes' => [$a, $b],
    'resultado' => [
        'global' => 'mal',
        'por_participante' => [
            ['residente_id' => $a, 'resultado' => 'mal'],
            ['residente_id' => $b, 'resultado' => 'mal'],
        ],
        'emociones' => [],
        'conflicto' => null,
    ],
    'hora' => 15,
];

// Disparar el evento real ENCUENTRO_TERMINADO
DomainEventDispatcher::emit($pA, DomainEvents::ENCUENTRO_TERMINADO, [
    'encuentro' => $pA['encuentros'][0],
    'resultado' => $pA['encuentros'][0]['resultado'],
    'actores' => [$a, $b],
]);

// Simular entrada de estado_emocional del mismo encuentro (como si el motor emocional la creara)
$eventoEmocion = 'emocion:' . $a . ':encuentro:' . $encId;
$pA['diario'][] = [
    'id' => 'dia_emocion_test',
    'dia' => $pA['reloj']['dia_pueblo'],
    'tipo' => 'estado_emocional',
    'texto' => 'Mi encuentro no salió como esperaba.',
    'actores' => [$a],
    'origen' => [
        'evento_id' => $eventoEmocion,
        'tipo_evento' => 'estado_emocional',
        'es_narrativo' => true,
        'informacion_revelada' => [
            'origen_emocional' => 'encuentro',
        ],
        '_placeholder' => false,
    ],
    '_placeholder_contenido' => false,
];

// Simular espejo de cotilleo del mismo encuentro
$pA['diario'][] = [
    'id' => 'dia_cotilleo_test',
    'dia' => $pA['reloj']['dia_pueblo'],
    'tipo' => 'cotilleo',
    'texto' => 'A y B han pasado la tarde en la Cafetería.',
    'actores' => [$a, $b],
    'origen' => [
        'evento_id' => $encId,
        'tipo_evento' => 'encuentro_terminado',
        'es_narrativo' => false,
        '_placeholder' => false,
    ],
    '_placeholder_contenido' => false,
];

// Verificar deduplicación via DiarioVista
$vista = DiarioVista::listarParaResidente($pA, $a);
$entradasEncuentro = array_filter($vista, static function ($e) {
    $texto = strtolower((string) ($e['explicacion'] ?? ''));
    return str_contains($texto, 'encuentro') || str_contains($texto, 'tard') || str_contains($texto, 'cafetería');
});
ok(count($entradasEncuentro) <= 1, 'A. mismo encuentro → max 1 entrada narrativa en vista');

// ============================================================
// CASO B: Dos encuentros distintos el mismo día → ambos sobreviven
// ============================================================
$pB = $service->nuevaPartida('juego_v1', 'dedup-dosencuentros');
$pB['features']['buzon_enabled'] = true;
$idsB = array_keys($pB['residentes']);
$aB = (string) $idsB[0];
$bB = (string) $idsB[1];
$cB = null;
foreach ($idsB as $rid) {
    if ($rid !== $aB && $rid !== $bB) {
        $cB = (string) $rid;
        break;
    }
}
if ($cB === null) {
    $cB = $aB;
}

// Encuentro 1: A y B (mañana)
$pB['reloj']['hora_actual'] = 10;
$encId1 = 'enc_B1';
$pB['encuentros'][] = [
    'id' => $encId1,
    'tipo' => 'plan',
    'participantes' => [$aB, $bB],
    'resultado' => [
        'global' => 'mal',
        'por_participante' => [
            ['residente_id' => $aB, 'resultado' => 'mal'],
            ['residente_id' => $bB, 'resultado' => 'mal'],
        ],
    ],
    'hora' => 10,
];
DomainEventDispatcher::emit($pB, DomainEvents::ENCUENTRO_TERMINADO, [
    'encuentro' => $pB['encuentros'][0],
    'resultado' => $pB['encuentros'][0]['resultado'],
    'actores' => [$aB, $bB],
]);

// Encuentro 2: A y C (tarde, mismo día)
$pB['reloj']['hora_actual'] = 16;
$encId2 = 'enc_B2';
$pB['encuentros'][] = [
    'id' => $encId2,
    'tipo' => 'plan',
    'participantes' => [$aB, $cB],
    'resultado' => [
        'global' => 'mal',
        'por_participante' => [
            ['residente_id' => $aB, 'resultado' => 'mal'],
            ['residente_id' => $cB, 'resultado' => 'mal'],
        ],
    ],
    'hora' => 16,
];
DomainEventDispatcher::emit($pB, DomainEvents::ENCUENTRO_TERMINADO, [
    'encuentro' => $pB['encuentros'][1],
    'resultado' => $pB['encuentros'][1]['resultado'],
    'actores' => [$aB, $cB],
]);

// Verificar que AMBOS encuentros aparecen en diario_hito
$hitoB = DiarioEngine::listarPorResidente($pB, $aB);
$hitoEnc1 = false;
$hitoEnc2 = false;
foreach ($hitoB as $e) {
    if (($e['tipo'] ?? '') !== 'diario_hito') continue;
    $evId = (string) ($e['origen']['evento_id'] ?? '');
    if (str_contains($evId, $encId1)) {
        $hitoEnc1 = true;
    }
    if (str_contains($evId, $encId2)) {
        $hitoEnc2 = true;
    }
}
ok($hitoEnc1, 'B1. encuentro 1 sobrevive en diario_hito');
ok($hitoEnc2, 'B2. encuentro 2 sobrevive en diario_hito');

// ============================================================
// CASO C: Dos residentes tienen perspectivas propias del mismo encuentro
// ============================================================
$pC = $service->nuevaPartida('juego_v1', 'dedup-perspectivas');
$idsC = array_keys($pC['residentes']);
$aC = (string) $idsC[0];
$bC = (string) $idsC[1];

$pC['reloj']['hora_actual'] = 12;
$encIdC = 'enc_C1';
$pC['encuentros'][] = [
    'id' => $encIdC,
    'tipo' => 'plan',
    'participantes' => [$aC, $bC],
    'resultado' => [
        'global' => 'mal',
        'por_participante' => [
            ['residente_id' => $aC, 'resultado' => 'mal'],
            ['residente_id' => $bC, 'resultado' => 'muy_mal'],
        ],
    ],
    'hora' => 12,
];

RelacionBitacora::registrar($pC, RelacionBitacora::DISCUSION_FUERTE, [$aC, $bC]);

// Cada residente ve SU propia entrada, no la del otro
$entradasAC = DiarioEngine::listarPorResidente($pC, $aC);
$entradasBC = DiarioEngine::listarPorResidente($pC, $bC);

$hayA = false;
foreach ($entradasAC as $e) {
    if (in_array($aC, $e['actores'] ?? [], true) && ($e['tipo'] ?? '') === 'diario_hito') {
        $hayA = true;
        break;
    }
}
ok($hayA, 'C1. residente A tiene su propia entrada de diario_hito');

$hayB = false;
foreach ($entradasBC as $e) {
    if (in_array($bC, $e['actores'] ?? [], true) && ($e['tipo'] ?? '') === 'diario_hito') {
        $hayB = true;
        break;
    }
}
ok($hayB, 'C2. residente B tiene su propia entrada de diario_hito');

// Verificar que las entradas de A y B son independientes
$actoresA = [];
foreach ($entradasAC as $e) {
    if (($e['tipo'] ?? '') === 'diario_hito') {
        $actoresA[] = $e['actores'][0] ?? '';
    }
}
ok(in_array($aC, $actoresA, true), 'C3. entrada de A pertenece a A');

// ============================================================
// CASO D: ENC_1 vs ENC_10 — sin colisión por subcadena
// ============================================================
$pD = $service->nuevaPartida('juego_v1', 'dedup-enc1-enc10');
$idsD = array_keys($pD['residentes']);
$aD = (string) $idsD[0];
$bD = (string) $idsD[1];

// Encounter 1: A y B
$pD['reloj']['hora_actual'] = 10;
$pD['encuentros'][] = [
    'id' => 'enc_1',
    'tipo' => 'plan',
    'participantes' => [$aD, $bD],
    'resultado' => [
        'global' => 'mal',
        'por_participante' => [
            ['residente_id' => $aD, 'resultado' => 'mal'],
            ['residente_id' => $bD, 'resultado' => 'mal'],
        ],
    ],
    'hora' => 10,
];
DomainEventDispatcher::emit($pD, DomainEvents::ENCUENTRO_TERMINADO, [
    'encuentro' => $pD['encuentros'][0],
    'resultado' => $pD['encuentros'][0]['resultado'],
    'actores' => [$aD, $bD],
]);

// Encounter 10: A y B (mismo día, distinto ID que empieza por "1")
$pD['reloj']['hora_actual'] = 16;
$pD['encuentros'][] = [
    'id' => 'enc_10',
    'tipo' => 'plan',
    'participantes' => [$aD, $bD],
    'resultado' => [
        'global' => 'mal',
        'por_participante' => [
            ['residente_id' => $aD, 'resultado' => 'mal'],
            ['residente_id' => $bD, 'resultado' => 'mal'],
        ],
    ],
    'hora' => 16,
];
DomainEventDispatcher::emit($pD, DomainEvents::ENCUENTRO_TERMINADO, [
    'encuentro' => $pD['encuentros'][1],
    'resultado' => $pD['encuentros'][1]['resultado'],
    'actores' => [$aD, $bD],
]);

// Verificar que AMBOS hitos existen
$hitoD = DiarioEngine::listarPorResidente($pD, $aD);
$enc1 = false;
$enc10 = false;
foreach ($hitoD as $e) {
    if (($e['tipo'] ?? '') !== 'diario_hito') continue;
    $evId = (string) ($e['origen']['evento_id'] ?? '');
    if (str_contains($evId, 'enc_1:')) $enc1 = true;
    if (str_contains($evId, 'enc_10:')) $enc10 = true;
}
ok($enc1, 'D1. ENC_1 sobrevive en diario_hito');
ok($enc10, 'D2. ENC_10 sobrevive en diario_hito (no colisiona con ENC_1)');

// ============================================================
// CASO E: ENC_12 vs ENC_112 — sin colisión por subcadena
// ============================================================
$pE = $service->nuevaPartida('juego_v1', 'dedup-enc12-enc112');
$idsE = array_keys($pE['residentes']);
$aE = (string) $idsE[0];
$bE = (string) $idsE[1];

$pE['reloj']['hora_actual'] = 10;
$pE['encuentros'][] = [
    'id' => 'enc_12',
    'tipo' => 'plan',
    'participantes' => [$aE, $bE],
    'resultado' => [
        'global' => 'mal',
        'por_participante' => [
            ['residente_id' => $aE, 'resultado' => 'mal'],
            ['residente_id' => $bE, 'resultado' => 'mal'],
        ],
    ],
    'hora' => 10,
];
DomainEventDispatcher::emit($pE, DomainEvents::ENCUENTRO_TERMINADO, [
    'encuentro' => $pE['encuentros'][0],
    'resultado' => $pE['encuentros'][0]['resultado'],
    'actores' => [$aE, $bE],
]);

$pE['reloj']['hora_actual'] = 16;
$pE['encuentros'][] = [
    'id' => 'enc_112',
    'tipo' => 'plan',
    'participantes' => [$aE, $bE],
    'resultado' => [
        'global' => 'mal',
        'por_participante' => [
            ['residente_id' => $aE, 'resultado' => 'mal'],
            ['residente_id' => $bE, 'resultado' => 'mal'],
        ],
    ],
    'hora' => 16,
];
DomainEventDispatcher::emit($pE, DomainEvents::ENCUENTRO_TERMINADO, [
    'encuentro' => $pE['encuentros'][1],
    'resultado' => $pE['encuentros'][1]['resultado'],
    'actores' => [$aE, $bE],
]);

$hitoE = DiarioEngine::listarPorResidente($pE, $aE);
$enc12 = false;
$enc112 = false;
foreach ($hitoE as $e) {
    if (($e['tipo'] ?? '') !== 'diario_hito') continue;
    $evId = (string) ($e['origen']['evento_id'] ?? '');
    if (str_contains($evId, 'enc_12:')) $enc12 = true;
    if (str_contains($evId, 'enc_112:')) $enc112 = true;
}
ok($enc12, 'E1. ENC_12 sobrevive en diario_hito');
ok($enc112, 'E2. ENC_112 sobrevive en diario_hito (no colisiona con ENC_12)');

// ============================================================
// CASO F: Dos encuentros mismo residente mismo día — ambos sobreviven
// ============================================================
$pF = $service->nuevaPartida('juego_v1', 'dedup-mismodía');
$pF['features']['buzon_enabled'] = true;
$pF['features']['diario_enabled'] = true;
$idsF = array_keys($pF['residentes']);
$aF = (string) $idsF[0];
$bF = (string) $idsF[1];
$cF = null;
foreach ($idsF as $rid) {
    if ($rid !== $aF && $rid !== $bF) {
        $cF = (string) $rid;
        break;
    }
}
if ($cF === null) {
    $cF = $aF;
}

// Simular hito de ENC_A (como lo crearía DiarioHitoEngine)
$eventoIdHitoA = 'diario_hito:encuentro:enc_F_A:' . $aF;
$pF['diario'][] = [
    'id' => 'dia_hito_enc_F_A_' . $aF,
    'dia' => $pF['reloj']['dia_pueblo'],
    'tipo' => 'diario_hito',
    'texto' => 'El plan con ' . $bF . ' se torció.',
    'actores' => [$aF],
    'ts_juego' => ['dia' => $pF['reloj']['dia_pueblo'], 'hora' => 10],
    'origen' => [
        'evento_id' => $eventoIdHitoA,
        'tipo_evento' => 'encuentro_terminado',
        'es_narrativo' => true,
        '_placeholder' => false,
    ],
    '_placeholder_contenido' => false,
];

// Simular emoción de ENC_B (causada por encuentro distinto, con encuentro_id propagado)
$pF['diario'][] = [
    'id' => 'dia_emocion_enc_F_B_' . $aF,
    'dia' => $pF['reloj']['dia_pueblo'],
    'tipo' => 'estado_emocional',
    'texto' => 'El encuentro con ' . $cF . ' me dejó hecha polva.',
    'actores' => [$aF],
    'origen' => [
        'evento_id' => 'emocion:' . $aF . ':encuentro:' . $pF['reloj']['dia_pueblo'],
        'tipo_evento' => 'estado_emocional',
        'es_narrativo' => true,
        'informacion_revelada' => [
            'origen_emocional' => 'encuentro',
            'estado' => 'triste',
            'encuentro_id' => 'enc_F_B',
        ],
        '_placeholder' => false,
    ],
    '_placeholder_contenido' => false,
];

// Verificar raw: hito de ENC_A existe en diario
$rawF = DiarioEngine::listarPorResidente($pF, $aF);
$hayHitoA = false;
foreach ($rawF as $e) {
    if (($e['tipo'] ?? '') !== 'diario_hito') continue;
    $evId = (string) ($e['origen']['evento_id'] ?? '');
    if (str_contains($evId, 'enc_F_A:')) $hayHitoA = true;
}
ok($hayHitoA, 'F1. hito ENC_A existe en diario para residente A');

// Verificar raw: emoción de ENC_B existe en diario
$hayEmocionBRaw = false;
foreach ($rawF as $e) {
    if (($e['tipo'] ?? '') !== 'estado_emocional') continue;
    $info = is_array($e['origen']['informacion_revelada'] ?? null) ? $e['origen']['informacion_revelada'] : [];
    if (($info['encuentro_id'] ?? '') === 'enc_F_B') {
        $hayEmocionBRaw = true;
        break;
    }
}
ok($hayEmocionBRaw, 'F2. emoción de ENC_B existe en diario para residente A');

// Verificar Vista: emoción de ENC_B NO se oculta por hito de ENC_A
// (encuentro_id: enc_F_B ≠ enc_F_A, así que no colisionan)
$vistaF = DiarioVista::listarParaResidente($pF, $aF);
$hayEmocionBVista = false;
foreach ($vistaF as $e) {
    $titulo = (string) ($e['titulo'] ?? '');
    $explicacion = (string) ($e['explicacion'] ?? '');
    if (str_contains($explicacion, 'hecha polva') || $titulo === 'Mi ánimo cambió' && str_contains($explicacion, $cF)) {
        $hayEmocionBVista = true;
        break;
    }
}
ok($hayEmocionBVista, 'F3. emoción de ENC_B visible en vista (encuentro_id distinto al hito de ENC_A)');

// Verificar Vista: emoción del MISMO encuentro que el hito SÍ se oculta
$pF['diario'][] = [
    'id' => 'dia_emocion_enc_F_A_' . $aF,
    'dia' => $pF['reloj']['dia_pueblo'],
    'tipo' => 'estado_emocional',
    'texto' => 'El encuentro con ' . $bF . ' me dejó triste.',
    'actores' => [$aF],
    'origen' => [
        'evento_id' => 'emocion:' . $aF . ':encuentro:1',
        'tipo_evento' => 'estado_emocional',
        'es_narrativo' => true,
        'informacion_revelada' => [
            'origen_emocional' => 'encuentro',
            'estado' => 'triste',
            'encuentro_id' => 'enc_F_A',
        ],
        '_placeholder' => false,
    ],
    '_placeholder_contenido' => false,
];
$vistaF2 = DiarioVista::listarParaResidente($pF, $aF);
$hayEmocionAVista = false;
foreach ($vistaF2 as $e) {
    $explicacion = (string) ($e['explicacion'] ?? '');
    if (str_contains($explicacion, 'me dejó triste')) {
        $hayEmocionAVista = true;
        break;
    }
}
ok(!$hayEmocionAVista, 'F4. emoción de ENC_A oculta en vista (encuentro_id = enc_F_A = hito de ENC_A)');

echo $failures === 0 ? "OK diario_deduplicacion\n" : "FAIL diario_deduplicacion ({$failures})\n";
exit($failures > 0 ? 1 : 0);
