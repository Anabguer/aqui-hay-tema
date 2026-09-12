<?php
declare(strict_types=1);

namespace AquiHayTema\Engine;

/**
 * Pistas sociales/románticas en el Diario personal.
 * Lee señales ya calculadas por el motor y genera entradas en primera persona.
 * No duplica cálculos. No crea estado nuevo salvo cooldown.
 */
final class DiarioPistasSociales
{
    /** @var list<string> */
    public const FAMILIAS = [
        'quimica_alta',
        'quimica_baja',
        'atraccion_asimetrica',
        'primer_rechazo_relevante',
        'rechazo_repetido',
        'conflicto_personal',
        'calentamiento_social',
        'enfriamiento_social',
        'estabilidad_pareja_baja',
    ];

    /** @var array<string, int> Cooldown mínimo en días entre entradas del mismo tipo para un par */
    private const COOLDOWN_DIAS = [
        'quimica_alta' => 9999,
        'quimica_baja' => 9999,
        'atraccion_asimetrica' => 30,
        'primer_rechazo_relevante' => 9999,
        'rechazo_repetido' => 30,
        'conflicto_personal' => 7,
        'calentamiento_social' => 30,
        'enfriamiento_social' => 30,
        'estabilidad_pareja_baja' => 10,
    ];

    /** @var array<string, int> Máximo de entradas por día (global, todas las familias) */
    public const MAX_POR_DIA = 2;

    private static array $publicadas = [];

    public static function reset(): void
    {
        self::$publicadas = [];
    }

    /**
     * Evalúa todas las pistas sociales y genera entradas de diario.
     * Llamar una vez por día (al cerrar día o al avanzar reloj).
     * Primero evalúa TODOS los pares, luego aplica límite MAX_POR_DIA.
     *
     * @return list<array{familia: string, desde: string, hacia: string, texto: string}>
     */
    public static function evaluarPistas(array &$partida, array $cal = []): array
    {
        if (!FeatureConfig::isEnabled($partida, 'diario_enabled')) {
            return [];
        }

        self::$publicadas = [];

        $dia = (int) ($partida['reloj']['dia_pueblo'] ?? 1);
        $cooldowns = self::cargarCooldowns($partida);

        $residentes = CapacidadViviendas::residentesActivos($partida);
        $candidatos = [];
        $procesadas = [];

        for ($i = 0; $i < count($residentes); $i++) {
            for ($j = $i + 1; $j < count($residentes); $j++) {
                $a = $residentes[$i];
                $b = $residentes[$j];
                $clavePar = $a . '|' . $b;
                if (isset($procesadas[$clavePar])) {
                    continue;
                }
                $procesadas[$clavePar] = true;

                $familias = self::evaluarPar($partida, $a, $b, $cal);
                foreach ($familias as $f) {
                    $familia = $f['familia'];
                    $desde = $f['desde'];
                    $hacia = $f['hacia'];

                    $clave = $familia . ':' . $desde . '|' . $hacia;
                    if (isset(self::$publicadas[$clave])) {
                        continue;
                    }
                    if (self::enCooldown($cooldowns, $familia, $desde, $hacia, $dia)) {
                        continue;
                    }

                    $nombreOtro = IdentidadPublica::nombre($partida, $hacia);
                    $texto = CopyPistasSociales::texto($familia, $nombreOtro);
                    if ($texto === '') {
                        continue;
                    }

                    $eventoId = 'pistas:' . $familia . ':' . $desde . '|' . $hacia;
                    if (DiarioEngine::entradaPorEvento($partida, $eventoId) !== null) {
                        continue;
                    }

                    $candidatos[] = [
                        'familia' => $familia,
                        'desde' => $desde,
                        'hacia' => $hacia,
                        'texto' => $texto,
                        'titulo' => CopyPistasSociales::titulo($familia, $nombreOtro),
                        'evento_id' => $eventoId,
                    ];
                }
            }
        }

        $publicadas = [];
        $countHoy = 0;
        $maxPorDia = self::MAX_POR_DIA;

        foreach ($candidatos as $c) {
            if ($countHoy >= $maxPorDia) {
                break;
            }

            DiarioEngine::crear(
                $partida,
                [
                    'dia' => $dia,
                    'tipo' => 'pista_social',
                    'subtipo' => $c['familia'],
                    'titulo' => $c['titulo'],
                    'texto' => $c['texto'],
                    'actores' => [$c['desde'], $c['hacia']],
                    'origen' => [
                        'evento_id' => $c['evento_id'],
                        'tipo_evento' => 'pista_social',
                        'es_narrativo' => true,
                    ],
                    'consecuencias' => [],
                ]
            );

            self::$publicadas[$c['familia'] . ':' . $c['desde'] . '|' . $c['hacia']] = true;
            self::registrarCooldown($partida, $c['familia'], $c['desde'], $c['hacia'], $dia);
            $countHoy++;

            $publicadas[] = [
                'familia' => $c['familia'],
                'desde' => $c['desde'],
                'hacia' => $c['hacia'],
                'texto' => $c['texto'],
            ];
        }

        return $publicadas;
    }

