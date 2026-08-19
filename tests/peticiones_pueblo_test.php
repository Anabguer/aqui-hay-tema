<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionEsquemas;
use AquiHayTema\Engine\PeticionPlantillas;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorPeticionesPueblo;
use AquiHayTema\Engine\VidaPuebloEngine;

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

$cal = CalibracionConfig::load($root);
$nombres = [];
foreach (PeticionPlantillas::catalogo() as $pl) {
    $nombres[] = (string) $pl['id'];
}
ok(count($nombres) >= 6 && count($nombres) <= 10, 'biblioteca 6–10 plantillas');
ok(count($nombres) === count(array_unique($nombres)), 'ids de plantilla únicos');

$t0 = new DateTimeImmutable('2026-08-17 08:00:00', Reloj::zona());
Reloj::fijarAhora($t0);

$rng = new RngService('b4-unit-a');
$mundo = SimuladorPeticionesPueblo::partidaLab(8, $rng, $cal, 'E2');
$mundo['_lab_peticiones_b4'] = false;
$mundo['_b4_forzar_nacer'] = true;
ok(PeticionPuebloEngine::capSimultaneas(8, $cal) === 3, 'cap n=8 → 3');
ok(PeticionPuebloEngine::capSimultaneas(16, $cal) === 6, 'cap n=16 → 6');
ok(PeticionPuebloEngine::capSimultaneas(32, $cal) === 10, 'cap n=32 → 10');

$cands = PeticionPuebloEngine::candidatosSpawn($mundo, $cal);
ok($cands !== [], 'hay generación posible con estado real');

$n1 = PeticionPuebloEngine::intentarNacer($mundo, $cal, $rng, null);
ok($n1 !== null, 'nace una petición realizable');
$enc1 = PeticionPuebloEngine::encuentroSinteticoPara($n1, $mundo);
ok(PeticionPuebloEngine::encaja($n1, $enc1), 'la nacida encaja con un encuentro controlable');
ok(strpos((string) ($n1['texto'] ?? ''), 'lab_r') === false, 'copy sin IDs de motor');
ok(strpos((string) ($n1['texto'] ?? ''), 'positivo') === false, 'copy sin jerga');

$rid = (string) ($n1['residente_id'] ?? '');
ok(PeticionPuebloEngine::pendienteDe($mundo, $rid), 'una pendiente por ese NPC');
$antesNpc = count(PeticionPuebloEngine::abiertas($mundo));
$mundo['_b4_forzar_nacer'] = true;
for ($i = 0; $i < 12; $i++) {
    PeticionPuebloEngine::intentarNacer($mundo, $cal, $rng, null);
}
$abiertas = PeticionPuebloEngine::abiertas($mundo);
ok(count($abiertas) <= 3, 'respeta cap por población');
$porNpc = [];
$doble = false;
foreach ($abiertas as $ap) {
    $id = (string) ($ap['residente_id'] ?? '');
    if (isset($porNpc[$id])) {
        $doble = true;
    }
    $porNpc[$id] = true;
}
ok(!$doble, 'máximo una pendiente por NPC');
ok(count($abiertas) >= $antesNpc, 'no pierde las ya abiertas');

$plCita = PeticionPlantillas::porId('primera_cita_pet');
$citas = PeticionPuebloEngine::candidatosDe($mundo, 'lab_r01', $plCita, $cal);
ok($citas === [], 'primera cita imposible sin señal = 0');

$plCon = PeticionPlantillas::porId('conocer_a_alguien');
ok(PeticionPuebloEngine::candidatosDe($mundo, 'lab_r01', $plCon, $cal) !== [], 'conocer nace si hay desconocidos');

