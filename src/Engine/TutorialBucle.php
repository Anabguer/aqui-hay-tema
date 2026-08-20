<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Primer tramo jugable. No es un wizard. El jugador aprende haciendo
 * y la partida sigue siendo la misma al completar.
 *
 * El NPC de las llaves NO se diseña aquí (decisión cerrada: no aún).
 */
final class TutorialBucle
{
    public const ID = 'bucle_1';
    public const HECHO_BUZON = 'leer_buzon';
    public const HECHO_VECINO = 'consultar_vecino';
    public const HECHO_PLAN = 'organizar_plan';

    /** @var list<string> */
    private const ORDEN_PISTA = [self::HECHO_BUZON, self::HECHO_VECINO, self::HECHO_PLAN];

    /**
     * @param array<string, mixed> $config
     */
    public static function debeArrancar(array $config): bool
    {
        return !empty($config['tutorial_bucle_1']);
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $config
     */
    public static function arrancar(array &$partida, array $config): void
    {
        if (!self::debeArrancar($config)) {
            return;
        }
        if (is_array($partida['tutorial'] ?? null) && ($partida['tutorial']['id'] ?? '') === self::ID) {
            return;
        }

        $ids = array_values(array_filter(array_keys($partida['residentes'] ?? []), static function ($id) {
            return is_string($id) && $id !== '';
        }));
        $de = isset($partida['residentes']['per_i03']) ? 'per_i03' : (string) ($ids[0] ?? '');
        $nombre = $de !== '' ? IdentidadPublica::nombre($partida, $de) : 'Alguien';

        BuzonEngine::crear($partida, [
            'id' => 'msg_bienvenida_bucle1',
            'clasificacion' => BuzonEngine::IMPORTANTE,
            'tipo' => 'bienvenida',
            'de_persona' => $de !== '' ? $de : null,
            'actores' => $de !== '' ? [$de] : [],
            'texto' => $nombre . ' te ha dejado un recado: echa un ojo a quién hay en el bloque. Si te apetece, organiza algo en la cafetería.',
            'origen' => [
                'evento_id' => null,
                'tipo_evento' => DomainEvents::PARTIDA_CREADA,
                'es_narrativo' => false,
                '_placeholder' => true,
            ],
            '_placeholder_contenido' => true,
        ]);

        $partida['tutorial'] = [
            'id' => self::ID,
            'activo' => true,
            'completado' => false,
            'hechos' => [],
            'sugerencia' => self::primerPlan($partida),
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function registrar(array &$partida, string $hecho): array
    {
        $tut = is_array($partida['tutorial'] ?? null) ? $partida['tutorial'] : null;
        if ($tut === null || empty($tut['activo']) || ($tut['id'] ?? '') !== self::ID) {
            return self::vista($partida);
        }
        $validos = self::ORDEN_PISTA;
        if (!in_array($hecho, $validos, true)) {
            return self::vista($partida);
        }
        $hechos = is_array($tut['hechos'] ?? null) ? $tut['hechos'] : [];
        if (!in_array($hecho, $hechos, true)) {
            $hechos[] = $hecho;
        }
        $tut['hechos'] = $hechos;
        $faltan = array_values(array_diff($validos, $hechos));
        if ($faltan === []) {
            $tut['activo'] = false;
            $tut['completado'] = true;
            $tut['sugerencia'] = null;
        }
        $partida['tutorial'] = $tut;
        return self::vista($partida);
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function vista(array $partida): array
    {
        $tut = is_array($partida['tutorial'] ?? null) ? $partida['tutorial'] : null;
        if ($tut === null || ($tut['id'] ?? '') !== self::ID) {
            return [
                'id' => null,
                'activo' => false,
                'completado' => false,
                'paso' => null,
                'zona' => null,
                'pista' => null,
                'sugerencia' => null,
            ];
        }
        $hechos = is_array($tut['hechos'] ?? null) ? $tut['hechos'] : [];
        $activo = !empty($tut['activo']) && empty($tut['completado']);
        $paso = null;
        if ($activo) {
            foreach (self::ORDEN_PISTA as $h) {
                if (!in_array($h, $hechos, true)) {
                    $paso = $h;
                    break;
                }
            }
        }
        return [
            'id' => self::ID,
            'activo' => $activo,
            'completado' => !empty($tut['completado']),
            'paso' => $paso,
            'zona' => self::zonaDe($paso),
            'pista' => self::pistaDe($paso),
            'sugerencia' => $activo ? ($tut['sugerencia'] ?? self::primerPlan($partida)) : null,
            'hechos' => $hechos,
        ];
    }

    /**
     * Plan válido según el motor real: dos desconocidos, conocerse, cafetería, hueco abierto.
     *
     * @param array<string, mixed> $partida
     * @return array<string, mixed>|null
     */
    public static function primerPlan(array $partida): ?array
    {
        $ids = [];
        foreach ($partida['residentes'] ?? [] as $id => $res) {
            if (!is_string($id) || $id === '') {
                continue;
            }
            if (($res['presencia'] ?? 'residente') !== 'residente') {
                continue;
            }
            $ids[] = $id;
        }
        if (count($ids) < 2) {
            return null;
        }
        $a = $ids[0];
        $b = $ids[1];
        if (isset($partida['residentes']['per_p001'], $partida['residentes']['per_p002'])) {
            $a = 'per_p001';
            $b = 'per_p002';
        } elseif ($a === $b) {
            return null;
        }
        $lugar = 'lug_cafeteria';
        $operativos = $partida['celeste']['lugares_desbloqueados'] ?? [];
        if (!in_array($lugar, $operativos, true)) {
            $lugar = is_string($operativos[0] ?? null) ? (string) $operativos[0] : '';
        }
        if ($lugar === '') {
            return null;
        }
        $tipo = RelacionEngine::seConocen($partida, $a, $b)
            ? PropuestaNivel::QUEDAR
            : PropuestaNivel::PRESENTAR;
        $slots = DisponibilidadEngine::slotsCompatibles($partida, [$a, $b], $tipo, null, null, 7, 48);
        $elegido = null;
        foreach ($slots['slots'] ?? [] as $s) {
            if (!is_array($s)) {
                continue;
            }
            $h = (int) ($s['hora'] ?? -1);
            if (ComplejoCatalog::estaAbierto($lugar, $h)) {
                $elegido = $s;
                break;
            }
        }
        if ($elegido === null) {
            return null;
        }
        return [
            'residente_a' => $a,
            'residente_b' => $b,
            'tipo' => $tipo,
            'lugar' => $lugar,
            'dia' => (int) $elegido['dia'],
            'hora' => (int) $elegido['hora'],
        ];
    }

    private static function zonaDe(?string $paso): ?string
    {
        if ($paso === self::HECHO_BUZON) {
            return 'buzon';
        }
        if ($paso === self::HECHO_VECINO) {
            return 'vecinos';
        }
        if ($paso === self::HECHO_PLAN) {
            return 'organizar';
        }
        return null;
    }

    /** Copy placeholder. C2 escribe la voz final. */
    private static function pistaDe(?string $paso): ?string
    {
        if ($paso === self::HECHO_BUZON) {
            return 'Tienes un recado. Ábrelo.';
        }
        if ($paso === self::HECHO_VECINO) {
            return 'Mira quién vive en el bloque.';
        }
        if ($paso === self::HECHO_PLAN) {
            return 'Organiza que se conozcan en la cafetería.';
        }
        return null;
    }
}
