<?php
declare(strict_types=1);

/**
 * R08-A — FIX TIRADAS FANTASMA.
 * cargar() y cargarParaRefresh() NUNCA generan peticiones: solo reconcilian
 * (caducar vencidas + fallos ya devengados). La generación autónoma vive
 * exclusivamente en el avance canónico del reloj (RelojOperations::avanzar).
 * Hermetico: sin tocar partidas reales; la unica escritura es una partida de
 * test con id unico (patron ya usado por peticiones_pueblo_test).
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionEsquemas;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\SimuladorPeticionesPueblo;

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

/** Extrae el cuerpo de una funcion/metodo por llaves balanceadas. */
function cuerpoFuncion(string $src, string $nombre): string
{
    $pos = strpos($src, $nombre);
    if ($pos === false) {
        return '';
    }
    $ini = strpos($src, '{', $pos);
    if ($ini === false) {
        return '';
    }
    $prof = 0;
    for ($i = $ini, $n = strlen($src); $i < $n; $i++) {
        if ($src[$i] === '{') {
            $prof++;
        } elseif ($src[$i] === '}') {
            $prof--;
            if ($prof === 0) {
                return substr($src, $pos, $i - $pos + 1);
            }
        }
    }
    return '';
}

$lifecycleSrc = (string) file_get_contents($root . '/src/Engine/PartidaLifecycle.php');

// ---------- A1) Estructural: cargar/cargarParaRefresh sin tiradas ----------
foreach (['public function cargar(', 'public function cargarParaRefresh('] as $firma) {
    $cuerpo = cuerpoFuncion($lifecycleSrc, $firma);
    ok($cuerpo !== '', "existe $firma");
    ok(strpos($cuerpo, 'tickPeticiones') === false, "$firma ya NO llama a tickPeticiones");
    ok(strpos($cuerpo, 'PeticionPuebloEngine::tick') === false, "$firma ya NO llama a PeticionPuebloEngine::tick");
    ok(strpos($cuerpo, 'intentarNacer') === false, "$firma no tira nacimientos");
    ok(strpos($cuerpo, 'reconciliarPeticiones') !== false, "$firma usa reconciliarPeticiones");
}
ok(substr_count($lifecycleSrc, '$this->tickPeticiones(') === 2, 'tickPeticiones solo queda en nueva()+reiniciar() (2 llamadas)');
$cuerpoRec = cuerpoFuncion($lifecycleSrc, 'private function reconciliarPeticiones');
ok($cuerpoRec !== '' && strpos($cuerpoRec, 'intentarNacer') === false, 'reconciliarPeticiones no nace nada');
ok(strpos($cuerpoRec, 'caducarVencidas') !== false && strpos($cuerpoRec, 'aplicarFalloPendiente') !== false,
    'reconciliarPeticiones conserva caducidad offline y fallo devengado');

// ---------- A2) Conductual: N cargas/refreshes => 0 cambios en peticiones ----------
$t0 = new DateTimeImmutable('2026-08-17 08:00:00', Reloj::zona());
Reloj::fijarAhora($t0);

$service = new AquiHayTema\Engine\PartidaService($root);
$testId = 'r08-fantasma-' . bin2hex(random_bytes(3));
$pSave = $service->nuevaPartida('debug_v0', $testId);
$pSave['features'][PeticionPuebloEngine::FLAG] = true;
$idsSave = array_keys($pSave['residentes']);
$cr = PeticionEngine::crear($pSave, (string) $idsSave[0], 'lugar', [
    'schema_b4' => true,
    'peso' => PeticionEsquemas::PESO_FACIL,
    'plazo_horas' => 48,
    'texto' => 'Me apetece ir al parque.',
    'plantilla_id' => 'ir_al_lugar',
    'params' => ['lugar_id' => 'lug_parque'],
], null);
ok(!empty($cr['ok']), 'partida de test con 1 peticion sembrada');
$service->guardar($pSave);
$pid = (string) ($pSave['meta']['partida_id'] ?? '');

$conteo = static function (array $p): array {
    $pets = 0;
    $msgs = 0;
    foreach ($p['peticiones'] ?? [] as $lp) {
        if (!empty($lp['schema_b4'])) {
            $pets++;
        }
    }
    foreach ($p['buzon'] ?? [] as $m) {
        if (($m['clasificacion'] ?? '') === 'peticion') {
            $msgs++;
        }
    }
    return [$pets, $msgs];
};

[$petBase, $msgBase] = $conteo($pSave);
$sinCambios = true;
for ($i = 0; $i < 3; $i++) {
    foreach (['cargar', 'cargarParaRefresh'] as $metodo) {
        $p2 = $service->{$metodo}($pid);
        [$nP, $nM] = $conteo($p2);
        if ($nP !== $petBase || $nM !== $msgBase) {
            $sinCambios = false;
        }
    }
}
ok($sinCambios, '3x cargar + 3x cargarParaRefresh SIN avanzar reloj: 0 nuevas peticiones/mensajes');

// ---------- A3) El avance canónico de 1h SÍ ejecuta su pipeline ----------
Reloj::fijarAhora($t0);
$cal = CalibracionConfig::load($root);
$pLab = SimuladorPeticionesPueblo::partidaLab(8, new RngService('r08-pipeline'), $cal, 'E3');
if (!isset($pLab['reloj']['dia_en_temporada'])) {
    $pLab['reloj']['dia_en_temporada'] = (int) ($pLab['reloj']['dia_pueblo'] ?? 1);
}
$pLab['_b4_forzar_nacer'] = true;
$antes = 0;
foreach ($pLab['peticiones'] as $lp) {
    if (!empty($lp['schema_b4'])) {
        $antes++;
    }
}
Reloj::avanzarHoras($pLab, 1);
PeticionPuebloEngine::tick($pLab, $cal, new RngService('r08-pipeline-t1'), null, 1);
$despues = 0;
foreach ($pLab['peticiones'] as $lp) {
    if (!empty($lp['schema_b4'])) {
        $despues++;
    }
}
ok($despues === $antes + 1, 'avance canónico +1h ejecuta exactamente el pipeline de peticiones (+1 forzada)');

Reloj::fijarAhora(null);
echo "\n" . ($failures > 0 ? "FALLOS: $failures" : 'TODO OK') . " — peticiones_refresh_fantasma\n";
exit($failures > 0 ? 1 : 0);
