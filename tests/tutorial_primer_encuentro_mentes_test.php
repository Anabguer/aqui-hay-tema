<?php
declare(strict_types=1);

/**
 * Tutorial — primer encuentro integrado con MENTES (contrato celeste_organizado).
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\EncuentroIntervencion;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\IniciativaSocial;
use AquiHayTema\Engine\MisionDiariaEngine;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\PropuestaNivel;
use AquiHayTema\Engine\Reloj;
use AquiHayTema\Engine\TutorialPrimerosPasos;

DomainBootstrap::boot();
$root = dirname(__DIR__);
$fail = 0;

function ok(bool $c, string $m): void
{
    global $fail;
    echo ($c ? 'OK' : 'FAIL') . ': ' . $m . PHP_EOL;
    if (!$c) {
        $fail++;
    }
}

function proponerParejaTutorial(array &$partida, string $a, string $b): ?array
{
    $diaBase = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
    for ($d = $diaBase; $d <= $diaBase + 2; $d++) {
        $hMin = ($d === $diaBase) ? max(8, (int) ($partida['reloj']['hora_actual'] ?? 0) + 1) : 8;
        for ($h = $hMin; $h < 22; $h++) {
            if (!Reloj::esFuturo($partida['reloj'] ?? [], $d, $h)) {
                continue;
            }
            $r = PropuestaEncuentroEngine::proponer($partida, [$a, $b], $d, $h, PropuestaNivel::PRESENTAR, 'lug_cafeteria');
            if (!empty($r['ok']) && !empty($r['programado'])) {
                return $r;
            }
        }
    }
    return null;
}

function proponerQuedarPareja(array &$partida, string $a, string $b): ?array
{
    $diaBase = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
    for ($d = $diaBase; $d <= $diaBase + 2; $d++) {
        $hMin = ($d === $diaBase) ? ((int) ($partida['reloj']['hora_actual'] ?? 0) + 1) : 8;
        for ($h = $hMin; $h < 22; $h++) {
            if (!Reloj::esFuturo($partida['reloj'] ?? [], $d, $h)) {
                continue;
            }
            $r = PropuestaEncuentroEngine::proponer($partida, [$a, $b], $d, $h, PropuestaNivel::QUEDAR, 'lug_cafeteria');
            if (!empty($r['ok']) && !empty($r['programado'])) {
                return $r;
            }
        }
    }
    return null;
}

function avanzarHastaEncuentro(array &$partida, PartidaService $svc, ?array $enc): void
{
    if ($enc === null) {
        return;
    }
    $target = (int) ($enc['dia'] ?? 0) * 24 + (int) ($enc['hora'] ?? 0);
    for ($i = 0; $i < 96; $i++) {
        $now = (int) ($partida['reloj']['dia_pueblo'] ?? 1) * 24 + (int) ($partida['reloj']['hora_actual'] ?? 0);
        if ($now >= $target) {
            return;
        }
        $svc->avanzarReloj($partida, 1);
    }
}

function encuentroParejaTutorial(array $partida, string $a, string $b): ?array
{
    $par = [$a, $b];
    sort($par);
    foreach ($partida['encuentros'] ?? [] as $enc) {
        if (!is_array($enc)) {
            continue;
        }
        $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
        sort($parts);
        if ($parts === $par && count($parts) === 2) {
            return $enc;
        }
    }
    return null;
}

$svc = new PartidaService($root);
$p = $svc->nuevaPartida('juego_v1', 'tutorial-mentes-fixture');

ok(($p['tutorial']['id'] ?? '') === TutorialPrimerosPasos::ID, '1. tutorial primeros_pasos arrancado');

$pareja = $p['tutorial']['pareja_mision1'];
$a = (string) $pareja['a'];
$b = (string) $pareja['b'];

/* Autonomía social no debe adelantarse a M1 sobre la pareja del tutorial */
$cal = \AquiHayTema\Engine\CalibracionConfig::load($root);
$rng = \AquiHayTema\Engine\RngService::fromPartida($p);
$bloq = IniciativaSocial::intentarQuedada($p, $a, $b, $cal, $svc->getCatalog(), $rng);
ok(($bloq['resultado'] ?? '') === 'tutorial_reserva_pareja_m1', '2. iniciativa social bloqueada sobre pareja M1');

/* Pasar el rato antes de organizar: no debe nacer conocerse autónomo del par tutorial */
for ($i = 0; $i < 3; $i++) {
    $svc->avanzarReloj($p, 1);
}
$autonomosPar = 0;
foreach ($p['encuentros'] ?? [] as $enc) {
    if (!is_array($enc)) {
        continue;
    }
    $parts = is_array($enc['participantes'] ?? null) ? $enc['participantes'] : [];
    sort($parts);
    $par = [$a, $b];
    sort($par);
    if ($parts !== $par || count($parts) !== 2) {
        continue;
    }
    if (($enc['intencion'] ?? '') !== 'celeste_organizado') {
        $autonomosPar++;
    }
}
ok($autonomosPar === 0, '3. sin encuentro autónomo del par tutorial antes de M1');

