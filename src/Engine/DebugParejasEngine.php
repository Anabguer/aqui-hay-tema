<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Parejas de prueba para validación visual del panel Parejas (solo DEBUG).
 * Usa ParejaEngine y marca relaciones con _origen_debug para retirarlas sin tocar parejas reales.
 */
final class DebugParejasEngine
{
    public const ORIGEN = 'parejas_prueba';

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function crear(array &$partida, array $cal = []): array
    {
        if (self::tieneDebugActivas($partida)) {
            return [
                'ok' => false,
                'error' => 'debug_parejas_ya_existen',
                'mensaje_ui' => 'Ya hay parejas de prueba. Usa «Quitar parejas de prueba» primero.',
            ];
        }

        $seleccion = self::elegirCuatroVecinos($partida, $cal);
        if ($seleccion === null) {
            return [
                'ok' => false,
                'error' => 'vecinos_insuficientes',
                'mensaje_ui' => 'No hay 4 vecinos disponibles para crear parejas de prueba (sin pareja activa ni veto de parentesco).',
            ];
        }

        $creadas = [];
        foreach ($seleccion['parejas'] as $idx => $par) {
            $a = $par['a'];
            $b = $par['b'];
            $snapshot = self::snapshotRomance(RelacionEngine::obtenerEntre($partida, $a, $b)['romance']);

            $form = ParejaEngine::formar($partida, $a, $b, true, true, RelacionBitacora::DECLARACION, $cal);
            if (!($form['ok'] ?? false)) {
                self::revertirCreadas($partida, $creadas);
                return [
                    'ok' => false,
                    'error' => 'formar_fallo',
                    'mensaje_ui' => 'No se pudo formar la pareja de prueba.',
                    'detalle' => $form,
                ];
            }

            if ($par['crisis']) {
                $cr = ParejaEngine::crisis($partida, $a, $b);
                if (!($cr['ok'] ?? false)) {
                    self::revertirCreadas($partida, $creadas);
                    self::restaurarRomance($partida, $a, $b, $snapshot);
                    return [
                        'ok' => false,
                        'error' => 'crisis_fallo',
                        'mensaje_ui' => 'No se pudo marcar la pareja de prueba en crisis.',
                        'detalle' => $cr,
                    ];
                }
            }

            self::marcarDebug($partida, $a, $b, $snapshot);
            $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'];
            $creadas[] = [
                'etiqueta' => $idx === 0 ? 'A' : 'B',
                'persona_a' => $a,
                'persona_b' => $b,
                'nombre_a' => self::nombreResidente($partida, $a),
                'nombre_b' => self::nombreResidente($partida, $b),
                'estado_pareja' => (string) ($rel['estado_pareja'] ?? ''),
                'en_crisis' => ($rel['estado_pareja'] ?? '') === ParejaEngine::CRISIS,
                'romance_a_hacia_b' => RelacionEngine::romanceHacia($partida, $a, $b),
                'romance_b_hacia_a' => RelacionEngine::romanceHacia($partida, $b, $a),
                'estabilidad_pareja' => $rel['estabilidad_pareja'] ?? null,
                'relacion_id' => (string) ($rel['id'] ?? ''),
                'claves' => self::clavesRelacion($rel),
            ];
        }

        AuditTrail::record($partida, 'debug_parejas_crear', ['n' => count($creadas)], 'DebugParejasEngine', 'crear');

        return [
            'ok' => true,
            'parejas' => $creadas,
            'debug_parejas' => self::vistaConsola($partida, $creadas),
        ];
    }

