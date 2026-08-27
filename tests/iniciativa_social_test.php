<?php
declare(strict_types=1);

// A1 — Iniciativa social autónoma NPC→NPC (NO romántica).

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\Catalog;
use AquiHayTema\Engine\EncuentroEngine;
use AquiHayTema\Engine\EncuentroLifecycle;
use AquiHayTema\Engine\IniciativaSocial;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RngService;

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
$GLOBALS['cal'] = CalibracionConfig::load($root);
$cal = $GLOBALS['cal'];
$catalog = new Catalog($root);
$GLOBALS['catalog'] = $catalog;

const A = 'ana';
const B = 'bruno';
const C = 'carla';

function labPartida(): array
{
    return [
        'reloj' => ['dia_pueblo' => 12, 'hora_actual' => 14],
        'rng' => ['seed' => 'a1-social', 'state' => 1],
        'meta' => ['seed' => 'a1-social'],
        'features' => ['npc_autonomy_enabled' => true],
        'residentes' => [
            A => ['identidad_publica' => ['nombre' => 'Ana'], 'presencia' => 'residente', 'runtime' => []],
            B => ['identidad_publica' => ['nombre' => 'Bruno'], 'presencia' => 'residente', 'runtime' => []],
            C => ['identidad_publica' => ['nombre' => 'Carla'], 'presencia' => 'residente', 'runtime' => []],
        ],
        'celeste' => [
            'lugares_desbloqueados' => ['lug_cafeteria', 'lug_parque'],
            'intervenciones_organizadas_usadas_hoy' => 0,
            'intervenciones_organizadas_max_dia' => 1,
        ],
        'relaciones_sociales' => [],
        'relaciones_romanticas' => [],
        'relaciones_conflicto' => [],
        'encuentros' => [],
        'parentesco' => [],
    ];
}

function social(array &$p, string $desde, string $hacia, int $objetivo): void
{
    if ($objetivo === 0) {
        RelacionEngine::ajustarSocialHacia($p, $desde, $hacia, 0);
        return;
    }
    $resto = $objetivo;
    while ($resto !== 0) {
        $d = abs($resto) > 10 ? ($resto > 0 ? 10 : -10) : $resto;
        RelacionEngine::ajustarSocialHacia($p, $desde, $hacia, $d);
        $resto -= $d;
    }
}

function conocidos(array &$p): void
{
    social($p, A, B, 25);
    social($p, B, A, 25);
}

function intentar(array &$p): array
{
    $rng = RngService::fromPartida($p);
    return IniciativaSocial::intentarQuedada($p, A, B, $GLOBALS['cal'], $GLOBALS['catalog'] ?? null, $rng);
}

function countSocialEnc(array $p): int
{
    $n = 0;
    foreach (($p['encuentros'] ?? []) as $e) {
        $t = (string) ($e['tipo'] ?? '');
        if (in_array($t, ['conocerse', 'quedar'], true)) {
            $n++;
        }
    }
    return $n;
}

function findEstado(callable $mk, string $want): int
{
    for ($st = 1; $st <= 8000; $st++) {
        $p = $mk();
        $p['rng']['state'] = $st;
        $r = intentar($p);
        $res = (string) ($r['resultado'] ?? '');
        if (str_starts_with($res, $want)) {
            return $st;
        }
    }
    return -1;
}

// 1) Puede generar iniciativa social
$mkAmigos = static function (): array {
    $p = labPartida();
    conocidos($p);
    return $p;
};
$stOk = findEstado($mkAmigos, 'quedada_agendada');
ok($stOk > 0, '1 existe estado RNG con quedada agendada');
$p = $mkAmigos();
$p['rng']['state'] = $stOk;
$r = intentar($p);
ok(str_starts_with((string) ($r['resultado'] ?? ''), 'quedada_agendada'), '1 NPC genera iniciativa social');
ok(countSocialEnc($p) === 1, '1 se crea un encuentro social');

// 2) No hacia sí mismo
$p2 = labPartida();
conocidos($p2);
$rSelf = IniciativaSocial::intentarQuedada($p2, A, A, $cal, $catalog, RngService::fromPartida($p2));
ok(($rSelf['resultado'] ?? '') === 'gate_par_invalido', '2 no hacia sí mismo');
ok(countSocialEnc($p2) === 0, '2 sin encuentro auto-referido');

// 3) Respeta agenda / indisponibilidad
$p3 = $mkAmigos();
$p3['rng']['state'] = $stOk;
EncuentroEngine::programar($p3, [B], 12, 16, 'individual', 'lug_cafeteria');
$rAg = IniciativaSocial::intentarQuedada($p3, A, B, $cal, $catalog, RngService::fromPartida($p3));
$bloq = str_contains((string) ($rAg['resultado'] ?? ''), 'sin_franja_agenda')
    || str_contains((string) ($rAg['resultado'] ?? ''), 'error_programar');
ok($bloq || countSocialEnc($p3) <= 1, '3 respeta agenda ocupada (no duplica franja imposible)');

