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
        $partida['relaciones_sociales'] ??= [];
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
                self::ensureSocialCampos($rel);
                self::aplicarDireccionSocial($rel, $personaA, $personaB, $tipo, $intensidad, $partida);
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
        self::ensureSocialCampos($rel);
        self::aplicarDireccionSocial($rel, $personaA, $personaB, $tipo, $intensidad, $partida);
        CompatibilidadOculta::ensurePar($partida, $a, $b);
        $partida['relaciones_sociales'][] = $rel;
        RelacionBitacora::registrar($partida, RelacionBitacora::SE_CONOCIERON, [$personaA, $personaB]);
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
        $partida['relaciones_romanticas'] ??= [];
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
                    if (array_key_exists($k, $defaults) || str_starts_with($k, 'atraccion_') || str_starts_with($k, 'necesidad_') || str_starts_with($k, 'romance_')) {
                        $rel[$k] = $v;
                    }
                }
                RelacionFase::ensure($rel);
                self::ensureRomanceCampos($rel);
                self::sincronizarRomanceDireccional($rel);
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
        self::ensureRomanceCampos($rel);
        self::sincronizarRomanceDireccional($rel);
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

    public static function ensureSocialCampos(array &$rel): void
    {
        RelacionFase::ensure($rel);
        if (!array_key_exists('conocidos', $rel)) {
            $rel['conocidos'] = true;
        }
        $rel['conocido_desde'] ??= null;
        $rel['ultimo_contacto_significativo'] ??= null;
        $rel['consolidacion'] ??= null;
        $rel['a_hacia_b'] ??= [
            'valor' => $rel['intensidad'] ?? null,
            'banda' => $rel['tipo'] ?? null,
        ];
        $rel['b_hacia_a'] ??= [
            'valor' => $rel['intensidad'] ?? null,
            'banda' => $rel['tipo'] ?? null,
        ];
        $rel['a_hacia_b']['valor'] = $rel['a_hacia_b']['valor'] ?? null;
        $rel['a_hacia_b']['banda'] = $rel['a_hacia_b']['banda'] ?? null;
        $rel['b_hacia_a']['valor'] = $rel['b_hacia_a']['valor'] ?? null;
        $rel['b_hacia_a']['banda'] = $rel['b_hacia_a']['banda'] ?? null;
    }

    public static function ensureRomanceCampos(array &$rel): void
    {
        RelacionFase::ensure($rel);
        if (!array_key_exists('romance_a_hacia_b', $rel)) {
            $rel['romance_a_hacia_b'] = $rel['atraccion_a_hacia_b'] ?? null;
        }
        if (!array_key_exists('romance_b_hacia_a', $rel)) {
            $rel['romance_b_hacia_a'] = $rel['atraccion_b_hacia_a'] ?? null;
        }
        $rel['estado_pareja'] ??= ParejaEngine::NINGUNA;
        $rel['estabilidad_pareja'] ??= [
            'activa' => false,
            'valor' => null,
            'memoria' => null,
            'base_reconciliacion' => null,
            '_bloqueado_decision' => ['valor_inicial', 'desgaste'],
        ];
        $rel['historial_parejas'] ??= [];
        $rel['flechazos'] ??= [];
    }

    public static function sincronizarRomanceDireccional(array &$rel): void
    {
        if (array_key_exists('atraccion_a_hacia_b', $rel) && $rel['atraccion_a_hacia_b'] !== null) {
            $rel['romance_a_hacia_b'] = $rel['atraccion_a_hacia_b'];
        }
        if (array_key_exists('atraccion_b_hacia_a', $rel) && $rel['atraccion_b_hacia_a'] !== null) {
            $rel['romance_b_hacia_a'] = $rel['atraccion_b_hacia_a'];
        }
        $rel['atraccion_a_hacia_b'] = $rel['romance_a_hacia_b'] ?? $rel['atraccion_a_hacia_b'] ?? null;
        $rel['atraccion_b_hacia_a'] = $rel['romance_b_hacia_a'] ?? $rel['atraccion_b_hacia_a'] ?? null;
    }

    /**
     * @param array<string, mixed> $partida
     */
    private static function aplicarDireccionSocial(
        array &$rel,
        string $desde,
        string $hacia,
        string $tipo,
        ?int $intensidad,
        array $partida
    ): void {
        $lo = (string) ($rel['persona_a'] ?? '');
        $key = $desde === $lo ? 'a_hacia_b' : 'b_hacia_a';
        $rel[$key]['banda'] = $tipo;
        if ($intensidad !== null) {
            $rel[$key]['valor'] = $intensidad;
        }
        $rel['conocidos'] = true;
        $marca = [
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
        ];
        $rel['conocido_desde'] ??= $marca;
        $rel['ultimo_contacto_significativo'] = $marca;
    }

    public static function seConocen(array $partida, string $a, string $b): bool
    {
        $rel = self::obtenerEntre($partida, $a, $b)['social'] ?? null;
        if (!is_array($rel)) {
            return false;
        }
        return !empty($rel['conocidos']);
    }

    public static function socialHacia(array $partida, string $desde, string $hacia): ?array
    {
        $rel = self::obtenerEntre($partida, $desde, $hacia)['social'] ?? null;
        if (!is_array($rel)) {
            return null;
        }
        self::ensureSocialCampos($rel);
        $lo = (string) ($rel['persona_a'] ?? '');
        return $desde === $lo ? $rel['a_hacia_b'] : $rel['b_hacia_a'];
    }

    public static function romanceHacia(array $partida, string $desde, string $hacia): ?int
    {
        $rel = self::obtenerEntre($partida, $desde, $hacia)['romance'] ?? null;
        if (!is_array($rel)) {
            return null;
        }
        self::ensureRomanceCampos($rel);
        $lo = (string) ($rel['persona_a'] ?? '');
        $v = $desde === $lo ? ($rel['romance_a_hacia_b'] ?? null) : ($rel['romance_b_hacia_a'] ?? null);
        return $v === null ? null : (int) $v;
    }

    public static function setRomanceHacia(array &$partida, string $desde, string $hacia, ?int $valor): array
    {
        self::upsertRomance($partida, $desde, $hacia, []);
        $rel = self::obtenerEntre($partida, $desde, $hacia)['romance'];
        self::ensureRomanceCampos($rel);
        $lo = (string) ($rel['persona_a'] ?? '');
        if ($desde === $lo) {
            $rel['romance_a_hacia_b'] = $valor;
            $rel['atraccion_a_hacia_b'] = $valor;
        } else {
            $rel['romance_b_hacia_a'] = $valor;
            $rel['atraccion_b_hacia_a'] = $valor;
        }
        self::persistirRomance($partida, $rel);
        return ['ok' => true, 'relacion' => $rel];
    }

    public static function persistirRomance(array &$partida, array $rel): void
    {
        self::persistirCanal($partida, 'romance', $rel);
    }

    /** @return array{0: string, 1: string} */
    private static function ordenarPar(string $a, string $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }
}
