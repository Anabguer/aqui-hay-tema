<?php
declare(strict_types=1);

/**
 * H1 — La seleccion social no debe tratar |social| como intensidad favorable.
 *
 * Contrato deseado:
 *  - social > 0  => bonus de peso (mantiene ventaja).
 *  - social = 0  => peso base.
 *  - social < 0  => SIN bonus por magnitud (sigue siendo elegible, sin veto).
 *
 * Antes del fix: abs(social) hacia que -90 pesara igual que +90.
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\InteraccionCasual;
use AquiHayTema\Engine\MotorVidaDiaria;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\RngService;
use AquiHayTema\Engine\RelacionEngine;

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
$svc = new PartidaService($root);
$cal = CalibracionConfig::load($root);

/** Fija social dirigido alcanzando el valor objetivo (techo de +/-10 por contacto). */
function fijarSocial(array &$p, string $desde, string $hacia, int $objetivo, array $cal): void
{
    for ($i = 0; $i < 40; $i++) {
        $actual = RelacionEngine::valorSocialHacia($p, $desde, $hacia);
        if ($actual === $objetivo) {
            if (!RelacionEngine::seConocen($p, $desde, $hacia)) {
                RelacionEngine::ajustarSocialHacia($p, $desde, $hacia, 0, $cal);
            }
            return;
        }
        $paso = $objetivo > $actual ? min(10, $objetivo - $actual) : max(-10, $objetivo - $actual);
        RelacionEngine::ajustarSocialHacia($p, $desde, $hacia, $paso, $cal);
    }
    if (RelacionEngine::valorSocialHacia($p, $desde, $hacia) !== $objetivo) {
        throw new RuntimeException('no se alcanzo el social objetivo');
    }
}

/** Partida con 3 residentes: social dirigido r1->r2 = $soc12 y r1->r3 = $soc13. */
function fixture(PartidaService $svc, array $cal, int $soc12, int $soc13): array
{
    $p = $svc->nuevaPartida('juego_v1', 'h1-signo-fixture');
    $ids = array_keys($p['residentes']);
    fijarSocial($p, $ids[0], $ids[1], $soc12, $cal);
    fijarSocial($p, $ids[0], $ids[2], $soc13, $cal);
    return ['partida' => $p, 'ids' => $ids];
}

function clonar(array $f): array
{
    return ['partida' => unserialize(serialize($f['partida'])), 'ids' => $f['ids']];
}

$mPares = new ReflectionMethod(InteraccionCasual::class, 'paresPonderados');
$mPares->setAccessible(true);
$mEv = new ReflectionMethod(MotorVidaDiaria::class, 'elegirEvento');
$mEv->setAccessible(true);

/** Posicion (0-based) de un par en el orden de intento; null si no aparece. */
function posicionPar(array $orden, string $a, string $b): ?int
{
    foreach ($orden as $i => $par) {
        if ($par[0] === $a && $par[1] === $b) {
            return $i;
        }
    }
    return null;
}

// ---------- A/C/F: el negativo NO compite con el positivo por magnitud ----------
$f = fixture($svc, $cal, 30, -90);
[$r1, $r2, $r3] = $f['ids'];
$rng = new RngService('h1-orden', 424242);
$orden = $mPares->invoke(null, $f['partida'], $f['ids'], $rng, $cal);
$posPos = posicionPar($orden, $r1, $r2);
$posNeg = posicionPar($orden, $r1, $r3);
ok($posPos !== null && $posNeg !== null, 'ambos pares siguen elegibles en paresPonderados');
ok($posPos < $posNeg, 'H1: par con social +30 ordena ANTES que par con social -90 (antes del fix ganaba -90 por magnitud)');

// ---------- E: conocido positivo conserva ventaja sobre desconocido ----------
$f2 = fixture($svc, $cal, 30, 0);
$rng = new RngService('h1-orden2', 424242);
$orden2 = $mPares->invoke(null, $f2['partida'], $f2['ids'], $rng, $cal);
$pPos2 = posicionPar($orden2, $f2['ids'][0], $f2['ids'][1]);
$pDesconocido = posicionPar($orden2, $f2['ids'][1], $f2['ids'][2]);
ok($pPos2 !== null && $pDesconocido !== null && $pPos2 < $pDesconocido, 'conocido +30 conserva ventaja sobre el par desconocido');

// ---------- B: social negativo ya no supera al positivo pequeno ni empuja por intensidad ----------
$f3 = fixture($svc, $cal, 5, -20);
$rng = new RngService('h1-base', 777);
$orden3 = $mPares->invoke(null, $f3['partida'], $f3['ids'], $rng, $cal);
$pPos3 = posicionPar($orden3, $f3['ids'][0], $f3['ids'][1]);
$pNeg3 = posicionPar($orden3, $f3['ids'][0], $f3['ids'][2]);
ok($pPos3 !== null && $pNeg3 !== null && $pPos3 < $pNeg3, 'social +5 ordena antes que -20 (antes del fix -20 empujaba mas)');
$f4 = fixture($svc, $cal, 0, -8);
$rng = new RngService('h1-base2', 777);
$orden4 = $mPares->invoke(null, $f4['partida'], $f4['ids'], $rng, $cal);
$pBase = posicionPar($orden4, $f4['ids'][0], $f4['ids'][1]);
$pNeg4 = posicionPar($orden4, $f4['ids'][0], $f4['ids'][2]);
ok($pBase !== null && $pNeg4 !== null && $pBase < $pNeg4, 'par base 0 ordena antes que -8 (sin bonus por magnitud)');

