<?php
declare(strict_types=1);

/* Fixture compartido de tests de Regalos F1. Partida de laboratorio minima. */

use AquiHayTema\Engine\CalibracionConfig;
use AquiHayTema\Engine\CatalogStore;

require_once dirname(__DIR__) . '/src/autoload.php';

function regalo_fixture_partida(array $perfiles = []): array
{
    $partida = [
        'meta' => ['partida_id' => 'part_lab_regalos_f1', 'seed' => 'lab-regalos-f1'],
        'reloj' => ['dia_pueblo' => 1, 'hora_actual' => 8],
        'residentes' => [],
        'relaciones_sociales' => [],
        'relaciones_romanticas' => [],
    ];
    $nombres = ['Ana', 'Beto', 'Caro', 'Dani'];
    $i = 0;
    foreach ($perfiles as $id => $perfil) {
        $partida['residentes'][$id] = [
            'identidad_publica' => ['nombre' => $nombres[$i % 4]],
            'runtime' => ['perfil_partida' => $perfil],
        ];
        $i++;
    }
    return $partida;
}

function regalo_perfil(array $over = []): array
{
    return array_merge([
        'fuente' => 'lab',
        'hobbies' => [],
        'rasgos' => [],
        'preferencias' => [
            'personalidad_pos' => [],
            'personalidad_neg' => [],
            'visual_pos' => [],
            'visual_neg' => [],
            'hobbies_pos' => [],
            'hobbies_neg' => [],
        ],
    ], $over);
}

function regalo_catalogo(): CatalogStore
{
    return new CatalogStore(dirname(__DIR__));
}

function regalo_cal(): array
{
    return CalibracionConfig::load(dirname(__DIR__));
}
