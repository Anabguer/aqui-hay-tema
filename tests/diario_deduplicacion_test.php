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

echo $failures === 0 ? "OK diario_deduplicacion\n" : "FAIL diario_deduplicacion ({$failures})\n";
exit($failures > 0 ? 1 : 0);
