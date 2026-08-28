<?php
declare(strict_types=1);

/**
 * E2E Mensajitos 2.0: caminos reales → cadencia/dedup → buzón → acciones → save.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\FeatureConfig;
use AquiHayTema\Engine\MensajitoAcciones;
use AquiHayTema\Engine\MensajitoDudaPermanenciaEngine;
use AquiHayTema\Engine\MensajitoGeneradorEspontaneo;
use AquiHayTema\Engine\MensajitoMediacionEngine;
use AquiHayTema\Engine\MensajitoPeticionEngine;
use AquiHayTema\Engine\MensajitosCadenciaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PeticionEngine;
use AquiHayTema\Engine\PeticionEsquemas;
use AquiHayTema\Engine\PeticionPuebloEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelojOperations;
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

DomainBootstrap::boot();
$cal = CalibracionConfig::load($root);
$svc = new PartidaService($root);
$reloj = new RelojOperations($root);

// --- Flag canónico ON en partida juego_v1 ---
$p = $svc->nuevaPartida('juego_v1', 'mensajitos-e2e-int-' . time());
ok(FeatureConfig::isEnabled($p, 'mensajitos_espontaneos_enabled'), 'flag espontaneos ON en juego_v1');
ok(FeatureConfig::isEnabled($p, 'buzon_enabled'), 'buzon ON');

// --- F3 camino real PeticionEngine ---
$p3 = $p;
$p3['features']['peticiones_pueblo_enabled'] = true;
$rid3 = PeticionPuebloEngine::residentes($p3)[0] ?? null;
ok($rid3 !== null, 'F3: hay residente');
if ($rid3 !== null) {
    $b3 = null;
    foreach (PeticionPuebloEngine::residentes($p3) as $c) {
        if ($c !== $rid3) {
            $b3 = $c;
            break;
        }
    }
    ok($b3 !== null, 'F3: hay otro residente');
    if ($b3 !== null) {
        $p3['relaciones'][$rid3][$b3] = ['social' => 30, 'romance' => 0];
        $res3 = PeticionEngine::crear($p3, $rid3, 'tiempo', [
            'schema_b4' => true,
            'plantilla_id' => 'quedar_con_x',
            'familia' => 'quedar',
            'params' => ['otro' => $b3],
            'texto' => '¿Podemos quedar esta semana?',
            'hecho' => 'Organizar un Quedar.',
            'peso' => PeticionEsquemas::PESO_RELEVANTE,
            'plazo_horas' => 24,
            '_placeholder_copy' => false,
        ]);
        $msg3 = BuzonEngine::buscar($p3, (string) (($res3['peticion']['buzon_id'] ?? '')));
        ok(($msg3['familia_mensajito'] ?? '') === MensajitoPeticionEngine::F3, 'F3: familia en buzón');
        $nAntes = count($p3['buzon'] ?? []);
        $rOrg = MensajitoAcciones::resolver(
            $p3,
            (string) ($msg3['id'] ?? ''),
            MensajitoAcciones::ORGANIZAR_ENCARGO,
            $root
        );
        ok(($rOrg['ok'] ?? false) && isset($rOrg['preset_organizar']), 'F3: Organizar devuelve preset');
        $msg3b = BuzonEngine::buscar($p3, (string) ($msg3['id'] ?? ''));
        ok(BuzonEngine::tieneDecisionPendiente($msg3b ?? []), 'F3: decisión sigue pendiente tras Organizar');
        ok(count($p3['buzon'] ?? []) === $nAntes, 'F3: refresh no duplica buzón');
    }
}

// --- F5 camino real conocer ---
$p5 = $svc->nuevaPartida('juego_v1', 'mensajitos-e2e-f5-' . time());
$p5['features']['peticiones_pueblo_enabled'] = true;
$a5 = null;
foreach (PeticionPuebloEngine::residentes($p5) as $cand) {
    if (count(PeticionPuebloEngine::presentablesParaConocer($p5, $cand)) >= 1) {
        $a5 = $cand;
        break;
    }
}
if ($a5 !== null) {
    foreach ($p5['peticiones'] as &$lp5) {
        if ((string) ($lp5['residente_id'] ?? '') === $a5) {
            $lp5['estado'] = PeticionPuebloEngine::EST_CADUCADA;
        }
    }
    unset($lp5);
    $pet5 = PeticionPuebloEngine::nacerConocer($p5, $a5, $cal);
    $msg5 = BuzonEngine::buscar($p5, (string) (($pet5['buzon_id'] ?? '')));
    ok(($msg5['familia_mensajito'] ?? '') === MensajitoPeticionEngine::F5, 'F5: familia presentación');
}

// --- F8 camino real al cerrar día ---
$p8 = $svc->nuevaPartida('juego_v1', 'mensajitos-e2e-f8-' . time());
$p8['features']['buzon_enabled'] = true;
$rid8 = PeticionPuebloEngine::residentes($p8)[0] ?? null;
ok($rid8 !== null, 'F8: hay residente');
if ($rid8 !== null) {
    $p8['reloj']['dia_pueblo'] = 6;
    $p8['residentes'][$rid8]['runtime']['ultimo_contacto_social_dia'] = 3;
    $gen8 = MensajitoDudaPermanenciaEngine::evaluarAlCerrarDia($p8, $cal, null);
    ok($gen8 !== null && ($gen8['ok'] ?? false), 'F8: evaluar al cerrar día');
    $nF8 = 0;
    foreach ($p8['buzon'] as $m) {
        if (is_array($m) && ($m['familia_mensajito'] ?? '') === MensajitoDudaPermanenciaEngine::FAMILIA) {
            $nF8++;
        }
    }
    ok($nF8 === 1, 'F8: un mensaje en buzón');
    MensajitoDudaPermanenciaEngine::evaluarAlCerrarDia($p8, $cal, null);
    $nF8b = 0;
    foreach ($p8['buzon'] as $m) {
        if (is_array($m) && ($m['familia_mensajito'] ?? '') === MensajitoDudaPermanenciaEngine::FAMILIA) {
            $nF8b++;
        }
    }
    ok($nF8b === 1, 'F8: segundo tick no duplica');
}

// --- F11 camino real tras conflicto ---
$p11 = $svc->nuevaPartida('juego_v1', 'mensajitos-e2e-f11-' . time());
$p11['features']['mensajitos_espontaneos_enabled'] = true;
$rid11 = PeticionPuebloEngine::residentes($p11)[0] ?? null;
$otro11 = null;
foreach (PeticionPuebloEngine::residentes($p11) as $c) {
    if ($c !== $rid11) {
        $otro11 = $c;
        break;
    }
}
ok($rid11 !== null && $otro11 !== null, 'F11: hay pareja');
if ($rid11 !== null && $otro11 !== null) {
    $p11['reloj']['dia_pueblo'] = 10;
    RelacionBitacora::registrar($p11, RelacionBitacora::DISCUSION_FUERTE, [$rid11, $otro11]);
    $c11 = MensajitoMediacionEngine::candidato($p11, $rid11);
    ok($c11 !== null, 'F11: candidato tras discusión');
    if ($c11 !== null) {
        $pub11 = MensajitoGeneradorEspontaneo::publicar(
            $p11,
            $rid11,
            array_merge($c11, ['residente_id' => $rid11]),
            $cal,
            new RngService('f11-e2e')
        );
        ok($pub11 !== null, 'F11: publicado en buzón');
        $dup11 = MensajitoGeneradorEspontaneo::publicar(
            $p11,
            $rid11,
            array_merge($c11, ['residente_id' => $rid11]),
            $cal,
            new RngService('f11-e2e-dup')
        );
        ok($dup11 === null, 'F11: dedup bloquea segunda copia');
    }
}

// --- Cadencia día + catch-up no duplica ---
$pDay = $svc->nuevaPartida('juego_v1', 'mensajitos-e2e-day-' . time());
$pDay['features']['mensajitos_espontaneos_enabled'] = true;
$pDay['reloj'] = ['dia_pueblo' => 5, 'hora_actual' => 22];
$antes = count($pDay['buzon'] ?? []);
$reloj->avanzar($pDay, 4, []);
$despues = count($pDay['buzon'] ?? []);
ok($despues >= $antes, 'espontaneos: avance día puede crear mensajes');
$nb = count($pDay['buzon'] ?? []);
$espAntes = 0;
foreach ($pDay['buzon'] ?? [] as $m) {
    if (is_array($m) && str_starts_with((string) ($m['tipo'] ?? ''), 'espontaneo_')) {
        $espAntes++;
    }
}
$reloj->avanzar($pDay, 24, ['catch_up' => true]);
$espDespues = 0;
foreach ($pDay['buzon'] ?? [] as $m) {
    if (is_array($m) && str_starts_with((string) ($m['tipo'] ?? ''), 'espontaneo_')) {
        $espDespues++;
    }
}
ok($espDespues === $espAntes, 'catch-up: no genera espontaneos extra');

// --- Save/load compat ---
$pid = (string) ($pDay['meta']['partida_id'] ?? '');
if ($pid !== '') {
    $svc->guardar($pDay);
    $loaded = $svc->cargar($pid);
    ok(is_array($loaded['mensajitos_cola'] ?? null) || !isset($loaded['mensajitos_cola']) || $loaded['mensajitos_cola'] === [], 'save: cola compatible');
    ok(FeatureConfig::isEnabled($loaded, 'mensajitos_espontaneos_enabled'), 'save: flag persiste');
}

echo "\n";
echo $failures === 0 ? "OK mensajitos_e2e_integracion\n" : "FAIL mensajitos_e2e_integracion ({$failures})\n";
exit($failures > 0 ? 1 : 0);
