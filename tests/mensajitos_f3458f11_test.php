<?php
declare(strict_types=1);

/**
 * Cierre funcional Mensajitos 2.0 — familias F3, F5, F8, F11.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoAcciones;
use AquiHayTema\Engine\MensajitoDudaPermanenciaEngine;
use AquiHayTema\Engine\MensajitoGeneradorEspontaneo;
use AquiHayTema\Engine\MensajitoMediacionEngine;
use AquiHayTema\Engine\MensajitoPeticionEngine;
use AquiHayTema\Engine\MensajitoVoz;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionEsquemas;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RngService;

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

function primerResidente(array $partida): ?string
{
    foreach ($partida['residentes'] ?? [] as $id => $r) {
        if (is_array($r) && ($r['presencia'] ?? '') === 'residente') {
            return (string) $id;
        }
    }
    return null;
}

function segundoResidente(array $partida, string $exclude): ?string
{
    foreach ($partida['residentes'] ?? [] as $id => $r) {
        if ((string) $id === $exclude) {
            continue;
        }
        if (is_array($r) && ($r['presencia'] ?? '') === 'residente') {
            return (string) $id;
        }
    }
    return null;
}

DomainBootstrap::boot();
$cal = CalibracionConfig::load($root);
$svc = new PartidaService($root);

// --- F3: petición/encargo con familia y preset ---
$p3 = $svc->nuevaPartida('juego_v1', 'f3-' . time());
$p3['features']['buzon_enabled'] = true;
$p3['features']['peticiones_pueblo_enabled'] = true;
$a3 = primerResidente($p3);
$b3 = $a3 !== null ? segundoResidente($p3, $a3) : null;
ok($a3 !== null && $b3 !== null, 'F3: hay pareja de residentes');
if ($a3 !== null && $b3 !== null) {
    foreach ($p3['peticiones'] as &$lp) {
        if ((string) ($lp['residente_id'] ?? '') === $a3) {
            $lp['estado'] = PeticionPuebloEngine::EST_CADUCADA;
        }
    }
    unset($lp);
    $p3['relaciones'][$a3][$b3] = ['social' => 30, 'romance' => 0];
    $res3 = PeticionEngine::crear($p3, $a3, 'tiempo', [
        'schema_b4' => true,
        'plantilla_id' => 'quedar_con_x',
        'familia' => 'quedar',
        'params' => ['otro' => $b3],
        'texto' => 'Quiero quedar con alguien del pueblo.',
        'hecho' => 'Organizar un Quedar.',
        'peso' => PeticionEsquemas::PESO_RELEVANTE,
        'plazo_horas' => 24,
        '_placeholder_copy' => false,
    ]);
    ok($res3['ok'] ?? false, 'F3: petición creada');
    $pet3 = $res3['peticion'] ?? null;
    if (is_array($pet3)) {
        $msg3 = BuzonEngine::buscar($p3, (string) ($pet3['buzon_id'] ?? ''));
        ok(($msg3['familia_mensajito'] ?? '') === MensajitoPeticionEngine::F3, 'F3: familia_mensajito');
        ok(trim((string) ($msg3['texto'] ?? '')) !== '', 'F3: copy con contexto');
        $ui3 = BuzonEngine::enriquecerParaUi($msg3 ?? [], $p3);
        ok(count($ui3['acciones_ui'] ?? []) >= 1, 'F3: CTA organizar');
        $r3 = MensajitoAcciones::resolver($p3, (string) ($msg3['id'] ?? ''), MensajitoAcciones::ORGANIZAR_ENCARGO, $root);
        ok(($r3['ok'] ?? false) && isset($r3['preset_organizar']), 'F3: preset organizar');
    }
}

// --- F5: presentación / selector ---
$p5 = $svc->nuevaPartida('juego_v1', 'f5-' . time());
$p5['features']['buzon_enabled'] = true;
$p5['features']['peticiones_pueblo_enabled'] = true;
$a5 = primerResidente($p5);
ok($a5 !== null, 'F5: hay peticionario');
if ($a5 !== null) {
    foreach ($p5['peticiones'] as &$lp5) {
        if ((string) ($lp5['residente_id'] ?? '') === $a5) {
            $lp5['estado'] = PeticionPuebloEngine::EST_CADUCADA;
        }
    }
    unset($lp5);
    $pet5 = PeticionPuebloEngine::nacerConocer($p5, $a5, $cal);
    ok($pet5 !== null, 'F5: petición conocer nacida');
    if ($pet5 !== null) {
        $msg5 = BuzonEngine::buscar($p5, (string) ($pet5['buzon_id'] ?? ''));
        ok(($msg5['familia_mensajito'] ?? '') === MensajitoPeticionEngine::F5, 'F5: familia_mensajito');
        $datos5 = is_array($msg5['datos_familia'] ?? null) ? $msg5['datos_familia'] : [];
        $tieneSelector = !empty($msg5['selector_opciones']) || (($datos5['subtipo'] ?? '') === 'selector');
        $tieneOtro = !empty($datos5['otro_id']);
        ok($tieneSelector || $tieneOtro, 'F5: candidato concreto o selector');
        if ($tieneSelector) {
            ok(in_array(MensajitoAcciones::ELEGIR_PERSONA, $msg5['acciones'] ?? [], true), 'F5: acción elegir persona');
        }
    }
}

// --- F8: duda permanencia + escalada ---
$p8 = $svc->nuevaPartida('juego_v1', 'f8-' . time());
$p8['features']['buzon_enabled'] = true;
$a8 = primerResidente($p8);
ok($a8 !== null, 'F8: hay residente');
if ($a8 !== null) {
    $p8['reloj']['dia_pueblo'] = 6;
    $p8['residentes'][$a8]['runtime']['ultimo_contacto_social_dia'] = 3;
    $gen8 = MensajitoDudaPermanenciaEngine::evaluarAlCerrarDia($p8, $cal);
    ok($gen8 !== null && ($gen8['ok'] ?? false), 'F8: genera duda permanencia');
    $msg8 = null;
    foreach ($p8['buzon'] as $m) {
        if (is_array($m) && ($m['familia_mensajito'] ?? '') === MensajitoDudaPermanenciaEngine::FAMILIA) {
            $msg8 = $m;
            break;
        }
    }
    ok($msg8 !== null, 'F8: mensaje en buzón');
    if ($msg8 !== null) {
        ok(strlen(trim((string) ($msg8['texto'] ?? ''))) > 15, 'F8: copy con cuerpo');
        $r8 = MensajitoAcciones::resolver($p8, (string) $msg8['id'], MensajitoAcciones::NO_METERSE, $root);
        ok($r8['ok'] ?? false, 'F8: ignorar cierra');
        ok(!empty($p8['mensajitos_duda_permanencia'][$a8]['escalada']), 'F8: marca escalada');
        ok(MensajitoDudaPermanenciaEngine::factorEscaladaMarcha($p8, $a8) > 1.0, 'F8: factor escalada marcha');
    }
}

// --- F11: mediación tras conflicto ---
$p11 = $svc->nuevaPartida('juego_v1', 'f11-' . time());
$p11['features']['buzon_enabled'] = true;
$p11['features']['mensajitos_espontaneos_enabled'] = true;
$a11 = primerResidente($p11);
$b11 = $a11 !== null ? segundoResidente($p11, $a11) : null;
ok($a11 !== null && $b11 !== null, 'F11: hay pareja en conflicto');
if ($a11 !== null && $b11 !== null) {
    $p11['reloj']['dia_pueblo'] = 10;
    RelacionBitacora::registrar($p11, RelacionBitacora::DISCUSION_FUERTE, [$a11, $b11]);
    ok(MensajitoMediacionEngine::candidato($p11, $a11) !== null, 'F11: candidato mediación');
    $gen11 = MensajitoGeneradorEspontaneo::evaluar($p11, $a11, $cal, new RngService('f11'));
    ok($gen11 !== null && ($gen11['familia_mensajito'] ?? '') === MensajitoMediacionEngine::FAMILIA, 'F11: generador produce familia');
    if ($gen11 !== null) {
        $ui11 = BuzonEngine::enriquecerParaUi($gen11, $p11);
        ok(count($ui11['acciones_ui'] ?? []) >= 2, 'F11: CTAs mediar/consejo');
        $r11 = MensajitoAcciones::resolver($p11, (string) $gen11['id'], MensajitoAcciones::MEDIAR_REPARAR, $root);
        ok(($r11['ok'] ?? false) && isset($r11['preset_organizar']), 'F11: mediar devuelve preset');
    }
}

// --- Voz: familias registradas ---
$voces = MensajitoVoz::familias();
foreach ([MensajitoPeticionEngine::F3, MensajitoPeticionEngine::F5, MensajitoDudaPermanenciaEngine::FAMILIA, MensajitoMediacionEngine::FAMILIA] as $fam) {
    ok(in_array($fam, $voces, true), "voz: familia $fam registrada");
}

echo "\n";
echo $failures === 0 ? "OK mensajitos_f3458f11\n" : "FAIL mensajitos_f3458f11 ({$failures})\n";
exit($failures > 0 ? 1 : 0);
