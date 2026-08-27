<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * A2 — Ponderación de selección social (H1 signo + evitación por conflicto).
 *
 * Solo afecta colas de selección (paresPonderados, elegirEvento).
 * NO altera probabilidadPar ni otros canales de resolución.
 */
final class SeleccionSocialPeso
{
  public static function bonusSocialDirigido(array $partida, string $desde, string $hacia): float
  {
    return (float) max(0, RelacionEngine::valorSocialHacia($partida, $desde, $hacia));
  }

  public static function intensidadConflicto(array $partida, string $a, string $b): int
  {
    $rel = RelacionEngine::obtenerEntre($partida, $a, $b);

    return max(0, (int) ($rel['conflicto']['intensidad'] ?? 0));
  }

  public static function debeOmitirPorConflicto(array $partida, string $a, string $b, array $cal): bool
  {
    $umbral = (int) CalibracionConfig::get($cal, 'seleccion_social.evitar_conflicto_intensidad_min', 0);
    if ($umbral <= 0) {
      return false;
    }

    return self::intensidadConflicto($partida, $a, $b) >= $umbral;
  }

  public static function penalizacionConflicto(array $partida, string $a, string $b, array $cal): float
  {
    $factor = (float) CalibracionConfig::get($cal, 'seleccion_social.penalizacion_por_punto_conflicto', 0.12);

    return self::intensidadConflicto($partida, $a, $b) * $factor;
  }

  public static function aplicarConflicto(float $w, array $partida, string $a, string $b, array $cal): float
  {
    return max(0.05, $w - self::penalizacionConflicto($partida, $a, $b, $cal));
  }
}