$pPlazo = SimuladorPeticionesPueblo::partidaLab(8, new RngService('b4-plazo'), $cal, 'E2');
$pPlazo['_lab_peticiones_b4'] = true;
$pPlazo['_b4_forzar_nacer'] = true;
Reloj::fijarAhora($t0);
$petPlazo = PeticionEngine::crear($pPlazo, 'lab_r01', 'tiempo', [
    'schema_b4' => true,
    'plantilla_id' => 'algo_distinto',
    'peso' => PeticionEsquemas::PESO_FACIL,
    'texto' => 'Necesito hacer algo distinto.',
    'plazo_horas' => 12,
    'params' => ['lugar_id' => 'lug_parque'],
], null);
ok(!empty($petPlazo['ok']), 'crear con plazo real');
ok(!empty($petPlazo['peticion']['vence_iso']), 'guarda vence_iso');
ok(PeticionEngine::caducarVencidas($pPlazo) === 0, 'no caduca antes de plazo real');
Reloj::fijarAhora($t0->modify('+11 hours'));
ok(PeticionEngine::caducarVencidas($pPlazo) === 0, 'a las 11 h sigue abierta');
Reloj::fijarAhora($t0->modify('+12 hours'));
ok(PeticionEngine::caducarVencidas($pPlazo) === 1, 'caduca a las 12 h reales');
ok(count(PeticionEngine::listar($pPlazo, 'caducada')) === 1, 'queda caducada');
$hum = PeticionPuebloEngine::plazoHumano($petPlazo['peticion']);
ok(strpos($hum, '20/08') === false && strpos($hum, '17:34') === false, 'plazo humano sin fecha técnica');

Reloj::fijarAhora($t0);
$pCum = SimuladorPeticionesPueblo::partidaLab(8, new RngService('b4-cumple'), $cal, 'E2');
$pCum['_lab_peticiones_b4'] = true;
$pCum['_lab_misiones_b3'] = true;
$vida0 = VidaPuebloEngine::valor($pCum);
$pos0 = (int) ($pCum['vida_pueblo']['positivos_desde_latido'] ?? 0);
$petF = PeticionEngine::crear($pCum, 'lab_r01', 'lugar', [
    'schema_b4' => true,
    'plantilla_id' => 'ir_al_lugar',
    'familia' => 'lugar',
    'peso' => PeticionEsquemas::PESO_FACIL,
    'texto' => 'Me apetece ir al parque.',
    'plazo_horas' => 24,
    'params' => ['lugar_id' => 'lug_parque'],
    'hecho' => 'Organizar un encuentro en el parque.',
], null);
ok(!empty($petF['ok']), 'hay petición fácil para cumplir');
if (!empty($petF['ok'])) {
    $encF = PeticionPuebloEngine::encuentroSinteticoPara($petF['peticion'], $pCum);
    $nOk = PeticionPuebloEngine::onEncuentroCelestine($pCum, $encF, $cal, null);
    $n2 = PeticionPuebloEngine::onEncuentroCelestine($pCum, $encF, $cal, null);
    ok($nOk === 1, 'cumplir por encuentro Celestine');
    ok($n2 === 0, 'el mismo encuentro no cumple otra');
    ok(VidaPuebloEngine::valor($pCum) === $vida0 + 1, 'fácil cumplida +1');
    ok((int) ($pCum['vida_pueblo']['positivos_desde_latido'] ?? 0) === $pos0, 'fácil no es positivo válido (no farm Latido)');
}

Reloj::fijarAhora($t0);
$pCad = SimuladorPeticionesPueblo::partidaLab(8, new RngService('b4-cad'), $cal, 'E2');
$pCad['_lab_peticiones_b4'] = true;
$vidaC = VidaPuebloEngine::valor($pCad);
PeticionEngine::crear($pCad, 'lab_r02', 'tiempo', [
    'schema_b4' => true,
    'peso' => PeticionEsquemas::PESO_FACIL,
    'plazo_horas' => 12,
    'texto' => 'Sácame de casa.',
], null);
Reloj::fijarAhora($t0->modify('+13 hours'));
PeticionPuebloEngine::tick($pCad, $cal, $rng, null, 1);
ok(VidaPuebloEngine::valor($pCad) === $vidaC - 1, 'caducada fácil −1 (E2)');

$service = new PartidaService($root);
$off = $service->nuevaPartida('debug_v0', 'b4-flag-off');
ok(!FeatureConfig::isEnabled($off, PeticionPuebloEngine::FLAG), 'flag global off');