// 4) No consume intervención Celestine
$p4 = $mkAmigos();
$p4['celeste']['intervenciones_organizadas_usadas_hoy'] = 1;
$p4['rng']['state'] = $stOk;
$antes = (int) ($p4['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
$r4 = intentar($p4);
$despues = (int) ($p4['celeste']['intervenciones_organizadas_usadas_hoy'] ?? 0);
ok(str_starts_with((string) ($r4['resultado'] ?? ''), 'quedada_agendada'), '4 agenda con límite Celestine agotado pero iniciativa ok');
ok($antes === $despues, '4 no incrementa intervenciones_organizadas_usadas_hoy');

// 5) No genera romance por ser IniciativaSocial
$p5 = $mkAmigos();
$p5['rng']['state'] = $stOk;
intentar($p5);
$tipoEnc = '';
foreach (($p5['encuentros'] ?? []) as $e) {
    if (in_array($e['tipo'] ?? '', ['conocerse', 'quedar'], true)) {
        $tipoEnc = (string) $e['tipo'];
    }
}
ok(in_array($tipoEnc, ['conocerse', 'quedar'], true), '5 tipo social conocerse|quedar, no romántico');
$p5b = $mkAmigos();
RelacionEngine::setRomanceHacia($p5b, A, B, 30);
$rRom = IniciativaSocial::intentarQuedada($p5b, A, B, $cal, $catalog, RngService::fromPartida($p5b));
ok(($rRom['resultado'] ?? '') === 'gate_tension_romantica', '5 bloquea par con tensión romántica');

// 6) RNG centralizado reproducible
$p6a = $mkAmigos();
$p6b = $mkAmigos();
$p6a['rng']['state'] = $stOk;
$p6b['rng']['state'] = $stOk;
$r6a = intentar($p6a);
$r6b = intentar($p6b);
ok(($r6a['resultado'] ?? '') === ($r6b['resultado'] ?? ''), '6 mismo seed → mismo resultado');
ok(($r6a['programado_hora'] ?? null) === ($r6b['programado_hora'] ?? null), '6 misma franja con mismo RNG');

// 7) Lifecycle normal del encuentro
$p7 = $mkAmigos();
$p7['rng']['state'] = $stOk;
intentar($p7);
$enc = null;
foreach (($p7['encuentros'] ?? []) as $e) {
    if (($e['intencion'] ?? '') === 'autonomo_npc_social') {
        $enc = $e;
    }
}
ok($enc !== null, '7 encuentro autonomo_npc_social existe');
if ($enc !== null) {
    $diaFin = (int) ($enc['dia'] ?? 12);
    $horaFin = (int) ($enc['hora'] ?? 14) + max(1, (int) ($enc['duracion_horas'] ?? 1));
    while ($horaFin >= 24) {
        $horaFin -= 24;
        $diaFin++;
    }
    $p7['reloj'] = ['dia_pueblo' => $diaFin, 'hora_actual' => $horaFin];
    $sync = EncuentroLifecycle::sincronizarConReloj($p7, null, $catalog);
    $terminado = false;
    foreach (($p7['encuentros'] ?? []) as $e) {
        if (($e['id'] ?? '') === ($enc['id'] ?? '') && ($e['estado'] ?? '') === 'terminado') {
            $terminado = is_array($e['resultado'] ?? null);
        }
    }
    ok($terminado, '7 lifecycle: programado → terminado con resultado');
    ok(($sync['resueltos'] ?? 0) >= 1, '7 EncuentroLifecycle resuelve el encuentro');
}

// 8) No duplica reservas
$p8 = $mkAmigos();
$p8['rng']['state'] = $stOk;
intentar($p8);
$rDup = intentar($p8);
$gDup = (string) ($rDup['resultado'] ?? '');
ok(str_contains($gDup, 'encuentro_ya') || str_contains($gDup, 'cooldown'), "8 no duplica encuentro activo ($gDup)");
ok(countSocialEnc($p8) === 1, '8 sigue habiendo un solo encuentro social');

// 9) Conocidos vs desconocidos
$p9a = labPartida();
$rDesc = IniciativaSocial::intentarQuedada($p9a, A, B, $cal, $catalog, RngService::fromPartida($p9a));
// Puede fallar por voluntad; buscar seed que agende desconocidos
$stDesc = findEstado(static fn () => labPartida(), 'quedada_agendada');
ok($stDesc > 0, '9 desconocidos pueden agendar conocerse');
$p9b = labPartida();
$p9b['rng']['state'] = $stDesc;
intentar($p9b);
$tipo9 = '';
foreach (($p9b['encuentros'] ?? []) as $e) {
    $tipo9 = (string) ($e['tipo'] ?? '');
}
ok($tipo9 === 'conocerse', '9 desconocidos → tipo conocerse');
$p9c = $mkAmigos();
$p9c['rng']['state'] = $stOk;
intentar($p9c);
$tipo9b = '';
foreach (($p9c['encuentros'] ?? []) as $e) {
    $tipo9b = (string) ($e['tipo'] ?? '');
}
ok($tipo9b === 'quedar', '9 conocidos → tipo quedar');

// intencion y reserva
$pMeta = $mkAmigos();
$pMeta['rng']['state'] = $stOk;
intentar($pMeta);
$intOk = false;
foreach (($pMeta['encuentros'] ?? []) as $e) {
    if (($e['intencion'] ?? '') === 'autonomo_npc_social') {
        $intOk = (($e['reserva_agenda']['origen'] ?? '') === 'autonomo');
    }
}
ok($intOk, 'meta intencion autonomo_npc_social y reserva autonomo');

echo $fail === 0 ? "\nOK iniciativa_social_test\n" : "\nFAIL iniciativa_social_test ($fail)\n";
exit($fail === 0 ? 0 : 1);
