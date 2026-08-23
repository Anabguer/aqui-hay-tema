<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\AcontecimientoDiario;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PeticionEsquemas;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\RelojOperations;
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
$service = new PartidaService($root);
$store = (new Catalog($root))->store();
$reloj = new RelojOperations($root);

$p = $service->nuevaPartida('playtest_01', 'vp-autonomia');
ok(VidaPuebloEngine::valor($p) === 65, '1. partida nueva = 65');

$ids = array_keys($p['residentes']);
$a = (string) $ids[0];
$b = (string) $ids[1];
$c = (string) $ids[2] ?? $a;
$d = (string) $ids[3] ?? $b;

$ejecutar = function (string $eventoId, array $participantes) use (&$p, $store, $cal): array {
    return AcontecimientoDiario::ejecutar($p, $eventoId, $participantes, $store, $cal, null);
};

$p['residentes'][$a]['runtime']['ocupacion'] = 'desempleado';
$r1 = $ejecutar('encontrar_trabajo', [$a]);
ok(($r1['ok'] ?? false) === true || ($r1['error'] ?? '') === 'cooldown', '2a. encontrar_trabajo ejecutado');
ok(VidaPuebloEngine::valor($p) === 65, '2a. encontrar_trabajo NO mueve Vida');

$f1 = ParejaEngine::formar($p, $a, $b, true, true, 'test', $cal);
$f2 = ParejaEngine::formar($p, $c, $d, true, true, 'test', $cal);
if (($f1['ok'] ?? false)) {
    ParejaEngine::romper($p, $a, $b, 'test');
}
$r2 = $ejecutar('reconciliacion', [$c, $d]);
if (($f2['ok'] ?? false) && ($r2['ok'] ?? false) === false && ($r2['error'] ?? '') !== 'no_elegible' && ($r2['error'] ?? '') !== 'cooldown') {
    ok(false, '2b. reconciliacion fallo inesperado: ' . json_encode($r2));
} else {
    ok(true, '2b. reconciliacion procesada');
}
ok(VidaPuebloEngine::valor($p) === 65, '2b. reconciliacion NO mueve Vida');

$p['residentes'][$a]['runtime']['ocupacion'] = 'empleado';
$r3 = $ejecutar('perder_trabajo', [$a]);
ok(($r3['ok'] ?? false) === true || ($r3['error'] ?? '') === 'cooldown', '3a. perder_trabajo ejecutado');
ok(VidaPuebloEngine::valor($p) === 65, '3a. perder_trabajo NO mueve Vida');

if (!($f1['ok'] ?? false)) {
    $f1 = ParejaEngine::formar($p, $a, $b, true, true, 'test', $cal);
}
if (($f1['ok'] ?? false)) {
    $r4 = $ejecutar('crisis_pareja', [$a, $b]);
    ok(($r4['ok'] ?? false) === true || isset($r4['error']), '3b. crisis_pareja procesada');
    $r5 = $ejecutar('ruptura', [$a, $b]);
    ok(($r5['ok'] ?? false) === true || isset($r5['error']), '3c. ruptura procesada');
}
ok(VidaPuebloEngine::valor($p) === 65, '3. crisis+ruptura NO mueven Vida');

$mision = null;
foreach (MisionDiariaEngine::delDia($p) as $m) {
    if (($m['estado'] ?? '') === MisionDiariaEngine::EST_PENDIENTE) {
        $mision = $m;
        break;
    }
}
ok($mision !== null, '4. hay misión pendiente');
if ($mision !== null) {
    $encM = MisionDiariaEngine::encuentroSinteticoPara($mision, $p);
    MisionDiariaEngine::onEncuentroCelestine($p, $encM, $cal, null);
    ok(VidaPuebloEngine::valor($p) === 67, '4. misión cumplida 65 -> 67');
}

VidaPuebloEngine::aplicar($p, VidaPuebloEngine::DELTA_MISION_FALLIDA, [
    'causa' => VidaPuebloEngine::CAUSA_MISION_FALLIDA,
    'origen' => VidaPuebloEngine::ORIGEN_SISTEMA,
    'atribuible_celestine' => true,
], $cal);
ok(VidaPuebloEngine::valor($p) === 64, '5. misión fallida -3 canonico');

$pet = PeticionEngine::crear($p, $a, 'lugar', [
    'schema_b4' => true,
    'plantilla_id' => 'ir_al_lugar',
    'familia' => 'lugar',
    'peso' => PeticionEsquemas::PESO_FACIL,
    'texto' => 'Me apetece ir al parque.',
    'plazo_horas' => 48,
    'params' => ['lugar_id' => 'lug_parque'],
    'hecho' => 'Organizar un encuentro en el parque.',
], null);
if (!empty($pet['ok'])) {
    $encPet = PeticionPuebloEngine::encuentroSinteticoPara($pet['peticion'], $p);
    PeticionPuebloEngine::onEncuentroCelestine($p, $encPet, $cal, null);
    ok(VidaPuebloEngine::valor($p) === 65, '6. petición fácil cumplida +1 sigue aplicando');
} else {
    ok(false, '6. no se pudo crear petición: ' . json_encode($pet));
}

$encB = ['id' => 'enc_auto_bien', 'intencion' => 'celeste_organizado', 'participantes' => [$a, $b]];
$resB = ['por_participante' => [$a => ['resultado' => 'muy_bien'], $b => ['resultado' => 'bien']]];
$v7 = VidaPuebloEngine::valor($p);
VidaPuebloEngine::aplicarEncuentroOrganizado($p, $encB, $resB, $cal);
ok(VidaPuebloEngine::valor($p) === $v7 + 2, '7. encuentro organizado Celestine +2');

$pSim = $service->nuevaPartida('playtest_01', 'vp-autonomia-13d');
$v0 = VidaPuebloEngine::valor($pSim);
for ($i = 0; $i < 13; $i++) {
    $reloj->avanzar($pSim, 24);
}
$v13 = VidaPuebloEngine::valor($pSim);
ok($v13 <= $v0, "8. 13 dias sin intervenir: $v0 -> $v13 (sin subida por RNG)");
$nAuto = 0;
foreach ($pSim['vida_pueblo']['ledger'] ?? [] as $e) {
    if (($e['causa'] ?? '') === 'acontecimiento_vida' && (int) ($e['delta_aplicado'] ?? $e['delta'] ?? 0) !== 0) {
        $nAuto++;
    }
}
ok($nAuto === 0, '8. ledger sin deltas de acontecimiento_vida');

exit($failures > 0 ? 1 : 0);
