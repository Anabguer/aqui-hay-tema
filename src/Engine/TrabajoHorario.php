<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/** Horario laboral persistente: 3 días × 2 h por empleo. Única fuente para agenda y ficha. */
final class TrabajoHorario
{
  private const DIAS_SEMANA = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

  private const DIAS_ABREV = [
    'lunes' => 'Lun',
    'martes' => 'Mar',
    'miercoles' => 'Mié',
    'jueves' => 'Jue',
    'viernes' => 'Vie',
    'sabado' => 'Sáb',
    'domingo' => 'Dom',
  ];

  /** @var list<string> */
  private const SIN_JORNADA = ['desempleado', 'jubilado', 'ninguna', ''];

  public static function empleado(?string $ocupacion): bool
  {
    if (!is_string($ocupacion) || $ocupacion === '') {
      return false;
    }
    return !in_array($ocupacion, self::SIN_JORNADA, true);
  }

  /**
   * Backfill seguro una vez si hay empleo válido sin horario persistido.
   */
  public static function asegurarHorario(array &$partida, string $residenteId): bool
  {
    if (!isset($partida['residentes'][$residenteId]['runtime'])) {
      return false;
    }
    $rt = &$partida['residentes'][$residenteId]['runtime'];
    if (!self::empleado($rt['ocupacion'] ?? null)) {
      return false;
    }
    if (self::horarioCompleto($rt)) {
      return false;
    }
    $seed = (string) (($partida['rng']['seed'] ?? $partida['meta']['seed'] ?? 'default') . ':trabajo:' . $residenteId);
    $rng = new RngService($seed);
    self::escribirHorario($rt, self::generar($rng));
    return true;
  }

  /**
   * @param array<string, mixed> $runtime
   * @return array{dias: list<string>, hora_inicio: int, hora_fin: int}|null
   */
  public static function bloqueDia(array $runtime, string $diaSemana): ?array
  {
    if (!self::empleado($runtime['ocupacion'] ?? null) || !self::horarioCompleto($runtime)) {
      return null;
    }
    $dias = $runtime['trabajo_dias'];
    if (!is_array($dias) || !in_array($diaSemana, $dias, true)) {
      return null;
    }
    $ini = (int) $runtime['trabajo_hora_inicio'];
    $fin = (int) $runtime['trabajo_hora_fin'];
    if ($fin <= $ini) {
      return null;
    }
    return [
      'dias' => array_values($dias),
      'hora_inicio' => $ini,
      'hora_fin' => $fin,
      'tipo' => 'trabajo',
    ];
  }

  public static function trabajaEseDia(array $partida, string $residenteId, int $diaPueblo): bool
  {
    $res = $partida['residentes'][$residenteId] ?? null;
    if (!is_array($res)) {
      return false;
    }
    self::asegurarHorario($partida, $residenteId);
    $rt = $partida['residentes'][$residenteId]['runtime'] ?? [];
    $diaSemana = Reloj::diaSemana($diaPueblo, $partida['reloj'] ?? []);
    return self::bloqueDia($rt, $diaSemana) !== null;
  }

  /**
   * @param array<string, mixed> $runtime
   */
  public static function limpiarHorario(array &$runtime): void
  {
    unset($runtime['trabajo_dias'], $runtime['trabajo_hora_inicio'], $runtime['trabajo_hora_fin']);
  }

  /**
   * @param array<string, mixed> $runtime
   */
  public static function asignarEmpleo(array &$runtime, string $ocupacion, RngService $rng): void
  {
    $runtime['ocupacion'] = $ocupacion;
    $runtime['busqueda_trabajo_estado'] = null;
    $runtime['busqueda_trabajo_cd_hasta'] = null;
    self::escribirHorario($runtime, self::generar($rng));
  }

