<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

final class RelacionEngine
{
    public static function upsertSocial(
        array &$partida,
        string $personaA,
        string $personaB,
        string $tipo,
        ?int $intensidad = null,
        ?bool $seSoportan = null,
        ?string $eventoOrigen = 'manual',
        ?string $correlacionId = null
    ): array {
        [$a, $b] = self::ordenarPar($personaA, $personaB);
        $id = "soc_{$a}_{$b}";
        $antes = self::obtenerEntre($partida, $a, $b)['social'];

        foreach ($partida['relaciones_sociales'] as &$rel) {
            if ($rel['id'] === $id) {
                $rel['tipo'] = $tipo;
                if ($intensidad !== null) {
                    $rel['intensidad'] = $intensidad;
                }
                if ($seSoportan !== null) {
                    $rel['se_soportan'] = $seSoportan;
                }
                RelacionFase::ensure($rel);
                CompatibilidadOculta::ensurePar($partida, $a, $b);
                self::postCambio($partida, $a, $b, 'social', $eventoOrigen, $antes, $rel, $correlacionId);
                return ['ok' => true, 'relacion' => $rel, 'creada' => false];
            }
        }

        $rel = [
            'id' => $id,
            'persona_a' => $a,
            'persona_b' => $b,
            'tipo' => $tipo,
            'es_familiar' => false,
            'veta_romance' => false,
            'intensidad' => $intensidad,
            'se_soportan' => $seSoportan,
            'notas' => '',
            '_placeholder_balance' => true,
        ];
        RelacionFase::ensure($rel);
        CompatibilidadOculta::ensurePar($partida, $a, $b);
        $partida['relaciones_sociales'][] = $rel;
        self::postCambio($partida, $a, $b, 'social', $eventoOrigen, $antes, $rel, $correlacionId);
        return ['ok' => true, 'relacion' => $rel, 'creada' => true];
    }

    public static function upsertRomance(
        array &$partida,
        string $personaA,
        string $personaB,
        array $valores = [],
        ?string $eventoOrigen = 'manual',
        ?string $correlacionId = null
    ): array {
        [$a, $b] = self::ordenarPar($personaA, $personaB);
        $id = "rel_{$a}_{$b}";
        $antes = self::obtenerEntre($partida, $a, $b)['romance'];

        $defaults = [
            'atraccion_a_hacia_b' => null,
            'atraccion_b_hacia_a' => null,
            'vinculo' => null,
            'conflicto' => null,
            'necesidad_contacto_a' => null,
            'necesidad_contacto_b' => null,
            'estado_actual' => 'solteros_con_interes',
        ];

        foreach ($partida['relaciones_romanticas'] as &$rel) {
            if ($rel['id'] === $id) {
                foreach ($valores as $k => $v) {
                    if (array_key_exists($k, $defaults) || str_starts_with($k, 'atraccion_') || str_starts_with($k, 'necesidad_')) {
                        $rel[$k] = $v;
                    }
                }
                RelacionFase::ensure($rel);
                CompatibilidadOculta::ensurePar($partida, $a, $b);
                self::postCambio($partida, $a, $b, 'romance', $eventoOrigen, $antes, $rel, $correlacionId, $valores);
                return ['ok' => true, 'relacion' => $rel, 'creada' => false];
            }
        }

        $rel = array_merge([
            'id' => $id,
            'persona_a' => $a,
            'persona_b' => $b,
            'recuerdos_compartidos' => [],
            'historial_citas' => [],
            'historial_encuentros' => [],
            'fecha_inicio' => null,
            '_placeholder_formulas' => true,
        ], $defaults, $valores);
        RelacionFase::ensure($rel);
        CompatibilidadOculta::ensurePar($partida, $a, $b);

        $partida['relaciones_romanticas'][] = $rel;
        self::postCambio($partida, $a, $b, 'romance', $eventoOrigen, $antes, $rel, $correlacionId, $valores);
        return ['ok' => true, 'relacion' => $rel, 'creada' => true];
    }

