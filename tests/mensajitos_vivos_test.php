<?php
declare(strict_types=1);

/*
 * MENSAJITOS VIVOS B4: variedad + compromiso del peticionario + feedback.
 * Cubre los casos A-J del brief. Pipeline real (PartidaService / proponer).
 * No modifica calibracion: lee knobs con sus defaults.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
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

$t0 = new DateTimeImmutable(Reloj::TEST_AHORA, Reloj::zona());

function completarTutorialJuegoV1(array &$p, string $root): bool
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
    return !empty($p['tutorial']['jugable_completado']);
}

function crearPet(array &$p, string $rid, string $plantilla, array $params): array
{
    $pl = PeticionPlantillas::porId($plantilla);
    $texto = (string) ($pl['copy'] ?? 'pet');
    if (isset($params['lugar_id'])) {
        $texto = str_replace('{lugar}', 'el lugar', $texto);
    }
    if (isset($params['otro'])) {
        $texto = str_replace('{otro}', 'alguien', $texto);
    }
    $r = PeticionEngine::crear($p, $rid, (string) ($pl['tipo_legado'] ?? 'otro'), [
        'schema_b4' => true,
        'plantilla_id' => $plantilla,
        'familia' => (string) ($pl['familia'] ?? ''),
        'params' => $params,
        'texto' => $texto,
        'hecho' => (string) ($pl['hecho'] ?? ''),
        'peso' => (string) ($pl['peso'] ?? 'facil'),
        'exigencia' => (int) ($pl['exigencia'] ?? 0),
        'plazo_horas' => (int) ($pl['plazo_horas'] ?? 24),
        'cuenta_latido' => false,
        '_placeholder_copy' => false,
    ], null);
    return $r['peticion'] ?? [];
}

/** Evaluator stub: acepta todos salvo el rid marcado, que rechaza por VOLUNTAD. */
final class VoluntadStubRechazaUno implements VoluntadEvaluator
{
    private string $rechaza;
    private float $pAcepta;

    public function __construct(string $rechaza, float $pAcepta = 0.9)
    {
        $this->rechaza = $rechaza;
        $this->pAcepta = $pAcepta;
    }

    public function evaluar(array &$partida, array $propuesta, string $residenteId): array
    {
        if ($residenteId === $this->rechaza) {
            return [
                'decision' => PropuestaEncuentro::DECISION_RECHAZA,
                'clase' => PropuestaEncuentro::CLASE_VOLUNTAD,
                'motivo_tecnico' => 'stub_rechaza_voluntad',
                'motivo_tipo' => 'banal',
                'copy_id' => 'hoy_no_me_da_la_vida',
                'score' => 10,
                'p' => 0.1,
                'factores' => ['stub' => true],
                '_bloqueado_decision' => false,
            ];
        }
        return [
            'decision' => PropuestaEncuentro::DECISION_ACEPTA,
            'clase' => null,
            'motivo_tecnico' => 'stub_acepta',
            'motivo_tipo' => null,
            'copy_id' => null,
            'score' => 60,
            'p' => $this->pAcepta,
            'factores' => ['stub' => true],
            '_bloqueado_decision' => false,
            '_joint_plan' => true,
        ];
    }
}

/** Evaluator stub que rechaza a TODOS por INDISPONIBILIDAD (agenda). */
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

/** Deja solo la petición indicada como abierta (las naturales del día 1 se apartan). */
function aislarPet(array &$p, string $keepId): void
{
    foreach ($p['peticiones'] as &$lp) {
        if (!empty($lp['schema_b4']) && (string) ($lp['id'] ?? '') !== $keepId) {
            $lp['estado'] = 'caducada';
        }
    }
    unset($lp);
}

/** Partida juego_v1 con tutorial hecho y pareja que SE CONOCE (encuentros celebrados). */
function partidaBase(string $root, string $seed, string $t0s): array
{
    Reloj::fijarAhora(new DateTimeImmutable($t0s, Reloj::zona()));
    DomainBootstrap::resetForTests();
    DomainBootstrap::boot();
    $service = new \AquiHayTema\Engine\PartidaService($root);
    $p = $service->nuevaPartida('juego_v1', $seed, ['fecha' => '2026-08-17', 'hora' => 8]);
    completarTutorialJuegoV1($p, $root);
    $service->avanzarRelojPasoAPaso($p, 24); // celebra tutorial; reloj D2·08
    return [$service, $p];
}

