<?php
declare(strict_types=1);

/**
 * Petición explícita → propuesta → Voluntad coherente.
 * PHP 7.4. Pipeline real PropuestaEncuentroEngine::proponer.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionPuebloEngine;
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

function estadoPet(array $p, string $petId): string
{
    foreach ($p['peticiones'] ?? [] as $lp) {
        if ((string) ($lp['id'] ?? '') === $petId) {
            return (string) ($lp['estado'] ?? '');
        }
    }
    return '';
}

/** p baja + joint: tumba el plan por media geométrica salvo compromiso peticionario. */
final class VoluntadStubPJointBajo implements VoluntadEvaluator
{
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        return [
            'decision' => PropuestaEncuentro::DECISION_ACEPTA,
            'clase' => null,
            'motivo_tecnico' => 'voluntad_p_calculada',
            'motivo_tipo' => null,
            'copy_id' => null,
            'score' => 8,
            'p' => 0.08,
            'factores' => ['stub' => true],
            '_bloqueado_decision' => false,
            '_joint_plan' => true,
        ];
    }
}

final class VoluntadStubIndisponibilidad implements VoluntadEvaluator
{
    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        return [
            'decision' => PropuestaEncuentro::DECISION_RECHAZA,
            'clase' => PropuestaEncuentro::CLASE_INDISPONIBILIDAD,
            'motivo_tecnico' => 'stub_agenda_ocupada',
            'copy_id' => null,
            'factores' => ['agenda_disponible' => false],
            '_bloqueado_decision' => false,
        ];
    }
}

echo "== A) volver_a_ver + peticion_id: compromiso, no auto-rechazo geom ==\n";
{
    $p = partidaBase('pvc-a');
    $par = $p['tutorial']['pareja_mision1'];
    $aId = (string) $par['a'];
    $bId = (string) $par['b'];
    $pet = crearPet($p, $aId, 'volver_a_ver', ['otro' => $bId]);
    $petId = (string) ($pet['id'] ?? '');
    aislarPet($p, $petId);
    $preset = PeticionPuebloEngine::presetOrganizarParaUi($p, $pet);
    ok(is_array($preset) && ($preset['peticion_id'] ?? '') === $petId, 'preset transporta peticion_id');
    ok(($preset['tipo'] ?? '') === 'quedar', 'preset transporta tipo canonico quedar');
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
            new VoluntadStubPJointBajo(),
            null,
            $petId
        );
        if (!empty($r['ok'])) {
            break;
        }
    }
    $prop = $r['propuesta'] ?? [];
    $reac = reacPet($prop, $aId);
    ok(is_array($reac), 'A: reaccion peticionario');
    ok(
        ($reac['decision'] ?? '') === PropuestaEncuentro::DECISION_ACEPTA
        && ($reac['motivo_tecnico'] ?? '') === PropuestaEncuentroEngine::MARCA_COMPROMISO_PETICION,
        'A: compromiso_peticion_propia'
    );
    ok(
        ($reac['motivo_tecnico'] ?? '') !== 'voluntad_rechaza_plan_geom_emocional'
        && ($reac['decision'] ?? '') !== PropuestaEncuentro::DECISION_RECHAZA,
        'A: peticionario no rechaza por geom propia'
    );
    ok(is_array($prop['origen_peticion'] ?? null) && ($prop['origen_peticion']['nivel'] ?? '') === 'exacta', 'A: origen_peticion exacta');
}

echo "\n== B) mismo plan sin peticion: RNG conjunto normal ==\n";
{
    $p = partidaBase('pvc-b');
    $par = $p['tutorial']['pareja_mision1'];
    $aId = (string) $par['a'];
    $bId = (string) $par['b'];
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
            new VoluntadStubPJointBajo()
        );
        if (!empty($r['ok'])) {
            break;
        }
    }
    $prop = $r['propuesta'] ?? [];
    ok(empty($prop['origen_peticion']), 'B: sin origen_peticion');
    $reac = reacPet($prop, $aId);
    ok(
        ($prop['estado'] ?? '') === 'rechazada'
        || ($reac['motivo_tecnico'] ?? '') !== PropuestaEncuentroEngine::MARCA_COMPROMISO_PETICION,
        'B: sin compromiso; plan puede caer por geom'
    );
    ok(
        ($prop['resolucion_plan']['modo'] ?? '') === 'media_geometrica'
        || ($prop['estado'] ?? '') === 'rechazada',
        'B: resolucion conjunta o rechazo'
    );
}

echo "\n== C) exacta + agenda dura: fallo legitimo, peticion abierta ==\n";
{
    $p = partidaBase('pvc-c');
    $par = $p['tutorial']['pareja_mision1'];
    $aId = (string) $par['a'];
    $bId = (string) $par['b'];
    $pet = crearPet($p, $aId, 'volver_a_ver', ['otro' => $bId]);
    $petId = (string) ($pet['id'] ?? '');
    aislarPet($p, $petId);
    $r = PropuestaEncuentroEngine::proponer(
        $p,
        [$aId, $bId],
        2,
        17,
        'quedar',
        'lug_cafeteria',
        null,
        new VoluntadStubIndisponibilidad(),
        null,
        $petId
    );
    $prop = $r['propuesta'] ?? [];
    ok(($prop['estado'] ?? '') === 'rechazada', 'C: plan rechazado por agenda');
    ok(estadoPet($p, $petId) === PeticionPuebloEngine::EST_ABIERTA, 'C: peticion sigue abierta');
}

