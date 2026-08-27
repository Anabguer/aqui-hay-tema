<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\BuzonEngine;
use AquiHayTema\Engine\VidaNarrativaBridge;
use AquiHayTema\Engine\VistaCotilleoV3;

$failures = 0;

function ok(bool $c, string $m): void
{
    global $failures;
    echo ($c ? 'OK' : 'FAIL') . ": $m\n";
    if (!$c) {
        $failures++;
    }
}

// Partida base
$partida = [
    'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 10],
    'features' => ['buzon_enabled' => true],
    'buzon' => [],
    'residentes' => [
        'per_antonio' => ['identidad_publica' => ['nombre' => 'Antonio']],
        'per_raul' => ['identidad_publica' => ['nombre' => 'Raúl']],
        'per_ines' => ['identidad_publica' => ['nombre' => 'Inés']],
    ],
];

// (1) Llega vecino nuevo -> cotilleo importante en el canal cotilleo
$r1 = BuzonEngine::crear($partida, [
    'clasificacion' => BuzonEngine::COTILLEO,
    'tipo' => 'llegada_pueblo',
    'texto' => 'Ha llegado Antonio. El pueblo gana un residente más.',
]);
ok((bool) ($r1['ok'] ?? false), 'llegada vecino nuevo crea cotilleo');

$vista = VistaCotilleoV3::de($partida);
ok(($vista['importantes_sin_ver'] ?? -1) === 1, '(2-3) badge = 1 tras importante nuevo');
ok(count($vista['hoy']) === 1 && $vista['hoy'][0]['destacado'] === true, 'llegada marcada como destacado');

// (4-5) Cotilleo normal no suma
BuzonEngine::crear($partida, [
    'clasificacion' => BuzonEngine::COTILLEO,
    'tipo' => 'cotilleo',
    'texto' => 'Raúl e Inés coincidieron varias veces en la plaza.',
    'cotilleo_meta' => ['categoria' => 'coincidencias', 'destacado' => false],
]);
$vista = VistaCotilleoV3::de($partida);
ok(($vista['importantes_sin_ver'] ?? -1) === 1, '(5) badge sigue = 1 tras cotilleo normal');

// (6) Otro importante: discusión fuerte
BuzonEngine::crear($partida, [
    'clasificacion' => BuzonEngine::COTILLEO,
    'tipo' => 'discusion',
    'texto' => 'Raúl e Inés se han enfadado.',
]);
$vista = VistaCotilleoV3::de($partida);
ok(($vista['importantes_sin_ver'] ?? -1) === 2, '(7) badge = 2 tras segundo importante');

// Encontrar trabajo también es importante (regresión del flip)
$p2 = $partida;
$rT = VidaNarrativaBridge::alAcontecimiento(
    $p2,
    'encontrar_trabajo',
    ['per_ines'],
    ['visibilidad_jugador' => 'pueblo', 'importancia' => 'relevante'],
    []
);
$meta = is_array($rT) ? ($rT['cotilleo_meta'] ?? null) : null;
ok(is_array($meta) && ($meta['destacado'] ?? false) === true, 'encontrar_trabajo queda como importante');
$vistaT = VistaCotilleoV3::de($p2);
ok(($vistaT['importantes_sin_ver'] ?? -1) === 3, 'encontrar_trabajo suma al contador');

// (8) El jugador abre Cotilleos: se consultan los importantes visibles
function idsImportantesDeFeed(array $vista): array
{
    $ids = [];
    foreach (['hoy', 'ayer', 'viejos'] as $bucket) {
        foreach ($vista[$bucket] ?? [] as $e) {
            if (($e['destacado'] ?? false) === true && (string) ($e['id'] ?? '') !== '') {
                $ids[] = (string) $e['id'];
            }
        }
    }
    return $ids;
}

$idsVisibles = idsImportantesDeFeed($vista);
ok(count($idsVisibles) === 2, '(8) feed visible tiene 2 importantes sin consultar');
$rVisto = VistaCotilleoV3::marcarVistas($partida, $idsVisibles);
ok((bool) ($rVisto['ok'] ?? false) && ($rVisto['importantes_sin_ver'] ?? -1) === 0, '(9) badge = 0 tras abrir Cotilleos');
ok(($rVisto['marcados'] ?? 0) === 2, 'se marcan exactamente los 2 importantes');

// Estado persistido en partida
$vistosGuardados = $partida['cotilleo_vistos'] ?? [];
ok(count(array_intersect($idsVisibles, $vistosGuardados)) === 2, 'visto/no visto persistido en partida.cotilleo_vistos');

// (10) Los eventos siguen en el feed y en su sitio
$vistaPost = VistaCotilleoV3::de($partida);
$totalAntes = count($vista['hoy']) + count($vista['ayer']) + count($vista['viejos']);
$totalDespues = count($vistaPost['hoy']) + count($vistaPost['ayer']) + count($vistaPost['viejos']);
ok($totalAntes === 3 && $totalDespues === 3, '(10) los eventos siguen en el feed (3 entradas)');
$textosPost = array_map(static fn($e) => $e['texto'], $vistaPost['hoy']);
ok(in_array('Ha llegado Antonio. El pueblo gana un residente más.', $textosPost, true), 'el cotilleo de llegada sigue publicado');
ok(in_array('Raúl e Inés coincidieron varias veces en la plaza.', $textosPost, true), 'el cotilleo normal sigue publicado');

// Idempotencia: reabrir no cambia nada
$rRe = VistaCotilleoV3::marcarVistas($partida, idsImportantesDeFeed($vistaPost));
ok(($rRe['marcados'] ?? -1) === 0 && ($rRe['importantes_sin_ver'] ?? -1) === 0, 'reabrir Cotilleos es idempotente');

// Nuevo importante después de consultar vuelve a subir el badge
BuzonEngine::crear($partida, [
    'clasificacion' => BuzonEngine::COTILLEO,
    'tipo' => 'senal_romantica',
    'texto' => 'A Raúl le ha dado un flechazo Inés.',
]);
$vistaFinal = VistaCotilleoV3::de($partida);
ok(($vistaFinal['importantes_sin_ver'] ?? -1) === 1, 'importante posterior a la consulta vuelve a marcar badge = 1');

echo $failures === 0 ? "\nTODO OK\n" : "\nFALLOS: $failures\n";
exit($failures === 0 ? 0 : 1);