    /**
     * Evalúa un par y devuelve las familias de señales detectadas.
     *
     * @return list<array{familia: string, desde: string, hacia: string}>
     */
    private static function evaluarPar(
        array $partida,
        string $a,
        string $b,
        array $cal
    ): array {
        $senales = [];
        $senales = array_merge($senales, self::evaluarQuimica($partida, $a, $b));
        $senales = array_merge($senales, self::evaluarAtraccionAsimetrica($partida, $a, $b, $cal));
        $senales = array_merge($senales, self::evaluarRechazos($partida, $a, $b));
        $senales = array_merge($senales, self::evaluarConflicto($partida, $a, $b));
        $senales = array_merge($senales, self::evaluarSocial($partida, $a, $b, $cal));
        $senales = array_merge($senales, self::evaluarEstabilidadPareja($partida, $a, $b));

        return $senales;
    }

    /**
     * Rechazos relevantes: lee rechazos_propuesta[] directamente.
     * Primer rechazo relevante (n=1, motivo=emocional|relacional): genera entrada.
     * Rechazo repetido (n≥2, motivo=emocional|relacional): genera entrada diferente.
     * Rechazo banal: NO genera entrada.
     * Si ya existe un RECHAZO_IMPORTANTE en bitacora para el par, suppress rechazo_repetido
     * para evitar duplicación con el diario_hito que ya crea DiarioHitoEngine.
     * Auto-rechazos (quien=hacia): NO generan entrada.
     *
     * @return list<array{familia: string, desde: string, hacia: string}>
     */
    private static function evaluarRechazos(array $partida, string $a, string $b): array
    {
        $rechazos = $partida['rechazos_propuesta'] ?? [];
        if ($rechazos === []) {
            return [];
        }

        $nRechazosA = 0;
        $nRechazosB = 0;
        $hayRelevanteA = false;
        $hayRelevanteB = false;

        foreach ($rechazos as $row) {
            if (!is_array($row)) {
                continue;
            }
            $quien = (string) ($row['quien'] ?? '');
            $hacia = (string) ($row['hacia'] ?? '');
            $motivo = (string) ($row['motivo'] ?? '');

            if ($quien === $hacia) {
                continue;
            }

            if (!in_array($motivo, ['emocional', 'relacional'], true)) {
                continue;
            }

            if ($quien === $a && $hacia === $b) {
                $nRechazosA++;
                $hayRelevanteA = true;
            }
            if ($quien === $b && $hacia === $a) {
                $nRechazosB++;
                $hayRelevanteB = true;
            }
        }

        $hayHitoA = self::hayRechazoImportante($partida, $a, $b);
        $hayHitoB = self::hayRechazoImportante($partida, $b, $a);

        $senales = [];

        if ($hayRelevanteA && $nRechazosA === 1) {
            $senales[] = ['familia' => 'primer_rechazo_relevante', 'desde' => $b, 'hacia' => $a];
        } elseif ($hayRelevanteA && $nRechazosA >= 2 && !$hayHitoA) {
            $senales[] = ['familia' => 'rechazo_repetido', 'desde' => $b, 'hacia' => $a];
        }

        if ($hayRelevanteB && $nRechazosB === 1) {
            $senales[] = ['familia' => 'primer_rechazo_relevante', 'desde' => $a, 'hacia' => $b];
        } elseif ($hayRelevanteB && $nRechazosB >= 2 && !$hayHitoB) {
            $senales[] = ['familia' => 'rechazo_repetido', 'desde' => $a, 'hacia' => $b];
        }

        return $senales;
    }