    /**
     * Canal de roce/conflicto independiente de social y romance.
     * Intensidad y umbrales BLOQUEADO_DECISION.
     *
     * @return array<string, mixed>
     */
    public static function upsertConflicto(
        array &$partida,
        string $personaA,
        string $personaB,
        ?int $intensidad = null,
        ?string $tipo = null,
        ?string $eventoOrigen = 'manual',
        ?string $correlacionId = null
    ): array {
        [$a, $b] = self::ordenarPar($personaA, $personaB);
        $id = "conf_{$a}_{$b}";
        $partida['relaciones_conflicto'] ??= [];
        $antes = self::obtenerEntre($partida, $a, $b)['conflicto'];

        foreach ($partida['relaciones_conflicto'] as &$rel) {
            if ($rel['id'] === $id) {
                if ($intensidad !== null) {
                    $rel['intensidad'] = $intensidad;
                }
                if ($tipo !== null) {
                    $rel['tipo'] = $tipo;
                }
                RelacionFase::ensure($rel);
                self::postCambio($partida, $a, $b, 'conflicto', $eventoOrigen, $antes, $rel, $correlacionId);
                return ['ok' => true, 'relacion' => $rel, 'creada' => false];
            }
        }

        $rel = [
            'id' => $id,
            'persona_a' => $a,
            'persona_b' => $b,
            'tipo' => $tipo,
            'intensidad' => $intensidad,
            '_placeholder_balance' => true,
            '_bloqueado_decision' => ['formula', 'umbrales'],
        ];
        RelacionFase::ensure($rel);
        $partida['relaciones_conflicto'][] = $rel;
        self::postCambio($partida, $a, $b, 'conflicto', $eventoOrigen, $antes, $rel, $correlacionId);
        return ['ok' => true, 'relacion' => $rel, 'creada' => true];
    }

    /**
     * Transición de fase explícita. No calcula umbrales.
     *
     * @return array<string, mixed>
     */
    public static function aplicarFase(
        array &$partida,
        string $personaA,
        string $personaB,
        string $canal,
        string $hacia
    ): array {
        $relWrap = self::obtenerEntre($partida, $personaA, $personaB);
        $key = $canal === 'romance' ? 'romance' : ($canal === 'conflicto' ? 'conflicto' : 'social');
        $rel = $relWrap[$key] ?? null;
        if (!is_array($rel)) {
            return GameError::respuesta(GameError::VALIDACION_FALLIDA, ['canal' => $canal]);
        }
        $r = RelacionFase::aplicar($rel, $hacia);
        if (!($r['ok'] ?? false)) {
            return GameError::respuesta(GameError::FASE_TRANSICION_INVALIDA, [
                'desde' => $r['desde'] ?? null,
                'hacia' => $hacia,
                'canal' => $canal,
            ]);
        }
        self::persistirCanal($partida, $key, $rel);
        return ['ok' => true, 'relacion' => $rel, 'canal' => $canal];
    }

    public static function obtenerEntre(array $partida, string $personaA, string $personaB): array
    {
        [$a, $b] = self::ordenarPar($personaA, $personaB);
        $socId = "soc_{$a}_{$b}";
        $romId = "rel_{$a}_{$b}";
        $confId = "conf_{$a}_{$b}";
        $social = null;
        $romance = null;
        $conflicto = null;
        foreach ($partida['relaciones_sociales'] ?? [] as $rel) {
            if ($rel['id'] === $socId) {
                $social = $rel;
            }
        }
        foreach ($partida['relaciones_romanticas'] ?? [] as $rel) {
            if ($rel['id'] === $romId) {
                $romance = $rel;
            }
        }
        foreach ($partida['relaciones_conflicto'] ?? [] as $rel) {
            if ($rel['id'] === $confId) {
                $conflicto = $rel;
            }
        }
        return ['social' => $social, 'romance' => $romance, 'conflicto' => $conflicto];
    }

    private static function persistirCanal(array &$partida, string $canal, array $rel): void
    {
        $bag = $canal === 'romance' ? 'relaciones_romanticas' : ($canal === 'conflicto' ? 'relaciones_conflicto' : 'relaciones_sociales');
        $partida[$bag] ??= [];
        foreach ($partida[$bag] as $i => $row) {
            if (($row['id'] ?? '') === ($rel['id'] ?? '')) {
                $partida[$bag][$i] = $rel;
                return;
            }
        }
    }

    private static function postCambio(
        array &$partida,
        string $a,
        string $b,
        string $canal,
        ?string $eventoOrigen,
        ?array $antes,
        array $despues,
        ?string $correlacionId,
        array $deltas = []
    ): void {
        RelacionHistorial::registrar(
            $partida,
            $a,
            $b,
            $canal,
            $eventoOrigen ?? 'manual',
            $deltas,
            $antes,
            $despues,
            $correlacionId
        );
        DomainEventDispatcher::emit($partida, DomainEvents::RELACION_MODIFICADA, [
            'canal' => $canal,
            'persona_a' => $a,
            'persona_b' => $b,
            'antes' => $antes,
            'despues' => $despues,
            'actores' => [$a, $b],
        ], null, 'RelacionEngine::upsert', [$a, $b]);
    }

    /** @return array{0: string, 1: string} */
    private static function ordenarPar(string $a, string $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }
}