$r1 = proponerParejaTutorial($p, $a, $b);
ok($r1 !== null, '4. propuesta M1 programada');
$enc1 = encuentroParejaTutorial($p, $a, $b);
ok($enc1 !== null, '4b. encuentro pareja tutorial creado');
ok(($enc1['intencion'] ?? '') === 'celeste_organizado', '4c. intencion celeste_organizado');
ok(($enc1['tipo'] ?? '') === PropuestaNivel::PRESENTAR, '4d. tipo conocerse');
ok((string) ($enc1['lugar'] ?? '') === 'lug_cafeteria', '4e. lugar cafeteria');

avanzarHastaEncuentro($p, $svc, $enc1);
EncuentroLifecycle::sincronizarConReloj($p, null, $svc->getCatalog());
$enc1 = encuentroParejaTutorial($p, $a, $b);
ok(($enc1['estado'] ?? '') === 'en_curso', '5. primer encuentro en_curso');

$iv1 = $enc1 !== null
    ? EncuentroIntervencion::vistaParaPlay($p, $enc1, $svc->getCatalog())
    : ['disponible' => false, 'acciones' => []];
ok(!empty($iv1['disponible']), '6. MENTES disponible en primer encuentro tutorial');
ok(count($iv1['acciones'] ?? []) >= 1, '6b. acciones de intervencion presentes');

$est = $svc->estadoResumido($p);
ok(is_array($est['encuentros_en_curso'] ?? null), '7. estado expone encuentros_en_curso');
$enCol = false;
foreach ($est['encuentros_en_curso'] ?? [] as $row) {
    if (($row['id'] ?? '') === ($enc1['id'] ?? '')) {
        $enCol = !empty($row['intervencion']['disponible']);
    }
}
ok($enCol, '7b. coleccion incluye intervencion disponible del primer encuentro');

/* M1 cumplida tras propuesta aceptada */
$m1 = '';
foreach ($p['misiones_diarias']['items'] ?? [] as $m) {
    if (($m['id'] ?? '') === TutorialPrimerosPasos::M1) {
        $m1 = (string) ($m['estado'] ?? '');
    }
}
ok($m1 === MisionDiariaEngine::EST_CUMPLIDA, '8. tutorial M1 cumplida');

/* Segundo encuentro normal (quedar) sigue con MENTES */
if ($enc1 === null) {
    echo "\ntutorial_primer_encuentro_mentes_test FAIL ($fail)\n";
    exit(1);
}
EncuentroLifecycle::sincronizarConReloj($p, null, $svc->getCatalog());
$finEnc1 = (int) ($enc1['dia'] ?? 1) * 24 + (int) ($enc1['hora'] ?? 0) + \AquiHayTema\Engine\LugarAtributos::horasDeEncuentro($enc1);
while (((int) ($p['reloj']['dia_pueblo'] ?? 1) * 24 + (int) ($p['reloj']['hora_actual'] ?? 0)) < $finEnc1) {
    $svc->avanzarReloj($p, 1);
}
$r2 = null;
$antesSegundo = 0;
for ($intento = 0; $intento < 8 && $r2 === null; $intento++) {
    $antesSegundo = count($p['encuentros'] ?? []);
    $r2 = proponerQuedarPareja($p, $a, $b);
    if ($r2 === null) {
        $svc->avanzarReloj($p, 2);
        EncuentroLifecycle::sincronizarConReloj($p, null, $svc->getCatalog());
    }
}
ok($r2 !== null, '9. segundo encuentro quedar programado');
ok(count($p['encuentros'] ?? []) === $antesSegundo + 1, '9b. sin duplicar encuentros al proponer quedar');

$enc2Id = (string) ($r2['propuesta']['encuentro_id'] ?? '');
$enc2 = null;
foreach ($p['encuentros'] ?? [] as $enc) {
    if (($enc['id'] ?? '') === $enc2Id) {
        $enc2 = $enc;
    }
}
ok($enc2 !== null && ($enc2['intencion'] ?? '') === 'celeste_organizado', '9c. segundo encuentro celeste_organizado');

avanzarHastaEncuentro($p, $svc, $enc2);
EncuentroLifecycle::sincronizarConReloj($p, null, $svc->getCatalog());
foreach ($p['encuentros'] ?? [] as $enc) {
    if (($enc['id'] ?? '') === $enc2Id) {
        $enc2 = $enc;
    }
}
ok($enc2 !== null && ($enc2['estado'] ?? '') === 'en_curso', '9d. segundo encuentro en_curso');
$iv2 = $enc2 !== null
    ? EncuentroIntervencion::vistaParaPlay($p, $enc2, $svc->getCatalog())
    : ['disponible' => false];
ok(!empty($iv2['disponible']), '10. MENTES disponible en segundo encuentro');

echo $fail === 0 ? "\ntutorial_primer_encuentro_mentes_test OK\n" : "\ntutorial_primer_encuentro_mentes_test FAIL ($fail)\n";
exit($fail === 0 ? 0 : 1);