    /**
     * Comprueba si existe un hito RECHAZO_IMPORTANTE en bitacora para un par direccional.
     */
    private static function hayRechazoImportante(array $partida, string $desde, string $hacia): bool
    {
        foreach (RelacionBitacora::entre($partida, $desde, $hacia) as $h) {
            if (($h['tipo'] ?? '') === RelacionBitacora::RECHAZO_IMPORTANTE) {
                $d = (string) ($h['direccion'] ?? '');
                if (str_starts_with($d, $desde . '>')) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Química extrema: alta o baja.
     *
     * @return list<array{familia: string, desde: string, hacia: string}>
     */
    private static function evaluarQuimica(array $partida, string $a, string $b): array
    {
        $par = QuimicaEngine::obtener($partida, $a, $b);
        if ($par === null) {
            return [];
        }

        $valor = (int) ($par['simetrica'] ?? ($par['a_hacia_b'] ?? 50));

        if ($valor >= 85) {
            return [['familia' => 'quimica_alta', 'desde' => $a, 'hacia' => $b]];
        }
        if ($valor <= 15) {
            return [['familia' => 'quimica_baja', 'desde' => $a, 'hacia' => $b]];
        }

        return [];
    }

    /**
     * Atracción asimétrica: uno tiene señal romántica y el otro no.
     * Usa valores de romance directos (no RomanticProgression que agrupa ambas direcciones).
     *
     * @return list<array{familia: string, desde: string, hacia: string}>
     */
    private static function evaluarAtraccionAsimetrica(
        array $partida,
        string $a,
        string $b,
        array $cal
    ): array {
        $tilin = (int) CalibracionConfig::get($cal, 'romance.cortes.tilin', 8);
        $romAB = (int) (RelacionEngine::romanceHacia($partida, $a, $b) ?? 0);
        $romBA = (int) (RelacionEngine::romanceHacia($partida, $b, $a) ?? 0);
        $flechAB = self::tieneFlechazo($partida, $a, $b);
        $flechBA = self::tieneFlechazo($partida, $b, $a);

        $senalA = $romAB >= $tilin || $flechAB;
        $senalB = $romBA >= $tilin || $flechBA;

        if ($senalA && !$senalB) {
            return [['familia' => 'atraccion_asimetrica', 'desde' => $a, 'hacia' => $b]];
        }
        if ($senalB && !$senalA) {
            return [['familia' => 'atraccion_asimetrica', 'desde' => $b, 'hacia' => $a]];
        }

        return [];
    }

    private static function tieneFlechazo(array $partida, string $desde, string $hacia): bool
    {
        foreach (RelacionBitacora::entre($partida, $desde, $hacia) as $h) {
            if (($h['tipo'] ?? '') === RelacionBitacora::FLECHAZO) {
                $d = (string) ($h['direccion'] ?? '');
                if (str_starts_with($d, $desde . '>')) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Conflicto personal: intensidad >= 2.
     *
     * @return list<array{familia: string, desde: string, hacia: string}>
     */
    private static function evaluarConflicto(array $partida, string $a, string $b): array
    {
        $relaciones = RelacionEngine::obtenerEntre($partida, $a, $b);
        $conf = $relaciones['conflicto'] ?? null;
        if (!is_array($conf)) {
            return [];
        }

        $intensidad = (int) ($conf['intensidad'] ?? 0);
        if ($intensidad < 2) {
            return [];
        }

        return [
            ['familia' => 'conflicto_personal', 'desde' => $a, 'hacia' => $b],
            ['familia' => 'conflicto_personal', 'desde' => $b, 'hacia' => $a],
        ];
    }

    /**
     * Calentamiento/enfriamiento social: cambio de banda significativo.
     *
     * @return list<array{familia: string, desde: string, hacia: string}>
     */
    private static function evaluarSocial(
        array $partida,
        string $a,
        string $b,
        array $cal
    ): array {
        $senales = [];

        $valorA = RelacionEngine::valorSocialHacia($partida, $a, $b);
        $valorB = RelacionEngine::valorSocialHacia($partida, $b, $a);

        if ($valorA >= 20 && $valorB >= 20) {
            $conocidos = RelacionEngine::seConocen($partida, $a, $b);
            if ($conocidos) {
                $senales[] = ['familia' => 'calentamiento_social', 'desde' => $a, 'hacia' => $b];
            }
        }

        if ($valorA <= -20 && $valorB <= -20) {
            $conocidos = RelacionEngine::seConocen($partida, $a, $b);
            if ($conocidos) {
                $senales[] = ['familia' => 'enfriamiento_social', 'desde' => $a, 'hacia' => $b];
            }
        }

        return $senales;
    }

    /**
     * Estabilidad de pareja baja: activa y valor <= 25.
     *
     * @return list<array{familia: string, desde: string, hacia: string}>
     */
    private static function evaluarEstabilidadPareja(array $partida, string $a, string $b): array
    {
        $relaciones = RelacionEngine::obtenerEntre($partida, $a, $b);
        $rom = $relaciones['romance'] ?? null;
        if (!is_array($rom)) {
            return [];
        }

        $pareja = $rom['estabilidad_pareja'] ?? null;
        if (!is_array($pareja) || empty($pareja['activa'])) {
            return [];
        }

        $valor = $pareja['valor'] ?? null;
        if ($valor === null || (int) $valor > 25) {
            return [];
        }

        return [
            ['familia' => 'estabilidad_pareja_baja', 'desde' => $a, 'hacia' => $b],
            ['familia' => 'estabilidad_pareja_baja', 'desde' => $b, 'hacia' => $a],
        ];
    }

    private static function cargarCooldowns(array $partida): array
    {
        return $partida['pistas_diario_cooldown'] ?? [];
    }

    private static function registrarCooldown(
        array &$partida,
        string $familia,
        string $desde,
        string $hacia,
        int $dia
    ): void {
        $partida['pistas_diario_cooldown'] ??= [];
        $clave = $familia . ':' . $desde . '|' . $hacia;
        $partida['pistas_diario_cooldown'][$clave] = $dia;
    }

    private static function enCooldown(
        array $cooldowns,
        string $familia,
        string $desde,
        string $hacia,
        int $dia
    ): bool {
        $clave = $familia . ':' . $desde . '|' . $hacia;
        $ultimoDia = $cooldowns[$clave] ?? null;
        if ($ultimoDia === null) {
            return false;
        }
        $cooldownDias = self::COOLDOWN_DIAS[$familia] ?? 30;
        return ($dia - $ultimoDia) < $cooldownDias;
    }
}