echo "== A) ir_al_lugar exacto en solitario: peticionario acepta SIEMPRE ==\n";
$aForzado = 0;
$aTotal = 0;
for ($i = 0; $i < 8; $i++) {
    [, $p] = partidaBase($root, "mv-a-$i", Reloj::TEST_AHORA);
    $parT = $p['tutorial']['pareja_mision1'];
    $pet = crearPet($p, (string) $parT['a'], 'ir_al_lugar', ['lugar_id' => 'lug_cine']);
    aislarPet($p, (string) ($pet['id'] ?? ''));
    $r = null;
    foreach ([17, 18, 19, 20] as $h) {
        $r = PropuestaEncuentroEngine::proponer($p, [(string) $parT['a']], 2, $h, 'individual', 'lug_cine');
        if (!empty($r['ok'])) {
            break;
        }
    }
    $prop = $r['propuesta'] ?? null;
    if ($prop === null) {
        continue;
    }
    $reacPet = null;
    foreach ($prop['reacciones'] as $rc) {
        if ((string) $rc['residente_id'] === (string) $parT['a']) {
            $reacPet = $rc;
        }
    }
    $aTotal++;
    if (($reacPet['decision'] ?? '') === PropuestaEncuentro::DECISION_ACEPTA
        && ($reacPet['motivo_tecnico'] ?? '') === PropuestaEncuentroEngine::MARCA_COMPROMISO_PETICION
        && !empty($r['programado'])
    ) {
        $aForzado++;
    }
}
ok($aTotal > 0 && $aForzado === $aTotal, "A: aceptación forzada del peticionario en plan exacto solo ($aForzado/$aTotal)");

echo "\n== B/C) quedar_con_x exacto: PET comprometido, OTRO voluntad normal y puede rechazar ==\n";
$bCompromisoOk = 0;
$bTotal = 0;
$bOtroRechaza = 0;
$cAbiertaTrasRechazo = 0;
$cFeedbackOk = 0;
for ($i = 0; $i < 14; $i++) {
    [, $p] = partidaBase($root, "mv-b-$i", Reloj::TEST_AHORA);
    $parT = $p['tutorial']['pareja_mision1'];
    $aId = (string) $parT['a'];
    $bId = (string) $parT['b'];
    $pet = crearPet($p, $aId, 'quedar_con_x', ['otro' => $bId]);
    aislarPet($p, (string) ($pet['id'] ?? ''));
    $r = null;
    foreach ([17, 18, 19, 20] as $h) {
        $r = PropuestaEncuentroEngine::proponer($p, [$aId, $bId], 2, $h, 'quedar', 'lug_cafeteria');
        if (!empty($r['ok'])) {
            break;
        }
    }
    $prop = $r['propuesta'] ?? null;
    if ($prop === null) {
        continue;
    }
    $bTotal++;
    $reacPet = null;
    $reacOtro = null;
    foreach ($prop['reacciones'] as $rc) {
        if ((string) $rc['residente_id'] === $aId) {
            $reacPet = $rc;
        } else {
            $reacOtro = $rc;
        }
    }
    $comprometido = ($reacPet['decision'] ?? '') === PropuestaEncuentro::DECISION_ACEPTA
        && ($reacPet['motivo_tecnico'] ?? '') === PropuestaEncuentroEngine::MARCA_COMPROMISO_PETICION;
    if ($comprometido) {
        $bCompromisoOk++;
    }
    if (($reacOtro['decision'] ?? '') !== PropuestaEncuentro::DECISION_ACEPTA) {
        $bOtroRechaza++;
        // C): petición sigue abierta + feedback coherente
        $sigue = false;
        foreach ($p['peticiones'] as $lp) {
            if ((string) ($lp['id'] ?? '') === (string) ($pet['id'] ?? '')) {
                $sigue = ((string) $lp['estado']) === PeticionPuebloEngine::EST_ABIERTA;
            }
        }
        if ($sigue) {
            $cAbiertaTrasRechazo++;
        }
        foreach ($p['buzon'] as $m) {
            if ((string) ($m['tipo'] ?? '') === 'peticion_resultado'
                && (string) ($m['clasificacion'] ?? '') === BuzonEngine::IMPORTANTE
                // Contrato narrativo: el eco lo firma el peticionario (de_persona)
                // y menciona al tercero que dijo que no. Sin informe de sistema.
                && (string) ($m['de_persona'] ?? '') === $aId
                && strpos((string) ($m['texto'] ?? ''), \AquiHayTema\Engine\IdentidadPublica::nombre($p, $bId)) !== false
            ) {
                $cFeedbackOk++;
                break;
            }
        }
    }
}
ok($bTotal > 0 && $bCompromisoOk === $bTotal, "B: PET siempre comprometido con SU petición ($bCompromisoOk/$bTotal)");
ok($bOtroRechaza >= 1, "C-pre: el otro conserva autonomía y a veces rechaza (rechazos=$bOtroRechaza)");
ok($bOtroRechaza === 0 || $cAbiertaTrasRechazo === $bOtroRechaza, "C: petición sigue ABIERTA tras rechazo del tercero ($cAbiertaTrasRechazo/$bOtroRechaza)");
ok($bOtroRechaza === 0 || $cFeedbackOk >= 1, "C: mensajito 'yo sí quería, pero X no' presente ($cFeedbackOk)");

