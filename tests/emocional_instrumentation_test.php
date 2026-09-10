<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AcontecimientoDiario;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EmocionalNarrativa;
use AquiHayTema\Engine\EmotionalInstrumentation;
use AquiHayTema\Engine\EmotionalStateService;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\VisualPackStore;

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

function eq($a, $b, string $m): void
{
    ok($a === $b, $m . " (esperado: " . var_export($b, true) . ", obtenido: " . var_export($a, true) . ")");
}

DomainBootstrap::boot();
$cal = CalibracionConfig::load($root);
$service = new PartidaService($root);

// ============================================================
// TEST 1: EmotionalInstrumentation::ensureRuntime
// ============================================================
echo "--- TEST 1: ensureRuntime ---\n";
$partida = $service->nuevaPartida('playtest_01', 'inst-test-1');
EmotionalInstrumentation::ensureRuntime($partida);
ok(isset($partida['runtime']['emotional_playtest']), 'runtime emocional existe');
ok(is_array($partida['runtime']['emotional_playtest']['eventos']), 'eventos es array');
ok(is_array($partida['runtime']['emotional_playtest']['acumuladores']), 'acumuladores es array');
ok(is_array($partida['runtime']['emotional_playtest']['expiraciones']), 'expiraciones es array');

// ============================================================
// TEST 2: registrarCambio con perder_trabajo
// ============================================================
echo "--- TEST 2: registrarCambio (perder_trabajo) ---\n";
$partida2 = $service->nuevaPartida('playtest_01', 'inst-test-2');
$rid = null;
foreach ($partida2['residentes'] as $id => $res) {
    $oc = (string) ($res['runtime']['ocupacion'] ?? '');
    if ($oc !== '' && $oc !== 'desempleado' && $oc !== 'jubilado' && $oc !== 'ninguna') {
        $rid = (string) $id;
        break;
    }
}
ok($rid !== null, 'NPC empleado encontrado');

$antes = $partida2['residentes'][$rid]['runtime']['estado_emocional'];
$store = (new Catalog($root))->store();
$r = AcontecimientoDiario::ejecutar($partida2, 'perder_trabajo', [$rid], $store, $cal);
ok($r['ok'] ?? false, 'perder_trabajo ejecuta');

$eventos = EmotionalInstrumentation::obtenerEventos($partida2);
ok(count($eventos) >= 1, 'al menos 1 evento registrado');
$ultimo = $eventos[count($eventos) - 1];
eq($ultimo['residente_id'], $rid, 'residente_id correcto');
eq($ultimo['estado_nuevo'], 'triste', 'estado nuevo es triste');
eq($ultimo['origen'], 'perder_trabajo', 'origen es perder_trabajo');
ok(isset($ultimo['necesidades']), 'snapshot de necesidades presente');
ok(isset($ultimo['relaciones']), 'snapshot de relaciones presente');
ok(is_array($ultimo['rechazos_recientes']), 'rechazos_recientes es array');
ok(is_array($ultimo['acontecimientos_recientes']), 'acontecimientos_recientes es array');
ok(is_array($ultimo['planes_recientes']), 'planes_recientes es array');

// Verificar acumulador
$acum = EmotionalInstrumentation::obtenerAcumuladores($partida2);
ok(isset($acum[$rid]), 'acumulador para residente existe');
eq($acum[$rid]['cambios_totales'], 1, 'cambios_totales = 1');

// ============================================================
// TEST 3: registrarCambio con encuentro (via EmotionalEventBridge)
// ============================================================
echo "--- TEST 3: EmotionalEventBridge + instrumentación ---\n";
$partida3 = $service->nuevaPartida('playtest_01', 'inst-test-3');
$rids = array_keys($partida3['residentes']);
if (count($rids) >= 2) {
    $rA = $rids[0];
    $rB = $rids[1];
    // Forzar un encuentro con resultado muy_bien
    $encuentro = [
        'id' => 'enc_inst_test',
        'participantes' => [$rA, $rB],
        'lugar' => 'lug_cafeteria',
        'tipo' => 'quedar',
    ];
    $resultado = [
        'resultado_global' => 'muy_bien',
        'por_participante' => [
            $rA => ['resultado' => 'muy_bien'],
            $rB => ['resultado' => 'muy_bien'],
        ],
    ];
    // Simular evento ENCUENTRO_TERMINADO
    $partida3['encuentros'] = [$encuentro];
    $antesA = $partida3['residentes'][$rA]['runtime']['estado_emocional'];
    $antesB = $partida3['residentes'][$rB]['runtime']['estado_emocional'];
    
    // Usar DomainEventDispatcher para dispatch correcto
    \AquiHayTema\Engine\DomainEventDispatcher::emit($partida3, \AquiHayTema\Engine\DomainEvents::ENCUENTRO_TERMINADO, [
        'encuentro' => $encuentro,
        'resultado' => $resultado,
        'actores' => [$rA, $rB],
    ]);
    
    $eventos3 = EmotionalInstrumentation::obtenerEventos($partida3);
    $encuentros = array_filter($eventos3, static fn($e) => ($e['origen'] ?? '') === 'encuentro' || ($e['origen'] ?? '') === 'hobby_recuperacion');
    $encontrado = false;
    foreach ($encuentros as $ev) {
        if ($ev['residente_id'] === $rA || $ev['residente_id'] === $rB) {
            $encontrado = true;
            ok(isset($ev['contexto']['encuentro_id']), 'contexto incluye encuentro_id');
            ok(isset($ev['contexto']['resultado_experiencia']), 'contexto incluye resultado_experiencia');
            ok(isset($ev['contexto']['participante_contra']), 'contexto incluye participante_contra');
            break;
        }
    }
    if (!$encontrado) {
        ok(false, 'evento de encuentro registrado');
    }
} else {
    echo "SKIP: no hay suficientes residentes para test de encuentro\n";
}