    /**
     * @param array<string, mixed> $partida
     * @return array<string, mixed>
     */
    public static function quitar(array &$partida): array
    {
        $quitadas = [];
        $bag = $partida['relaciones_romanticas'] ?? [];
        $nuevas = [];

        foreach ($bag as $rel) {
            if (!is_array($rel) || ($rel['_origen_debug'] ?? '') !== self::ORIGEN) {
                $nuevas[] = $rel;
                continue;
            }
            $a = (string) ($rel['persona_a'] ?? '');
            $b = (string) ($rel['persona_b'] ?? '');
            $quitadas[] = [
                'persona_a' => $a,
                'persona_b' => $b,
                'nombre_a' => self::nombreResidente($partida, $a),
                'nombre_b' => self::nombreResidente($partida, $b),
                'estado_pareja' => (string) ($rel['estado_pareja'] ?? ''),
            ];
            $snapshot = $rel['_debug_pareja_snapshot'] ?? null;
            if ($snapshot === null) {
                continue;
            }
            $restored = $snapshot;
            unset($restored['_origen_debug'], $restored['_debug_pareja_snapshot']);
            $nuevas[] = $restored;
        }

        $partida['relaciones_romanticas'] = array_values($nuevas);
        AuditTrail::record($partida, 'debug_parejas_quitar', ['n' => count($quitadas)], 'DebugParejasEngine', 'quitar');

        return [
            'ok' => true,
            'quitadas' => $quitadas,
            'n' => count($quitadas),
            'debug_parejas' => [
                'accion' => 'quitar',
                'quitadas' => $quitadas,
                'claves_modificadas' => ['relaciones_romanticas'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $partida
     */
    public static function tieneDebugActivas(array $partida): bool
    {
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if (is_array($rel) && ($rel['_origen_debug'] ?? '') === self::ORIGEN) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return array{residentes: list<string>, parejas: list<array{a: string, b: string, crisis: bool}>}|null
     */
    public static function elegirCuatroVecinos(array $partida, array $cal): ?array
    {
        $disponibles = self::vecinosDisponibles($partida);
        $n = count($disponibles);
        if ($n < 4) {
            return null;
        }

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                for ($k = $j + 1; $k < $n; $k++) {
                    for ($l = $k + 1; $l < $n; $l++) {
                        $cuatro = [$disponibles[$i], $disponibles[$j], $disponibles[$k], $disponibles[$l]];
                        $parejas = self::partirEnDosParejas($cuatro, $partida, $cal);
                        if ($parejas !== null) {
                            return ['residentes' => $cuatro, 'parejas' => $parejas];
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $partida
     * @return list<string>
     */
    public static function vecinosDisponibles(array $partida): array
    {
        $ocupados = self::residentesEnParejaActiva($partida);
        $ids = [];
        foreach ($partida['residentes'] ?? [] as $id => $res) {
            if (!is_array($res) || ($res['presencia'] ?? '') !== 'residente') {
                continue;
            }
            if (in_array((string) $id, $ocupados, true)) {
                continue;
            }
            $ids[] = (string) $id;
        }
        sort($ids);
        return $ids;
    }

    /**
     * @param array<string, mixed> $partida
     * @return list<string>
     */
    public static function residentesEnParejaActiva(array $partida): array
    {
        $ids = [];
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $est = (string) ($rel['estado_pareja'] ?? '');
            if ($est !== ParejaEngine::PAREJA && $est !== ParejaEngine::CRISIS) {
                continue;
            }
            $ids[] = (string) ($rel['persona_a'] ?? '');
            $ids[] = (string) ($rel['persona_b'] ?? '');
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param list<string> $cuatro
     * @param array<string, mixed> $partida
     * @param array<string, mixed> $cal
     * @return list<array{a: string, b: string, crisis: bool}>|null
     */
    private static function partirEnDosParejas(array $cuatro, array $partida, array $cal): ?array
    {
        $splits = [[[0, 1], [2, 3]], [[0, 2], [1, 3]], [[0, 3], [1, 2]]];
        foreach ($splits as $split) {
            [$p1, $p2] = $split;
            $a1 = $cuatro[$p1[0]];
            $b1 = $cuatro[$p1[1]];
            $a2 = $cuatro[$p2[0]];
            $b2 = $cuatro[$p2[1]];
            if (ParentescoVeto::bloqueaRomance($partida, $a1, $b1, $cal)) {
                continue;
            }
            if (ParentescoVeto::bloqueaRomance($partida, $a2, $b2, $cal)) {
                continue;
            }
            return [
                ['a' => $a1, 'b' => $b1, 'crisis' => false],
                ['a' => $a2, 'b' => $b2, 'crisis' => true],
            ];
        }
        return null;
    }

    /**
     * @param array<string, mixed>|null $rel
     * @return array<string, mixed>|null
     */
    private static function snapshotRomance(?array $rel): ?array
    {
        if ($rel === null) {
            return null;
        }
        $copy = json_decode(json_encode($rel), true);
        if (!is_array($copy)) {
            return null;
        }
        unset($copy['_origen_debug'], $copy['_debug_pareja_snapshot']);
        return $copy;
    }

    /**
     * @param array<string, mixed> $partida
     * @param array<string, mixed>|null $snapshot
     */
    private static function restaurarRomance(array &$partida, string $a, string $b, ?array $snapshot): void
    {
        $id = 'rel_' . min($a, $b) . '_' . max($a, $b);
        $bag = &$partida['relaciones_romanticas'];
        $bag ??= [];
        $found = false;
        foreach ($bag as $i => $rel) {
            if (($rel['id'] ?? '') !== $id) {
                continue;
            }
            $found = true;
            if ($snapshot === null) {
                unset($bag[$i]);
            } else {
                $bag[$i] = $snapshot;
            }
            break;
        }
        if (!$found && $snapshot !== null) {
            $bag[] = $snapshot;
        }
        $partida['relaciones_romanticas'] = array_values($bag);
    }

    /**
     * @param list<array<string, mixed>> $creadas
     */
    private static function revertirCreadas(array &$partida, array $creadas): void
    {
        foreach (array_reverse($creadas) as $item) {
            $rel = RelacionEngine::obtenerEntre($partida, (string) $item['persona_a'], (string) $item['persona_b'])['romance'];
            $snapshot = is_array($rel) ? ($rel['_debug_pareja_snapshot'] ?? null) : null;
            self::restaurarRomance($partida, (string) $item['persona_a'], (string) $item['persona_b'], $snapshot);
        }
    }

    /**
     * @param array<string, mixed>|null $snapshot
     */
    private static function marcarDebug(array &$partida, string $a, string $b, ?array $snapshot): void
    {
        $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'];
        if (!is_array($rel)) {
            return;
        }
        $rel['_origen_debug'] = self::ORIGEN;
        $rel['_debug_pareja_snapshot'] = $snapshot;
        RelacionEngine::persistirRomance($partida, $rel);
    }

    /**
     * @param array<string, mixed> $partida
     * @param list<array<string, mixed>> $creadas
     * @return array<string, mixed>
     */
    private static function vistaConsola(array $partida, array $creadas): array
    {
        $enCrisis = null;
        foreach ($creadas as $p) {
            if (!empty($p['en_crisis'])) {
                $enCrisis = $p['etiqueta'];
            }
        }

        return [
            'accion' => 'crear',
            'parejas' => $creadas,
            'en_crisis' => $enCrisis,
            'claves_modificadas' => [
                'relaciones_romanticas',
                'estado_pareja',
                'estabilidad_pareja',
                'fecha_inicio',
                'historial_parejas',
                'romance_a_hacia_b',
                'romance_b_hacia_a',
                '_origen_debug',
                '_debug_pareja_snapshot',
            ],
            'vecinos_usados' => array_values(array_unique(array_merge(
                array_column($creadas, 'persona_a'),
                array_column($creadas, 'persona_b')
            ))),
        ];
    }

    /**
     * @param array<string, mixed> $rel
     * @return list<string>
     */
    private static function clavesRelacion(array $rel): array
    {
        $keys = array_keys($rel);
        sort($keys);
        return $keys;
    }

    /**
     * @param array<string, mixed> $partida
     */
    private static function nombreResidente(array $partida, string $id): string
    {
        $res = $partida['residentes'][$id] ?? [];
        if (!is_array($res)) {
            return $id;
        }
        $nom = $res['identidad_publica']['nombre'] ?? null;
        return is_string($nom) && $nom !== '' ? $nom : $id;
    }
}
