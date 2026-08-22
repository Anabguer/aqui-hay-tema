<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\DiscoveryReveal;
use AquiHayTema\Engine\EncuentroCotilleoCopy;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\LugarAtributos;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\VistaCotilleoV3;

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

// --- Cita normal + copy + preferencia en cupo ---
$service = new PartidaService($root);
$partida = $service->nuevaPartida('playtest_01', 'cotilleo-v2');
$partida['features']['buzon_enabled'] = true;
$partida['features']['discovery_enabled'] = true;
$catalog = $service->getCatalog();
$ida = 'per_p001';
$idb = 'per_p002';

$cands = DiscoveryReveal::candidatosEncuentro($partida, $ida, $idb, ['lugar' => 'lug_cafeteria'], $catalog);
$campos = array_column($cands, 'campo');
ok(isset($campos[0]) && str_starts_with((string) $campos[0], 'hobby:'), 'cola: primero 1 hobby/rasgo de A');
ok(
    in_array('gusto_personalidad:', array_map(static fn($c) => substr((string) $c, 0, 20), $campos), true)
    || in_array('gusto_hobby:', array_map(static fn($c) => substr((string) $c, 0, 12), $campos), true)
    || in_array('rechazo_personalidad:', array_map(static fn($c) => substr((string) $c, 0, 22), $campos), true),
    'cola: preferencias presentes tras el primer rasgo/hobby'
);
$top2 = array_slice($campos, 0, 2);
ok(
    count(array_filter($top2, static fn($c) => str_starts_with((string) $c, 'hobby:') || str_starts_with((string) $c, 'rasgo:'))) <= 1,
    'cola: máximo 1 hobby/rasgo en los 2 primeros slots de A'
);

$enc = $service->programarEncuentro($partida, [$ida, $idb], 1, 19, 'conocerse', 'lug_cafeteria');
ok($enc['ok'] ?? false, 'programa cita');
$dia = (int) $enc['encuentro']['dia'];
$hora = (int) $enc['encuentro']['hora'];
$now = ((int) $partida['reloj']['dia_pueblo']) * 24 + (int) $partida['reloj']['hora_actual'];
$dur = max(1, (int) ($enc['encuentro']['duracion_horas'] ?? 1));
$adv = $service->avanzarRelojPasoAPaso($partida, max(1, $dia * 24 + $hora + $dur - $now));
ok($adv['ok'] ?? false, 'cita termina');

$cotilleos = array_values(array_filter($partida['buzon'] ?? [], static fn($m) => is_array($m) && ($m['clasificacion'] ?? '') === BuzonEngine::COTILLEO));
ok($cotilleos !== [], 'cotilleo cita');
$textoCita = (string) ($cotilleos[count($cotilleos) - 1]['texto'] ?? '');
ok(str_contains($textoCita, 'Cafetería'), 'copy lugar con acento');
ok(!str_contains($textoCita, 'Cafeteria'), 'copy sin Cafeteria sin tilde');
ok(!str_contains($textoCita, 'tímido/a') && !str_contains($textoCita, 'timido/a'), 'copy sin barra de género');
$desc = is_array($partida['encuentros'][0]['resultado']['descubrimientos'] ?? null)
    ? $partida['encuentros'][0]['resultado']['descubrimientos']
    : [];
$tienePref = false;
foreach ($desc as $d) {
    $c = (string) ($d['campo'] ?? '');
    if (str_starts_with($c, 'gusto_personalidad:') || str_starts_with($c, 'rechazo_personalidad:')
        || str_starts_with($c, 'gusto_hobby:') || str_starts_with($c, 'rechazo_hobby:')) {
        $tienePref = true;
        break;
    }
}
ok($tienePref, 'cita puede revelar preferencia en cupo (slot 2)');

echo "\n--- Cita ---\n{$textoCita}\n";

// --- Plan autónomo ---
$service2 = new PartidaService($root);
$partida2 = $service2->nuevaPartida('playtest_01', 'cotilleo-autonomo');
$partida2['features']['buzon_enabled'] = true;
$solo = 'per_p003';
$attr = LugarAtributos::de('lug_cine');
$partida2['encuentros'][] = [
    'id' => 'enc_auto_cine',
    'tipo' => 'individual',
    'intencion' => 'autonomo',
    'participantes' => [$solo],
    'lugar' => 'lug_cine',
    'dia' => 1,
    'hora' => 17,
    'duracion_horas' => $attr['horas'],
    'duracion_minutos' => $attr['duracion_minutos'],
    'estado' => 'programado',
    'reserva_agenda' => ['tipo' => 'autonomo', 'origen' => 'npc_autonomo'],
];
$partida2['reloj']['hora_actual'] = 17;
$fin = 17 + max(1, (int) ($attr['horas'] ?? 1));
$partida2['reloj']['hora_actual'] = $fin;
EncuentroLifecycle::sincronizarConReloj($partida2, null, $service2->getCatalog());

$auto = array_values(array_filter($partida2['buzon'] ?? [], static function ($m) {
    return is_array($m) && ($m['tipo'] ?? '') === 'cotilleo_autonomo';
}));
ok($auto !== [], 'cotilleo plan autónomo');
$textoAuto = (string) ($auto[count($auto) - 1]['texto'] ?? '');
ok(str_contains($textoAuto, 'Cine') || str_contains($textoAuto, 'cine'), 'autónomo menciona destino');
ok(
    str_contains($textoAuto, 'por su cuenta')
    || str_contains($textoAuto, 'despejarse')
    || str_contains($textoAuto, 'No haremos preguntas'),
    'autónomo copy reconocible'
);
$copyDirect = EncuentroCotilleoCopy::mensajeAutonomo($partida2, $partida2['encuentros'][0], $service2->getCatalog(), $root);
ok(is_string($copyDirect) && $copyDirect !== '', 'EncuentroCotilleoCopy autónomo');
ok(count(VistaCotilleoV3::de($partida2)['hoy'] ?? []) >= 1, 'VistaCotilleo expone autónomo');

echo "\n--- Autónomo ---\n{$textoAuto}\n";

exit($failures > 0 ? 1 : 0);
