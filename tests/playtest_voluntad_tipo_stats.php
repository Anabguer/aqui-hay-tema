<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\PropuestaEncuentroEngine;
use AquiHayTema\Engine\RelacionBitacora;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\Voluntad\VoluntadPonderadaEvaluator;

$root = dirname(__DIR__);
$service = new PartidaService($root);
$calBase = CalibracionConfig::load($root);
$n = 60;
$bonos = [28, 34, 40];
$a = 'per_p001';
$b = 'per_p002';

/**
 * @param array<string, mixed> $cal
 */
function conBono(array $cal, int $bono): array
{
    $cal['voluntad']['mod_tipo'] = [
        'conocerse' => $bono,
        'quedar' => 0,
        'primera_cita' => 0,
        'cita' => 0,
    ];
    return $cal;
}

/**
 * @param array<string, mixed> $partida
 * @param array<string, mixed> $cal
 * @return array<string, mixed>
 */
function proponerCon(array &$partida, array $cal, array $ids, string $tipo): array
{
    $ev = new VoluntadPonderadaEvaluator($cal);
    return PropuestaEncuentroEngine::proponer(
        $partida,
        $ids,
        1,
        18,
        $tipo,
        'lug_cafeteria',
        null,
        $ev
    );
}

function aceptada(array $r): bool
{
    if (!empty($r['rechazada'])) {
        return false;
    }
    if (($r['ok'] ?? true) === false) {
        return false;
    }
    if (($r['propuesta']['estado'] ?? '') === 'rechazada') {
        return false;
    }
    return true;
}

$acc = [];
$scores = [];
foreach ($bonos as $bono) {
    foreach (['conocerse', 'quedar', 'primera_cita', 'cita'] as $esc) {
        $acc[$bono][$esc] = ['ok' => 0, 'ko' => 0];
        $scores[$bono][$esc] = [];
    }
}

$prop0 = ['participantes' => [$a, $b], 'tipo' => 'conocerse', 'lugar' => 'lug_cafeteria'];
$scoreAntes = null;

for ($i = 0; $i < $n; $i++) {
    $p0 = $service->nuevaPartida('playtest_01', 'vol-tipo-' . $i);
    if ($scoreAntes === null) {
        $scoreAntes = VoluntadPonderadaEvaluator::score($p0, $prop0, $a, $b, $calBase);
    }
    foreach ($bonos as $bono) {
        $cal = conBono($calBase, $bono);

        $pK = $p0;
        $rK = proponerCon($pK, $cal, [$a, $b], 'conocerse');
        if (aceptada($rK)) {
            $acc[$bono]['conocerse']['ok']++;
        } else {
            $acc[$bono]['conocerse']['ko']++;
        }
        foreach (($rK['propuesta']['reacciones'] ?? []) as $reac) {
            if (isset($reac['score'])) {
                $scores[$bono]['conocerse'][] = (int) $reac['score'];
            }
        }

        $pQ = $p0;
        RelacionEngine::registrarContacto($pQ, $a, $b, 'normal', $cal);
        RelacionEngine::registrarContacto($pQ, $b, $a, 'normal', $cal);
        $rQ = proponerCon($pQ, $cal, [$a, $b], 'quedar');
        if (aceptada($rQ)) {
            $acc[$bono]['quedar']['ok']++;
        } else {
            $acc[$bono]['quedar']['ko']++;
        }

        $pP = $p0;
        RelacionEngine::registrarContacto($pP, $a, $b, 'normal', $cal);
        RelacionEngine::registrarContacto($pP, $b, $a, 'normal', $cal);
        RelacionEngine::setRomanceHacia($pP, $a, $b, 22);
        $rP = proponerCon($pP, $cal, [$a, $b], 'primera_cita');
        if (aceptada($rP)) {
            $acc[$bono]['primera_cita']['ok']++;
        } else {
            $acc[$bono]['primera_cita']['ko']++;
        }

        $pC = $p0;
        RelacionEngine::registrarContacto($pC, $a, $b, 'normal', $cal);
        RelacionEngine::registrarContacto($pC, $b, $a, 'normal', $cal);
        RelacionEngine::setRomanceHacia($pC, $a, $b, 22);
        RelacionBitacora::registrar($pC, RelacionBitacora::PRIMERA_CITA, [$a, $b]);
        $rC = proponerCon($pC, $cal, [$a, $b], 'cita');
        if (aceptada($rC)) {
            $acc[$bono]['cita']['ok']++;
        } else {
            $acc[$bono]['cita']['ko']++;
        }
    }
}

echo "=== CANDIDATOS BONUS CONOCERSE (n={$n} Carmen-José, sin tocar base) ===\n";
echo "score_conocerse_antes (sin bonus, 1 seed)={$scoreAntes}\n";
echo "fórmula: score += voluntad.mod_tipo.{tipo}; p=p_min+(score/100)*(p_max-p_min); nunca 100%\n";
foreach ($bonos as $bono) {
    echo "\n-- bonus conocerse = +{$bono} --\n";
    foreach (['conocerse', 'quedar', 'primera_cita', 'cita'] as $esc) {
        $ok = $acc[$bono][$esc]['ok'];
        $ko = $acc[$bono][$esc]['ko'];
        $tot = $ok + $ko;
        $pct = $tot > 0 ? round(100 * $ok / $tot, 1) : 0.0;
        $avg = $scores[$bono][$esc] !== []
            ? round(array_sum($scores[$bono][$esc]) / count($scores[$bono][$esc]), 1)
            : '-';
        echo "{$esc}: n={$tot} aceptadas={$ok} rechazadas={$ko} pct_conjunto={$pct}% score_medio={$avg}\n";
    }
}
echo "base=" . json_encode(CalibracionConfig::get($calBase, 'voluntad.base', null))
    . " p_min=" . json_encode(CalibracionConfig::get($calBase, 'voluntad.p_min', null))
    . " p_max=" . json_encode(CalibracionConfig::get($calBase, 'voluntad.p_max', null)) . "\n";
exit(0);
