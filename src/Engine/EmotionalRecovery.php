<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Recuperación emocional tras encuentro: resultado + hobby como red de seguridad.
 * No sustituye el azar de EncuentroExperiencia; garantiza mejora mínima si ya estaba mal.
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
