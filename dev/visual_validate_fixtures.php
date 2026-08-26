<?php
declare(strict_types=1);

use AquiHayTema\Engine\IdentidadPublica;
use AquiHayTema\Engine\TutorialPrimerosPasos;

/** Fixtures visuales DEV — no alteran saves en disco. */

function visual_validate_animo_fixture(string $residenteId, array $partida): array
{
    $nom = IdentidadPublica::nombre($partida, $residenteId);
    if ($nom === '' || $nom === $residenteId) {
        $nom = 'Jaime';
    }

    return [
        'estado_id' => 'enfadado',
        'texto_estado' => 'Está enfadado',
        'explicacion' => 'Compartió un rato con otra persona que se torció, y salió de allí con el ánimo por los suelos.',
        'desde_texto' => 'Desde hoy',
        'consecuencias' => [
            ['icono' => '✕', 'texto' => 'Puede rechazar algunos planes'],
            ['icono' => '💔', 'texto' => 'Sus relaciones pueden cambiar'],
        ],
        'consejo' => 'Un buen plan podría mejorar su ánimo',
        'diario_evento_id' => 'evt_animo_demo',
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function visual_validate_diario_fixture(string $residenteId, array $partida): array
{
    $otro = '';
    foreach (array_keys($partida['residentes'] ?? []) as $rid) {
        if ($rid !== $residenteId) {
            $otro = (string) $rid;
            break;
        }
    }

    return [
        [
            'titulo' => 'Una tarde que salió mal',
            'texto' => 'Un plan se torció y el vecino terminó enfadado.',
            'categoria' => 'animo',
            'dia' => 4,
            'fecha_corta' => 'DÍA 4 · 27/08',
            'actores' => [$residenteId, $otro],
            'evento_id' => 'evt_animo_demo',
        ],
        [
            'titulo' => 'Las cosas se tensaron',
            'texto' => 'Su relación cambió.',
            'categoria' => 'relacion',
            'dia' => 4,
            'fecha_corta' => 'DÍA 4 · 27/08',
            'actores' => [$residenteId, $otro],
            'evento_id' => 'evt_rel_demo',
        ],
        [
            'titulo' => 'Una idea inesperada',
            'texto' => 'Propuso quedar con alguien del edificio.',
            'categoria' => 'plan',
            'dia' => 3,
            'fecha_corta' => 'DÍA 3 · 26/08',
            'actores' => [$residenteId, $otro],
            'evento_id' => 'evt_plan_demo',
        ],
        [
            'titulo' => 'Llegada al edificio',
            'texto' => 'El edificio estrena residente.',
            'categoria' => 'llegada',
            'dia' => 1,
            'fecha_corta' => 'DÍA 1 · 24/08',
            'actores' => [$residenteId],
            'evento_id' => 'evt_llegada_demo',
        ],
        [
            'titulo' => 'Primeros pasos',
            'texto' => 'Empieza a conocer el pueblo.',
            'categoria' => 'historia',
            'dia' => -2,
            'fecha_corta' => 'DÍA -2 · 22/08',
            'actores' => [$residenteId],
            'evento_id' => 'evt_hist_demo',
        ],
        [
            'titulo' => 'Un café que prometía',
            'texto' => 'Quedó para tomar algo y charlar.',
            'categoria' => 'plan',
            'dia' => 2,
            'fecha_corta' => 'DÍA 2 · 25/08',
            'actores' => [$residenteId],
            'evento_id' => 'evt_cafe_demo',
        ],
    ];
}

/**
 * @param array<string, mixed> $partida
 * @return array<string, mixed>
 */
function visual_validate_tutorial_fixture(array $partida, int $step): array
{
    $partida['tutorial'] = [
        'id' => TutorialPrimerosPasos::ID,
        'activo' => true,
        'jugable_completado' => false,
        'finale_visto' => false,
    ];
    $vista = TutorialPrimerosPasos::vistaPublica($partida);
    $vista['paso_visual'] = max(1, min(5, $step));
    return $vista;
}

/**
 * Encuentro en curso con panel de intervención (solo arnés DEV, no persiste).
 *
 * @param array<string, mixed> $refresh
 * @return list<array<string, mixed>>
 */
function visual_validate_intervencion_fixture(array $refresh): array
{
    $partida = is_array($refresh['partida'] ?? null) ? $refresh['partida'] : [];
    $resKeys = array_keys($partida['residentes'] ?? []);
    $a = $resKeys[0] ?? 'per_qa_valid';
    $b = $resKeys[1] ?? $a;
    $dia = (int) ($partida['reloj']['dia'] ?? 8);
    $hora = (int) ($partida['reloj']['hora_actual'] ?? 20);

    return [[
        'id' => 'enc_visual_fixture',
        'estado' => 'en_curso',
        'dia' => $dia,
        'hora' => $hora,
        'hora_inicio' => $hora,
        'participantes' => [$a, $b],
        'lugar' => 'lug_parque',
        'lugar_nombre' => 'Parque',
        'tipo' => 'conocerse',
        'intervencion' => [
            'disponible' => true,
            'usada' => false,
            'acciones' => [
                ['id' => 'hablar', 'etiqueta' => 'Animar un poco', 'disponible' => true],
                ['id' => 'broma', 'etiqueta' => 'Romper el hielo', 'disponible' => true],
                [
                    'id' => 'hobby',
                    'etiqueta' => 'Hobby',
                    'disponible' => true,
                    'hobbies' => [
                        ['id' => 'hob_demo', 'residente_id' => $a, 'etiqueta' => 'Deportes'],
                    ],
                ],
            ],
        ],
    ]];
}
