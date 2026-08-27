<?php
declare(strict_types=1);

/**
 * F10 — Ritual contextual (cumpleaños) — tests focalizados.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\MensajitoAcciones;
use AquiHayTema\Engine\MensajitoContextualEngine;
use AquiHayTema\Engine\MensajitoVoz;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\ResidenteCumpleanosEngine;

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

function alinearCumpleHoy(array &$p, string $rid): void
{
    $diaPueblo = (int) ($p['reloj']['dia_pueblo'] ?? 1);
    $fecha = Reloj::fechaDeDia($p['reloj'] ?? [], $diaPueblo);
    $p['residentes'][$rid]['identidad_publica']['cumpleanos'] = [
        'dia' => (int) $fecha->format('j'),
        'mes' => (int) $fecha->format('n'),
    ];
}

function alinearCumpleOtroDia(array &$p, string $rid): void
{
    $diaPueblo = (int) ($p['reloj']['dia_pueblo'] ?? 1);
    $fecha = Reloj::fechaDeDia($p['reloj'] ?? [], $diaPueblo);
    $dia = ((int) $fecha->format('j') % 28) + 1;
    $mes = ((int) $fecha->format('n') % 12) + 1;
    if ($dia === (int) $fecha->format('j') && $mes === (int) $fecha->format('n')) {
        $dia = $dia === 28 ? 1 : $dia + 1;
    }
    $p['residentes'][$rid]['identidad_publica']['cumpleanos'] = ['dia' => $dia, 'mes' => $mes];
}

function contarF10(array $p): int
{
    $n = 0;
    foreach ($p['buzon'] ?? [] as $m) {
        if (!is_array($m)) {
            continue;
        }
        if (($m['familia_mensajito'] ?? '') === 'f_ritual_contextual') {
            $n++;
        }
    }
    return $n;
}

DomainBootstrap::boot();
$cal = CalibracionConfig::load($root);
$catalog = new Catalog($root);
$svc = new PartidaService($root);

// 1. Cumpleaños hoy → F10 generado
$p1 = $svc->nuevaPartida('juego_v1', 'f10-hoy-' . time());
$p1['features']['buzon_enabled'] = true;
$p1['residentes']['per_p002']['identidad_publica']['nombre'] = 'Lucía';
$p1['relaciones']['per_p001']['per_p002'] = ['social' => 40, 'romance' => 0];
alinearCumpleHoy($p1, 'per_p002');
$emit1 = MensajitoContextualEngine::evaluarAlComenzarDia($p1, $cal, $catalog);
ok(count($emit1) === 1, 'F10: genera mensaje si cumple hoy');
ok(contarF10($p1) === 1, 'F10: un mensaje en buzón');
$msgF10 = null;
foreach ($p1['buzon'] as $m) {
    if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'f_ritual_contextual') {
        $msgF10 = $m;
        break;
    }
}
ok($msgF10 !== null, 'F10: mensaje localizado');
ok(($msgF10['datos_familia']['cumpleanero_id'] ?? '') === 'per_p002', 'F10: cumpleañero correcto');
ok(strpos((string) ($msgF10['texto'] ?? ''), 'Lucía') !== false || strpos((string) ($msgF10['texto'] ?? ''), 'cumple') !== false, 'F10: copy contextual');

// 2. Otro día → no F10
$p2 = $svc->nuevaPartida('juego_v1', 'f10-otro-' . time());
$p2['features']['buzon_enabled'] = true;
$p2['relaciones']['per_p001']['per_p002'] = ['social' => 40, 'romance' => 0];
alinearCumpleOtroDia($p2, 'per_p002');
$emit2 = MensajitoContextualEngine::evaluarAlComenzarDia($p2, $cal, $catalog);
ok(count($emit2) === 0 && contarF10($p2) === 0, 'F10: no genera si no es cumple hoy');

// 3. Reevaluación → no duplicado
$p3 = $svc->nuevaPartida('juego_v1', 'f10-dedup-' . time());
$p3['features']['buzon_enabled'] = true;
$p3['relaciones']['per_p001']['per_p002'] = ['social' => 40, 'romance' => 0];
alinearCumpleHoy($p3, 'per_p002');
MensajitoContextualEngine::evaluarAlComenzarDia($p3, $cal, $catalog);
MensajitoContextualEngine::evaluarAlComenzarDia($p3, $cal, $catalog);
ok(contarF10($p3) === 1, 'F10: dedup en reevaluación');

// 4. Save antiguo sin campo → carga sin error
$p4 = $svc->nuevaPartida('juego_v1', 'f10-legacy-' . time());
$ridLegacy = (string) array_key_first($p4['residentes'] ?? []);
unset($p4['residentes'][$ridLegacy]['identidad_publica']['cumpleanos']);
$c1 = ResidenteCumpleanosEngine::obtener($p4, $ridLegacy, $catalog);
$c2 = ResidenteCumpleanosEngine::obtener($p4, $ridLegacy, $catalog);
ok($c1 !== null && $c2 !== null, 'F10: save antiguo asigna cumpleaños lazy');
ok($c1['dia'] === $c2['dia'] && $c1['mes'] === $c2['mes'], 'F10: asignación estable al recargar');

// 5. Copy canónico en voz
$linea = MensajitoVoz::linea($p1, 'f_ritual_cumple_aviso', ['otro' => 'Lucía'], 'test', 'per_p001');
ok($linea !== '' && strpos($linea, 'Lucía') !== false, 'F10: banco voz aviso');

// 6. Respuesta participar + sin pareja accidental
$p6 = $svc->nuevaPartida('juego_v1', 'f10-resp-' . time());
$p6['features']['buzon_enabled'] = true;
$p6['relaciones']['per_p001']['per_p002'] = ['social' => 40, 'romance' => 0];
alinearCumpleHoy($p6, 'per_p002');
MensajitoContextualEngine::evaluarAlComenzarDia($p6, $cal, $catalog);
$msgId6 = '';
foreach ($p6['buzon'] as $m) {
    if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'f_ritual_contextual') {
        $msgId6 = (string) ($m['id'] ?? '');
        break;
    }
}
$nProps = count($p6['propuestas_encuentro'] ?? []);
$r6 = MensajitoAcciones::resolver($p6, $msgId6, MensajitoAcciones::PARTICIPAR_CUMPLE, $root);
ok($r6['ok'] ?? false, 'F10: participar ok');
ok(count($p6['propuestas_encuentro'] ?? []) === $nProps, 'F10: participar no crea pareja');

// 7. Organizar devuelve preset
$p7 = $svc->nuevaPartida('juego_v1', 'f10-org-' . time());
$p7['features']['buzon_enabled'] = true;
$p7['relaciones']['per_p001']['per_p002'] = ['social' => 40, 'romance' => 0];
alinearCumpleHoy($p7, 'per_p002');
MensajitoContextualEngine::evaluarAlComenzarDia($p7, $cal, $catalog);
$msgId7 = '';
foreach ($p7['buzon'] as $m) {
    if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'f_ritual_contextual') {
        $msgId7 = (string) ($m['id'] ?? '');
        break;
    }
}
$r7 = MensajitoAcciones::resolver($p7, $msgId7, MensajitoAcciones::ORGANIZAR_CUMPLE, $root);
ok($r7['ok'] ?? false && isset($r7['preset_organizar']), 'F10: organizar devuelve preset');

// 8. Ignorar cierra hilo
$p8 = $svc->nuevaPartida('juego_v1', 'f10-ign-' . time());
$p8['features']['buzon_enabled'] = true;
$p8['relaciones']['per_p001']['per_p002'] = ['social' => 40, 'romance' => 0];
alinearCumpleHoy($p8, 'per_p002');
MensajitoContextualEngine::evaluarAlComenzarDia($p8, $cal, $catalog);
$msgId8 = '';
foreach ($p8['buzon'] as $m) {
    if (is_array($m) && ($m['familia_mensajito'] ?? '') === 'f_ritual_contextual') {
        $msgId8 = (string) ($m['id'] ?? '');
        break;
    }
}
$r8 = MensajitoAcciones::resolver($p8, $msgId8, MensajitoAcciones::IGNORAR_CONTEXTUAL, $root);
ok($r8['ok'] ?? false, 'F10: ignorar ok');
$fin8 = BuzonEngine::buscar($p8, $msgId8);
ok(($fin8['estado_decision'] ?? '') === BuzonEngine::DECISION_RESUELTO, 'F10: decisión resuelta');

echo "\n" . ($failures === 0 ? 'TODOS OK' : "$failures FALLOS") . "\n";
exit($failures === 0 ? 0 : 1);