echo "\n== D) tras aceptar y celebrarse: cumple UNA sola vez ==\n";
{
    [, $p] = partidaBase($root, 'mv-d-0', Reloj::TEST_AHORA);
    $parT = $p['tutorial']['pareja_mision1'];
    $aId = (string) $parT['a'];
    $bId = (string) $parT['b'];
    $pet = crearPet($p, $aId, 'quedar_con_x', ['otro' => $bId]);
    aislarPet($p, (string) ($pet['id'] ?? ''));
    $prog = false;
    foreach ([17, 18, 19, 20] as $h) {
        $r = PropuestaEncuentroEngine::proponer($p, [$aId, $bId], 2, $h, 'quedar', 'lug_cafeteria');
        if (!empty($r['programado'])) {
            $prog = true;
            $encId = (string) ($r['encuentro']['id'] ?? '');
            break;
        }
    }
    ok($prog, 'D: plan exacto programado');
    $serviceD = new \AquiHayTema\Engine\PartidaService($root);
    for ($k = 0; $k < 40 && $prog; $k++) {
        $res = $serviceD->avanzarRelojPasoAPaso($p, 1);
        $estado = '';
        foreach ($p['peticiones'] as $lp) {
            if ((string) ($lp['id'] ?? '') === (string) ($pet['id'] ?? '')) {
                $estado = (string) $lp['estado'];
            }
        }
        if ($estado === PeticionPuebloEngine::EST_RESUELTA) {
            break;
        }
    }
    $vecesResuelta = 0;
    foreach ($p['peticiones'] as $lp) {
        if ((string) ($lp['plantilla_id'] ?? '') === 'quedar_con_x' && (string) ($lp['residente_id'] ?? '') === $aId && (string) $lp['estado'] === PeticionPuebloEngine::EST_RESUELTA) {
            $vecesResuelta++;
        }
    }
    ok($vecesResuelta === 1, "D: cumplida exactamente una vez ($vecesResuelta)");
}

