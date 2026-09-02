<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Recuperación emocional tras encuentro: resultado + hobby como red de seguridad.
 * No sustituye el azar de EncuentroExperiencia; garantiza mejora mínima si ya estaba mal.
 *
 * Tambien expone evaluarRegalo() con la misma lógica rank-ordered para Regalos v2:
 * el regalo puede MEJORAR un estado negativo (nunca empeorarlo), preservando
 * la causa histórica en `contexto.estado_antes_*` (responsabilidad del caller).
 */
final class EmotionalRecovery
{
  /** @var array<string, int> */
    private const RANK = [
        EstadoEmocional::TRISTE => 0,
        EstadoEmocional::ENFADADO => 0,
        EstadoEmocional::NEUTRO => 1,
        EstadoEmocional::ALEGRE => 2,
    ];

    /**
     * @return array{estado: string, motivo: string, hobby_match: bool, desde_resultado: ?string}|null
     */
    public static function evaluar(
        string $estadoAntes,
        string $resultadoExperiencia,
        bool $hobbyMatch
    ): ?array {
        $estadoAntes = EstadoEmocional::canonId($estadoAntes);
        $desdeResultado = self::estadoDesdeResultado($resultadoExperiencia);

        $negativoAntes = in_array($estadoAntes, [EstadoEmocional::TRISTE, EstadoEmocional::ENFADADO], true);

        if (!$negativoAntes || !$hobbyMatch) {
            if ($desdeResultado === null) {
                return null;
            }
            return [
                'estado' => $desdeResultado,
                'motivo' => 'encuentro',
                'hobby_match' => false,
                'desde_resultado' => $desdeResultado,
            ];
        }

        $candidatos = [EstadoEmocional::NEUTRO];
        if ($desdeResultado !== null) {
            $candidatos[] = $desdeResultado;
        }
        $final = self::mejor(...$candidatos);
        if ($final === null || self::rank($final) <= self::rank($estadoAntes)) {
            return null;
        }

        $motivo = 'hobby_recuperacion';
        if ($desdeResultado !== null && self::rank($desdeResultado) >= self::rank($final)) {
            $motivo = 'encuentro';
        } elseif ($desdeResultado !== null && in_array($desdeResultado, [EstadoEmocional::ALEGRE], true)) {
            $motivo = 'encuentro_y_hobby';
        }

        return [
            'estado' => $final,
            'motivo' => $motivo,
            'hobby_match' => true,
            'desde_resultado' => $desdeResultado,
        ];
    }

    public static function estadoDesdeResultado(string $resultado): ?string
    {
        if ($resultado === 'muy_bien' || $resultado === 'bien') {
            return EstadoEmocional::ALEGRE;
        }
        if ($resultado === 'muy_mal') {
            return EstadoEmocional::TRISTE;
        }
        if ($resultado === 'mal') {
            return EstadoEmocional::ENFADADO;
        }
        return null;
    }

    /**
     * Regalos v2 — transición emocional mejoradora por regalo.
     *
     * Reglas:
     *  - indiferente: NUNCA aplica emoción. Devuelve null (caller no hace nada).
     *  - no_le_gusta: NUNCA devuelve un estado mejorado. Devuelve null SIEMPRE
     *    (caller decide: si estado antes ∈ {TRISTE, ENFADADO} → mantener;
     *    si estado antes ∈ {NEUTRO, ALEGRE} → legacy enfadado 4h).
     *  - Si estado antes ∈ {NEUTRO, ALEGRE}: devuelve null (no aplica nada nuevo;
     *    el caller mantiene su flujo actual de "regalo aplica alegria").
     *  - Si estado antes ∈ {TRISTE, ENFADADO}:
     *      · le_gusta   → candidato = NEUTRO (motivo 'regalo_alivia').
     *      · le_encanta → candidatos = [NEUTRO, ALEGRE] (motivo 'regalo_animó' si final=ALEGRE,
     *                                                  'regalo_alivia' si final=NEUTRO).
     *      · siempre se exige rank(final) > rank(antes) — nunca empeora.
     *
     * Si hobbyMatch=true, el regalo acertó en un gusto conocido del receptor y la
     * mejora es plausible. Si hobbyMatch=false (acierto a ciegas), se permite
     * igualmente la transición si la reacción es le_encanta — pero se devuelve
     * con `motivo='regalo_animó_sin_match'` para que el caller pueda reflejar
     * el carácter "a ciegas" en copy si quiere. Por defecto le_gusta sin match
     * devuelve null (no es suficiente para aliviar sin afinidad confirmada).
     *
     * @return array{estado: string, motivo: string, hobby_match: bool, mejor_de_rank: bool}|null
     */
    public static function evaluarRegalo(
        string $estadoAntes,
        string $reaccion,
        bool $hobbyMatch
    ): ?array {
        $estadoAntes = EstadoEmocional::canonId($estadoAntes);

        // Sin cambio emocional en indiferente (caller no hace nada).
        if ($reaccion === RegaloEngine::INDIFERENTE) {
            return null;
        }

        // no_le_gusta: nunca devuelve estado mejorado. Caller decide legacy vs mantener.
        if ($reaccion === RegaloEngine::NO_LE_GUSTA) {
            return null;
        }

        // Si ya está bien, el caller sigue su flujo propio (alegre por regalo).
        if (!in_array($estadoAntes, [EstadoEmocional::TRISTE, EstadoEmocional::ENFADADO], true)) {
            return null;
        }

        if ($reaccion === RegaloEngine::LE_GUSTA) {
            // Sin hobby_match: no basta para aliviar.
            if (!$hobbyMatch) {
                return null;
            }
            return [
                'estado' => EstadoEmocional::NEUTRO,
                'motivo' => 'regalo_alivia',
                'hobby_match' => true,
                'mejor_de_rank' => self::rank(EstadoEmocional::NEUTRO) > self::rank($estadoAntes),
            ];
        }

        if ($reaccion === RegaloEngine::LE_ENCANTA) {
            // le_encanta siempre permite el salto a ALEGRE (con o sin match).
            // Candidatos: NEUTRO + ALEGRE → elegimos el mejor (siempre ALEGRE si ambos).
            $candidatos = [EstadoEmocional::NEUTRO, EstadoEmocional::ALEGRE];
            $final = self::mejor(...$candidatos);
            if ($final === null || self::rank($final) <= self::rank($estadoAntes)) {
                return null;
            }
            $motivo = $hobbyMatch
                ? ($final === EstadoEmocional::ALEGRE ? 'regalo_animó' : 'regalo_alivia')
                : ($final === EstadoEmocional::ALEGRE ? 'regalo_animó_sin_match' : 'regalo_alivia_sin_match');
            return [
                'estado' => $final,
                'motivo' => $motivo,
                'hobby_match' => $hobbyMatch,
                'mejor_de_rank' => true,
            ];
        }

        return null;
    }

    private static function rank(string $estado): int
    {
        return self::RANK[EstadoEmocional::canonId($estado)] ?? 0;
    }

    private static function mejor(string ...$estados): ?string
    {
        $best = null;
        $bestR = -1;
        foreach ($estados as $e) {
            $r = self::rank($e);
            if ($r > $bestR) {
                $bestR = $r;
                $best = EstadoEmocional::canonId($e);
            }
        }
        return $best;
    }
}