  /**
   * @param array<string, mixed> $runtime
   * @return array{ocupacion: string|null, linea_principal: string|null, linea_horario: string|null, desempleado: bool}
   */
  public static function paraFicha(array $runtime, ?string $genero, CatalogStore $store): array
  {
    $ocId = $runtime['ocupacion'] ?? null;
    if (!self::empleado(is_string($ocId) ? $ocId : null)) {
      return [
        'ocupacion' => null,
        'linea_principal' => self::etiquetaDesempleado($genero),
        'linea_horario' => null,
        'desempleado' => true,
      ];
    }
    $ocLabel = EtiquetaFicha::ocupacion((string) $ocId, $store);
    $horario = self::horarioCompleto($runtime)
      ? self::formatearHorario($runtime)
      : null;
    return [
      'ocupacion' => $ocLabel,
      'linea_principal' => $ocLabel,
      'linea_horario' => $horario,
      'desempleado' => false,
    ];
  }

  /**
   * @return array{dias: list<string>, hora_inicio: int, hora_fin: int}
   */
  public static function generar(RngService $rng): array
  {
    $dias = $rng->pickUnique(self::DIAS_SEMANA, 3);
    usort($dias, static function ($a, $b): int {
      return array_search($a, self::DIAS_SEMANA, true) <=> array_search($b, self::DIAS_SEMANA, true);
    });
    $horaInicio = $rng->nextInt(8, 20);
    return [
      'dias' => array_values($dias),
      'hora_inicio' => $horaInicio,
      'hora_fin' => $horaInicio + 2,
    ];
  }

  /**
   * @param list<array<string, mixed>> $profesiones
   */
  public static function elegirOcupacion(array $profesiones, ?string $preferida, RngService $rng): string
  {
    if (is_string($preferida) && $preferida !== '' && self::empleado($preferida)) {
      return $preferida;
    }
    $pool = [];
    foreach ($profesiones as $item) {
      if (!is_array($item)) {
        continue;
      }
      $id = (string) ($item['id'] ?? '');
      if ($id !== '' && self::empleado($id)) {
        $pool[] = $id;
      }
    }
    if ($pool === []) {
      return 'oficina';
    }
    $picked = $rng->pickUnique($pool, 1);
    return (string) ($picked[0] ?? 'oficina');
  }

  /**
   * @param array<string, mixed> $runtime
   */
  private static function horarioCompleto(array $runtime): bool
  {
    $dias = $runtime['trabajo_dias'] ?? null;
    if (!is_array($dias) || count($dias) !== 3) {
      return false;
    }
    if (!isset($runtime['trabajo_hora_inicio'], $runtime['trabajo_hora_fin'])) {
      return false;
    }
    $ini = (int) $runtime['trabajo_hora_inicio'];
    $fin = (int) $runtime['trabajo_hora_fin'];
    return $ini >= 0 && $fin === $ini + 2;
  }

  /**
   * @param array{dias: list<string>, hora_inicio: int, hora_fin: int} $horario
   * @param array<string, mixed> $runtime
   */
  private static function escribirHorario(array &$runtime, array $horario): void
  {
    $runtime['trabajo_dias'] = array_values($horario['dias']);
    $runtime['trabajo_hora_inicio'] = (int) $horario['hora_inicio'];
    $runtime['trabajo_hora_fin'] = (int) $horario['hora_fin'];
  }

  /**
   * @param array<string, mixed> $runtime
   */
  private static function formatearHorario(array $runtime): string
  {
    $dias = is_array($runtime['trabajo_dias'] ?? null) ? $runtime['trabajo_dias'] : [];
    $partes = [];
    foreach ($dias as $dia) {
      if (!is_string($dia) || $dia === '') {
        continue;
      }
      $partes[] = self::DIAS_ABREV[$dia] ?? ucfirst($dia);
    }
    $ini = (int) ($runtime['trabajo_hora_inicio'] ?? 0);
    $fin = (int) ($runtime['trabajo_hora_fin'] ?? 0);
    return implode(' · ', $partes) . ' · ' . $ini . '–' . $fin;
  }

  private static function etiquetaDesempleado(?string $genero): string
  {
    switch ($genero) {
      case 'mujer':
        return 'Desempleada';
      case 'hombre':
        return 'Desempleado';
      default:
        return 'Desempleado/a';
    }
  }
}
