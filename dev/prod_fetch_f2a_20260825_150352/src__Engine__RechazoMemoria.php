<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Rechazo banal aislado ≈ 0 consecuencias.
 * Repetidos hacia la misma persona erosionan romance del rechazado (no compatibilidad).
 */
final class RechazoMemoria
{
    public static function ensure(array &$partida): void
    {
        $partida['rechazos_propuesta'] ??= [];
    }

    /**
     * @param array<string, mixed> $cal
     * @return array<string, mixed>
     */
    public static function registrar(
        array &$partida,
        string $quienRechaza,
        string $hacia,
        string $motivo,
        array $cal,
        string $tipo = 'conocerse'
    ): array {
        self::ensure($partida);
        if ($motivo === 'agenda' || $motivo === 'cooldown') {
            return ['ok' => true, 'delta_romance' => 0, 'entrada' => null];
        }
        $row = [
            'quien' => $quienRechaza,
            'hacia' => $hacia,
            'motivo' => $motivo,
            'tipo' => $tipo,
            'dia' => (int) ($partida['reloj']['dia_pueblo'] ?? 1),
            'hora' => (int) ($partida['reloj']['hora_actual'] ?? 0),
        ];
        $partida['rechazos_propuesta'][] = $row;
        PropuestaCooldown::marcar($partida, $hacia, $quienRechaza, $tipo, $cal);

        $delta = 0;
        $triste = false;
        $n = self::countHacia($partida, $quienRechaza, $hacia);
        $umbral = (int) CalibracionConfig::get($cal, 'rechazos.repetidos_umbral', 3);
        $relevante = $motivo === 'emocional' || $motivo === 'relacional';
        $erode = $relevante || $n >= $umbral;
        if ($erode && $hacia !== '') {
            $delta = (int) CalibracionConfig::get($cal, 'rechazos.delta_romance_hacia_quien_rechaza', -3);
            $actual = RelacionEngine::romanceHacia($partida, $hacia, $quienRechaza);
            $nuevo = RelacionBandas::clampRomance(($actual ?? 0) + $delta);
            RelacionEngine::setRomanceHacia($partida, $hacia, $quienRechaza, $nuevo);
            $triste = $n >= $umbral || $relevante;
            if ($triste) {
                $reloj = $partida['reloj'] ?? [];
                $dur = (int) CalibracionConfig::get($cal, 'emociones_v1.duracion_horas_default.triste', 10);
                $hasta = EstadoEmocional::hastaDesdeDuracion($reloj, $dur);
                $root = dirname(__DIR__, 2);
                $emoSvc = new EmotionalStateService(
                    new VisualPackStore($root),
                    new CatalogStore($root),
                    null
                );
                $emoSvc->aplicar(
                    $partida,
                    $hacia,
                    EstadoEmocional::TRISTE,
                    'rechazo_repetido',
                    null,
                    $hasta,
                    ['hacia' => $quienRechaza],
                    $dur
                );
                EmocionalNarrativa::publicarCotilleo(
                    $partida,
                    $hacia,
                    'rechazo_repetido',
                    ['hacia' => $quienRechaza, 'quien' => $quienRechaza]
                );
            }
            $est = ParejaEngine::estado($partida, $quienRechaza, $hacia);
            if ($est === ParejaEngine::PAREJA || $est === ParejaEngine::CRISIS) {
                self::tocarEstabilidad($partida, $quienRechaza, $hacia, -2);
            }
            if ($n >= $umbral + 1) {
                RelacionBitacora::registrar($partida, RelacionBitacora::RECHAZO_IMPORTANTE, [$quienRechaza, $hacia], $quienRechaza . '>' . $hacia);
            }
        }
        return ['ok' => true, 'delta_romance' => $delta, 'entrada' => $row, 'triste' => $triste];
    }

    public static function countHacia(array $partida, string $quienRechaza, string $hacia): int
    {
        $n = 0;
        foreach ($partida['rechazos_propuesta'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['quien'] ?? '') === $quienRechaza && ($row['hacia'] ?? '') === $hacia) {
                $n++;
            }
        }
        return $n;
    }

    private static function tocarEstabilidad(array &$partida, string $a, string $b, int $delta): void
    {
        $rel = RelacionEngine::obtenerEntre($partida, $a, $b)['romance'] ?? null;
        if (!is_array($rel) || empty($rel['estabilidad_pareja']['activa'])) {
            return;
        }
        $v = $rel['estabilidad_pareja']['valor'];
        if (!is_numeric($v)) {
            return;
        }
        $nv = (int) $v + $delta;
        if ($nv < 0) {
            $nv = 0;
        }
        if ($nv > 100) {
            $nv = 100;
        }
        $rel['estabilidad_pareja']['valor'] = $nv;
        RelacionEngine::persistirRomance($partida, $rel);
    }
}