// ---------- elegirEvento: frecuencias deterministas ----------
$item = ['id' => 'ev_h1_vida', 'familia' => 'vida', 'participantes' => 2];
$f5 = fixture($svc, $cal, 60, -60);
$p5 = $f5['partida'];
[$prot, $socioPos, $socioNeg] = $f5['ids'];
$nIter = 900;
$cnt = ['pos' => 0, 'neg' => 0];
$secuencia = [];
for ($i = 0; $i < $nIter; $i++) {
    $rngI = new RngService('h1-elegir', 100000 + $i * 7919);
    /** @var array{id:string,participantes:list<string>}|null $res */
    $res = $mEv->invoke(null, $p5, [$item], $prot, $cal, $rngI);
    $secuencia[] = $res === null ? '' : ($res['id'] . ':' . implode('|', $res['participantes']));
    if ($res === null) {
        continue;
    }
    $otro = (string) $res['participantes'][1];
    if ($otro === $socioPos) {
        $cnt['pos']++;
    } elseif ($otro === $socioNeg) {
        $cnt['neg']++;
    }
}
ok($cnt['pos'] > 0 && $cnt['neg'] > 0, 'positivo y negativo siguen siendo elegibles como companeros (sin veto)');
ok($cnt['pos'] > $cnt['neg'] * 1.5, "H1: +60 gana mas veces que -60 (pos={$cnt['pos']} neg={$cnt['neg']}); antes del fix empataban");

// ---------- J: determinismo misma seed/estado ----------
$rngJ1 = new RngService('h1-det', 999);
$rngJ2 = new RngService('h1-det', 999);
$o1 = $mPares->invoke(null, $f5['partida'], $f5['ids'], $rngJ1, $cal);
$o2 = $mPares->invoke(null, $f5['partida'], $f5['ids'], $rngJ2, $cal);
ok($o1 === $o2 && $rngJ1->getState() === $rngJ2->getState(), 'paresPonderados determinista (salida y estado RNG)');

$f6 = clonar($f5);
$p6 = $f6['partida'];
$sec2 = [];
for ($i = 0; $i < $nIter; $i++) {
    $rngI = new RngService('h1-elegir', 100000 + $i * 7919);
    $res = $mEv->invoke(null, $p6, [$item], $f6['ids'][0], $cal, $rngI);
    $sec2[] = $res === null ? '' : ($res['id'] . ':' . implode('|', $res['participantes']));
}
ok($secuencia === $sec2, 'elegirEvento determinista: misma seed => misma secuencia completa');

// ---------- G/H/I: conflicto, quimica y emocion intactos (probabilidadPar ignora signo social) ----------
$fPos = fixture($svc, $cal, 60, 0);
$fNeg = fixture($svc, $cal, -60, 0);
$aP = $fPos['ids'][0];
$bP = $fPos['ids'][1];
$aN = $fNeg['ids'][0];
$bN = $fNeg['ids'][1];
$pProbPos = InteraccionCasual::probabilidadPar($fPos['partida'], $aP, $bP, $cal);
$pProbNeg = InteraccionCasual::probabilidadPar($fNeg['partida'], $aN, $bN, $cal);
ok(abs($pProbPos - $pProbNeg) < 1e-12, 'probabilidadPar no depende del signo del social (formula intacta)');

foreach ($fPos['partida']['residentes'] as &$resTmp) {
    $resTmp['runtime']['estado_emocional']['id'] = 'triste';
}
unset($resTmp);
foreach ($fNeg['partida']['residentes'] as &$resTmp) {
    $resTmp['runtime']['estado_emocional']['id'] = 'triste';
}
unset($resTmp);
$pTristePos = InteraccionCasual::probabilidadPar($fPos['partida'], $aP, $bP, $cal);
$pTristeNeg = InteraccionCasual::probabilidadPar($fNeg['partida'], $aN, $bN, $cal);
ok($pTristePos < $pProbPos, 'emocion triste reduce probabilidad (canal emocion intacto)');
ok(abs($pTristePos - $pTristeNeg) < 1e-12, 'con emocion, probabilidadPar sigue sin depender del signo social');

RelacionEngine::upsertConflicto($fNeg['partida'], $aN, $bN, 3);
$pConfNeg = InteraccionCasual::probabilidadPar($fNeg['partida'], $aN, $bN, $cal);
ok(abs($pConfNeg - $pTristeNeg) < 1e-12, 'conflicto activo no altera probabilidadPar (evitacion solo en seleccion)');

echo "\nH1 signo-social: " . ($failures === 0 ? 'TODO OK' : "$failures FALLOS") . "\n";
exit($failures > 0 ? 1 : 0);