$play = $service->nuevaPartida('playtest_01', 'b4-playtest-off');
ok(!FeatureConfig::isEnabled($play, PeticionPuebloEngine::FLAG), 'playtest no enciende B4 hasta elegir esquema');

DomainBootstrap::resetForTests();
DomainBootstrap::boot();
Reloj::fijarAhora($t0);
$pBuz = SimuladorPeticionesPueblo::partidaLab(8, new RngService('b4-buzon'), $cal, 'E2');
$pBuz['_lab_peticiones_b4'] = false;
$pBuz['_lab_misiones_b3'] = false;
$pBuz['_b4_forzar_nacer'] = true;
$pBuz['features']['buzon_enabled'] = true;
$pBuz['buzon'] = [];
$petB = PeticionPuebloEngine::intentarNacer($pBuz, $cal, new RngService('b4-buzon-n'), null);
ok($petB !== null, 'nace petición con buzón');
$nMsg = 0;
$copyOk = false;
if ($petB !== null) {
    foreach ($pBuz['buzon'] ?? [] as $m) {
        if (($m['peticion_id'] ?? '') === ($petB['id'] ?? '')) {
            $nMsg++;
            $tx = (string) ($m['texto'] ?? '');
            $copyOk = $tx !== ''
                && strpos($tx, 'pet_') === false
                && strpos($tx, 'PeticionEngine') === false
                && strpos($tx, 'lab_r') === false;
        }
    }
}
ok($nMsg === 1, 'buzón sin duplicados (n=' . $nMsg . ')');
ok($copyOk, 'mensaje de residente sin IDs ni copy de motor');

Reloj::fijarAhora($t0);
$pSave = $service->nuevaPartida('debug_v0', 'b4-saveload');
$pSave['features'][PeticionPuebloEngine::FLAG] = true;
$pSave['features'][VidaPuebloEngine::FLAG] = true;
$idsSave = array_keys($pSave['residentes']);
ok(count($idsSave) >= 1, 'debug_v0 tiene residentes para save/load');
if (count($idsSave) >= 1) {
    $cr = PeticionEngine::crear($pSave, (string) $idsSave[0], 'lugar', [
        'schema_b4' => true,
        'peso' => PeticionEsquemas::PESO_FACIL,
        'plazo_horas' => 24,
        'texto' => 'Me apetece ir al parque.',
        'plantilla_id' => 'ir_al_lugar',
        'params' => ['lugar_id' => 'lug_parque'],
    ], null);
    $service->guardar($pSave);
    $pid = (string) ($pSave['meta']['partida_id'] ?? '');
    Reloj::fijarAhora($t0->modify('+25 hours'));
    $loaded = $service->cargar($pid);
    $cadLoad = 0;
    foreach ($loaded['peticiones'] ?? [] as $lp) {
        if (!empty($lp['schema_b4']) && ($lp['estado'] ?? '') === 'caducada') {
            $cadLoad++;
        }
    }
    ok($cadLoad >= 1, 'save/load conserva timestamps y caduca offline');
}

$labMini = SimuladorPeticionesPueblo::ejecutarComparacion(
    $root,
    ['E2'],
    [8],
    [7],
    1,
    'lab-b4-test'
);
$a7 = $labMini['esquemas']['E2']['por_tamano']['8']['por_perfil']['A']['por_horizonte']['7'] ?? [];
ok((int) round((float) ($a7['imposibles'] ?? 1)) === 0, 'lab mini imposibles = 0');
ok(empty($labMini['esquemas']['E2']['farming_detectado']), 'lab mini sin farming');
ok((float) ($a7['sobre_cap'] ?? 1) === 0.0, 'lab mini nunca supera cap');
ok((float) ($a7['doble_npc'] ?? 1) === 0.0, 'lab mini una por NPC');
ok((float) ($a7['validos_facil'] ?? 1) === 0.0, 'fáciles no generan válido');

Reloj::fijarAhora(null);
exit($failures > 0 ? 1 : 0);
