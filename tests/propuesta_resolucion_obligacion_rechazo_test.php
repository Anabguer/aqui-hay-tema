<?php
declare(strict_types=1);

/**
 * BLOQUE 3 — Romance: resolución contradictoria obligación vs rechazo.
 * Si un participante está forzado a aceptar (compromiso de petición) y el otro
 * rechaza genuinamente la Voluntad, el plan NO debe ser aceptado por una tirada
 * que sobreescribe el rechazo real. La decisión explícita debe respetarse.
 *
 * PHP 7.4. Pipeline real PropuestaEncuentroEngine::proponer.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionPlantillas;
use AquiHayTema\Engine\PropuestaEncuentro;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\TutorialPrimerosPasos;
use AquiHayTema\Engine\Voluntad\VoluntadEvaluator;

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

Reloj::fijarAhora(new DateTimeImmutable(Reloj::TEST_AHORA, Reloj::zona()));

function completarTutorialJuegoV1(array &$p, string $root): void
{
    $catalogTut = new Catalog($root);
    $par = is_array($p['tutorial']['pareja_mision1'] ?? null) ? $p['tutorial']['pareja_mision1'] : [];
    $aTut = (string) ($par['a'] ?? '');
    $bTut = (string) ($par['b'] ?? '');
    $tercero = (string) ($p['tutorial']['tercero'] ?? '');
    $lugM3 = (string) ($p['tutorial']['lugar_m3'] ?? 'lug_cine');
    foreach ([12, 14, 16, 18, 20] as $hTut) {
        $rM1 = PropuestaEncuentroEngine::proponer($p, [$aTut, $bTut], 1, $hTut, 'conocerse', 'lug_cafeteria');
        if (!empty($rM1['ok'])) {
            break;
        }
    }
    TutorialPrimerosPasos::alLeerMensaje($p, (string) ($p['tutorial']['mensajito_id'] ?? ''), $catalogTut);
    foreach ([21, 19, 17, 15] as $hTut) {
        $rM3 = PropuestaEncuentroEngine::proponer($p, [$tercero], 1, $hTut, 'individual', $lugM3);
        if (!empty($rM3['ok'])) {
            break;
        }
    }
}

function partidaBase(string $seed): array
{
    DomainBootstrap::resetForTests();
    DomainBootstrap::boot();
    $service = new \AquiHayTema\Engine\PartidaService(dirname(__DIR__));
    $p = $service->nuevaPartida('juego_v1', $seed, ['fecha' => '2026-08-17', 'hora' => 8]);
    completarTutorialJuegoV1($p, dirname(__DIR__));
    $service->avanzarRelojPasoAPaso($p, 24);
    return $p;
}

function crearPet(array &$p, string $rid, string $plantilla, array $params): array
{
    $pl = PeticionPlantillas::porId($plantilla);
    $r = PeticionEngine::crear($p, $rid, (string) ($pl['tipo_legado'] ?? 'otro'), [
        'schema_b4' => true,
        'plantilla_id' => $plantilla,
        'familia' => (string) ($pl['familia'] ?? ''),
        'params' => $params,
        'texto' => (string) ($pl['copy'] ?? 'pet'),
        'hecho' => (string) ($pl['hecho'] ?? ''),
        'peso' => (string) ($pl['peso'] ?? 'facil'),
        'plazo_horas' => 24,
        'cuenta_latido' => false,
    ], null);
    return $r['peticion'] ?? [];
}

function aislarPet(array &$p, string $keepId): void
{
    foreach ($p['peticiones'] as &$lp) {
        if (!empty($lp['schema_b4']) && (string) ($lp['id'] ?? '') !== $keepId) {
            $lp['estado'] = 'caducada';
        }
    }
    unset($lp);
}

function reacPet(array $prop, string $rid): ?array
{
    foreach ($prop['reacciones'] ?? [] as $rc) {
        if (is_array($rc) && (string) ($rc['residente_id'] ?? '') === $rid) {
            return $rc;
        }
    }
    return null;
}

/** El peticionario acepta (lo fuerza el compromiso); el otro rechaza de verdad. */
final class VoluntadStubObligacionRechazo implements VoluntadEvaluator
{
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        return [
            'decision' => PropuestaEncuentro::DECISION_RECHAZA,
            'clase' => PropuestaEncuentro::CLASE_VOLUNTAD,
            'motivo_tecnico' => 'voluntad_rechaza_fuerte',
            'motivo_tipo' => 'banal',
            'copy_id' => 'banal',
            'score' => 2,
            'p' => 1.0, // p alta: sin el fix, la tirada individual voltearía a aceptar
            'factores' => ['stub' => true],
            '_bloqueado_decision' => false,
            '_joint_plan' => true,
        ];
    }
}