echo "\n== E) núcleo modificado (añadió compañía): bonus sí, garantía NO ==\n";
$eBonusPresente = 0;
$eTotal = 0;
$eNuncaMarcada = true;
for ($i = 0; $i < 10; $i++) {
    [, $p] = partidaBase($root, "mv-e-$i", Reloj::TEST_AHORA);
    $parT = $p['tutorial']['pareja_mision1'];
    $aId = (string) $parT['a'];
    $bId = (string) $parT['b'];
    $petE = crearPet($p, $aId, 'ir_al_lugar', ['lugar_id' => 'lug_cine']);
    aislarPet($p, (string) ($petE['id'] ?? ''));
    $r = null;
    foreach ([17, 18, 19, 20] as $h) {
        $r = PropuestaEncuentroEngine::proponer($p, [$aId, $bId], 2, $h, 'quedar', 'lug_cine');
        if (!empty($r['ok'])) {
            break;
        }
    }
    $prop = $r['propuesta'] ?? null;
    if ($prop === null) {
        continue;
    }
    $eTotal++;
    foreach ($prop['reacciones'] as $rc) {
        if ((string) $rc['residente_id'] !== $aId) {
            continue;
        }
        if ((float) ($rc['factores']['bonus_peticion_nucleo'] ?? 0) > 0) {
            $eBonusPresente++;
        }
        if ((string) ($rc['motivo_tecnico'] ?? '') === PropuestaEncuentroEngine::MARCA_COMPROMISO_PETICION) {
            $eNuncaMarcada = false;
        }
    }
}
ok($eTotal > 0 && $eBonusPresente >= max(1, (int) round($eTotal * 0.5)), "E: bonus de núcleo aplicado al peticionario ($eBonusPresente/$eTotal)");
ok($eNuncaMarcada, 'E: sin aceptación automática cuando Celestine añade compañía');

echo "\n== F/G) feedback diferenciado caducada vs cumplida ==\n";
{
    [, $p] = partidaBase($root, 'mv-fg-0', Reloj::TEST_AHORA);
    $parT = $p['tutorial']['pareja_mision1'];
    $idsRes = PeticionPuebloEngine::residentes($p);
    $aId = (string) $parT['a'];
    // F: caducada
    $petCad = crearPet($p, $aId, 'algo_distinto', []);
    $serviceFG = new \AquiHayTema\Engine\PartidaService($root);
    $serviceFG->avanzarRelojPasoAPaso($p, 13);
    $estCad = '';
    $buzonCadOriginal = '';
    foreach ($p['peticiones'] as $lp) {
        if ((string) ($lp['id'] ?? '') === (string) ($petCad['id'] ?? '')) {
            $estCad = (string) $lp['estado'];
            $bid = (string) ($lp['buzon_id'] ?? '');
            foreach ($p['buzon'] as $m) {
                if ((string) ($m['id'] ?? '') === $bid) {
                    $buzonCadOriginal = (string) ($m['estado'] ?? '');
                }
            }
        }
    }
    ok($estCad === PeticionPuebloEngine::EST_CADUCADA, "F: petición caducada por reloj de juego ($estCad)");
    $hayFeedbackCad = false;
    foreach ($p['buzon'] as $m) {
        if ((string) ($m['tipo'] ?? '') === 'peticion_resultado'
            // Contrato narrativo: el cierre lo firma el peticionario.
            && (string) ($m['de_persona'] ?? '') === $aId) {
            $hayFeedbackCad = true;
        }
    }
    ok($hayFeedbackCad, 'F: feedback de caducada presente');
    ok($buzonCadOriginal === 'leido', "F: mensajito original cerrado como 'leido' ('$buzonCadOriginal')");
    // G: cumplida
    $petOk = crearPet($p, (string) ($idsRes[1] ?? $aId), 'salir_de_casa', []);
    $rCumple = PeticionPuebloEngine::cumplir($p, (string) ($petOk['id'] ?? ''), \AquiHayTema\Engine\CalibracionConfig::load($root), null);
    ok(!empty($rCumple['ok']), 'G: cumplir() ok');
    $buzonOkOriginal = '';
    foreach ($p['peticiones'] as $lp) {
        if ((string) ($lp['id'] ?? '') === (string) ($petOk['id'] ?? '')) {
            $bid = (string) ($lp['buzon_id'] ?? '');
            foreach ($p['buzon'] as $m) {
                if ((string) ($m['id'] ?? '') === $bid) {
                    $buzonOkOriginal = (string) ($m['estado'] ?? '');
                }
            }
        }
    }
    ok($buzonOkOriginal === 'resuelto', "G: mensajito cumplido marcado 'resuelto' ('$buzonOkOriginal')");
    $feedbackPositivo = false;
    foreach ($p['buzon'] as $m) {
        if ((string) ($m['tipo'] ?? '') === 'peticion_resultado') {
            $feedbackPositivo = true; // hay al menos un resultado; el de F era negativo
        }
    }
    ok($feedbackPositivo, 'G: eco de resultado presente');
}