// ============================================================
// TEST 4: expiración de emoción
// ============================================================
echo "--- TEST 4: registrarExpiracion ---\n";
$partida4 = $service->nuevaPartida('playtest_01', 'inst-test-4');
$rid4 = null;
foreach ($partida4['residentes'] as $id => $res) {
    $rid4 = (string) $id;
    break;
}
// Forzar emoción temporal
$root2 = dirname(__DIR__, 2);
$emoSvc = new EmotionalStateService(new VisualPackStore($root), $store, null);
$reloj = $partida4['reloj'] ?? [];
$hasta = EstadoEmocional::hastaDesdeDuracion($reloj, 1);
$emoSvc->aplicar($partida4, $rid4, EstadoEmocional::ALEGRE, 'test_instrumentacion', null, $hasta, [], 1);

// Avanzar reloj para que expire
$partida4['reloj']['hora_actual'] = ($partida4['reloj']['hora_actual'] ?? 0) + 2;
$antes4 = $partida4['residentes'][$rid4]['runtime']['estado_emocional'];
$emoSvc->expirarVencidos($partida4);

$expiraciones = EmotionalInstrumentation::obtenerExpiraciones($partida4);
ok(count($expiraciones) >= 1, 'al menos 1 expiración registrada');
$ultimaExp = $expiraciones[count($expiraciones) - 1];
eq($ultimaExp['residente_id'], $rid4, 'expiración: residente_id correcto');
eq($ultimaExp['estado_anterior'], 'alegre', 'expiración: estado anterior alegre');
ok($ultimaExp['origen_original'] === 'test_instrumentacion', 'expiración: origen original registrado');

// ============================================================
// TEST 5: estadísticas consolidadas
// ============================================================
echo "--- TEST 5: estadísticas ---\n";
$stats = EmotionalInstrumentation::estadisticas($partida4);
ok(isset($stats['total_cambios']), 'total_cambios existe');
ok(isset($stats['total_expiraciones']), 'total_expiraciones existe');
ok(isset($stats['por_origen']), 'por_origen existe');
ok(isset($stats['por_emocion_nueva']), 'por_emocion_nueva existe');
ok(isset($stats['por_residente']), 'por_residente existe');

// ============================================================
// TEST 6: no rompe nada existente
// ============================================================
echo "--- TEST 6: no rompe flujo existente ---\n";
$partida6 = $service->nuevaPartida('playtest_01', 'inst-test-6');
$antes6 = $partida6['residentes']['per_p001']['runtime']['estado_emocional'];
$emoSvc6 = new EmotionalStateService(new VisualPackStore($root), $store, null);
$emoSvc6->aplicar($partida6, 'per_p001', EstadoEmocional::TRISTE, 'test_manual', null, null, [], null);
$despues6 = $partida6['residentes']['per_p001']['runtime']['estado_emocional'];
eq($despues6['id'], EstadoEmocional::TRISTE, 'cambio emocional funciona correctamente');

// Verificar que el evento emocional se registró en la instrumentación
$eventos6 = EmotionalInstrumentation::obtenerEventos($partida6);
$encontradoEmocion = false;
foreach ($eventos6 as $ev) {
    if (($ev['residente_id'] ?? '') === 'per_p001' && ($ev['estado_nuevo'] ?? '') === 'triste') {
        $encontradoEmocion = true;
        break;
    }
}
ok($encontradoEmocion, 'evento emocional registrado en instrumentación');

echo $failures === 0 ? "\nALL OK\n" : "\nFAILURES: $failures\n";
exit($failures > 0 ? 1 : 0);
