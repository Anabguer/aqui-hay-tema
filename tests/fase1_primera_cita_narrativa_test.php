<?php
declare(strict_types=1);

// FASE 1 ┬À Micro-test de integraci├│n narrativa ÔÇö la primera cita aut├│noma
// (intencion='autonomo_npc') fluye por los sistemas can├│nicos ya existentes:
//   - cotilleo en `buzon` (EncuentroCotilleoCopy via BuzonPlayBridge, ENCUENTRO_TERMINADO)
//   - hito PRIMERA_CITA ├║nico en RelacionBitacora (canon: al resolver)
// No inventa UI ni mec├ínica nueva: protege el comportamiento derivado real.

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\IniciativaRomantica;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;

$fail = 0;
function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $fail++;
    }
}

$root = dirname(__DIR__);
$cal = CalibracionConfig::load($root);

/** Partida real (pipeline completo) con se├▒al A->B fijada. */
function partidaConSenal(PartidaService $service, string $a, string $b, string $seed): array
{
    $p = $service->nuevaPartida('playtest_01', $seed);
    $p['features']['buzon_enabled'] = true;
    $p['features']['discovery_enabled'] = true;
    foreach ([[$a, $b], [$b, $a]] as [$x, $y]) {
        $resto = 30;
        while ($resto > 0) {
            $d = min(10, $resto);
            RelacionEngine::ajustarSocialHacia($p, $x, $y, $d);
            $resto -= $d;
        }
    }
    RelacionEngine::setRomanceHacia($p, $a, $b, 28);
    return $p;
}

$service = new PartidaService($root);
$ids = array_keys($service->nuevaPartida('playtest_01', 'fase1-narrativa')['residentes'] ?? []);
ok(count($ids) >= 2, 'hay residentes en la partida real');

// --- Selecci├│n determinista de pareja elegible (gates can├│nicos) ---
$parElegido = null;
$seedBase = 'fase1-narrativa';
$buscaPar = static function () use ($service, $ids, $seedBase, $cal): ?array {
    foreach ($ids as $a) {
        foreach ($ids as $b) {
            if ($a === $b || $a === '' || $b === '') {
                continue;
            }
            $p = partidaConSenal($service, $a, $b, $seedBase);
            $r = IniciativaRomantica::intentarPrimeraCita($p, $a, $b, $cal);
            $res = (string) ($r['resultado'] ?? '');
            if (str_contains($res, 'no_elegible') || str_contains($res, 'parentesco_veto') || str_contains($res, 'ya_pareja')) {
                continue;
            }
            return [$a, $b];
        }
    }
    return null;
};
$parElegido = $buscaPar();
ok($parElegido !== null, 'existe pareja elegible con senal (gates canonicos)');
if ($parElegido === null) {
    exit(1);
}
[$A, $B] = $parElegido;

// --- Scan determinista del estado rng hasta aceptaci├│n mutua ---
$stOk = 0;
for ($s = 1; $s <= 120; $s++) {
    $p = partidaConSenal($service, $A, $B, $seedBase);
    $p['rng']['state'] = $s;
    $r = IniciativaRomantica::intentarPrimeraCita($p, $A, $B, $cal);
    if ((string) ($r['resultado'] ?? '') === 'primera_cita_agendada') {
        $stOk = $s;
        break;
    }
}
ok($stOk > 0, "estado rng con aceptacion mutua (state=$stOk)");

$p = partidaConSenal($service, $A, $B, $seedBase);
$p['rng']['state'] = $stOk;
$r = IniciativaRomantica::intentarPrimeraCita($p, $A, $B, $cal);
ok((string) ($r['resultado'] ?? '') === 'primera_cita_agendada', 'primera cita autonoma AGENDADA');
$encId = '';
foreach (($p['encuentros'] ?? []) as $e) {
    if (($e['tipo'] ?? '') === 'primera_cita' && in_array(($e['estado'] ?? ''), ['programado', 'pendiente'], true)) {
        $encId = (string) ($e['id'] ?? '');
        ok(($e['intencion'] ?? '') === 'autonomo_npc', 'intencion=autonomo_npc (control)');
        break;
    }
}
ok($encId !== '', 'encuentro primera_cita localizado');

// --- Resoluci├│n can├│nica: avanzar el reloj hasta terminar la cita ---
$enc = null;
foreach (($p['encuentros'] ?? []) as $e) {
    if (($e['id'] ?? '') === $encId) {
        $enc = $e;
        break;
    }
}
$dur = max(1, (int) ($enc['duracion_horas'] ?? 1));
$finAbs = ((int) $enc['dia']) * 24 + (int) $enc['hora'] + $dur;
$nowAbs = ((int) $p['reloj']['dia_pueblo']) * 24 + (int) $p['reloj']['hora_actual'];
$adv = $service->avanzarRelojPasoAPaso($p, max(1, $finAbs - $nowAbs));
ok((bool) ($adv['ok'] ?? false), 'cita resuelta avanzando el reloj canonico');

// --- Visibilidad narrativa can├│nica ---
$hito = RelacionBitacora::entre($p, $A, $B, RelacionBitacora::PRIMERA_CITA);
ok(count($hito) === 1, 'hito PRIMERA_CITA unico registrado al resolver');

$cotis = array_values(array_filter(
    $p['buzon'] ?? [],
    static fn ($m) => is_array($m)
        && ($m['clasificacion'] ?? '') === BuzonEngine::COTILLEO
        && str_contains((string) (($m['origen']['evento_id'] ?? '')), $encId)
));
ok(count($cotis) >= 1, 'cotilleo del encuentro autonomo en buzon');
if (count($cotis) >= 1) {
    $c = $cotis[0];
    $actores = is_array($c['actores'] ?? null) ? $c['actores'] : [];
    ok(in_array($A, $actores, true) && in_array($B, $actores, true), 'actores del cotilleo = los dos citados');
    ok(trim((string) ($c['texto'] ?? '')) !== '', 'texto de cotilleo no vacio');
    ok(($c['origen']['tipo_evento'] ?? '') === 'encuentro_terminado', 'origen encuentro_terminado');
}

$logOk = false;
foreach (($p['iniciativa_romantica_log'] ?? []) as $row) {
    if (($row['resultado'] ?? '') === 'primera_cita_agendada' && in_array($A, [$row['desde'] ?? '', $row['hacia'] ?? ''], true)) {
        $logOk = true;
        break;
    }
}
ok($logOk, 'traza primera_cita_agendada en iniciativa_romantica_log');

echo $fail === 0 ? "\nOK fase1_primera_cita_narrativa\n" : "\nFAIL fase1_primera_cita_narrativa ($fail)\n";
exit($fail === 0 ? 0 : 1);
