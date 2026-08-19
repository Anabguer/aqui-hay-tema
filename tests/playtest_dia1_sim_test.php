<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\DomainBootstrap;
use AquiHayTema\Engine\IdentidadPublica;
use AquiHayTema\Engine\PartidaService;
use AquiHayTema\Engine\ParejaEngine;
use AquiHayTema\Engine\RelacionEngine;
use AquiHayTema\Engine\RelojOperations;

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

function corte(array $partida): array
{
    $emo = ['neutro' => 0, 'alegre' => 0, 'triste' => 0, 'enfadado' => 0];
    foreach ($partida['residentes'] ?? [] as $r) {
        $id = (string) ($r['runtime']['estado_emocional']['id'] ?? 'neutro');
        if (!isset($emo[$id])) {
            $emo[$id] = 0;
        }
        $emo[$id]++;
    }
    $amistades = 0;
    $neg = 0;
    foreach ($partida['residentes'] ?? [] as $a => $_) {
        foreach ($partida['residentes'] ?? [] as $b => $__) {
            if ($a >= $b) {
                continue;
            }
            if (!RelacionEngine::seConocen($partida, (string) $a, (string) $b)) {
                continue;
            }
            $v = RelacionEngine::valorSocialHacia($partida, (string) $a, (string) $b);
            if ($v >= 40) {
                $amistades++;
            }
            if ($v < 0) {
                $neg++;
            }
        }
    }
    $parejas = 0;
    $crisis = 0;
    $ex = 0;
    $ids = array_keys($partida['residentes'] ?? []);
    for ($i = 0; $i < count($ids); $i++) {
        for ($j = $i + 1; $j < count($ids); $j++) {
            $est = ParejaEngine::estado($partida, (string) $ids[$i], (string) $ids[$j]);
            if ($est === ParejaEngine::PAREJA) {
                $parejas++;
            } elseif ($est === ParejaEngine::CRISIS) {
                $crisis++;
            } elseif ($est === ParejaEngine::EX) {
                $ex++;
            }
        }
    }
    $props = $partida['propuestas_encuentro'] ?? [];
    $enc = $partida['encuentros'] ?? [];
    $aut = 0;
    foreach ($enc as $e) {
        if (($e['origen'] ?? '') === 'autonomo' || ($e['origen'] ?? '') === 'npc') {
            $aut++;
        }
    }
    return [
        'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 0),
        'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
        'residentes' => count($partida['residentes'] ?? []),
        'propuestas' => count($props),
        'aceptadas' => count(array_filter($props, static function ($p) {
            return in_array($p['estado'] ?? '', ['aceptada', 'programada'], true);
        })),
        'rechazadas' => count(array_filter($props, static function ($p) {
            return ($p['estado'] ?? '') === 'rechazada';
        })),
        'encuentros' => count($enc),
        'encuentros_terminados' => count(array_filter($enc, static function ($e) {
            return ($e['estado'] ?? '') === 'terminado';
        })),
        'autonomos' => $aut,
        'casuales' => count($partida['historial_coincidencias'] ?? []),
        'descubrimientos' => count($partida['descubrimientos'] ?? $partida['discovery'] ?? []),
        'emociones' => $emo,
        'amistades' => $amistades,
        'negativas' => $neg,
        'parejas' => $parejas,
        'crisis' => $crisis,
        'ex' => $ex,
        'buzon' => count($partida['buzon'] ?? []),
    ];
}

function par(array $partida, string $a, string $b): array
{
    return [
        'conocidos' => RelacionEngine::seConocen($partida, $a, $b) ? 'conocido' : 'desconocido',
        'social' => RelacionEngine::valorSocialHacia($partida, $a, $b),
        'romance' => RelacionEngine::romanceHacia($partida, $a, $b),
        'nombres' => IdentidadPublica::nombre($partida, $a) . ' → ' . IdentidadPublica::nombre($partida, $b),
    ];
}

DomainBootstrap::boot();
$service = new PartidaService($root);
$partida = $service->nuevaPartida('playtest_01', 'playtest-01-sim');
$reloj = new RelojOperations($root, $service->getLogger(), $service->emociones());

$prop = $service->proponerEncuentro($partida, ['per_p001', 'per_p002'], 1, 18, 'conocerse', 'lug_cafeteria');
ok(isset($prop['ok']), 'propuesta inicial Carmen-José ejecutada');
$forced = $service->programarEncuentro($partida, ['per_p001', 'per_p002'], 1, 20, 'conocerse', 'lug_cafeteria');
ok(($forced['ok'] ?? false) || !empty($prop['programado']), 'Carmen-José quedan (propuesta o forzado QA)');

$cortes = [1, 3, 7, 14, 30];
$informe = [];
$pares = [];
$paresIds = [
    ['per_p001', 'per_p002'],
    ['per_p003', 'per_p005'],
    ['per_p008', 'per_p006'],
];

$objetivoHoras = [];
foreach ($cortes as $d) {
    $objetivoHoras[$d] = ($d * 24 + 22) - (1 * 24 + 8);
}

$avanzadas = 0;
foreach ($cortes as $d) {
    $need = $objetivoHoras[$d] - $avanzadas;
    if ($need > 0) {
        $r = $reloj->avanzarPasoAPaso($partida, $need);
        ok(($r['ok'] ?? true) !== false, "avance hasta D$d");
        $avanzadas += $need;
    }
    $informe[$d] = corte($partida);
    foreach ($paresIds as $pair) {
        $pares[$d][$pair[0] . '|' . $pair[1]] = par($partida, $pair[0], $pair[1]);
    }
}

ok($informe[1]['residentes'] === 8, 'D1 sigue con 8');
ok($informe[30]['dia'] >= 30, 'reloj llega a día 30');

echo "\n=== CORTES PLAYTEST DÍA 1 ===\n";
foreach ($informe as $d => $row) {
    echo "D$d " . json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
echo "=== PARES ===\n";
foreach ($pares as $d => $rows) {
    echo "D$d\n";
    foreach ($rows as $row) {
        echo '  ' . $row['nombres'] . ' social ' . $row['social'] . ' romance ' . var_export($row['romance'], true) . ' ' . $row['conocidos'] . PHP_EOL;
    }
}

exit($failures > 0 ? 1 : 0);
