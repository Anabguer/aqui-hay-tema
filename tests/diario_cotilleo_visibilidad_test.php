<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use AquiHayTema\Engine\CotilleoCategoria;
use AquiHayTema\Engine\DiarioHitoEngine;
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

/**
 * Regla canónica: DIARIO = memoria personal del residente.
 * COTILLEOS = subconjunto público: solo entradas con clasificación explícita
 * (cotilleo_meta.categoria). Sin esa señal, la entrada NO circula.
 */
function base(): array
{
    return [
        'reloj' => ['dia_pueblo' => 4, 'hora_actual' => 12],
        'buzon' => [],
        'diario' => [],
        'residentes' => [
            'per_a' => ['identidad_publica' => ['nombre' => 'Amelia']],
            'per_b' => ['identidad_publica' => ['nombre' => 'Berto']],
        ],
    ];
}

function textosFeed(array $partida): array
{
    $out = [];
    $feed = VistaCotilleoV3::de($partida);
    foreach (['hoy', 'ayer', 'viejos'] as $b) {
        foreach ($feed[$b] ?? [] as $e) {
            if (($e['origen'] ?? '') === 'diario') {
                $out[] = (string) ($e['texto'] ?? '');
            }
        }
    }
    return $out;
}

// 1) Entrada privada diario_hito → NO Cotilleo
$p = base();
$p['diario'][] = [
    'id' => 'd_hito',
    'dia' => 4,
    'tipo' => 'diario_hito',
    'titulo' => 'Ruptura',
    'texto' => 'Amelia y Berto lo han dejado.',
    'actores' => ['per_a', 'per_b'],
];
ok(textosFeed($p) === [], 'diario_hito privado → NO aparece en Cotilleos');

// 2) Entrada privada genérica → NO Cotilleo
$p['diario'][] = [
    'id' => 'd_priv',
    'dia' => 4,
    'tipo' => 'ruido',
    'texto' => 'Pensamientos privados de Amelia.',
    'actores' => ['per_a'],
];
ok(textosFeed($p) === [], 'entrada privada sin clasificación → NO aparece en Cotilleos');

// 3) Entrada con clasificación explícita → SÍ aparece
$p['diario'][] = [
    'id' => 'd_pub',
    'dia' => 4,
    'tipo' => 'hito_relacion',
    'texto' => 'Amelia y Berto han cruzado una mirada imposible.',
    'actores' => ['per_a', 'per_b'],
    'cotilleo_meta' => CotilleoCategoria::meta(CotilleoCategoria::ROMANCE, true),
];
$feed = textosFeed($p);
ok(count($feed) === 1 && str_contains($feed[0], 'mirada'), 'entrada cotilleable clasificada → SÍ aparece en Cotilleos');
ok(count($p['diario']) === 3, 'el Diario conserva todas sus entradas');

// 4) DiarioHitoEngine genera memoria privada por defecto
$p2 = base();
DiarioHitoEngine::alHito($p2, [
    'id' => 'h1',
    'tipo' => 'flechazo',
    'participantes' => ['per_a', 'per_b'],
]);
ok(count($p2['diario'] ?? []) === 1, 'engine: crea entrada');
ok(count(textosFeed($p2)) === 0, 'engine: hito sin cotilleo_meta NO llega al feed');

echo $failures === 0 ? "OK diario_cotilleo_visibilidad\n" : "FAIL diario_cotilleo_visibilidad ({$failures})\n";
exit($failures > 0 ? 1 : 0);
