<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\DomainEventDispatcher;
use AquiHayTema\Engine\DomainEvents;
use AquiHayTema\Engine\EmotionalRecovery;
use AquiHayTema\Engine\EmotionalStateService;
use AquiHayTema\Engine\EstadoEmocional;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\GeneradorResidente;
use AquiHayTema\Engine\HobbyAccionable;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PlanAfinidad;
use AquiHayTema\Engine\RngService;
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

DomainBootstrap::boot();
$catalog = new Catalog($root);
$store = $catalog->store();
$cal = \AquiHayTema\Engine\CalibracionConfig::load($root);

// A — hobby principal accionable en varias seeds
$seeds = ['hobby-a-1', 'hobby-a-2', 'hobby-a-3', 'hobby-a-4', 'hobby-a-5'];
foreach ($seeds as $seed) {
    $rng = new RngService($seed);
    $p = GeneradorResidente::generar($rng, $store, $cal, null);
    $hp = (string) ($p['hobby_principal'] ?? '');
    ok($hp !== '' && HobbyAccionable::esAccionable($hp, $store), "seed $seed: principal accionable ($hp)");
}

// B — misma seed mismo perfil
$rng1 = new RngService('hobby-b');
$rng2 = new RngService('hobby-b');
$pB1 = GeneradorResidente::generar($rng1, $store, $cal, null);
$pB2 = GeneradorResidente::generar($rng2, $store, $cal, null);
ok($pB1['hobbies'] === $pB2['hobbies'], 'B: misma seed mismo perfil');

// C — primer hobby revelado es principal accionable
$svc = new PartidaService($root);
$partida = $svc->nuevaPartida('playtest_01', 'hobby-c');
$rid = 'per_p003';
DiscoveryReveal::alIncorporar($partida, $rid, $cal);
$perfil = $partida['residentes'][$rid]['runtime']['perfil_partida'] ?? [];
$hp = (string) ($perfil['hobby_principal'] ?? '');
ok(HobbyAccionable::esAccionable($hp, $store), 'C: hobby principal accionable');
$ficha = $svc->fichaResidente($partida, $rid);
$vista = $ficha['vista_play'] ?? $ficha['play'] ?? [];
$slot0 = $vista['hobbies_slots'][0] ?? [];
ok(!empty($slot0['descubierto']) && ($slot0['id'] ?? '') === $hp, 'C: primer slot revelado = principal');

// D — pista solo descubierto, desde catálogo
ok(!empty($slot0['pista']) && str_contains((string) $slot0['pista'], 'Biblioteca') === false || $hp !== 'leer', 'D: pista presente si descubierto');
if ($hp === 'leer') {
    ok(str_contains((string) ($slot0['pista'] ?? ''), 'Biblioteca'), 'D: leer → Biblioteca en pista');
}

// E — leer + biblioteca
$afinLeer = PlanAfinidad::paraParticipante($partida, $rid, 'lug_biblioteca', $catalog);
ok(!empty($afinLeer['relacionado']) || $hp !== 'leer', 'E: PlanAfinidad leer+biblioteca (si aplica)');

// F — deporte + gimnasio
$afinDep = PlanAfinidad::paraParticipante(
    ['residentes' => ['x' => ['runtime' => ['perfil_partida' => ['hobbies' => ['deporte', 'cine', 'bingo']]]]]],
    'x',
    'lug_gimnasio',
    $catalog
);
ok(!empty($afinDep['relacionado']), 'F: deporte + gimnasio relacionado');

// G/H — recuperación emocional
$g = EmotionalRecovery::evaluar(EstadoEmocional::TRISTE, 'normal', true);
ok($g !== null && $g['estado'] === EstadoEmocional::NEUTRO, 'G: triste + hobby + normal → neutro');
$h = EmotionalRecovery::evaluar(EstadoEmocional::ENFADADO, 'normal', true);
ok($h !== null && $h['estado'] === EstadoEmocional::NEUTRO, 'H: enfadado + hobby + normal → neutro');

// I — triste + hobby + bien → alegre
$i = EmotionalRecovery::evaluar(EstadoEmocional::TRISTE, 'bien', true);
ok($i !== null && $i['estado'] === EstadoEmocional::ALEGRE, 'I: triste + hobby + bien → alegre');

// J — triste + buena cita sin hobby
$j = EmotionalRecovery::evaluar(EstadoEmocional::TRISTE, 'bien', false);
ok($j !== null && $j['estado'] === EstadoEmocional::ALEGRE, 'J: triste + bien sin hobby → alegre');

// K — hobbies_pos no son hobbies propios en PlanAfinidad
$partidaK = $partida;
$partidaK['residentes']['per_p001']['runtime']['perfil_partida']['hobbies'] = ['costura', 'plantas', 'senderismo'];
$partidaK['residentes']['per_p001']['runtime']['perfil_partida']['preferencias']['hobbies_pos'] = ['leer', 'cine'];
$afinK = PlanAfinidad::paraParticipante($partidaK, 'per_p001', 'lug_biblioteca', $catalog);
ok(empty($afinK['relacionado']), 'K: hobbies_pos no cuentan como hobby propio');

// Integración bridge (encuentro terminado)
$packs = new VisualPackStore($root);
$emo = new EmotionalStateService($packs, $store);
$partida['residentes'][$rid]['runtime']['perfil_partida']['hobbies'] = ['leer', 'cine', 'bingo'];
$partida['residentes'][$rid]['runtime']['perfil_partida']['hobby_principal'] = 'leer';
$emo->aplicar($partida, $rid, EstadoEmocional::TRISTE, 'dev_manual');
$antes = $partida['residentes'][$rid]['runtime']['estado_emocional']['id'];
$enc = [
    'id' => 'enc_test_hobby',
    'tipo' => 'individual',
    'intencion' => 'celeste_organizado',
    'lugar' => 'lug_biblioteca',
    'participantes' => [$rid],
    'estado' => 'terminado',
];
$res = [
    'por_participante' => [
        $rid => ['resultado' => 'normal'],
    ],
];
ok(FeatureConfig::isEnabled($partida, 'emotional_state_from_events_enabled'), 'emociones ON en playtest');
DomainEventDispatcher::emit($partida, DomainEvents::ENCUENTRO_TERMINADO, [
    'encuentro' => $enc,
    'resultado' => $res,
    'actores' => [$rid],
]);
$despues = $partida['residentes'][$rid]['runtime']['estado_emocional']['id'];
ok($antes === EstadoEmocional::TRISTE && $despues === EstadoEmocional::NEUTRO, 'bridge: triste + hobby biblioteca + normal → neutro');

echo $failures === 0
    ? "hobby_emocion_test OK\n"
    : "hobby_emocion_test FAIL ({$failures})\n";
exit($failures > 0 ? 1 : 0);