/** El peticionario acepta (compromiso); el otro acepta de verdad también. */
final class VoluntadStubObligacionAcepta implements VoluntadEvaluator
{
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        return [
            'decision' => PropuestaEncuentro::DECISION_ACEPTA,
            'clase' => null,
            'motivo_tecnico' => 'voluntad_acepta_fuerte',
            'motivo_tipo' => null,
            'copy_id' => null,
            'score' => 9,
            'p' => 0.9,
            'factores' => ['stub' => true],
            '_bloqueado_decision' => false,
            '_joint_plan' => true,
        ];
    }
}

echo "== B3: peticionario obligado + otro rechaza => plan RECHAZADO (no voltear) ==\n";
{
    $p = partidaBase('b3-rechazo');
    $par = $p['tutorial']['pareja_mision1'];
    $aId = (string) $par['a'];
    $bId = (string) $par['b'];
    $pet = crearPet($p, $aId, 'volver_a_ver', ['otro' => $bId]);
    $petId = (string) ($pet['id'] ?? '');
    aislarPet($p, $petId);
    $r = null;
    foreach ([17, 18, 19, 20] as $h) {
        $r = PropuestaEncuentroEngine::proponer(
            $p,
            [$aId, $bId],
            2,
            $h,
            'quedar',
            'lug_cafeteria',
            null,
            new VoluntadStubObligacionRechazo(),
            null,
            $petId
        );
        if (!empty($r['ok'])) {
            break;
        }
    }
    $prop = $r['propuesta'] ?? [];
    $reacA = reacPet($prop, $aId);
    $reacB = reacPet($prop, $bId);
    ok(($reacA['decision'] ?? '') === PropuestaEncuentro::DECISION_ACEPTA
        && ($reacA['motivo_tecnico'] ?? '') === PropuestaEncuentroEngine::MARCA_COMPROMISO_PETICION,
        'B3: peticionario sigue obligado a aceptar (compromiso)');
    ok(($reacB['decision'] ?? '') === PropuestaEncuentro::DECISION_RECHAZA,
        'B3: rechazo genuino del otro NO es volteado por la tirada');
    ok(($prop['estado'] ?? '') === 'rechazada',
        'B3: el plan queda rechazado (no aceptado por obligación+tirada)');
    ok(!empty($prop['rechazo_clase']) || ($prop['estado'] ?? '') === 'rechazada',
        'B3: se registra rechazo coherente');
}

echo "\n== B3 control: peticionario obligado + otro acepta => plan ACEPTADO ==\n";
{
    $p = partidaBase('b3-acepta');
    $par = $p['tutorial']['pareja_mision1'];
    $aId = (string) $par['a'];
    $bId = (string) $par['b'];
    $p['propuestas_cooldown'] = [];
    $pet = crearPet($p, $aId, 'volver_a_ver', ['otro' => $bId]);
    $petId = (string) ($pet['id'] ?? '');
    aislarPet($p, $petId);
    $r = null;
    foreach ([17, 18, 19, 20] as $h) {
        $r = PropuestaEncuentroEngine::proponer(
            $p,
            [$aId, $bId],
            2,
            $h,
            'quedar',
            'lug_cafeteria',
            null,
            new VoluntadStubObligacionAcepta(),
            null,
            $petId
        );
        if (!empty($r['ok'])) {
            break;
        }
    }
    $prop = $r['propuesta'] ?? [];
    ok((($prop['estado'] ?? '') === 'aceptada' || ($prop['estado'] ?? '') === 'programada')
        && !empty($r['programado']),
        'B3 control: ambos aceptan => plan aceptado y programado');
}

echo ($failures === 0 ? "\ntests OK\n" : "\nFAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