echo "\n== D) nucleo: bonus visible, sin compromiso absoluto ==\n";
{
    $p = partidaBase('pvc-d');
    $par = $p['tutorial']['pareja_mision1'];
    $aId = (string) $par['a'];
    $bId = (string) $par['b'];
    $pet = crearPet($p, $aId, 'algo_distinto', []);
    $petId = (string) ($pet['id'] ?? '');
    aislarPet($p, $petId);
    $origen = PeticionPuebloEngine::resolverOrigenPropuesta(
        $p,
        [$aId, $bId],
        'quedar',
        'lug_parque',
        $petId
    );
    ok($origen !== null && ($origen['nivel'] ?? '') === 'nucleo', 'D: origen nucleo');
    $propSynth = [
        'participantes' => [$aId, $bId],
        'tipo' => 'quedar',
        'origen_peticion' => $origen['origen_peticion'] ?? null,
        '_bonus_voluntad' => [
            $aId => (int) \AquiHayTema\Engine\CalibracionConfig::get(
                \AquiHayTema\Engine\CalibracionConfig::load(dirname(__DIR__)),
                'peticiones_pueblo.bonus_nucleo_modificado',
                30
            ),
        ],
    ];
    $cal = \AquiHayTema\Engine\CalibracionConfig::load(dirname(__DIR__));
    $evN = (new \AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator($cal))->evaluar($p, $propSynth, $aId);
    ok((int) ($evN['factores']['bonus_peticion_nucleo'] ?? 0) > 0, 'D: bonus_peticion_nucleo en Voluntad');
}

echo "\n== E) peticion_id explicito invalido/no encaja: sin compromiso ==\n";
{
    $p = partidaBase('pvc-e');
    $par = $p['tutorial']['pareja_mision1'];
    $aId = (string) $par['a'];
    $bId = (string) $par['b'];
    $pet = crearPet($p, $aId, 'volver_a_ver', ['otro' => $bId]);
    $petId = (string) ($pet['id'] ?? '');
    aislarPet($p, $petId);
    $r = PropuestaEncuentroEngine::proponer(
        $p,
        [$aId, $bId],
        2,
        17,
        'conocerse',
        'lug_cafeteria',
        null,
        new VoluntadStubPJointBajo(),
        null,
        $petId
    );
    $prop = $r['propuesta'] ?? [];
    $reac = reacPet($prop, $aId);
    ok(empty($prop['origen_peticion']), 'E: tipo incompatible => sin origen');
    ok(
        ($reac['motivo_tecnico'] ?? '') !== PropuestaEncuentroEngine::MARCA_COMPROMISO_PETICION,
        'E: sin compromiso con peticion_id que no encaja'
    );
}

echo "\n== F) propuesta rechazada: peticion abierta, sin cumplir ==\n";
{
    $p = partidaBase('pvc-f');
    $par = $p['tutorial']['pareja_mision1'];
    $aId = (string) $par['a'];
    $bId = (string) $par['b'];
    $pet = crearPet($p, $aId, 'quedar_con_x', ['otro' => $bId]);
    $petId = (string) ($pet['id'] ?? '');
    aislarPet($p, $petId);
    $usadosAntes = count($p['peticiones_pueblo']['encuentros_usados'] ?? []);
    $r = PropuestaEncuentroEngine::proponer(
        $p,
        [$aId, $bId],
        2,
        17,
        'quedar',
        'lug_cafeteria',
        null,
        new VoluntadStubPJointBajo(),
        null,
        $petId
    );
    ok(($r['propuesta']['estado'] ?? '') === 'rechazada' || empty($r['programado']), 'F: propuesta no programada');
    ok(estadoPet($p, $petId) === PeticionPuebloEngine::EST_ABIERTA, 'F: peticion abierta tras rechazo');
    $usadosDespues = count($p['peticiones_pueblo']['encuentros_usados'] ?? []);
    ok($usadosAntes === $usadosDespues, 'F: sin encuentro usado por intento fallido');
}

echo "\n== Regresion resolverOrigen ==\n";
{
    $p = partidaBase('pvc-meta');
    $par = $p['tutorial']['pareja_mision1'];
    $aId = (string) $par['a'];
    $bId = (string) $par['b'];
    $pet = crearPet($p, $aId, 'volver_a_ver', ['otro' => $bId]);
    $origen = PeticionPuebloEngine::resolverOrigenPropuesta($p, [$aId, $bId], 'quedar', 'lug_cafeteria', (string) ($pet['id'] ?? ''));
    ok($origen !== null && ($origen['nivel'] ?? '') === 'exacta', 'resolverOrigenPropuesta explicito exacta');
}

if ($failures > 0) {
    fwrite(STDERR, "\n$failures fallo(s)\n");
    exit(1);
}
echo "\nTodos OK ($failures fallos)\n";
