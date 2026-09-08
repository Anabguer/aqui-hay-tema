<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\ComplejoCatalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\LugarAtributos;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PresenciaEngine;

DomainBootstrap::boot();
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

function idsEnLugar(array $mapa, string $lugarId): array
{
    foreach ($mapa['lugares'] ?? [] as $l) {
        if (($l['id'] ?? '') === $lugarId) {
            return array_values(array_map(static fn($p) => (string) ($p['id'] ?? ''), $l['residentes_presentes'] ?? []));
        }
    }
    return [];
}

function allPresentIds(array $mapa): array
{
    $ids = [];
    foreach ($mapa['lugares'] ?? [] as $l) {
        foreach ($l['residentes_presentes'] ?? [] as $p) {
            $ids[] = (string) ($p['id'] ?? '');
        }
    }
    return array_values(array_unique($ids));
}

function setupWithPrefs(array $prefs): array
{
    global $root;
    $service = new PartidaService($root);
    $p = $service->nuevaPartida('test_fixtures_v0', 'rutina_cerrado');
    $ph = $service->crearResidentePlaceholderDev($p);
    $rid = $ph['residente']['catalog_id'];
    $p['residentes'][$rid]['runtime']['perfil_partida']['lugares_preferentes'] = $prefs;
    return [$service, $p, $rid];
}

function setHora(array &$p, int $dia, int $hora): void
{
    $p['reloj']['dia_pueblo'] = $dia;
    $p['reloj']['hora_actual'] = $hora;
}

[$svc, $p, $rid] = setupWithPrefs(['lug_discoteca']);
setHora($p, 1, 15);
$m = PresenciaEngine::resolver($p, $root);
ok(!in_array($rid, idsEnLugar($m, 'lug_discoteca'), true), 'T1: discoteca 15:00 NO devuelve lug_discoteca');

[$svc, $p, $rid] = setupWithPrefs(['lug_bingo']);
setHora($p, 1, 10);
$m = PresenciaEngine::resolver($p, $root);
ok(!in_array($rid, idsEnLugar($m, 'lug_bingo'), true), 'T2: bingo 10:00 NO devuelve lug_bingo');

[$svc, $p, $rid] = setupWithPrefs(['lug_cine']);
setHora($p, 1, 10);
$m = PresenciaEngine::resolver($p, $root);
ok(!in_array($rid, idsEnLugar($m, 'lug_cine'), true), 'T3: cine 10:00 NO devuelve lug_cine');

[$svc, $p, $rid] = setupWithPrefs(['lug_discoteca']);
setHora($p, 1, 23);
$m = PresenciaEngine::resolver($p, $root);
ok(
    ComplejoCatalog::estaAbierto('lug_discoteca', 23) && in_array($rid, idsEnLugar($m, 'lug_discoteca'), true)
    || !ComplejoCatalog::estaAbierto('lug_discoteca', 23),
    'T4: discoteca 23:00 SÍ devuelve si abierta'
);

[$svc, $p, $rid] = setupWithPrefs(['lug_discoteca', 'lug_bingo', 'lug_cine']);
setHora($p, 1, 10);
$m = PresenciaEngine::resolver($p, $root);
$presente = in_array($rid, allPresentIds($m), true);
$algunaAbierta = ComplejoCatalog::estaAbierto('lug_discoteca', 10)
    || ComplejoCatalog::estaAbierto('lug_bingo', 10)
    || ComplejoCatalog::estaAbierto('lug_cine', 10);
ok($algunaAbierta === $presente, 'T5: sin preferentes abiertos → null');

[$svc, $p, $rid] = setupWithPrefs(['lug_discoteca']);
$attrDisc = LugarAtributos::de('lug_discoteca');
$p['encuentros'][] = [
    'id' => 'enc_disc_22',
    'tipo' => 'individual',
    'participantes' => [$rid],
    'lugar' => 'lug_discoteca',
    'dia' => 1,
    'hora' => 22,
    'duracion_horas' => $attrDisc['horas'],
    'duracion_minutos' => $attrDisc['duracion_minutos'],
    'estado' => 'programado',
    'reserva_agenda' => ['tipo' => 'encuentro', 'origen' => 'celeste'],
];
setHora($p, 1, 15);
$m = PresenciaEngine::resolver($p, $root);
ok(!in_array($rid, idsEnLugar($m, 'lug_discoteca'), true), 'T6: encuentro futuro discoteca 22:00 no aparece allí a las 15:00');

[$svc, $p, $rid] = setupWithPrefs(['lug_discoteca']);
$attrDisc = LugarAtributos::de('lug_discoteca');
$p['encuentros'][] = [
    'id' => 'enc_disc_22b',
    'tipo' => 'individual',
    'participantes' => [$rid],
    'lugar' => 'lug_discoteca',
    'dia' => 1,
    'hora' => 22,
    'duracion_horas' => $attrDisc['horas'],
    'duracion_minutos' => $attrDisc['duracion_minutos'],
    'estado' => 'programado',
    'reserva_agenda' => ['tipo' => 'encuentro', 'origen' => 'celeste'],
];
setHora($p, 1, 22);
EncuentroLifecycle::sincronizarConReloj($p);
$m = PresenciaEngine::resolver($p, $root);
ok(in_array($rid, idsEnLugar($m, 'lug_discoteca'), true), 'T7: encuentro activo a las 22:00 muestra lugar real');

echo $failures === 0
    ? "presencia_lugar_rutina_cerrado_test OK (7/7)\n"
    : "presencia_lugar_rutina_cerrado_test FAIL ({$failures})\n";
exit($failures > 0 ? 1 : 0);