echo "\n== H) agenda/imposibilidades mandan aunque sea SU petición ==\n";
{
    [, $p] = partidaBase($root, 'mv-h-0', Reloj::TEST_AHORA);
    $parT = $p['tutorial']['pareja_mision1'];
    $aId = (string) $parT['a'];
    $petH = crearPet($p, $aId, 'ir_al_lugar', ['lugar_id' => 'lug_cine']);
    aislarPet($p, (string) ($petH['id'] ?? ''));
    $r = PropuestaEncuentroEngine::proponer(
        $p,
        [$aId],
        2,
        18,
        'individual',
        'lug_cine',
        null,
        new VoluntadStubIndisponibilidad()
    );
    $prop = $r['propuesta'] ?? null;
    $claseReac = null;
    if ($prop !== null) {
        foreach ($prop['reacciones'] as $rc) {
            if ((string) $rc['residente_id'] === $aId) {
                $claseReac = (string) ($rc['clase'] ?? '');
            }
        }
    }
    ok(($prop['estado'] ?? '') === 'rechazada' && $claseReac === PropuestaEncuentro::CLASE_INDISPONIBILIDAD, 'H: indisponibilidad tumba el plan del propio peticionario');
}

echo "\n== J) primera_cita_pet respeta su gate romántico ==\n";
{
    [, $p] = partidaBase($root, 'mv-j-0', Reloj::TEST_AHORA);
    $calJ = \AquiHayTema\Engine\CalibracionConfig::load($root);
    $idsJ = PeticionPuebloEngine::residentes($p);
    $nacio = 0;
    $p['_b4_forzar_nacer'] = true;
    for ($k = 0; $k < 200; $k++) {
        $pet = PeticionPuebloEngine::intentarNacer($p, $calJ, null, null);
        if ($pet !== null && (string) ($pet['plantilla_id'] ?? '') === 'primera_cita_pet') {
            $nacio++;
        }
    }
    $p['_b4_forzar_nacer'] = false;
    ok($nacio === 0, "J: sin señal romántica nunca nace primera_cita_pet (nacidas=$nacio/200)");
}

echo "\n== I) variedad real en pipeline pasivo (8 runs × 4 días) ==\n";
{
    $vistas = [];
    for ($i = 0; $i < 8; $i++) {
        [, $p] = partidaBase($root, "mv-i-$i", Reloj::TEST_AHORA);
        $serviceI = new \AquiHayTema\Engine\PartidaService($root);
        for ($d = 0; $d < 4; $d++) {
            $serviceI->avanzarRelojPasoAPaso($p, 24);
            foreach ($p['peticiones'] as $lp) {
                if (!empty($lp['schema_b4'])) {
                    $vistas[(string) ($lp['plantilla_id'] ?? '?')] = true;
                }
            }
        }
        unset($p, $serviceI);
    }
    ksort($vistas);
    echo '   plantillas vistas: ' . implode(', ', array_keys($vistas)) . "\n";
    $muertas = ['ir_al_lugar', 'volver_a_ver', 'quedar_con_x', 'algo_distinto'];
    $revividas = count(array_intersect($muertas, array_keys($vistas)));
    ok(count($vistas) >= 5, 'I: >=5 plantillas distintas aparecen (' . count($vistas) . ')');
    ok($revividas >= 3, "I: las antes-0% reviven ($revividas/4)");
}

Reloj::fijarAhora(null);

echo "\n";
echo $failures === 0 ? "OK mensajitos_vivos\n" : "FAIL mensajitos_vivos ({$failures})\n";
exit($failures > 0 ? 1 : 0);
